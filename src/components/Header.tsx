import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Link } from 'react-router-dom';
import { Nav } from './Nav';

/** Inline SVG camera icon — always visible regardless of asset availability */
const LogoIcon = () => (
  <Box
    component="svg"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    aria-hidden="true"
    sx={{ width: 32, height: 32, fill: 'currentColor', flexShrink: 0 }}
  >
    <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5M7 2l-1.83 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-2.17L17 2H7m5 4a6 6 0 0 1 6 6 6 6 0 0 1-6 6 6 6 0 0 1-6-6 6 6 0 0 1 6-6z" />
  </Box>
);

export const Header = () => (
  <AppBar position="sticky" color="primary" elevation={2}>
    <Toolbar sx={{ gap: 2 }}>
      <Box
        component={Link}
        to="/"
        sx={{
          display: 'flex',
          alignItems: 'center',
          gap: 1,
          color: 'inherit',
          textDecoration: 'none',
          flexShrink: 0,
        }}
      >
        <LogoIcon />
        <Typography
          variant="h6"
          component="span"
          sx={{ fontWeight: 700, letterSpacing: '0.02em', whiteSpace: 'nowrap' }}
        >
          A Day In The Life
        </Typography>
      </Box>

      <Nav />
    </Toolbar>
  </AppBar>
);
