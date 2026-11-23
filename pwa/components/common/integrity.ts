/** Integrity check of the collector's own code (ADR 0013, collector#5): a signal that catches honest drift, not proof */
export type IntegrityStatus = "verified" | "modified" | "unknown";

/** GET /v1/integrity (public) */
export type CollectorIntegrity = {
  status: IntegrityStatus;
  appVersion: string;
  gitSha: string | null;
  manifestDigest: string | null;
  digestUrl: string | null;
  checkedAt: string | null;
};

export const REPOSITORY_URL = "https://github.com/FetchDock/fetchdock-collector";

export const commitUrl = (gitSha: string) => `${REPOSITORY_URL}/commit/${encodeURIComponent(gitSha)}`;

export const statusLabel = (status: IntegrityStatus, appVersion: string): string =>
  ({
    verified: `Unmodified fetchdock-collector ${appVersion}`,
    modified: "Modified",
    unknown: "Can't be verified",
  })[status];

export const statusColor: Record<IntegrityStatus, string> = {
  verified: "#2e7d32",
  modified: "#c62828",
  unknown: "#757575",
};
