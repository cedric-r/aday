import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { PhotoFeed } from '@/components/PhotoFeed';
import { StatsStrip } from '@/components/StatsStrip';
import { HighlightsStrip } from '@/components/HighlightsStrip';
import { PhotoFeedResponseSchema, StatsResponseSchema, StatusResponseSchema } from '@/schemas/photo.schema';
import type { StatsResponse, Photo } from '@/schemas/photo.schema';

const POLL_MS = 60_000;

export const HomePage = () => {
  const [eventDate, setEventDate] = useState<string | null>(null);
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [highlights, setHighlights] = useState<Photo[]>([]);

  useEffect(() => {
    const loadStatus = async () => {
      try {
        const res = await fetch('/api/status.php');
        const raw: unknown = await res.json();
        const parsed = StatusResponseSchema.parse(raw);
        setEventDate(parsed.event_date ?? null);
      } catch {
        setEventDate(null);
      }
    };

    const loadStats = async () => {
      try {
        const res = await fetch('/api/stats.php');
        const raw: unknown = await res.json();
        setStats(StatsResponseSchema.parse(raw));
      } catch {
        // leave previous stats
      }
    };

    const loadHighlights = async () => {
      try {
        const res = await fetch('/api/photos.php?highlight=1');
        const raw: unknown = await res.json();
        const data = PhotoFeedResponseSchema.parse(raw);
        setHighlights(data.photos);
      } catch {
        // leave previous highlights
      }
    };

    void loadStatus();
    void loadStats();
    void loadHighlights();

    const timer = setInterval(() => {
      void loadStatus();
      void loadStats();
      void loadHighlights();
    }, POLL_MS);

    return () => clearInterval(timer);
  }, []);

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 4 }}>
      <Typography variant="h3" component="h1" gutterBottom>
        Document Your Life
      </Typography>
      <Typography variant="body1" color="text.secondary" sx={{ mb: 4 }}>
        One day. Many photographers. One shared moment.
      </Typography>
      <StatsStrip stats={stats} />
      <HighlightsStrip photos={highlights} />
      <PhotoFeed eventDate={eventDate} />
    </Box>
  );
};