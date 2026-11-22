import { useEffect, useState } from "react";
import { useRouter } from "next/router";
import { configureOidc, getOidcServerSideProps, OidcPageProps } from "../../components/common/oidcConfig";

const AuthCallback = ({ oidc }: OidcPageProps) => {
  configureOidc(oidc);
  const router = useRouter();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!router.isReady) return;

    // Loaded in the browser only: the page is server-rendered, and the authProvider imports react-admin
    import("../../components/common/authProvider")
      .then(({ default: authProvider }) => authProvider.handleCallback())
      .then(() => router.replace("/admin"))
      .catch((e) => {
        console.error("Sign-in failed:", e);
        setError("Sign-in failed. Please try again.");
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
