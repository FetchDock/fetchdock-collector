import {
  BooleanField,
  BooleanInput,
  Button,
  DateField,
  List,
  NumberField,
  Show,
  SimpleShowLayout,
  TextField,
  TopToolbar,
  Datagrid,
  useNotify,
  useRecordContext,
  useRefresh,
  useUpdate,
} from "react-admin";
import { Link } from "react-router-dom";
import { INSTANCES, REPORTS } from "./reports";

type Instance = { id: string; blocked: boolean; retiredAt: string | null };

/** The instance's UUID from its IRI (/admin/instances/{uuid}) */
const uuidOf = (iri: string) => iri.split("/").pop() ?? iri;

/** Blocks or unblocks an instance: a blocked instance's ingest calls get 403 */
const BlockButton = () => {
  const record = useRecordContext<Instance>();
  const [update, { isPending }] = useUpdate();
  const notify = useNotify();
  const refresh = useRefresh();
  if (!record) {
    return null;
  }
  const blocked = !record.blocked;

  return (
    <Button
      label={blocked ? "Block" : "Unblock"}
      disabled={isPending}
      onClick={(event) => {
        event.stopPropagation();
        if (blocked && !window.confirm("Block this instance? Its reports and comments will be rejected with 403.")) {
          return;
        }
        update(
          INSTANCES,
          { id: record.id, data: { blocked }, previousData: record },
          {
            mutationMode: "pessimistic",
            onSuccess: () => {
              notify(blocked ? "Instance blocked" : "Instance unblocked", { type: "info" });
              refresh();
            },
            onError: (error) => notify((error as Error)?.message ?? "Update failed", { type: "error" }),
          },
        );
      }}
    />
  );
};

const ReportsLink = () => {
  const record = useRecordContext<Instance>();
  if (!record) {
    return null;
  }
  const filter = encodeURIComponent(JSON.stringify({ instance: uuidOf(record.id) }));

  return (
    <Link to={`/${REPORTS}?filter=${filter}`} onClick={(event) => event.stopPropagation()}>
      Reports
    </Link>
  );
};

const instanceFilters = [<BooleanInput key="blocked" source="blocked" />];

export const InstanceList = () => (
  <List filters={instanceFilters} sort={{ field: "registeredAt", order: "DESC" }}>
    <Datagrid rowClick="show" bulkActionButtons={false}>
      <TextField source="originId" label="Id" sortable={false} />
      <TextField source="serverVersion" sortable={false} />
      <DateField source="registeredAt" />
      <DateField source="lastSeenOn" />
      <DateField source="retiredAt" emptyText="–" sortable={false} />
      <BooleanField source="blocked" sortable={false} />
      <NumberField source="reportCount" sortable={false} />
      <ReportsLink />
      <BlockButton />
    </Datagrid>
  </List>
);

const InstanceShowActions = () => (
  <TopToolbar>
    <ReportsLink />
    <BlockButton />
  </TopToolbar>
);

export const InstanceShow = () => (
  <Show actions={<InstanceShowActions />}>
    <SimpleShowLayout>
      <TextField source="originId" label="Id" />
      <TextField source="serverVersion" />
      <TextField source="acceptedPolicyVersion" />
      <DateField source="registeredAt" showTime />
      <DateField source="lastSeenOn" />
      <DateField source="retiredAt" showTime emptyText="–" />
      <BooleanField source="blocked" />
      <NumberField source="reportCount" />
    </SimpleShowLayout>
  </Show>
);
