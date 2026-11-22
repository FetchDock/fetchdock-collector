import { HydraAdmin, fetchHydra as baseFetchHydra, hydraDataProvider } from "@api-platform/admin";
import { parseHydraDocumentation } from "@api-platform/api-doc-parser";
import authProvider from "../common/authProvider";
import { getAuthHeaders } from "../common/api";
import Dashboard from "./Dashboard";
import LoginPage from "./LoginPage";

const fetchHydra = (url: URL, options?: Record<string, any>) =>
  baseFetchHydra(url, {
    ...options,
    headers: getAuthHeaders,
  });

const dataProvider = hydraDataProvider({
  entrypoint: window.origin,
  httpClient: fetchHydra,
  useEmbedded: true,
  // Live updates (Mercure, POST /users/me/subscription-token) are wired in with the maintainer API
  mercure: false,
  apiDocumentationParser: () => parseHydraDocumentation(window.origin, { headers: getAuthHeaders }),
});

/** The maintainer PWA. Resources (reports, stats, instances) are declared here as the maintainer API lands. */
const App = () => (
  <HydraAdmin
    authProvider={authProvider}
    dataProvider={dataProvider}
    entrypoint={window.origin}
    loginPage={LoginPage}
    dashboard={Dashboard}
    requireAuth
    title="FetchDock Collector"
  >
    {[]}
  </HydraAdmin>
);

export default App;
