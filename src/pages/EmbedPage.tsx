import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import { PhotoFeed } from '@/components/PhotoFeed';
import { StatusResponseSchema } from '@/schemas/photo.schema';

/**
 * Minimal, header-less page intended for iframing from outside (e.g. a
 * Substack post). Supports:
 *   ?photographer=username  — show only one photographer's photos
 *   ?theme=dark             — dark GitHub palette (default: light)
 * The theme toggle is only useful inside the embed (no header chrome), so it
 * lives here rather than in the main app.
 */
export const EmbedPage = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const photographer = searchParams.get('photographer') || null;
  const [isDark, setIsDark] = useState(
    () => new URLSearchParams(window.location.search).get('theme') === 'dark',
  );
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

  const toggleTheme = () => {
    const next = !isDark;
    setIsDark(next);
    setSearchParams(
      (prev) => {
        const p = new URLSearchParams(prev);
        if (next) p.set('theme', 'dark');
        else p.delete('theme');
        return p;
      },
      { replace: true },
    );
  };

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 3, px: 1 }}>
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
        <Typography variant="h5" component="h1">
          Document Your Life
        </Typography>
        <IconButton onClick={toggleTheme} aria-label="Toggle theme" size="small" title="Toggle light/dark">
          {isDark ? '☀️' : '🌙'}
        </IconButton>
      </Box>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 3 }}>
        {photographer
          ? `Photos by ${photographer} — one day. Many photographers. One shared moment.`
          : 'One day. Many photographers. One shared moment.'}
      </Typography>
      <PhotoFeed eventDate={eventDate} photographer={photographer} />
    </Box>
  );
};