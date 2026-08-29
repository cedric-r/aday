import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogActions from '@mui/material/DialogActions';
import { PhotographerDetailSchema } from '@/schemas/photographer.schema';
import type { PhotographerDetail } from '@/schemas/photographer.schema';
import { PhotoCard } from '@/components/PhotoCard';
import { useAuth } from '@/context/AuthContext';
import { NotFoundPage } from './NotFoundPage';

interface PendingDelete {
  id: number;
}

export const PhotographerPage = () => {
  const { username } = useParams<{ username: string }>();
  const { user } = useAuth();
  const [profile, setProfile] = useState<PhotographerDetail | null>(null);
  const [notFound, setNotFound] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [pendingDelete, setPendingDelete] = useState<PendingDelete | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  const isOwnProfile = user !== null && user.username === username;

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

  const reload = async () => {
    if (!username) return;
    const res = await fetch(`/api/photographers.php?username=${encodeURIComponent(username)}`);
    if (res.ok) {
      setProfile(PhotographerDetailSchema.parse(await res.json()));
    }
  };

  const handleDelete = async () => {
    if (!pendingDelete) return;
    setDeleteError(null);
    try {
      const res = await fetch(`/api/photos.php?id=${pendingDelete.id}`, { method: 'DELETE' });
      if (res.ok) {
        setPendingDelete(null);
        await reload();
      } else {
        setDeleteError('Failed to delete the photo.');
      }
    } catch {
      setDeleteError('Failed to delete the photo.');
    }
  };

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

      {profile.bio && (
        <Typography variant="body1" color="text.secondary" sx={{ mb: 3, whiteSpace: 'pre-line' }}>
          {profile.bio}
        </Typography>
      )}

      {isOwnProfile && profile.photos.length > 0 && (
        <Button
          variant="outlined"
          size="small"
          component="a"
          href="/api/my-export.php"
          sx={{ mb: 3 }}
        >
          ⬇ Download my photos (ZIP)
        </Button>
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
            onDelete={isOwnProfile ? (p) => setPendingDelete({ id: p.id }) : undefined}
          />
        ))
      )}

      <Dialog open={pendingDelete !== null} onClose={() => setPendingDelete(null)}>
        <DialogTitle>Delete this photo?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            This permanently removes the photo and its files. This cannot be undone.
          </DialogContentText>
          {deleteError && <Typography color="error" sx={{ mt: 1 }}>{deleteError}</Typography>}
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setPendingDelete(null)}>Cancel</Button>
          <Button onClick={() => { void handleDelete(); }} color="error">
            Delete
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
