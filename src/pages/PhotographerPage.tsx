import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import { PhotographerDetailSchema } from '@/schemas/photographer.schema';
import type { PhotographerDetail } from '@/schemas/photographer.schema';
import { PhotoCard } from '@/components/PhotoCard';
import { NotFoundPage } from './NotFoundPage';

export const PhotographerPage = () => {
  const { username } = useParams<{ username: string }>();
  const [profile, setProfile] = useState<PhotographerDetail | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!username) return;

    const load = async () => {
      try {
        const res = await fetch(`/api/photographers.php?username=${encodeURIComponent(username)}`);

        if (res.status === 404) {
          setNotFound(true);
          return;
        }

        const raw: unknown = await res.json();
        setProfile(PhotographerDetailSchema.parse(raw));
      } finally {
        setIsLoading(false);
      }
    };
    void load();
  }, [username]);

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" mt={4}>
        <CircularProgress />
      </Box>
    );
  }

  if (notFound || !profile) return <NotFoundPage />;

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        {profile.name}
      </Typography>

      {profile.substack_url && (
        <Typography
          component="a"
          href={profile.substack_url}
          target="_blank"
          rel="noopener noreferrer"
          sx={{ color: 'primary.main', display: 'block', mb: 3 }}
        >
          Substack
        </Typography>
      )}

      {profile.photos.length === 0 ? (
        <Typography color="text.secondary">No photos yet.</Typography>
      ) : (
        profile.photos.map((photo) => (
          <PhotoCard
            key={photo.id}
            photo={{
              id: photo.id,
              username: profile.username,
              name: profile.name,
              substack_url: profile.substack_url,
              filename: photo.filename,
              description: photo.description,
              posted_at: photo.posted_at,
            }}
          />
        ))
      )}
    </Box>
  );
};
