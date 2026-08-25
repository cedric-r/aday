import { useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';

const IFRAME_SNIPPET = `<iframe
  src="https://aday.photoni.st/embed"
  width="100%"
  height="700"
  frameborder="0"
  loading="lazy"
  style="border:0; border-radius:8px;"
></iframe>`;

const LINK_SNIPPET = `Live gallery: https://aday.photoni.st/embed`;

/** Copy-paste widget snippets for embedding the live feed elsewhere (Substack…). */
export const EmbedPanel = () => {
  const [copied, setCopied] = useState(false);

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
        {IFRAME_SNIPPET}
      </Box>
      <Button size="small" variant="outlined" onClick={() => void copy(IFRAME_SNIPPET)}>
        Copy iframe
      </Button>

      <Typography variant="subtitle2" sx={{ mt: 3, mb: 0.5 }}>
        Or just link to the gallery
      </Typography>
      <Box
        component="pre"
        sx={{ bgcolor: 'action.hover', p: 2, borderRadius: 1, overflowX: 'auto', fontSize: 12, mb: 1 }}
      >
        {LINK_SNIPPET}
      </Box>
      <Button size="small" variant="outlined" onClick={() => void copy(LINK_SNIPPET)}>
        Copy link
      </Button>
    </Box>
  );
};