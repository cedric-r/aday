import { useState } from 'react';
import { Link } from 'react-router-dom';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import type { Photo } from '@/schemas/photo.schema';

interface Props {
  photo: Photo;
}

const formatTimestamp = (posted_at: string): string =>
  new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(posted_at));

export const PhotoCard = ({ photo }: Props) => {
  const [imgError, setImgError] = useState(false);

  const { username, name, substack_url, filename, description, posted_at } = photo;

  return (
    <Box
      component="article"
      sx={{ mb: 4, pb: 4, borderBottom: '1px solid', borderColor: 'divider' }}
    >
      {imgError ? (
        <Box
          sx={{
            width: '100%',
            height: 200,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            bgcolor: 'action.hover',
            borderRadius: 1,
          }}
        >
          <Typography color="text.secondary">Image unavailable</Typography>
        </Box>
      ) : (
        <Box
          component="img"
          src={`/uploads/${username}/${filename}`}
          alt={name}
          loading="lazy"
          onError={() => setImgError(true)}
          sx={{ width: '100%', borderRadius: 1, display: 'block' }}
        />
      )}

      <Box sx={{ mt: 1.5 }}>
        <Typography
          component={Link}
          to={`/photographers/${username}`}
          variant="subtitle1"
          sx={{ fontWeight: 600, textDecoration: 'none', color: 'text.primary' }}
        >
          {name}
        </Typography>

        {substack_url && (
          <Box component="span" sx={{ ml: 1 }}>
            <Typography
              component="a"
              href={substack_url}
              target="_blank"
              rel="noopener noreferrer"
              variant="body2"
              sx={{ color: 'primary.main' }}
            >
              Substack
            </Typography>
          </Box>
        )}
      </Box>

      <Typography variant="body1" sx={{ mt: 1 }}>
        {description}
      </Typography>

      <Typography variant="caption" color="text.secondary" sx={{ mt: 0.5, display: 'block' }}>
        {formatTimestamp(posted_at)}
      </Typography>
    </Box>
  );
};
