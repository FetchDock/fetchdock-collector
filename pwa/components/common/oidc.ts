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
      // Renews the access token with the refresh token before it expires
      automaticSilentRenew: true,
    });
  }

  return manager;
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
