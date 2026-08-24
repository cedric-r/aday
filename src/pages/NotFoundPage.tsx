import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import { Link } from 'react-router-dom';

export const NotFoundPage = () => (
  <Box sx={{ textAlign: 'center', mt: 8 }}>
    <Typography variant="h3" component="h1" gutterBottom>
      404 — Page not found
    </Typography>
    <Button component={Link} to="/" variant="contained">
      Back to home
    </Button>
  </Box>
);
