import { User, UserManager, WebStorageStateStore } from "oidc-client-ts";
import { currentOidcConfig, type OidcConfig } from "./oidcConfig";

/**
 * Maintainer login: the PWA is a public OIDC client (authorization code + PKCE, no client secret), e.g. the Authentik
 * applications "fetchdock-collector" and "fetchdock-collector-staging". The API validates the access token
 * (security.yaml) and takes the roles from its claims.
 */
let manager: UserManager | null = null;
let managerConfig: OidcConfig | null = null;

const requireConfig = (): { issuer: string; clientId: string; redirectUri: string } => {
  const { issuer, clientId, redirectUri } = currentOidcConfig() ?? { issuer: null, clientId: null, redirectUri: null };
  if (!issuer || !clientId || !redirectUri) {
    throw new Error(
      "OIDC isn't configured: set NEXT_PUBLIC_OIDC_ISSUER, NEXT_PUBLIC_OIDC_CLIENT_ID and NEXT_PUBLIC_OIDC_REDIRECT_URI on the pwa service"
    );
  }

  return { issuer, clientId, redirectUri };
};

export const userManager = (): UserManager => {
  // Re-created if the settings change (they're set by each page)
  if (!manager || managerConfig !== currentOidcConfig()) {
    managerConfig = currentOidcConfig();
    const { issuer, clientId, redirectUri } = requireConfig();
    manager = new UserManager({
      authority: issuer,
      client_id: clientId,
      redirect_uri: redirectUri,
      response_type: "code",
      scope: "openid profile email offline_access",
      // Survives reloads and new tabs; cleared on logout
      userStore: new WebStorageStateStore({ store: window.localStorage }),
      // Renewal is ours (below): oidc-client-ts's automatic renewal falls back to a hidden iframe without a refresh
      // token, and that can only fail (Authentik's pages send X-Frame-Options: DENY, our CSP has frame-src 'none').
      automaticSilentRenew: false,
    });
    // Renews the access token with the refresh token before it expires. That needs the IdP to issue refresh tokens
    // (Authentik: the offline_access scope mapping on the provider). Without one, the next request renews through the
    // IdP (see renewSession()).
    manager.events.addAccessTokenExpiring(() => {
      if (storedUser()?.refresh_token) {
        userManager()
          .signinSilent()
          .catch((e) => console.warn("Background token renewal failed:", e));
      }
    });
  }

  return manager;
};

/** Seconds before expiry at which a token counts as expired, so a request doesn't reach the API with a dying token */
const EXPIRY_MARGIN = 10;

/** Where to go back to after signing in, passed through the sign-in redirect's state */
export type SigninState = { returnTo?: string };

/** Signs in through the IdP, coming back to the current admin route (e.g. "#/admin/reports") */
export const signinRedirect = (): Promise<void> =>
  userManager().signinRedirect({ state: { returnTo: window.location.hash } satisfies SigninState });

let renewal: Promise<User | null> | null = null;

/**
 * Renews the access token with the refresh token, once at a time. Without one (the IdP didn't issue it, or it expired),
 * the stored user is removed and the browser goes through the IdP again: with an IdP session that's a quick round trip
 * back to the current route, instead of the login page. Resolves to null when there's no renewed session.
 */
export const renewSession = (): Promise<User | null> => {
  renewal ??= (async () => {
    const hadRefreshToken = !!storedUser()?.refresh_token;
    try {
      // Without a refresh token, signinSilent() would try a hidden iframe, which only times out (about 10 s)
      if (!hadRefreshToken) {
        throw new Error("No refresh token");
      }

      return await userManager().signinSilent();
    } catch (e) {
      if (hadRefreshToken) {
        console.warn("Token renewal failed:", e);
      }
      await userManager().removeUser();
      // Navigates away; if it can't (IdP unreachable), the caller falls back to the login page
      await signinRedirect().catch((redirectError) => console.error("Sign-in redirect failed:", redirectError));

      return null;
    }
  })().finally(() => {
    renewal = null;
  });

  return renewal;
};

/**
 * Starts the background renewal of a stored session: it needs the user loaded into a UserManager, and without this a
 * reloaded page wouldn't load it until the token had already expired.
 */
export const resumeSession = (): void => {
  try {
    if (storedUser()) {
      // Loading the user arms the access token expiry events the background renewal listens to
      userManager()
        .getUser()
        .catch((e) => console.error(e));
    }
  } catch (e) {
    // Incomplete OIDC settings: the login page reports them
    console.error(e);
  }
};

/** A valid access token, renewed first when it's (about to be) expired; null when not signed in */
export const accessToken = async (): Promise<string | null> => {
  const user = storedUser();
  if (!user) {
    return null;
  }
  if (undefined === user.expires_in || user.expires_in > EXPIRY_MARGIN) {
    return user.access_token;
  }

  return (await renewSession())?.access_token ?? null;
};

/**
 * The signed-in user, read synchronously from the store oidc-client-ts writes (key "oidc.user:{authority}:{client}").
 */
export const storedUser = (): User | null => {
  const config = currentOidcConfig();
  if (typeof window === "undefined" || !config?.issuer || !config.clientId) {
    return null;
  }
  const stored = window.localStorage.getItem(`oidc.user:${config.issuer}:${config.clientId}`);

  return stored ? User.fromStorageString(stored) : null;
};
