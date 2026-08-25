import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Link } from 'react-router-dom';
import type { Photo } from '@/schemas/photo.schema';

/**
 * Horizontal strip of admin-picked highlight photos. Each thumbnail links to
 * the home feed with ?photo=ID, which opens the lightbox on load.
 */
export const HighlightsStrip = ({ photos }: { photos: Photo[] }) => {
  if (photos.length === 0) return null;

  return (
    <Box sx={{ mb: 3 }}>
      <Typography variant="subtitle2" color="text.secondary" sx={{ mb: 1 }}>
        Highlights ⭐
      </Typography>
      <Box sx={{ display: 'flex', gap: 1, overflowX: 'auto', pb: 1 }}>
        {photos.map((p) => (
          <Link key={p.id} to={`/?photo=${p.id}`}>
            <Box
              component="img"
              src={`/uploads/${p.username}/${p.filename}`}
              alt={p.description || `${p.name}'s photo`}
              title={p.description || p.name}
              sx={{
                width: 96,
                height: 96,
                objectFit: 'cover',
                borderRadius: 1,
                display: 'block',
              }}
            />
          </Link>
        ))}
      </Box>
    </Box>
  );
};