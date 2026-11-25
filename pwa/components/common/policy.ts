import type { GetServerSidePropsResult } from "next";

/** GET /v1/policy/{version} */
export type PolicyText = {
  version: string;
  current: boolean;
  markdown: string;
};

/**
 * The API's address from the server, read at request time like the OIDC settings (see oidcConfig.ts): http://php in
 * the published images, http://api in development.
 */
const entrypoint = (): string => {
  const env: Record<string, string | undefined> = process.env;

  return env["NEXT_PUBLIC_ENTRYPOINT"] || process.env.NEXT_PUBLIC_ENTRYPOINT || "http://php";
};

const getJson = async <T>(path: string): Promise<T | null> => {
  const response = await fetch(new URL(path, entrypoint()), { headers: { Accept: "application/json" } });
  if (404 === response.status) {
    return null;
  }
  if (!response.ok) {
    throw new Error(`GET ${path}: ${response.status} ${response.statusText}`);
  }

  return (await response.json()) as T;
};

/** Props of the /policy pages: the text of the given version, or of the current one; a 404 for any other version */
export const loadPolicyText = async (version?: string): Promise<GetServerSidePropsResult<{ policy: PolicyText }>> => {
  if (undefined === version) {
    const current = await getJson<{ version: string }>("/v1/policy");
    if (null === current) {
      throw new Error("GET /v1/policy: 404");
    }
    version = current.version;
  }

  const policy = await getJson<PolicyText>(`/v1/policy/${encodeURIComponent(version)}`);

  return null === policy ? { notFound: true } : { props: { policy } };
};
