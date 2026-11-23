import Button from "@mui/material/Button";
import { Login, useLogin } from "react-admin";

/**
 * MUI's button rather than react-admin's: that one becomes an icon-only button on small screens, and without an icon
 * the sign-in button was invisible on phones.
 */
const LoginPage = () => {
  const login = useLogin();

  return (
    <Login>
      <Button variant="contained" onClick={() => login({})} sx={{ m: 2 }}>
        Sign in
      </Button>
    </Login>
  );
};

export default LoginPage;
