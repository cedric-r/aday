import AppBar from '@mui/material/AppBar';
import Toolbar from '@mui/material/Toolbar';
import Box from '@mui/material/Box';
import { Link } from 'react-router-dom';
import { Nav } from './Nav';

export const Header = () => (
  <AppBar position="sticky" elevation={2}>
    <Toolbar sx={{ gap: 2 }}>
      <Box
        component={Link}
        to="/"
        sx={{
          display: 'flex',
          alignItems: 'center',
          color: 'inherit',
          textDecoration: 'none',
          flexShrink: 0,
        }}
      >
        <Box
          component="img"
          src="/documentyourlife.png"
          alt="Document Your Life"
          sx={{ height: 48, objectFit: 'contain' }}
        />
      </Box>

      <Nav />
    </Toolbar>
  </AppBar>
);
