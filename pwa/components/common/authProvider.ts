import type { AuthProvider } from "react-admin";
import { storedUser, userManager } from "./oidc";
import { fetchRoles } from "./permissions";

const authProvider: AuthProvider & { handleCallback: () => Promise<void> } = {
  login: async () => {
    await userManager().signinRedirect();
  },
  logout: async () => {
    await userManager().removeUser();
  },
  checkError: async (error) => {
    // Expired or revoked token, or a user without a collector role
    if (401 === error?.status || 403 === error?.status) {
      await userManager().removeUser();
      throw new Error("Please sign in again.");
    }
  },
  checkAuth: async () => {
    const user = storedUser();
    if (!user) {
      throw { redirectTo: "/login", message: false };
    }
    if (user.expired) {
      // signinSilent uses the refresh token (offline_access)
      try {
        await userManager().signinSilent();
      } catch {
        await userManager().removeUser();
        throw { redirectTo: "/login", message: false };
      }
    }
  },
  // The maintainer's effective roles from GET /users/me (see ./permissions)
  getPermissions: () => fetchRoles(),
  getIdentity: async () => {
    const user = storedUser();
    if (!user) {
      throw new Error("Not signed in");
    }

    return {
      id: user.profile.sub,
      fullName: user.profile.name ?? user.profile.preferred_username ?? user.profile.sub,
    };
  },
  handleCallback: async () => {
    await userManager().signinRedirectCallback();
    // Remove ?code&state from the URL
    window.history.replaceState({}, window.document.title, window.location.pathname);
  },
};

export default authProvider;
