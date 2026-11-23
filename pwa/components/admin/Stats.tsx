import { useEffect, useMemo, useState } from "react";
import { Title, useNotify, usePermissions } from "react-admin";
import { apiFetch } from "../common/api";
import { hasRole, ROLE_ADMIN } from "../common/permissions";

type Row = { key: string | null; period: string; reports: number; instances: number };
type Period = "day" | "week" | "month";

const GROUP_BY = [
  { id: "", name: "Nothing (totals)" },
  { id: "host", name: "Host" },
  { id: "site", name: "Site" },
  { id: "kind", name: "Kind" },
  { id: "downloader", name: "Downloader" },
  { id: "errorCategory", name: "Error category" },
  { id: "status", name: "Status" },
];

/** Default range per period, like the API's: the last 30 days, 12 weeks or 12 months */
const defaultSince = (period: Period): string => {
  const date = new Date();
  if ("day" === period) date.setDate(date.getDate() - 29);
  if ("week" === period) date.setDate(date.getDate() - 7 * 11);
  if ("month" === period) date.setMonth(date.getMonth() - 11, 1);

  return date.toISOString().slice(0, 10);
};

const MAX_KEYS = 25;

const cell: React.CSSProperties = { padding: "0.25rem 0.5rem", borderBottom: "1px solid rgba(127,127,127,0.25)", textAlign: "right", whiteSpace: "nowrap" };

/** Private stats for ROLE_ADMIN (GET /admin/report_stats) */
const Stats = () => {
  const { permissions, isPending: permissionsPending } = usePermissions();
  const notify = useNotify();
  const [period, setPeriod] = useState<Period>("week");
  const [groupBy, setGroupBy] = useState("host");
  const [since, setSince] = useState(defaultSince("week"));
  const [rows, setRows] = useState<Row[] | null>(null);
  const isAdmin = hasRole(permissions, ROLE_ADMIN);

  useEffect(() => {
    if (!isAdmin) {
      return;
    }
    const query = new URLSearchParams({ period, since });
    if (groupBy) query.set("groupBy", groupBy);
    setRows(null);
    apiFetch<{ member?: Row[] }>(`/admin/report_stats?${query}`)
      .then((body) => setRows(body?.member ?? []))
      .catch((error: Error) => {
        notify(error.message, { type: "error" });
        setRows([]);
      });
  }, [isAdmin, period, groupBy, since, notify]);

  // Pivot: one line per key (top MAX_KEYS by reports), one column per period
  const { periods, lines } = useMemo(() => {
    const periods = Array.from(new Set((rows ?? []).map((row) => row.period))).sort();
    const byKey = new Map<string, { key: string | null; total: number; cells: Map<string, Row> }>();
    for (const row of rows ?? []) {
      const id = row.key ?? "\0null";
      const line = byKey.get(id) ?? { key: row.key, total: 0, cells: new Map<string, Row>() };
      line.total += row.reports;
      line.cells.set(row.period, row);
      byKey.set(id, line);
    }
    const lines = Array.from(byKey.values()).sort((a, b) => b.total - a.total).slice(0, MAX_KEYS);

    return { periods, lines };
  }, [rows]);
  const max = Math.max(1, ...lines.flatMap((line) => Array.from(line.cells.values()).map((row) => row.reports)));

  if (permissionsPending) {
    return null;
  }
  if (!isAdmin) {
    return <p style={{ padding: "1rem" }}>Stats are available to admins only.</p>;
  }

  return (
    <div style={{ padding: "1rem" }}>
      <Title title="Report stats" />
      <form style={{ display: "flex", gap: "1rem", flexWrap: "wrap", alignItems: "end", marginBottom: "1rem" }} onSubmit={(event) => event.preventDefault()}>
        <label>
          Group by{" "}
          <select value={groupBy} onChange={(event) => setGroupBy(event.target.value)}>
            {GROUP_BY.map((option) => (
              <option key={option.id} value={option.id}>
                {option.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Period{" "}
          <select
            value={period}
            onChange={(event) => {
              const next = event.target.value as Period;
              setPeriod(next);
              setSince(defaultSince(next));
            }}
          >
            <option value="day">Day</option>
            <option value="week">Week</option>
            <option value="month">Month</option>
          </select>
        </label>
        <label>
          Since <input type="date" value={since} onChange={(event) => event.target.value && setSince(event.target.value)} />
        </label>
      </form>
      <p style={{ opacity: 0.7, marginTop: 0 }}>
        Reports received per {period}; the number in brackets counts distinct reporting instances.
        {groupBy && ` Top ${MAX_KEYS} by reports.`}
      </p>
      {null === rows ? (
        <p>Loading…</p>
      ) : 0 === rows.length ? (
        <p>No reports in this range.</p>
      ) : (
        <div style={{ overflowX: "auto" }}>
          <table style={{ borderCollapse: "collapse", fontSize: "0.875rem" }}>
            <thead>
              <tr>
                <th style={{ ...cell, textAlign: "left" }}>{groupBy ? GROUP_BY.find((option) => option.id === groupBy)?.name : ""}</th>
                <th style={cell}>Total</th>
                {periods.map((p) => (
                  <th key={p} style={cell}>
                    {p}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {lines.map((line) => (
                <tr key={line.key ?? "\0null"}>
                  <td style={{ ...cell, textAlign: "left" }}>{line.key ?? (groupBy ? "(none)" : "All reports")}</td>
                  <td style={{ ...cell, fontWeight: 600 }}>{line.total}</td>
                  {periods.map((p) => {
                    const row = line.cells.get(p);

                    return (
                      <td key={p} style={{ ...cell, background: row ? `rgba(25, 118, 210, ${(0.08 + (0.5 * row.reports) / max).toFixed(2)})` : undefined }}>
                        {row ? `${row.reports} (${row.instances})` : ""}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
};

export default Stats;
