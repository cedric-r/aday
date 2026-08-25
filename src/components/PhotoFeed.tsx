import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import { PhotoFeedResponseSchema } from '@/schemas/photo.schema';
import type { Photo } from '@/schemas/photo.schema';
import { PhotoCard } from './PhotoCard';
import { PhotoLightbox } from './PhotoLightbox';

const POLL_INTERVAL_MS = 60_000;

const isBeforeEventDate = (eventDate: string): boolean => {
  if (!eventDate) return false;
  // Build today's local date as YYYY-MM-DD; lexicographic compare is correct for this format.
  const now = new Date();
  const todayStr = [
    now.getFullYear(),
    String(now.getMonth() + 1).padStart(2, '0'),
    String(now.getDate()).padStart(2, '0'),
  ].join('-');
  return todayStr < eventDate;
};

export const PhotoFeed = ({ eventDate = null, photographer = null }: { eventDate?: string | null; photographer?: string | null }) => {
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [nextCursor, setNextCursor] = useState<string | null>(null);
  const [isLoadingInitial, setIsLoadingInitial] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [lightbox, setLightbox] = useState<{ photos: Photo[]; index: number } | null>(null);
  const [searchParams, setSearchParams] = useSearchParams();

  const feedQuery = photographer
    ? `/api/photos.php?photographer=${encodeURIComponent(photographer)}&limit=20`
    : '/api/photos.php?limit=20';

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
        const data = await fetchPhotos(feedQuery);
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
  }, [feedQuery, fetchPhotos]);

  // Deep link (?photo=N): open the lightbox once the initial feed is known,
  // fetching the single photo if it is not in the loaded page.
  useEffect(() => {
    const raw = searchParams.get('photo');
    const target = raw ? Number(raw) : 0;
    if (!target || isLoadingInitial || lightbox) return;

    const idx = photos.findIndex((p) => p.id === target);
    if (idx >= 0) {
      setLightbox({ photos, index: idx });
    } else {
      void (async () => {
        try {
          const data = await fetchPhotos(`/api/photos.php?photo=${target}`);
          if (data.photos.length > 0) {
            setLightbox({ photos: data.photos, index: 0 });
          }
        } catch {
          // ignore — just don't open a lightbox for a broken deep link
        }
      })();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams]);

  const handleOpen = (photo: Photo) => {
    const idx = photos.findIndex((p) => p.id === photo.id);
    setLightbox({ photos, index: idx >= 0 ? idx : 0 });
    setSearchParams({ photo: String(photo.id) }, { replace: true });
  };

  const handleNavigate = (index: number) => {
    setLightbox((prev) => (prev ? { ...prev, index } : prev));
    const photo = lightbox?.photos[index];
    if (photo) {
      setSearchParams({ photo: String(photo.id) }, { replace: true });
    }
  };

  const handleClose = () => {
    setLightbox(null);
    setSearchParams({}, { replace: true });
  };

  // Polling
  useEffect(() => {
    const poll = async () => {
      if (isPollingRef.current) return;
      isPollingRef.current = true;
      try {
        const since = latestTimestampRef.current;
        const pollQuery = photographer
          ? `/api/photos.php?photographer=${encodeURIComponent(photographer)}`
          : '/api/photos.php';
        const url = since
          ? `${pollQuery}&after=${encodeURIComponent(since)}&limit=50`
          : `${pollQuery}?limit=20`;
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
          {photographer ? 'No photos from this photographer yet. Check back soon!' : 'No photos yet. Check back soon!'}
        </Typography>
        {eventDate && isBeforeEventDate(eventDate) && (
          <Typography color="text.secondary">
            Event date: {eventDate}
          </Typography>
        )}
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
        <PhotoCard key={photo.id} photo={photo} onOpen={handleOpen} />
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

      {lightbox && (
        <PhotoLightbox
          photos={lightbox.photos}
          initialIndex={lightbox.index}
          onClose={handleClose}
          onNavigate={handleNavigate}
        />
      )}
    </Box>
  );
};
