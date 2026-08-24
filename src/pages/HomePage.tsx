import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { PhotoFeed } from '@/components/PhotoFeed';

export const HomePage = () => (
  <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 4 }}>
    <Typography variant="h3" component="h1" gutterBottom>
      A Day In The Life
    </Typography>
    <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
      One day. Many photographers. One shared moment.
    </Typography>
    <PhotoFeed />
  </Box>
);
