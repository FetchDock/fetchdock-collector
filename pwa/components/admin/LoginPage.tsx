import { Button, Login, useLogin } from "react-admin";

const LoginPage = () => {
  const login = useLogin();

  return (
    <Login>
      <Button variant="contained" label="Sign in" onClick={() => login({})} sx={{ m: 2 }} />
    </Login>
  );
};

export default LoginPage;
