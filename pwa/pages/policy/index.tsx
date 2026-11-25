import type { GetServerSideProps } from "next";
import PolicyPage from "../../components/PolicyPage";
import { loadPolicyText, type PolicyText } from "../../components/common/policy";

/** /policy: the current collector policy (the `url` of GET /v1/policy) */
export const getServerSideProps: GetServerSideProps<{ policy: PolicyText }> = () => loadPolicyText();

export default PolicyPage;
