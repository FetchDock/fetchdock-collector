import { useEffect, useState } from "react";
import { type CollectorIntegrity, commitUrl, REPOSITORY_URL, statusColor, statusLabel } from "./common/integrity";

/** The public integrity status on the index page (GET /v1/integrity), with what it does and doesn't prove */
const IntegrityStatus = () => {
  const [integrity, setIntegrity] = useState<CollectorIntegrity | null>(null);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    fetch("/v1/integrity", { headers: { Accept: "application/json" } })
      .then((response) => (response.ok ? response.json() : Promise.reject(new Error(response.statusText))))
      .then(setIntegrity)
      .catch(() => setFailed(true));
  }, []);

  return (
    <section className="mt-10" aria-labelledby="integrity">
      <h2 id="integrity" className="text-xl font-semibold mb-3">
        Does this service run the published code?
      </h2>
      {failed ? (
        <p className="mb-3">The integrity status is not available right now.</p>
      ) : null === integrity ? (
        <p className="mb-3 opacity-70">Checking…</p>
      ) : (
        <>
          <p className="mb-3">
            <span
              className="inline-block rounded px-2 py-1 text-sm font-semibold text-white"
              style={{ background: statusColor[integrity.status] }}
            >
              {statusLabel(integrity.status, integrity.appVersion)}
            </span>
          </p>
          <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1 text-sm mb-4">
            <dt className="font-semibold">Version</dt>
            <dd>{integrity.appVersion}</dd>
            <dt className="font-semibold">Commit</dt>
            <dd className="font-mono break-all">
              {integrity.gitSha ? (
                <a className="underline" href={commitUrl(integrity.gitSha)}>
                  {integrity.gitSha}
                </a>
              ) : (
                "unknown"
              )}
            </dd>
            <dt className="font-semibold">Manifest digest</dt>
            <dd className="font-mono break-all">
              {integrity.manifestDigest ?? "none"}
              {integrity.digestUrl && (
                <>
                  {" "}
                  (<a className="underline font-sans" href={integrity.digestUrl}>
                    published digest
                  </a>
                  )
                </>
              )}
            </dd>
            <dt className="font-semibold">Last check</dt>
            <dd>{integrity.checkedAt ? new Date(integrity.checkedAt).toLocaleString() : "none in the last 48 hours"}</dd>
          </dl>
        </>
      )}
      <div className="text-sm space-y-2 opacity-90">
        <p>
          Every official build records a SHA-256 hash of each application file of the api image (code, configuration,
          dependencies) in a manifest, and CI publishes the manifest&apos;s digest for that version on the{" "}
          <a className="underline" href={`${REPOSITORY_URL}/tree/integrity-data`}>
            integrity-data
          </a>{" "}
          branch. The api and worker containers check their files against the manifest on start and daily, and compare
          the manifest with the published digest. The status is the worst result of the last 48 hours.
        </p>
        <p>
          <strong>This is a signal, not proof.</strong> It catches honest drift: local patches, images built from changed
          source, mounted source folders. A deliberately modified deployment can report anything it likes. The check
          covers the api image only: not this web page (the PWA container), the base image, PHP or OS packages.
        </p>
        <p>
          To verify it yourself, compare the manifest digest above with the published file for this version, or rebuild
          the api image from the release tag and run <code>bin/console app:integrity:manifest</code>: it prints the
          digest the build produces.
        </p>
      </div>
    </section>
  );
};

export default IntegrityStatus;
