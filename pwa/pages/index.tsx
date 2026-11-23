import Head from "next/head";
import Link from "next/link";
import IntegrityStatus from "../components/IntegrityStatus";

const Home = () => (
  <main className="mx-auto max-w-2xl px-4 py-16 font-sans">
    <Head>
      <title>FetchDock Collector</title>
    </Head>
    <h1 className="text-3xl font-bold mb-4">FetchDock Collector</h1>
    <p className="mb-4">
      This service receives failure reports that FetchDock instances forward with their users&apos; consent, so the
      FetchDock developers can fix broken sites and downloaders.
    </p>
    <ul className="list-disc pl-6 space-y-2">
      <li>
        Source code, privacy policy and retention rules:{" "}
        <a className="underline" href="https://github.com/FetchDock/fetchdock-collector">
          github.com/FetchDock/fetchdock-collector
        </a>
      </li>
      <li>
        <Link className="underline" href="/admin">
          Maintainer sign-in
        </Link>
      </li>
    </ul>
    <IntegrityStatus />
  </main>
);

export default Home;
