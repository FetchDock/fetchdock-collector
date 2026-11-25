import type { GetServerSideProps } from "next";
import PolicyPage from "../../components/PolicyPage";
import { loadPolicyText, type PolicyText } from "../../components/common/policy";

/** /policy/{version}: the current or a still accepted policy version; 404 for any other */
export const getServerSideProps: GetServerSideProps<{ policy: PolicyText }, { version: string }> = ({ params }) =>
  loadPolicyText(params?.version ?? "");

export default PolicyPage;
