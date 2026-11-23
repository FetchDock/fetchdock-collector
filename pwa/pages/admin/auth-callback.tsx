import { useEffect, useState } from "react";
import { useRouter } from "next/router";
import type { SigninState } from "../../components/common/oidc";
import { configureOidc, getOidcServerSideProps, OidcPageProps } from "../../components/common/oidcConfig";

/** The OIDC redirect URI: completes the sign-in and goes back to the admin route it started from */
const AuthCallback = ({ oidc }: OidcPageProps) => {
  configureOidc(oidc);
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!router.isReady) return;

    // Loaded in the browser only (oidc-client-ts needs window). Only oidc-client-ts: not the react-admin bundle.
    import("../../components/common/oidc").then(async ({ userManager }) => {
      try {
        const user = await userManager().signinRedirectCallback();
        // Only a hash route of the admin, from our own state (see signinRedirect())
        const returnTo = (user.state as SigninState | undefined)?.returnTo;
        await router.replace(`/admin${returnTo?.startsWith("#/") ? returnTo : ""}`);
      } catch (e) {
        console.error("Sign-in failed:", e);
        setError("Sign-in failed. Please try again.");
      }
    });
  }, [router.isReady, router]);

  return (
    <div className="flex h-screen items-center justify-center">
      <p>{error ?? "Signing in, please wait..."}</p>
    </div>
  );
};

export const getServerSideProps = getOidcServerSideProps;

export default AuthCallback;
