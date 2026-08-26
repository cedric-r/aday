import { useCallback, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import IconButton from '@mui/material/IconButton';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { PhotoFeedResponseSchema } from '@/schemas/photo.schema';
import type { Photo } from '@/schemas/photo.schema';
import { PhotoCard } from './PhotoCard';
import { PhotoLightbox } from './PhotoLightbox';

const POLL_INTERVAL_MS = 60_000;

type FeedView = 'cards' | 'grid';

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
  const [view, setView] = useState<FeedView>(() => {
    try {
      return localStorage.getItem('aday_view') === 'grid' ? 'grid' : 'cards';
    } catch {
      return 'cards';
    }
  });
  const [hour, setHour] = useState<number | null>(null);
  const [isRandomLoading, setIsRandomLoading] = useState(false);

  const feedQuery =
    hour !== null
      ? `/api/photos.php?hour=${hour}&limit=50`
      : photographer
        ? `/api/photos.php?photographer=${encodeURIComponent(photographer)}&limit=20`
        : '/api/photos.php?limit=20';

  const latestTimestampRef = useRef<string | null>(null);
  const latestIdRef = useRef<number>(0);
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
          latestIdRef.current = data.photos[0].id;
        }
      } catch {
        setError('Failed to load photos. Please refresh.');
      } finally {
        setIsLoadingInitial(false);
      }
    };
    void load();
  }, [feedQuery, fetchPhotos]);

  // Deep link (?photo=N): opens the lightbox once the initial feed is known,
  // fetching the single photo if it is not in the loaded page. Keyed on the
  // photo param + loading state (not on the mount effect), so a cold visit to
  // /?photo=N works: the effect runs again once isLoadingInitial flips false.
  const photoParam = searchParams.get('photo');
  useEffect(() => {
    if (isLoadingInitial || lightbox) return;
    const target = photoParam ? Number(photoParam) : 0;
    if (!target) return;

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
    // NOTE: `lightbox` is intentionally NOT a dependency. The router's
    // searchParams update lags one tick behind local state on close; keying
    // on `lightbox` would make the effect re-run in that lag tick with a
    // stale photoParam and immediately re-open the lightbox (audit C2
    // regression). Keying on photoParam/loading/photos is sufficient: the
    // effect only needs to run at cold load (loading flip) and on URL change.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [photoParam, isLoadingInitial, photos, fetchPhotos]);

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

  const changeView = (next: FeedView) => {
    setView(next);
    try {
      localStorage.setItem('aday_view', next);
    } catch {
      // storage unavailable — view still applies for this session
    }
  };

  // Polling
  useEffect(() => {
    const poll = async () => {
      if (isPollingRef.current) return;
      isPollingRef.current = true;
      try {
        // In hour-filter mode the poll would need a tz-aware "new matches"
        // query the API doesn't offer — skip polling instead of mixing
        // other-hour photos into the filtered feed. The banner's "clear"
        // button returns to the normal live feed.
        if (hour !== null) return;
        const since = latestTimestampRef.current;
        const sinceId = latestIdRef.current;
        const pollQuery = photographer
          ? `/api/photos.php?photographer=${encodeURIComponent(photographer)}`
          : '/api/photos.php';
        const url = since
          ? `${pollQuery}&after=${encodeURIComponent(`${since}|${sinceId}`)}&limit=50`
          : `${pollQuery}?limit=20`;
        const data = await fetchPhotos(url);
        if (data.photos.length > 0) {
          setPhotos((prev) => [...data.photos, ...prev]);
          latestTimestampRef.current = data.photos[0].posted_at;
          latestIdRef.current = data.photos[0].id;
        }
      } finally {
        isPollingRef.current = false;
      }
    };

    const timer = setInterval(() => { void poll(); }, POLL_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [fetchPhotos, photographer, hour]);

    const handleLoadMore = async () => {
      if (!nextCursor) return;
      setIsLoadingMore(true);
      try {
        const data = await fetchPhotos(`${feedQuery}${feedQuery.includes('?') ? '&' : '?'}before=${encodeURIComponent(nextCursor)}`);
        setPhotos((prev) => [...prev, ...data.photos]);
        setNextCursor(data.next_cursor);
      } catch {
        setError('Failed to load more photos.');
      } finally {
        setIsLoadingMore(false);
      }
    };

    const handleRandom = async () => {
      setIsRandomLoading(true);
      try {
        const data = await fetchPhotos('/api/photos.php?random=1');
        if (data.photos.length > 0) {
          const photo = data.photos[0];
          // Show it in a one-photo lightbox (deep-linkable like the rest).
          // NOTE: deliberately do NOT touch latestTimestampRef/latestIdRef —
          // the random photo can be older than the feed's newest, and moving
          // the poll cursor backwards would re-fetch existing photos as
          // "new" duplicates on the next tick.
          setLightbox({ photos: [photo], index: 0 });
          setSearchParams({ photo: String(photo.id) }, { replace: true });
        }
      } catch {
        // ignore — button is best-effort
      } finally {
        setIsRandomLoading(false);
      }
    };

    const changeHour = (next: number | null) => {
      setHour(next);
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
      {hour !== null && (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
          <Typography variant="body2" color="text.secondary">
            Local hour <strong>{String(hour).padStart(2, '0')}:00</strong> across every photographer's day
            (ordered east → west) ·{' '}
            <Button size="small" onClick={() => changeHour(null)} sx={{ p: 0, minWidth: 0, textTransform: 'none' }}>
              clear
            </Button>
          </Typography>
        </Box>
      )}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 1 }}>
        <TextField
          select
          size="small"
          label="Follow the sun — local hour"
          value={hour === null ? '' : String(hour)}
          onChange={(e) => {
            const v = e.target.value;
            changeHour(v === '' ? null : Number(v));
          }}
          sx={{ minWidth: 240 }}
          SelectProps={{ displayEmpty: true }}
          data-testid="hour-select"
        >
          <MenuItem value="">All hours</MenuItem>
          {Array.from({ length: 24 }, (_, h) => (
            <MenuItem key={h} value={String(h)}>
              {String(h).padStart(2, '0')}:00 local
            </MenuItem>
          ))}
        </TextField>
        <Box sx={{ display: 'flex', gap: 0.5 }}>
          <IconButton
            aria-label="Cards view"
            title="Cards view"
            size="small"
            color={view === 'cards' ? 'primary' : 'default'}
            onClick={() => changeView('cards')}
          >
            <Box component="svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" sx={{ width: 20, height: 20, fill: 'currentColor' }} aria-hidden="true">
              <path d="M3 5h18v4H3V5zm0 10h18v4H3v-4zm0-5h8v4H3v-4zm11 0h7v4h-7v-4z" />
            </Box>
          </IconButton>
          <IconButton
            aria-label="Grid view"
            title="Grid view"
            size="small"
            color={view === 'grid' ? 'primary' : 'default'}
            onClick={() => changeView('grid')}
          >
            <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '2px', width: 20, height: 20 }} aria-hidden="true">
              <Box sx={{ bgcolor: 'currentColor', borderRadius: '2px' }} />
              <Box sx={{ bgcolor: 'currentColor', borderRadius: '2px' }} />
              <Box sx={{ bgcolor: 'currentColor', borderRadius: '2px' }} />
              <Box sx={{ bgcolor: 'currentColor', borderRadius: '2px' }} />
            </Box>
          </IconButton>
          <IconButton
            aria-label="Surprise me"
            title="Show a random photo"
            size="small"
            disabled={isRandomLoading}
            onClick={() => { void handleRandom(); }}
          >
            🎲
          </IconButton>
        </Box>
      </Box>

      {view === 'grid' ? (
        <Box sx={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(160px, 1fr))', gap: 1 }}>
          {photos.map((photo) => (
            <Box
              key={photo.id}
              role="button"
              tabIndex={0}
              aria-label={photo.description || `${photo.name}'s photo`}
              onClick={() => handleOpen(photo)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                  e.preventDefault();
                  handleOpen(photo);
                }
              }}
              sx={{
                aspectRatio: '1',
                overflow: 'hidden',
                borderRadius: 1,
                cursor: 'zoom-in',
                bgcolor: 'action.hover',
                '&:focus-visible': { outline: '2px solid', outlineColor: 'primary.main' },
              }}
              title={photo.description || photo.name}
            >
              <Box
                component="img"
                src={photo.thumb_url || `/uploads/${photo.username}/${photo.filename}`}
                alt={photo.description || `${photo.name}'s photo`}
                loading="lazy"
                sx={{ width: '100%', height: '100%', objectFit: 'cover', display: 'block' }}
              />
            </Box>
          ))}
        </Box>
      ) : (
        photos.map((photo) => (
          <PhotoCard key={photo.id} photo={photo} onOpen={handleOpen} />
        ))
      )}

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
