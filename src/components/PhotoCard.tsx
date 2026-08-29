import { useState } from 'react';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Link from '@mui/material/Link';
import IconButton from '@mui/material/IconButton';
import { Link as RouterLink } from 'react-router-dom';
import type { Photo } from '@/schemas/photo.schema';

interface Props {
  photo: Photo;
  onOpen?: (photo: Photo) => void;
  onDelete?: (photo: Photo) => void;
}

const CameraPlaceholder = () => (
  <Box
    sx={{
      height: 240,
      bgcolor: 'action.hover',
      color: 'action.disabled',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
    }}
    aria-label="Image unavailable"
  >
    <Box
      component="svg"
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 24 24"
      aria-hidden="true"
      sx={{ width: 48, height: 48, fill: 'currentColor' }}
    >
      <path d="M12 15.5A3.5 3.5 0 0 1 8.5 12 3.5 3.5 0 0 1 12 8.5a3.5 3.5 0 0 1 3.5 3.5 3.5 3.5 0 0 1-3.5 3.5M7 2l-1.83 2H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-2.17L17 2H7m5 4a6 6 0 0 1 6 6 6 6 0 0 1-6 6 6 6 0 0 1-6-6 6 6 0 0 1 6-6z" />
    </Box>
  </Box>
);

/** Build a compact "Canon EOS 5D · 50mm · f/1.8 · 1/125s · ISO 400" line. */
export const exifLine = (photo: Photo): string | null => {
  const parts: string[] = [];
  const camera = [photo.exif_make, photo.exif_model].filter(Boolean).join(' ').trim();
  if (camera) parts.push(camera);
  if (photo.exif_focal) parts.push(photo.exif_focal);
  if (photo.exif_aperture) parts.push(photo.exif_aperture);
  if (photo.exif_shutter) parts.push(photo.exif_shutter);
  if (photo.exif_iso) parts.push(`ISO ${photo.exif_iso}`);
  return parts.length > 0 ? parts.join(' · ') : null;
};

export const PhotoCard = ({ photo, onOpen, onDelete }: Props) => {
  const [imgError, setImgError] = useState(false);
  const { username, name, substack_url, filename, description, posted_at } = photo;

  const formattedDate = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(posted_at));

  const gearNote = photo.gear?.trim() ? photo.gear.trim() : null;
  const exif = exifLine(photo);
  const gearLine = gearNote && exif ? `${gearNote} — ${exif}` : (gearNote ?? exif);

  return (
    <Card sx={{ mb: 3 }}>
      {imgError ? (
        <CameraPlaceholder />
      ) : (
        <CardMedia
          component="img"
          image={`/uploads/${username}/${filename}`}
          alt={description}
          title={description}
          sx={{
            maxHeight: 480,
            objectFit: 'contain',
            bgcolor: 'action.hover',
            cursor: onOpen ? 'zoom-in' : undefined,
          }}
          onError={() => setImgError(true)}
          onClick={() => { onOpen?.(photo); }}
        />
      )}

      <CardContent>
        <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', mb: 0.5 }}>
          <Box>
            <Typography
              component={RouterLink}
              to={`/photographers/${username}`}
              variant="subtitle1"
              fontWeight={700}
              sx={{ textDecoration: 'none', color: 'text.primary', '&:hover': { textDecoration: 'underline' } }}
            >
              {name}
            </Typography>
            {substack_url && (
              <Box>
                <Link
                  href={substack_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="caption"
                  color="text.secondary"
                >
                  Substack ↗
                </Link>
              </Box>
            )}
          </Box>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, flexShrink: 0, ml: 1 }}>
            {photo.highlight && <span aria-label="Highlighted photo" title="Highlighted">⭐</span>}
            <Typography variant="caption" color="text.secondary">
              {formattedDate}
            </Typography>
            {onDelete && (
              <IconButton
                size="small"
                aria-label="Delete photo"
                title="Delete photo"
                onClick={() => onDelete(photo)}
                sx={{ color: 'text.secondary', '&:hover': { color: 'error.main' } }}
              >
                <Box component="svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" sx={{ width: 18, height: 18, fill: 'currentColor' }}>
                  <path d="M6 19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z" />
                </Box>
              </IconButton>
            )}
          </Box>
        </Box>

        {description && (
          <Typography variant="body2" color="text.secondary">
            {description}
          </Typography>
        )}

        {gearLine && (
          <Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 0.5 }}>
            {gearLine}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
};