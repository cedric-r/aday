import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CircularProgress from '@mui/material/CircularProgress';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import {
  AdminMessageListSchema,
  AdminMessageCreateSchema,
  type Message,
} from '@/schemas/message.schema';

export const MessagePanel = () => {
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [messages, setMessages] = useState<Message[]>([]);
  const [recipients, setRecipients] = useState<number | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [confirmOpen, setConfirmOpen] = useState(false);
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [result, setResult] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null);

  const load = async () => {
    try {
      const res = await fetch('/api/admin/messages.php');
      const parsed = AdminMessageListSchema.parse(await res.json());
      setMessages(parsed.messages);
      setRecipients(parsed.recipients);
    } catch {
      setRecipients(null);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void load();
  }, []);

  const canSend =
    subject.trim().length > 0 &&
    subject.length <= 200 &&
    body.trim().length > 0 &&
    body.length <= 5000;

  const handleSend = async () => {
    setConfirmOpen(false);
    setSending(true);
    setResult(null);
    try {
      const res = await fetch('/api/admin/messages.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ subject: subject.trim(), body }),
      });
      const parsed = AdminMessageCreateSchema.parse(await res.json());
      setSubject('');
      setBody('');
      const mailed = parsed.email?.sent ?? 0;
      const mailFailed = parsed.email?.failed ?? 0;
      setResult({
        kind: 'ok',
        text:
          mailFailed > 0
            ? `Notification posted and emailed to ${mailed} recipient(s); ${mailFailed} email(s) failed.`
            : `Notification posted and emailed to ${mailed} recipient(s).`,
      });
      await load();
    } catch {
      setResult({ kind: 'error', text: 'Failed to post the notification.' });
    } finally {
      setSending(false);
    }
  };

  const handleDelete = async () => {
    if (deleteId === null) return;
    setResult(null);
    try {
      const res = await fetch(`/api/admin/messages.php?id=${deleteId}`, { method: 'DELETE' });
      if (!res.ok) {
        setResult({ kind: 'error', text: 'Failed to delete the notification.' });
      } else {
        setResult({ kind: 'ok', text: 'Notification deleted.' });
      }
      setDeleteId(null);
      await load();
    } catch {
      setResult({ kind: 'error', text: 'Failed to delete the notification.' });
      setDeleteId(null);
    }
  };

  if (isLoading) {
    return <Box display="flex" justifyContent="center" mt={2}><CircularProgress /></Box>;
  }

  return (
    <Box sx={{ maxWidth: 640, display: 'flex', flexDirection: 'column', gap: 2 }}>
      <Typography variant="h6">Send a notification</Typography>
      <Typography variant="body2" color="text.secondary">
        Posts a message to the Notifications tab
        {recipients !== null ? ` for ${recipients} validated participant(s)` : ''} and emails
        {' '}<strong>the same message</strong> to those participants.
      </Typography>

      {result && <Alert severity={result.kind === 'ok' ? 'success' : 'error'}>{result.text}</Alert>}

      <TextField
        id="message-subject"
        label="Subject"
        value={subject}
        onChange={(e) => { setSubject(e.target.value); setResult(null); }}
        error={subject.length > 200}
        helperText={`${subject.length}/200`}
        inputProps={{ 'aria-label': 'Subject', maxLength: 200 }}
      />

      <TextField
        id="message-body"
        label="Message"
        multiline
        minRows={5}
        value={body}
        onChange={(e) => { setBody(e.target.value); setResult(null); }}
        error={body.length > 5000}
        helperText={`${body.length}/5000`}
        inputProps={{ 'aria-label': 'Message', maxLength: 5000 }}
      />

      <Box>
        <Button variant="contained" disabled={!canSend || sending} onClick={() => setConfirmOpen(true)}>
          {sending ? <CircularProgress size={20} /> : 'Post notification'}
        </Button>
      </Box>

      <Typography variant="h6" sx={{ mt: 2 }}>
        Sent notifications
      </Typography>

      {messages.length === 0 ? (
        <Typography color="text.secondary">Nothing sent yet.</Typography>
      ) : (
        messages.map((m) => (
          <Card key={m.id} variant="outlined">
            <CardContent sx={{ display: 'flex', justifyContent: 'space-between', gap: 2, alignItems: 'flex-start' }}>
              <Box>
                <Typography variant="subtitle1" fontWeight={600}>{m.subject}</Typography>
                <Typography variant="caption" color="text.secondary" display="block" sx={{ mb: 0.5 }}>
                  {m.created_at}
                </Typography>
                <Typography variant="body2" color="text.secondary" sx={{ whiteSpace: 'pre-line' }}>
                  {m.body}
                </Typography>
              </Box>
              <Button
                size="small"
                color="error"
                aria-label={`Delete notification ${m.subject}`}
                onClick={() => setDeleteId(m.id)}
              >
                Delete
              </Button>
            </CardContent>
          </Card>
        ))
      )}

      <Dialog open={confirmOpen} onClose={() => setConfirmOpen(false)}>
        <DialogTitle>Post and email this notification?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            It will appear on the Notifications tab and be <strong>emailed</strong> to
            {recipients !== null ? ` ${recipients} validated participant(s)` : ' every validated participant'}.
            This sends real email and cannot be undone.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setConfirmOpen(false)}>Cancel</Button>
          <Button onClick={() => { void handleSend(); }}>Post &amp; email</Button>
        </DialogActions>
      </Dialog>

      <Dialog open={deleteId !== null} onClose={() => setDeleteId(null)}>
        <DialogTitle>Delete this notification?</DialogTitle>
        <DialogContent>
          <DialogContentText>
            Participants will no longer see it. This cannot be undone.
          </DialogContentText>
        </DialogContent>
        <DialogActions>
          <Button onClick={() => setDeleteId(null)}>Cancel</Button>
          <Button color="error" onClick={() => { void handleDelete(); }}>Delete</Button>
        </DialogActions>
      </Dialog>
    </Box>
  );
};
