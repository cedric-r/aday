import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import CircularProgress from '@mui/material/CircularProgress';
import FormControl from '@mui/material/FormControl';
import FormLabel from '@mui/material/FormLabel';
import { StatusResponseSchema } from '@/schemas/photo.schema';
import { ApiErrorSchema } from '@/schemas/auth.schema';
import type { StatusResponse } from '@/schemas/photo.schema';
import { PostingWindowBanner } from '@/components/PostingWindowBanner';
import { useAuth } from '@/context/AuthContext';

const MAX_FILE_BYTES = 15 * 1024 * 1024;

type SubmitState = 'idle' | 'success' | 'error';

export const PostPage = () => {
  const { user } = useAuth(); // ProtectedRoute guarantees user is non-null
  const [status, setStatus] = useState<StatusResponse | null>(null);
  const [isLoadingStatus, setIsLoadingStatus] = useState(true);
  const [fileSizeWarning, setFileSizeWarning] = useState(false);
  const [description, setDescription] = useState('');
  const [gear, setGear] = useState('');
  const [submitState, setSubmitState] = useState<SubmitState>('idle');
  const [apiError, setApiError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);

  useEffect(() => {
    const loadStatus = async () => {
      try {
        const res = await fetch('/api/status.php');
        const raw: unknown = await res.json();
        setStatus(StatusResponseSchema.parse(raw));
      } finally {
        setIsLoadingStatus(false);
      }
    };
    void loadStatus();
  }, []);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    setSelectedFile(file);
    setFileSizeWarning(file !== null && file.size > MAX_FILE_BYTES);
  };

  const handleSubmit = async () => {
    if (!selectedFile || fileSizeWarning) return;

    setIsSubmitting(true);
    setApiError(null);

    const formData = new FormData();
    formData.append('photo', selectedFile);
    formData.append('description', description);
    formData.append('gear', gear);

    try {
      const res = await fetch('/api/photos.php', { method: 'POST', body: formData });

      if (res.status === 201) {
        setSubmitState('success');
        return;
      }

      if (res.status === 403) {
        // Refresh status — window may have closed
        const statusRes = await fetch('/api/status.php');
        const raw: unknown = await statusRes.json();
        setStatus(StatusResponseSchema.parse(raw));
        return;
      }

      const raw: unknown = await res.json();
      const parsed = ApiErrorSchema.safeParse(raw);
      setApiError(parsed.success ? parsed.data.error : 'An error occurred. Please try again.');
      setSubmitState('error');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoadingStatus) {
    return (
      <Box display="flex" justifyContent="center" mt={4}>
        <CircularProgress />
      </Box>
    );
  }

  if (submitState === 'success') {
    return (
      <Box sx={{ maxWidth: 600, mx: 'auto', mt: 6, px: 2 }}>
        <Alert severity="success" sx={{ mb: 2 }}>
          Photo posted! Your photo is now live.
        </Alert>
        <Button component={Link} to="/" variant="outlined">
          View home feed
        </Button>
      </Box>
    );
  }

  const windowOpen = status?.window_open ?? false;
  const eventDate = status?.event_date ?? '';
  const userTimezone = user!.timezone;

  return (
    <Box component="main" sx={{ maxWidth: 600, mx: 'auto', mt: 6, px: 2 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Post a photo
      </Typography>

      <PostingWindowBanner
        eventDate={eventDate}
        userTimezone={userTimezone}
        windowOpen={windowOpen}
      />

      {apiError && <Alert severity="error" sx={{ mb: 2 }}>{apiError}</Alert>}

      {windowOpen && (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
          <FormControl>
            <FormLabel htmlFor="photo">Photo (JPEG, PNG, or WebP — max 15 MB)</FormLabel>
            <input
              id="photo"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              aria-label="Photo"
              onChange={handleFileChange}
              required
            />
            {fileSizeWarning && (
              <Typography color="error" variant="caption">
                File is too large — max 15 MB.
              </Typography>
            )}
          </FormControl>

          <TextField
            id="description"
            label="Description"
            multiline
            minRows={3}
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            inputProps={{ 'aria-label': 'Description' }}
            InputLabelProps={{ htmlFor: 'description' }}
          />

          <TextField
            id="gear"
            label="Gear (optional)"
            placeholder="e.g. Hasselblad 500C/M · 80mm · Portra 400"
            value={gear}
            onChange={(e) => setGear(e.target.value)}
            inputProps={{ 'aria-label': 'Gear (optional)' }}
            InputLabelProps={{ htmlFor: 'gear' }}
            helperText="For film or manual setups — camera, lens, film. Digital EXIF is captured automatically."
          />

          <Button
            type="button"
            variant="contained"
            disabled={isSubmitting || fileSizeWarning || !selectedFile}
            onClick={() => { void handleSubmit(); }}
          >
            {isSubmitting ? 'Uploading…' : 'Submit'}
          </Button>
        </Box>
      )}
    </Box>
  );
};
