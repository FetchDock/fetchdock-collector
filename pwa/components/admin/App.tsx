import { HydraAdmin, fetchHydra as baseFetchHydra, hydraDataProvider } from "@api-platform/admin";
import { parseHydraDocumentation } from "@api-platform/api-doc-parser";
import { CustomRoutes, Resource } from "react-admin";
import { Route } from "react-router-dom";
import authProvider from "../common/authProvider";
import { getAuthHeaders } from "../common/api";
import { resumeSession } from "../common/oidc";
import { hasRole, ROLE_ADMIN } from "../common/permissions";
import Dashboard from "./Dashboard";
import Integrity from "./Integrity";
import { InstanceList, InstanceShow } from "./instances";
import Layout from "./Layout";
import LoginPage from "./LoginPage";
import { INSTANCES, REPORTS, ReportEdit, ReportList, ReportShow } from "./reports";
import Stats from "./Stats";

const fetchHydra = async (url: URL, options?: Record<string, any>) =>
  baseFetchHydra(url, {
    ...options,
    headers: await getAuthHeaders(),
  });

const dataProvider = hydraDataProvider({
  entrypoint: window.origin,
  httpClient: fetchHydra,
  useEmbedded: true,
  // Live updates (Mercure, POST /users/me/subscription-token) are a follow-up
  mercure: false,
  // The entrypoint and the docs are public: no token needed, so the docs load in parallel with the session checks
  apiDocumentationParser: () => parseHydraDocumentation(window.origin),
});

// Keeps a session from an earlier visit alive (background token renewal)
resumeSession();

/** The maintainer PWA: report triage for ROLE_MAINTAINER, instances, stats and integrity for ROLE_ADMIN */
const App = () => (
  <HydraAdmin
    authProvider={authProvider}
    dataProvider={dataProvider}
    entrypoint={window.origin}
    loginPage={LoginPage}
    dashboard={Dashboard}
    layout={Layout}
    requireAuth
    disableTelemetry
    title="FetchDock Collector"
  >
    {(permissions: unknown) => (
      <>
        <Resource name={REPORTS} list={ReportList} show={ReportShow} edit={ReportEdit} options={{ label: "Reports" }} />
        {hasRole(permissions, ROLE_ADMIN) && <Resource name={INSTANCES} list={InstanceList} show={InstanceShow} options={{ label: "Instances" }} />}
        <CustomRoutes>
          <Route path="/stats" element={<Stats />} />
          <Route path="/integrity" element={<Integrity />} />
        </CustomRoutes>
      </>
    )}
  </HydraAdmin>
);

export default App;
