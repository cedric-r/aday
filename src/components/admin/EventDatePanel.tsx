import { useEffect, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import CircularProgress from '@mui/material/CircularProgress';
import FormControlLabel from '@mui/material/FormControlLabel';
import Switch from '@mui/material/Switch';
import { AdminSettingsSchema } from '@/schemas/admin.schema';

const isPastDate = (dateStr: string): boolean => {
  if (!dateStr) return false;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return new Date(dateStr) < today;
};

export const EventDatePanel = () => {
  const [eventDate, setEventDate] = useState('');
  const [allowLate, setAllowLate] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch('/api/admin/settings.php');
        const raw: unknown = await res.json();
        const parsed = AdminSettingsSchema.parse(raw);
        setEventDate(parsed.event_date ?? '');
        setAllowLate(parsed.allow_late_submissions ?? false);
      } finally {
        setIsLoading(false);
      }
    };
    void load();
  }, []);

  const handleSave = async () => {
    setIsSaving(true);
    setSaved(false);
    setError(null);
    try {
      const res = await fetch('/api/admin/settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_date: eventDate, allow_late_submissions: allowLate }),
      });
      if (res.ok) {
        setSaved(true);
      } else {
        setError('Failed to save event date.');
      }
    } finally {
      setIsSaving(false);
    }
  };

  if (isLoading) {
    return <Box display="flex" justifyContent="center" mt={2}><CircularProgress /></Box>;
  }

  const showPastWarning = isPastDate(eventDate);

  return (
    <Box sx={{ maxWidth: 400, display: 'flex', flexDirection: 'column', gap: 2 }}>
      <Typography variant="h6">Event Date</Typography>

      {showPastWarning && (
        <Alert severity="warning">
          This date is in the past. Registration will be closed on event day.
        </Alert>
      )}

      {saved && <Alert severity="success">Event date saved.</Alert>}
      {error && <Alert severity="error">{error}</Alert>}

      <TextField
        id="event-date"
        label="Event date"
        type="date"
        value={eventDate}
        onChange={(e) => {
          setEventDate(e.target.value);
          setSaved(false);
        }}
        InputLabelProps={{ shrink: true, htmlFor: 'event-date' }}
        inputProps={{ 'aria-label': 'Event date' }}
      />

      <FormControlLabel
        control={
          <Switch
            checked={allowLate}
            onChange={(e) => {
              setAllowLate(e.target.checked);
              setSaved(false);
            }}
            inputProps={{ 'aria-label': 'Allow late submissions' }}
          />
        }
        label="Keep submissions open after the event date (for late submitters)"
      />

      <Button
        variant="contained"
        disabled={isSaving || !eventDate}
        onClick={() => { void handleSave(); }}
      >
        Save
      </Button>
    </Box>
  );
};
