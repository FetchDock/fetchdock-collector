import { HttpError } from "react-admin";
import { storedUser } from "./oidc";

/** The Authorization header for API calls, with the maintainer's access token */
export const getAuthHeaders = (): Record<string, string> => {
  const user = storedUser();

  return user && !user.expired ? { Authorization: `Bearer ${user.access_token}` } : {};
};

/**
 * Calls the API (same origin as the PWA) with the maintainer's token and returns the JSON body, or null for 204
 * responses. Throws a react-admin HttpError with the API's error description otherwise.
 */
export const apiFetch = async <T = unknown>(path: string, init: RequestInit = {}): Promise<T | null> => {
  const response = await fetch(new URL(path, window.origin), {
    ...init,
    headers: {
      Accept: "application/ld+json",
      ...getAuthHeaders(),
      ...(init.headers as Record<string, string> | undefined),
    },
  });

  if (204 === response.status) {
    return null;
  }

  const body = await response.json().catch(() => null);
  if (!response.ok) {
    const message = body?.description ?? body?.detail ?? body?.["hydra:description"] ?? response.statusText;
    throw new HttpError(message, response.status, body);
  }

  return body as T;
};
