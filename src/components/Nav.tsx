import { NavLink } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';

export const Nav = () => {
  const { user, logout } = useAuth();

  const handleLogout = () => {
    void logout();
  };

  return (
    <nav>
      <NavLink to="/">Home</NavLink>
      <NavLink to="/index">Index</NavLink>

      {!user && <NavLink to="/login">Log in</NavLink>}
      {user && !user.is_admin && <NavLink to="/post">Post</NavLink>}
      {user?.is_admin && <NavLink to="/admin">Admin</NavLink>}
      {user && <button type="button" onClick={handleLogout}>Log out</button>}
    </nav>
  );
};
