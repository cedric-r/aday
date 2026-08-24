import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import { NavLink } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';

interface NavButtonProps {
  to: string;
  children: React.ReactNode;
}

const NavButton = ({ to, children }: NavButtonProps) => (
  <Button
    component={NavLink}
    to={to}
    color="inherit"
    size="small"
    sx={{
      opacity: 0.85,
      '&.active': {
        opacity: 1,
        fontWeight: 700,
        borderBottom: '2px solid currentColor',
        borderRadius: 0,
      },
    }}
  >
    {children}
  </Button>
);

export const Nav = () => {
  const { user, logout } = useAuth();

  const handleLogout = () => {
    void logout();
  };

  return (
    <Box
      component="nav"
      sx={{ display: 'flex', alignItems: 'center', gap: 0.5, ml: 'auto', flexWrap: 'wrap' }}
    >
      <NavButton to="/">Home</NavButton>
      <NavButton to="/index">Index</NavButton>

      {!user && <NavButton to="/login">Log in</NavButton>}
      {user && !user.is_admin && <NavButton to="/post">Post</NavButton>}
      {user?.is_admin && <NavButton to="/admin">Admin</NavButton>}

      {user && (
        <Button
          color="inherit"
          size="small"
          variant="outlined"
          onClick={handleLogout}
          sx={{ opacity: 0.85, ml: 1, borderColor: 'rgba(255,255,255,0.5)' }}
        >
          Log out
        </Button>
      )}
    </Box>
  );
};
