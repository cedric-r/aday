import { useEffect, useRef, useState } from 'react';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Typography from '@mui/material/Typography';
import type { Photo } from '@/schemas/photo.schema';
import { exifLine } from './PhotoCard';

interface Props {
  photos: Photo[];
  initialIndex: number;
  onClose: () => void;
  onNavigate: (index: number) => void;
}

const SLIDESHOW_INTERVAL_MS = 5000;

const gearCaption = (photo: Photo): string | null => {
  const note = photo.gear?.trim() ? photo.gear.trim() : null;
  const exif = exifLine(photo);
  return note && exif ? `${note} — ${exif}` : (note ?? exif);
};

const ArrowButton = ({ direction, onClick }: { direction: 'prev' | 'next'; onClick: () => void }) => (
  <IconButton
    onClick={(e) => {
      e.stopPropagation();
      onClick();
    }}
    aria-label={direction === 'prev' ? 'Previous photo' : 'Next photo'}
    sx={{
      position: 'absolute',
      top: '50%',
      transform: 'translateY(-50%)',
      left: direction === 'prev' ? 8 : undefined,
      right: direction === 'next' ? 8 : undefined,
      zIndex: 2,
      color: 'white',
      bgcolor: 'rgba(0,0,0,0.35)',
      '&:hover': { bgcolor: 'rgba(0,0,0,0.6)' },
    }}
  >
    <Box
      component="svg"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      sx={{
        width: 28,
        height: 28,
        fill: 'currentColor',
        transform: direction === 'prev' ? 'scaleX(-1)' : undefined,
      }}
      aria-hidden="true"
    >
      <path d="M8.59 16.59 13.17 12 8.59 7.41 10 6l6 6-6 6z" />
    </Box>
  </IconButton>
);

export const PhotoLightbox = ({ photos, initialIndex, onClose, onNavigate }: Props) => {
  const photo = photos[initialIndex];
  const [playing, setPlaying] = useState(false);
  const navRef = useRef(onNavigate);
  navRef.current = onNavigate;

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowLeft' && initialIndex > 0) onNavigate(initialIndex - 1);
      if (e.key === 'ArrowRight' && initialIndex < photos.length - 1) onNavigate(initialIndex + 1);
      if (e.key === ' ') {
        e.preventDefault();
        setPlaying((p) => !p);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose, onNavigate, initialIndex, photos.length]);

  // Slideshow: advance every SLIDESHOW_INTERVAL_MS while playing; wraps at end.
  useEffect(() => {
    if (!playing || photos.length < 2) return;
    const timer = setInterval(() => {
      navRef.current(initialIndex >= photos.length - 1 ? 0 : initialIndex + 1);
    }, SLIDESHOW_INTERVAL_MS);
    return () => clearInterval(timer);
  }, [playing, initialIndex, photos.length]);

  if (!photo) return null;

  const hasPrev = initialIndex > 0;
  const hasNext = initialIndex < photos.length - 1;

  return (
    <Box
      role="dialog"
      aria-modal="true"
      aria-label="Photo viewer"
      onClick={onClose}
      sx={{
        position: 'fixed',
        inset: 0,
        zIndex: 1300,
        bgcolor: 'rgba(0,0,0,0.92)',
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
      }}
    >
      <IconButton
        onClick={onClose}
        aria-label="Close viewer"
        autoFocus
        sx={{ position: 'absolute', top: 12, right: 12, zIndex: 2, color: 'white' }}
      >
        ✕
      </IconButton>

      {photos.length >= 2 && (
        <IconButton
          onClick={(e) => {
            e.stopPropagation();
            setPlaying((p) => !p);
          }}
          aria-label={playing ? 'Pause slideshow' : 'Play slideshow'}
          title={playing ? 'Pause slideshow (space)' : 'Play slideshow (space)'}
          sx={{ position: 'absolute', top: 12, right: 56, zIndex: 2, color: 'white', bgcolor: 'rgba(0,0,0,0.35)' }}
        >
          {playing ? '❚❚' : '▶'}
        </IconButton>
      )}

      {hasPrev && <ArrowButton direction="prev" onClick={() => onNavigate(initialIndex - 1)} />}
      {hasNext && <ArrowButton direction="next" onClick={() => onNavigate(initialIndex + 1)} />}

      <Box
        component="img"
        src={`/uploads/${photo.username}/${photo.filename}`}
        alt={photo.description}
        onClick={(e: React.MouseEvent) => e.stopPropagation()}
        sx={{ maxWidth: '92vw', maxHeight: '78vh', objectFit: 'contain', borderRadius: 1 }}
      />

      <Box
        onClick={(e: React.MouseEvent) => e.stopPropagation()}
        sx={{ mt: 2, px: 3, textAlign: 'center', color: 'white', maxWidth: '80vw' }}
      >
        <Typography variant="subtitle1" fontWeight={700}>
          {photo.name}
          {photo.highlight ? ' ⭐' : ''}
        </Typography>
        {photo.description && (
          <Typography variant="body2" sx={{ opacity: 0.85 }}>
            {photo.description}
          </Typography>
        )}
        {gearCaption(photo) && (
          <Typography variant="caption" display="block" sx={{ opacity: 0.6, mt: 0.5 }}>
            {gearCaption(photo)}
          </Typography>
        )}
        <Typography variant="caption" display="block" sx={{ opacity: 0.5, mt: 0.5 }}>
          {photo.username} ·{' '}
          {new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
            new Date(photo.posted_at),
          )}
        </Typography>
      </Box>
    </Box>
  );
};