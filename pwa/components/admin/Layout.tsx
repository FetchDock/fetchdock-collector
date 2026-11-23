import type { ReactNode } from "react";
import { Layout as RaLayout, Menu as RaMenu, usePermissions } from "react-admin";
import { hasRole, ROLE_ADMIN } from "../common/permissions";

const Menu = () => {
  const { permissions } = usePermissions();

  return (
    <RaMenu>
      <RaMenu.DashboardItem />
      <RaMenu.ResourceItems />
      {hasRole(permissions, ROLE_ADMIN) && <RaMenu.Item to="/stats" primaryText="Stats" />}
    </RaMenu>
  );
};

const Layout = ({ children }: { children: ReactNode }) => <RaLayout menu={Menu}>{children}</RaLayout>;

export default Layout;
