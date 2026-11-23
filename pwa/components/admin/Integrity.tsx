import { useEffect, useState } from "react";
import { Title, useNotify, usePermissions } from "react-admin";
import { apiFetch } from "../common/api";
import { commitUrl, type IntegrityStatus, statusColor, statusLabel } from "../common/integrity";
import { hasRole, ROLE_ADMIN } from "../common/permissions";

type Check = {
  host: string;
  role: "api" | "worker";
  status: IntegrityStatus;
  manifestDigest: string | null;
  appVersion: string;
  mismatches: string[];
  checkedAt: string;
};

type AdminIntegrity = {
  status: IntegrityStatus;
  appVersion: string;
  manifestDigest: string | null;
  gitSha: string | null;
  checkOnline: boolean;
  checks: Check[];
};

const Badge = ({ status, appVersion }: { status: IntegrityStatus; appVersion: string }) => (
  <span style={{ background: statusColor[status], color: "#fff", borderRadius: 4, padding: "0.125rem 0.5rem", fontWeight: 600, fontSize: "0.875rem" }}>
    {statusLabel(status, appVersion)}
  </span>
);

const cell: React.CSSProperties = { padding: "0.25rem 0.5rem", borderBottom: "1px solid rgba(127,127,127,0.25)", textAlign: "left", verticalAlign: "top" };

/** Integrity of the collector's own code for ROLE_ADMIN (GET /admin/integrity), with the paths that differ */
const Integrity = () => {
  const { permissions, isPending: permissionsPending } = usePermissions();
  const notify = useNotify();
  const [integrity, setIntegrity] = useState<AdminIntegrity | null>(null);
  const isAdmin = hasRole(permissions, ROLE_ADMIN);

  useEffect(() => {
    if (!isAdmin) {
      return;
    }
    apiFetch<AdminIntegrity>("/admin/integrity")
      .then(setIntegrity)
      .catch((error: Error) => notify(error.message, { type: "error" }));
  }, [isAdmin, notify]);

  if (permissionsPending) {
    return null;
  }
  if (!isAdmin) {
    return <p style={{ padding: "1rem" }}>The integrity check is available to admins only.</p>;
  }
  if (!integrity) {
    return <p style={{ padding: "1rem" }}>Loading…</p>;
  }

  return (
    <div style={{ padding: "1rem" }}>
      <Title title="Integrity" />
      <p>
        <Badge status={integrity.status} appVersion={integrity.appVersion} />
      </p>
      <p style={{ fontSize: "0.875rem" }}>
        Commit{" "}
        {integrity.gitSha ? (
          <a href={commitUrl(integrity.gitSha)}>
            <code>{integrity.gitSha}</code>
          </a>
        ) : (
          "unknown"
        )}
        , manifest <code>{integrity.manifestDigest ?? "none"}</code>
        {!integrity.checkOnline && " — INTEGRITY_CHECK_ONLINE is off: the published digest isn't compared, so the status can't get past “can't be verified”."}
      </p>
      <p style={{ opacity: 0.7 }}>
        The latest check of each container in the last 48 hours. The public index page shows the status, never the
        paths.
      </p>
      {0 === integrity.checks.length ? (
        <p>No checks in the last 48 hours.</p>
      ) : (
        <table style={{ borderCollapse: "collapse", fontSize: "0.875rem" }}>
          <thead>
            <tr>
              {["Container", "Role", "Status", "Version", "Checked", "Paths that differ"].map((heading) => (
                <th key={heading} style={cell}>
                  {heading}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {integrity.checks.map((check) => (
              <tr key={`${check.host}\0${check.role}`}>
                <td style={cell}>{check.host}</td>
                <td style={cell}>{check.role}</td>
                <td style={cell}>
                  <Badge status={check.status} appVersion={check.appVersion} />
                </td>
                <td style={cell}>{check.appVersion}</td>
                <td style={cell}>{new Date(check.checkedAt).toLocaleString()}</td>
                <td style={cell}>
                  {0 === check.mismatches.length ? (
                    "—"
                  ) : (
                    <ul style={{ margin: 0, paddingLeft: "1rem", fontFamily: "monospace" }}>
                      {check.mismatches.map((path) => (
                        <li key={path}>{path}</li>
                      ))}
                    </ul>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
};

export default Integrity;
