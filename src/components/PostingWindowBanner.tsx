import { useEffect, useState } from 'react';
import Alert from '@mui/material/Alert';
import Typography from '@mui/material/Typography';

interface Props {
  eventDate: string | null;
  userTimezone: string;
  windowOpen: boolean | null;
}

const getMidnightMs = (eventDate: string, timezone: string): number => {
  // Midnight at end of event date in user's timezone
  const nextDay = new Date(`${eventDate}T00:00:00`);
  nextDay.setDate(nextDay.getDate() + 1);
  return new Date(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: timezone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(nextDay) + 'T00:00:00',
  ).getTime();
};

const formatRemaining = (ms: number): string => {
  const totalSec = Math.max(0, Math.floor(ms / 1000));
  const h = Math.floor(totalSec / 3600);
  const m = Math.floor((totalSec % 3600) / 60);
  const s = totalSec % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
};

export const PostingWindowBanner = ({ eventDate, userTimezone, windowOpen }: Props) => {
  const [remaining, setRemaining] = useState<string>('');

  useEffect(() => {
    if (!windowOpen || !eventDate) return;

    const tick = () => {
      const now = Date.now();
      const midnight = getMidnightMs(eventDate, userTimezone);
      setRemaining(formatRemaining(midnight - now));
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [windowOpen, eventDate, userTimezone]);

  if (!windowOpen) {
    return (
      <Alert severity="warning" sx={{ mb: 2 }}>
        Posting window is closed.
      </Alert>
    );
  }

  return (
    <Alert severity="success" sx={{ mb: 2 }}>
      <Typography component="span">
        Posting window is open — closes at midnight in your timezone.
      </Typography>
      {remaining && (
        <Typography component="span" sx={{ ml: 1, fontWeight: 600 }}>
          {remaining} remaining
        </Typography>
      )}
    </Alert>
  );
};
