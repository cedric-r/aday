import { useEffect, useState } from 'react';
import { Link as RouterLink, useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import CircularProgress from '@mui/material/CircularProgress';
import Link from '@mui/material/Link';
import Typography from '@mui/material/Typography';
import { PhotoFeedResponseSchema } from '@/schemas/photo.schema';
import type { Photo } from '@/schemas/photo.schema';
import { exifLine } from '@/components/PhotoCard';

export const PhotoPage = () => {
  const { id } = useParams<{ id: string }>();
  const [photo, setPhoto] = useState<Photo | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch(`/api/photos.php?photo=${encodeURIComponent(id ?? '')}`);
        const raw: unknown = await res.json();
        const data = PhotoFeedResponseSchema.parse(raw);
        if (data.photos.length > 0) {
          setPhoto(data.photos[0]);
        } else {
          setNotFound(true);
        }
      } catch {
        setNotFound(true);
      } finally {
        setIsLoading(false);
      }
    };
    void load();
  }, [id]);

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" mt={6}>
        <CircularProgress />
      </Box>
    );
  }

  if (notFound || !photo) {
    return (
      <Box sx={{ maxWidth: 600, mx: 'auto', mt: 6, textAlign: 'center' }}>
        <Typography variant="h5" component="h1" gutterBottom>
          Photo not found
        </Typography>
        <Typography color="text.secondary" sx={{ mb: 2 }}>
          It may have been removed or is no longer public.
        </Typography>
        <Button component={RouterLink} to="/" variant="outlined">
          Back to the gallery
        </Button>
      </Box>
    );
  }

  const gearLine = photo.gear?.trim() && exifLine(photo)
    ? `${photo.gear.trim()} — ${exifLine(photo)}`
    : (photo.gear?.trim() || exifLine(photo));

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard unavailable
    }
  };

  return (
    <Box sx={{ maxWidth: 900, mx: 'auto', py: 3 }}>
      <Button component={RouterLink} to="/" size="small" sx={{ mb: 2 }}>
        ← Back to gallery
      </Button>

      <Card>
        <CardMedia
          component="img"
          image={`/uploads/${photo.username}/${photo.filename}`}
          alt={photo.description}
          sx={{ maxHeight: '75vh', objectFit: 'contain', bgcolor: 'action.hover' }}
        />
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 1 }}>
            <Box>
              <Typography
                component={RouterLink}
                to={`/photographers/${photo.username}`}
                variant="h6"
                sx={{ textDecoration: 'none', color: 'text.primary', '&:hover': { textDecoration: 'underline' } }}
              >
                {photo.name}
                {photo.highlight ? ' ⭐' : ''}
              </Typography>
              {photo.substack_url && (
                <Link href={photo.substack_url} target="_blank" rel="noopener noreferrer" variant="body2" color="text.secondary">
                  Substack ↗
                </Link>
              )}
            </Box>
            <Box sx={{ textAlign: 'right' }}>
              <Button size="small" variant="outlined" onClick={() => void handleCopy()}>
                {copied ? 'Copied!' : 'Copy link'}
              </Button>
              <Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 0.5 }}>
                {new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(
                  new Date(photo.posted_at),
                )}
              </Typography>
            </Box>
          </Box>

          {photo.description && (
            <Typography color="text.secondary" sx={{ mt: 1 }}>
              {photo.description}
            </Typography>
          )}

          {gearLine && (
            <Typography variant="caption" display="block" color="text.secondary" sx={{ mt: 1 }}>
              {gearLine}
            </Typography>
          )}
        </CardContent>
      </Card>
    </Box>
  );
};