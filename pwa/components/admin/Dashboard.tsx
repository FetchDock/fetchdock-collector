import { Title, useCreatePath, useGetIdentity, useGetList, usePermissions } from "react-admin";
import { Link } from "react-router-dom";
import { hasRole, ROLE_ADMIN } from "../common/permissions";
import { REPORTS } from "./reports";

/** Number of reports with a status, linking to the filtered list */
const StatusCount = ({ status, label }: { status: string; label: string }) => {
  const { total, isPending } = useGetList(REPORTS, { filter: { status }, pagination: { page: 1, perPage: 1 } });
  const createPath = useCreatePath();
  const filter = encodeURIComponent(JSON.stringify({ status }));

  return (
    <li>
      <Link to={`${createPath({ resource: REPORTS, type: "list" })}?filter=${filter}`}>
        {label}: {isPending ? "…" : (total ?? 0)}
      </Link>
    </li>
  );
};

/** Landing page of the maintainer PWA */
const Dashboard = () => {
  const { identity } = useGetIdentity();
  const { permissions } = usePermissions();
  const isAdmin = hasRole(permissions, ROLE_ADMIN);

  return (
    <div style={{ padding: "1rem" }}>
      <Title title="FetchDock Collector" />
      <p>Signed in as {identity?.fullName ?? "…"}.</p>
      <p>Role: {isAdmin ? "admin (triage, stats, instances)" : "maintainer (triage)"}</p>
      <h3>Reports to triage</h3>
      <ul>
        <StatusCount status="new" label="New" />
        <StatusCount status="acknowledged" label="Acknowledged" />
      </ul>
      {isAdmin && (
        <p>
          <Link to="/stats">Stats</Link>
        </p>
      )}
    </div>
  );
};

export default Dashboard;
