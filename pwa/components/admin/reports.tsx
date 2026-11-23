import {
  ChipField,
  DateField,
  DeleteButton,
  Edit,
  EditButton,
  FunctionField,
  Labeled,
  List,
  NumberField,
  SaveButton,
  SelectField,
  SelectInput,
  Show,
  ShowButton,
  SimpleForm,
  SimpleShowLayout,
  TextField,
  TextInput,
  Toolbar,
  TopToolbar,
  UrlField,
  Datagrid,
  useCreatePath,
  usePermissions,
  useRecordContext,
} from "react-admin";
import { Link } from "react-router-dom";
import { hasRole, ROLE_ADMIN } from "../common/permissions";

export const REPORTS = "admin/reports";
export const INSTANCES = "admin/instances";

export const STATUS_CHOICES = [
  { id: "new", name: "New" },
  { id: "acknowledged", name: "Acknowledged" },
  { id: "resolved", name: "Resolved" },
  { id: "wont_fix", name: "Won't fix" },
  { id: "duplicate", name: "Duplicate" },
];

export const KIND_CHOICES = [
  { id: "job_failed", name: "Failed job" },
  { id: "unsupported_url", name: "Unsupported URL" },
];

/** The rules in the API's config/packages/collector.yaml (collector.error_categories), plus "other" */
export const ERROR_CATEGORY_CHOICES = [
  "http_403",
  "http_429",
  "geo_blocked",
  "login_required",
  "unsupported_url",
  "extractor_error",
  "other",
].map((id) => ({ id, name: id }));

type Report = {
  id: string;
  instance: string | null;
  diagnostics: Record<string, string> | null;
  consent: { policyVersion: string; consentedAt: string };
  comments: { id: string; comment: string; createdAt: string; receivedAt: string }[];
};

const reportFilters = (isAdmin: boolean) => [
  <SelectInput key="status" source="status" choices={STATUS_CHOICES} alwaysOn />,
  <SelectInput key="kind" source="kind" choices={KIND_CHOICES} alwaysOn />,
  <TextInput key="host" source="host" label="Host (exact)" alwaysOn />,
  <TextInput key="site" source="site" label="Site (exact)" />,
  <TextInput key="downloader" source="downloader" label="Downloader (exact)" />,
  <SelectInput key="errorCategory" source="errorCategory" choices={ERROR_CATEGORY_CHOICES} />,
  ...(isAdmin ? [<TextInput key="instance" source="instance" label="Instance id" />] : []),
];

/** Link to the instance (ROLE_ADMIN), whose id is only in reports for admins */
const InstanceLink = () => {
  const record = useRecordContext<Report>();
  const createPath = useCreatePath();
  if (!record?.instance) {
    return null;
  }

  return (
    <Link to={createPath({ resource: INSTANCES, type: "show", id: `/admin/instances/${record.instance}` })} onClick={(e) => e.stopPropagation()}>
      {record.instance.slice(0, 8)}
    </Link>
  );
};

export const ReportList = () => {
  const { permissions } = usePermissions();
  const isAdmin = hasRole(permissions, ROLE_ADMIN);

  return (
    <List filters={reportFilters(isAdmin)} filterDefaultValues={{ status: "new" }} sort={{ field: "receivedAt", order: "DESC" }}>
      <Datagrid rowClick="show" bulkActionButtons={false}>
        <DateField source="receivedAt" showTime />
        <SelectField source="kind" choices={KIND_CHOICES} sortable={false} />
        <TextField source="host" sortable={false} />
        <TextField source="site" sortable={false} emptyText="–" />
        <FunctionField label="Downloader" render={(record: Report) => record.diagnostics?.downloader ?? "–"} />
        <ChipField source="errorCategory" sortable={false} emptyText="–" />
        <SelectField source="status" choices={STATUS_CHOICES} sortable={false} />
        <FunctionField label="Comments" render={(record: Report) => record.comments?.length ?? 0} />
        {isAdmin && <InstanceLink />}
        <EditButton label="Triage" />
      </Datagrid>
    </List>
  );
};

