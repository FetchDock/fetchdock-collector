import type { NextPage } from "next";
import dynamic from "next/dynamic";
import { configureOidc, getOidcServerSideProps, OidcPageProps } from "../../components/common/oidcConfig";

// The admin runs client-side only
const App = dynamic(() => import("../../components/admin/App"), {
  ssr: false,
  loading: () => <p>Loading...</p>,
});

const Admin: NextPage<OidcPageProps> = ({ oidc }) => {
  configureOidc(oidc);

  return <App />;
};

// The OIDC settings are read at request time, so the published image works for any deployment
export const getServerSideProps = getOidcServerSideProps;

export default Admin;
