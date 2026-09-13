import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import { MessageListSchema } from '@/schemas/message.schema';
import type { Message } from '@/schemas/message.schema';

const formatDate = (value: string): string => {
  const parsed = new Date(value.replace(' ', 'T') + (value.includes('Z') ? '' : 'Z'));
  if (Number.isNaN(parsed.getTime())) return value;
  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(parsed);
};

export const NotificationsPage = () => {
  const [messages, setMessages] = useState<Message[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch('/api/messages.php');
        if (res.status === 403) {
          setError('Your account is awaiting validation — notifications will appear here once approved.');
          return;
        }
        if (!res.ok) {
          setError('Could not load notifications.');
          return;
        }
        const parsed = MessageListSchema.parse(await res.json());
        setMessages(parsed.messages);
      } catch {
        setError('Could not load notifications.');
      } finally {
        setIsLoading(false);
      }
    };
    void load();
  }, []);

  if (isLoading) {
    return (
      <Box display="flex" justifyContent="center" mt={4}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box component="main" sx={{ maxWidth: 800, mx: 'auto', py: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Notifications
      </Typography>

      {error && <Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>}

      {messages.length === 0 ? (
        <Typography color="text.secondary">No notifications yet.</Typography>
      ) : (
        messages.map((m) => (
          <Card key={m.id} sx={{ mb: 2 }}>
            <CardContent>
              <Typography variant="h6" component="h2" gutterBottom>
                {m.subject}
              </Typography>
              <Typography variant="caption" color="text.secondary" display="block" sx={{ mb: 1 }}>
                {formatDate(m.created_at)}
              </Typography>
              <Typography variant="body1" sx={{ whiteSpace: 'pre-line' }}>
                {m.body}
              </Typography>
            </CardContent>
          </Card>
        ))
      )}
    </Box>
  );
};
