import Head from "next/head";
import Link from "next/link";
import Markdown from "react-markdown";
import remarkGfm from "remark-gfm";
import type { PolicyText } from "./common/policy";

/** A collector policy version, public (GET /v1/policy points instances and their users here) */
const PolicyPage = ({ policy }: { policy: PolicyText }) => (
  <main className="policy mx-auto max-w-3xl px-4 py-12 font-sans">
    <Head>
      <title>{`Privacy policy, version ${policy.version} · FetchDock Collector`}</title>
    </Head>
    <nav className="mb-6 text-sm">
      <Link className="underline" href="/">
        FetchDock Collector
      </Link>
    </nav>
    {!policy.current && (
      <p role="note" className="mb-6 rounded border border-amber-500 bg-amber-50 px-4 py-3 text-amber-900">
        This is version {policy.version} of the policy, which still applies to consent given under it. The current
        version is at{" "}
        <Link className="underline" href="/policy">
          /policy
        </Link>
        .
      </p>
    )}
    {/* No raw HTML: react-markdown escapes it, so the policy files can't inject markup */}
    <Markdown remarkPlugins={[remarkGfm]}>{policy.markdown}</Markdown>
  </main>
);

export default PolicyPage;
