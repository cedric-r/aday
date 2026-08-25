import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import { AdminUserListSchema } from '@/schemas/admin.schema';

const iframeFor = (src: string) =>
  `<iframe
  src="${src}"
  width="100%"
  height="700"
  frameborder="0"
  loading="lazy"
  style="border:0; border-radius:8px;"
></iframe>`;

const BASE = 'https://aday.photoni.st/embed';

/** Copy-paste widget snippets for embedding the live feed elsewhere (Substack…). */
export const EmbedPanel = () => {
  const [users, setUsers] = useState<{ username: string; name: string }[]>([]);
  const [photographer, setPhotographer] = useState('');
  const [dark, setDark] = useState(false);
  const [copied, setCopied] = useState(false);

  useEffect(() => {
    // Load validated users for the photographer dropdown (admin-only endpoint).
    fetch('/api/admin/users.php')
      .then((r) => r.json())
      .then((raw: unknown) => {
        const parsed = AdminUserListSchema.safeParse(raw);
        if (parsed.success) {
          setUsers(
            parsed.data
              .filter((u) => u.status === 'validated')
              .map((u) => ({ username: u.username, name: u.name }))
              .sort((a, b) => a.name.localeCompare(b.name)),
          );
        }
      })
      .catch(() => {});
  }, []);

  const query = new URLSearchParams();
  if (photographer) query.set('photographer', photographer);
  if (dark) query.set('theme', 'dark');
  const qs = query.toString();
  const src = qs ? `${BASE}?${qs}` : BASE;

  const iframeSnippet = iframeFor(src);
  const linkSnippet = `Live gallery: ${src}`;

  const copy = async (text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // clipboard unavailable — let the user select manually
    }
  };

  return (
    <Box sx={{ maxWidth: 640 }}>
      <Typography variant="h6" sx={{ mb: 1 }}>
        Embed the gallery
      </Typography>
      <Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
        Paste this iframe into a Substack post (or any HTML page) to show the
        live photo feed on your site. The embed page has no site header, so it
        frames cleanly.
      </Typography>

      {copied && <Alert severity="success" sx={{ mb: 2 }}>Copied!</Alert>}

      <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap', mb: 2 }}>
        <TextField
          select
          label="Photographer"
          value={photographer}
          onChange={(e) => setPhotographer(e.target.value)}
          size="small"
          sx={{ minWidth: 220 }}
        >
          <MenuItem value="">
            <em>Everyone (whole gallery)</em>
          </MenuItem>
          {users.map((u) => (
            <MenuItem key={u.username} value={u.username}>
              {u.name} (@{u.username})
            </MenuItem>
          ))}
        </TextField>
        <Button
          size="small"
          variant={dark ? 'contained' : 'outlined'}
          onClick={() => setDark((d) => !d)}
          sx={{ alignSelf: 'center' }}
        >
          {dark ? 'Dark theme' : 'Light theme'}
        </Button>
      </Box>

      <Typography variant="subtitle2" sx={{ mb: 0.5 }}>
        iframe snippet
      </Typography>
      <Box
        component="pre"
        sx={{
          bgcolor: 'action.hover',
          p: 2,
          borderRadius: 1,
          overflowX: 'auto',
          fontSize: 12,
          mb: 1,
        }}
      >
        {iframeSnippet}
      </Box>
      <Button size="small" variant="outlined" onClick={() => void copy(iframeSnippet)}>
        Copy iframe
      </Button>

      <Typography variant="subtitle2" sx={{ mt: 3, mb: 0.5 }}>
        Or just link to the gallery
      </Typography>
      <Box
        component="pre"
        sx={{ bgcolor: 'action.hover', p: 2, borderRadius: 1, overflowX: 'auto', fontSize: 12, mb: 1 }}
      >
        {linkSnippet}
      </Box>
      <Button size="small" variant="outlined" onClick={() => void copy(linkSnippet)}>
        Copy link
      </Button>
    </Box>
  );
};