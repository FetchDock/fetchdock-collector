import { Title, useGetIdentity, usePermissions } from "react-admin";
import { hasRole, ROLE_ADMIN } from "../common/permissions";

/** Landing page of the maintainer PWA; the triage list, stats and instances follow with the maintainer API */
const Dashboard = () => {
  const { identity } = useGetIdentity();
  const { permissions } = usePermissions();

  return (
    <div style={{ padding: "1rem" }}>
      <Title title="FetchDock Collector" />
      <p>Signed in as {identity?.fullName ?? "…"}.</p>
      <p>Role: {hasRole(permissions, ROLE_ADMIN) ? "admin (triage, stats, instances)" : "maintainer (triage)"}</p>
    </div>
  );
};

export default Dashboard;
