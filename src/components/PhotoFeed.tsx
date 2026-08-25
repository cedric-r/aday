import { useCallback, useEffect, useRef, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import { PhotoFeedResponseSchema } from '@/schemas/photo.schema';
import type { Photo } from '@/schemas/photo.schema';
import { PhotoCard } from './PhotoCard';

const POLL_INTERVAL_MS = 60_000;

export const PhotoFeed = () => {
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [isLoadingInitial, setIsLoadingInitial] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const latestTimestampRef = useRef<string | null>(null);
  const isPollingRef = useRef(false);

  const fetchPhotos = useCallback(async (url: string): Promise<{ photos: Photo[]; next_cursor: string | null }> => {
    const res = await fetch(url);
    const raw: unknown = await res.json();
    return PhotoFeedResponseSchema.parse(raw);
  }, []);

  // Initial load
  useEffect(() => {
    const load = async () => {
      try {
        const data = await fetchPhotos('/api/photos.php?limit=20');
        setPhotos(data.photos);
        setNextCursor(data.next_cursor);
        if (data.photos.length > 0) {
          latestTimestampRef.current = data.photos[0].posted_at;
        }
      } catch {
        setError('Failed to load photos. Please refresh.');
      } finally {
        setIsLoadingInitial(false);
      }
    };
    void load();
  }, [fetchPhotos]);

  // Polling
  useEffect(() => {
    const poll = async () => {
      if (isPollingRef.current) return;
      isPollingRef.current = true;
      try {
        const since = latestTimestampRef.current;
        const url = since
          ? `/api/photos.php?after=${encodeURIComponent(since)}&limit=50`
          : '/api/photos.php?limit=20';
        const data = await fetchPhotos(url);
        if (data.photos.length > 0) {
          setPhotos((prev) => [...data.photos, ...prev]);
          latestTimestampRef.current = data.photos[0].posted_at;
        }
      } finally {
        isPollingRef.current = false;
      }
    };

    const timer = setInterval(() => { void poll(); }, POLL_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [fetchPhotos]);

  const handleLoadMore = async () => {
    if (!nextCursor) return;
    setIsLoadingMore(true);
    try {
      const data = await fetchPhotos(
        `/api/photos.php?before=${encodeURIComponent(nextCursor)}&limit=20`,
      );
      setPhotos((prev) => [...prev, ...data.photos]);
      setNextCursor(data.next_cursor);
    } finally {
      setIsLoadingMore(false);
    }
  };

  if (isLoadingInitial) {
    return (
      <Box display="flex" justifyContent="center" mt={4}>
        <CircularProgress />
      </Box>
    );
  }

  if (error) {
    return <Typography color="error">{error}</Typography>;
  }

  if (photos.length === 0) {
    return (
      <Box display="flex" flexDirection="column" alignItems="center" textAlign="center" gap={3} mt={2}>
        <Typography color="text.secondary">
          No photos yet. Check back soon!
        </Typography>
        <Box
          component="img"
          src="/documentyourlife.png"
          alt="Document Your Life"
          sx={{ width: 260, height: 260, objectFit: 'contain', opacity: 0.9 }}
        />
      </Box>
    );
  }

  return (
    <Box>
      {photos.map((photo) => (
        <PhotoCard key={photo.id} photo={photo} />
      ))}

      {nextCursor && (
        <Box display="flex" justifyContent="center" mt={2}>
          <Button
            variant="outlined"
            onClick={() => { void handleLoadMore(); }}
            disabled={isLoadingMore}
          >
            {isLoadingMore ? 'Loading…' : 'Load more'}
          </Button>
        </Box>
      )}
    </Box>
  );
};
