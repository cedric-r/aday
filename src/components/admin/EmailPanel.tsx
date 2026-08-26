import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import CircularProgress from '@mui/material/CircularProgress';
import MenuItem from '@mui/material/MenuItem';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogActions from '@mui/material/DialogActions';
import {
  AdminEmailPreviewSchema,
  AdminEmailSendSchema,
  type AdminEmailForm,
} from '@/schemas/admin.schema';

export const EmailPanel = () => {
  const [scope, setScope] = useState<AdminEmailForm['scope']>('validated');
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [preview, setPreview] = useState<number | null>(null);
  const [previewLoading, setPreviewLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [result, setResult] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null);

  // Live recipient-count preview whenever the scope changes.
  useEffect(() => {
    let cancelled = false;
    setPreviewLoading(true);
    const load = async () => {
      try {
        const res = await fetch(`/api/admin/email.php?scope=${scope}`);
        if (!cancelled) {
          const parsed = AdminEmailPreviewSchema.parse(await res.json());
          setPreview(parsed.recipients);
        }
      } catch {
        if (!cancelled) setPreview(null);
      } finally {
        if (!cancelled) setPreviewLoading(false);
      }
    };
    void load();
    return () => {
      cancelled = true;
    };
  }, [scope]);

  const canSend =
    subject.trim().length > 0 &&
    subject.length <= 200 &&
    body.trim().length > 0 &&
    body.length <= 5000 &&
    (preview ?? 0) > 0;

  const handleSend = async () => {
    setConfirmOpen(false);
    setSending(true);
    setResult(null);
    try {
      const res = await fetch('/api/admin/email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ scope, subject: subject.trim(), body }),
      });
      const parsed = AdminEmailSendSchema.parse(await res.json());
      if (parsed.status === 'no_recipients') {
        setResult({ kind: 'error', text: 'No recipients matched the selected audience.' });
      } else {
        setResult({
          kind: 'ok',
          text:
            parsed.failed > 0
              ? `Sent to ${parsed.sent} recipient(s); ${parsed.failed} failed.`
              : `Sent to ${parsed.sent} recipient(s).`,
        });
      }
    } catch {
      setResult({ kind: 'error', text: 'Failed to send email.' });
    } finally {
      setSending(false);
    }
  };

  return (
    <Box sx={{ maxWidth: 560, display: 'flex', flexDirection: 'column', gap: 2 }}>
      <Typography variant="h6">Email participants</Typography>
      <Typography variant="body2" color="text.secondary">
        Send a one-off message (e.g. an event-date reminder) to registered users.
      </Typography>

      <TextField
        id="email-scope"
        label="Recipients"
        select
        value={scope}
        onChange={(e) => {
          setScope(e.target.value as AdminEmailForm['scope']);
          setResult(null);
        }}
        helperText={
          previewLoading
            ? 'Counting recipients…'
            : preview !== null
              ? `Will be sent to ${preview} recipient(s).`
              : 'Could not count recipients.'
        }
        inputProps={{ 'aria-label': 'Recipients' }}
      >
        <MenuItem value="validated">Validated participants</MenuItem>
        <MenuItem value="all">All registered users</MenuItem>
      </TextField>

      <TextField
        id="email-subject"
        label="Subject"
        value={subject}
        onChange={(e) => {
          setSubject(e.target.value);
          setResult(null);
        }}
        error={subject.length > 200}
        helperText={`${subject.length}/200`}
        inputProps={{ 'aria-label': 'Subject', maxLength: 200 }}
      />

      <TextField
        id="email-body"
        label="Message"
        multiline
        minRows={6}
        value={body}
        onChange={(e) => {
          setBody(e.target.value);
          setResult(null);
        }}
        error={body.length > 5000}
        helperText={`${body.length}/5000`}
        inputProps={{ 'aria-label': 'Message', maxLength: 5000 }}
      />

      {result && (
        <Alert severity={result.kind === 'ok' ? 'success' : 'error'}>{result.text}</Alert>
      )}

      <Box sx={{ display: 'flex', gap: 1 }}>
        <Button
          variant="contained"
          disabled={!canSend || sending}
          onClick={() => setConfirmOpen(true)}
        >
          {sending ? <CircularProgress size={20} /> : 'Send email'}
        </Button>
      </Box>

      <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
        <DialogTitle>Send email to {preview} recipient(s)?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            This sends a real email to every {scope === 'all' ? 'registered user' : 'validated participant'}. This cannot be undone.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmOpen(false)}>Cancel</Button>
          <Button onClick={() => { void handleSend(); }} color="primary">
            Send
          </Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
