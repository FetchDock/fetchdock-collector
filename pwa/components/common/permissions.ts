import { apiFetch } from "./api";
import { storedUser } from "./oidc";

/** Triage reports */
export const ROLE_MAINTAINER = "ROLE_MAINTAINER";
/** Also stats, instances, blocking instances and deleting reports */
export const ROLE_ADMIN = "ROLE_ADMIN";

/** The maintainer's effective roles, as returned by GET /users/me */
export type Permissions = string[];

let cache: { token: string | null; roles: Promise<Permissions> } | null = null;

/**
 * The effective roles (including those granted through the role hierarchy), cached per token. Resolves to [] when not
 * signed in or when the API can't be reached, so nothing role-specific is shown.
 */
export const fetchRoles = (): Promise<Permissions> => {
  const token = storedUser()?.access_token ?? null;
  if (!token) {
    return Promise.resolve([]);
  }
  if (cache?.token !== token) {
    const roles = apiFetch<{ roles?: unknown }>("/users/me")
      .then((user) => (Array.isArray(user?.roles) ? user.roles.filter((role): role is string => "string" === typeof role) : []))
      .catch(() => {
        cache = null;
        return [];
      });
    cache = { token, roles };
  }

  return cache.roles;
};

export const hasRole = (permissions: unknown, role: string): boolean =>
  Array.isArray(permissions) && permissions.includes(role);
