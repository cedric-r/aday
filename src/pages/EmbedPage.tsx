import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { PhotoFeed } from '@/components/PhotoFeed';
import { useEffect, useState } from 'react';
import { StatusResponseSchema } from '@/schemas/photo.schema';

/**
 * Minimal, header-less page intended for iframing from outside (e.g. a
 * Substack post): "Document Your Life" heading + the live feed only.
 */
export const EmbedPage = () => {
  const [eventDate, setEventDate] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch('/api/status.php');
        const raw: unknown = await res.json();
        setEventDate(StatusResponseSchema.parse(raw).event_date ?? null);
      } catch {
        setEventDate(null);
      }
    };
    void load();
  }, []);

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 3, px: 1 }}>
      <Typography variant="h5" component="h1" sx={{ mb: 1 }}>
        Document Your Life
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        One day. Many photographers. One shared moment.
      </Typography>
      <PhotoFeed eventDate={eventDate} />
    </Box>
  );
};