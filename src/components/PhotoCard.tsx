import { useState } from 'react';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Link from '@mui/material/Link';
import { Link as RouterLink } from 'react-router-dom';
import type { Photo } from '@/schemas/photo.schema';

interface Props {
  photo: Photo;
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

export const PhotoCard = ({ photo }: Props) => {
  const [imgError, setImgError] = useState(false);
  const { username, name, substack_url, filename, description, posted_at } = photo;

  const formattedDate = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(posted_at));

  return (
    <Card elevation={2} sx={{ mb: 3 }}>
      {imgError ? (
        <CameraPlaceholder />
      ) : (
        <CardMedia
          component="img"
          image={`/uploads/${username}/${filename}`}
          alt={description}
          sx={{ maxHeight: 480, objectFit: 'contain', bgcolor: 'action.hover' }}
          onError={() => setImgError(true)}
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
          <Typography variant="caption" color="text.secondary" sx={{ flexShrink: 0, ml: 1 }}>
            {formattedDate}
          </Typography>
        </Box>

        {description && (
          <Typography variant="body2" color="text.secondary">
            {description}
          </Typography>
        )}
      </CardContent>
    </Card>
  );
};