const Pre = ({ children }: { children: string }) => (
  <pre style={{ whiteSpace: "pre-wrap", wordBreak: "break-word", margin: 0, fontFamily: "monospace", fontSize: "0.85rem" }}>{children}</pre>
);

const Diagnostics = () => {
  const record = useRecordContext<Report>();
  if (!record?.diagnostics) {
    return <span>–</span>;
  }
  const { errorMessage, ...rest } = record.diagnostics;

  return (
    <div>
      {Object.entries(rest).map(([key, value]) => (
        <div key={key}>
          <strong>{key}:</strong> {value}
        </div>
      ))}
      {errorMessage && <Pre>{errorMessage}</Pre>}
    </div>
  );
};

/** Comments the instance's admin appended after forwarding */
const Comments = () => {
  const record = useRecordContext<Report>();
  if (!record?.comments?.length) {
    return <span>–</span>;
  }

  return (
    <div style={{ display: "grid", gap: "0.75rem" }}>
      {record.comments.map((comment) => (
        <div key={comment.id}>
          <small>
            Written {new Date(comment.createdAt).toLocaleString()}, received {new Date(comment.receivedAt).toLocaleString()}
          </small>
          <Pre>{comment.comment}</Pre>
        </div>
      ))}
    </div>
  );
};

const ReportShowActions = () => {
  const { permissions } = usePermissions();

  return (
    <TopToolbar>
      <EditButton label="Triage" />
      {hasRole(permissions, ROLE_ADMIN) && <DeleteButton mutationMode="pessimistic" confirmTitle="Delete this report and its comments?" />}
    </TopToolbar>
  );
};

export const ReportShow = () => {
  const { permissions } = usePermissions();

  return (
    <Show actions={<ReportShowActions />}>
      <SimpleShowLayout>
        <SelectField source="status" choices={STATUS_CHOICES} />
        <TextField source="maintainerNote" emptyText="–" />
        <SelectField source="kind" choices={KIND_CHOICES} />
        <UrlField source="url" target="_blank" rel="noopener noreferrer nofollow" />
        <TextField source="host" />
        <TextField source="site" emptyText="–" />
        <ChipField source="errorCategory" emptyText="–" />
        <Labeled label="Diagnostics">
          <Diagnostics />
        </Labeled>
        <FunctionField label="User comment" render={(record: { comment: string | null }) => (record.comment ? <Pre>{record.comment}</Pre> : "–")} />
        <FunctionField label="Instance admin comment" render={(record: { adminComment: string | null }) => (record.adminComment ? <Pre>{record.adminComment}</Pre> : "–")} />
        <Labeled label="Later comments from the instance">
          <Comments />
        </Labeled>
        <TextField source="userAgent" emptyText="–" />
        <TextField source="referrer" emptyText="–" />
        <TextField source="serverVersion" />
        <FunctionField
          label="Consent"
          render={(record: Report) => `Policy ${record.consent.policyVersion}, ${new Date(record.consent.consentedAt).toLocaleString()}`}
        />
        <TextField source="operatorPolicyVersion" label="Operator policy version" />
        <NumberField source="schemaVersion" />
        <DateField source="reportedAt" showTime />
        <DateField source="receivedAt" showTime />
        <DateField source="updatedAt" showTime />
        {hasRole(permissions, ROLE_ADMIN) && (
          <Labeled label="Instance">
            <InstanceLink />
          </Labeled>
        )}
      </SimpleShowLayout>
    </Show>
  );
};

const TriageToolbar = () => (
  <Toolbar>
    <SaveButton />
  </Toolbar>
);

const TriageActions = () => (
  <TopToolbar>
    <ShowButton />
  </TopToolbar>
);

/** Triage: status and the maintainers' internal note; the report itself stays as received */
export const ReportEdit = () => (
  <Edit actions={<TriageActions />} mutationMode="pessimistic" redirect="show">
    <SimpleForm toolbar={<TriageToolbar />}>
      <TextField source="url" />
      <SelectInput source="status" choices={STATUS_CHOICES} />
      <TextInput source="maintainerNote" multiline minRows={4} fullWidth helperText="Internal, max. 4000 characters" />
      <Labeled label="Diagnostics">
        <Diagnostics />
      </Labeled>
    </SimpleForm>
  </Edit>
);
