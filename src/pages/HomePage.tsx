import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { PhotoFeed } from '@/components/PhotoFeed';
import { StatusResponseSchema } from '@/schemas/photo.schema';

export const HomePage = () => {
  const [eventDate, setEventDate] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch('/api/status.php');
        const raw: unknown = await res.json();
        const parsed = StatusResponseSchema.parse(raw);
        setEventDate(parsed.event_date ?? null);
      } catch {
        setEventDate(null);
      }
    };
    void load();
  }, []);

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 4 }}>
      <Typography variant="h3" component="h1" gutterBottom>
        Document Your Life
      </Typography>
      <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
        One day. Many photographers. One shared moment.
      </Typography>
      <PhotoFeed eventDate={eventDate} />
    </Box>
  );
};
