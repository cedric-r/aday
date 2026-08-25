import { useEffect, useRef, useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Alert from '@mui/material/Alert';
import Typography from '@mui/material/Typography';
import { SubmissionListSchema } from '@/schemas/admin.schema';
import type { Submission } from '@/schemas/admin.schema';

const POLL_MS = 60_000;

export const SubmissionsPanel = () => {
  const [submissions, setSubmissions] = useState<Submission[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const isPollingRef = useRef(false);

  const loadSubmissions = async (prepend = false) => {
    if (prepend && isPollingRef.current) return;
    if (prepend) isPollingRef.current = true;

    try {
      const res = await fetch('/api/admin/submissions.php');
      const raw: unknown = await res.json();
      const data = SubmissionListSchema.parse(raw);

      if (prepend) {
        setSubmissions((prev) => {
          const prevIds = new Set(prev.map((s) => s.id));
          const newEntries = data.filter((s) => !prevIds.has(s.id));
          return newEntries.length > 0 ? [...newEntries, ...prev] : prev;
        });
      } else {
        setSubmissions(data);
      }
    } finally {
      if (prepend) isPollingRef.current = false;
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadSubmissions(false);
    const timer = setInterval(() => { void loadSubmissions(true); }, POLL_MS);
    return () => clearInterval(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    // loadSubmissions is defined inside the component; stable-ref approach via isPollingRef
    // avoids adding it as a dependency (would cause infinite re-registration of the interval)
  }, []);

  const [actionError, setActionError] = useState<string | null>(null);

  const handleDelete = async (s: Submission) => {
    if (!globalThis.confirm(`Delete submission from ${s.name}?`)) return;
    setActionError(null);
    const res = await fetch(`/api/admin/submissions.php?id=${s.id}`, { method: 'DELETE' });
    if (!res.ok) {
      setActionError('Failed to delete submission. Please try again.');
      return;
    }
    void loadSubmissions(false);
  };

  const handleExport = () => {
    globalThis.location.href = '/api/admin/export.php';
  };

  const handleExportCsv = () => {
    globalThis.location.href = '/api/admin/export.php?format=csv';
  };

  const handleToggleHighlight = async (s: Submission) => {
    setActionError(null);
    const highlight = !s.highlight;
    const res = await fetch('/api/admin/submissions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: s.id, highlight }),
    });
    if (!res.ok) {
      setActionError('Failed to update highlight. Please try again.');
      return;
    }
    setSubmissions((prev) =>
      prev.map((x) => (x.id === s.id ? { ...x, highlight } : x)),
    );
  };

  const handleToggleHidden = async (s: Submission) => {
    setActionError(null);
    const hidden = !s.hidden;
    const res = await fetch('/api/admin/submissions.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: s.id, hidden }),
    });
    if (!res.ok) {
      setActionError('Failed to update visibility. Please try again.');
      return;
    }
    setSubmissions((prev) =>
      prev.map((x) => (x.id === s.id ? { ...x, hidden } : x)),
    );
  };

  const handleWrapUp = async () => {
    if (!globalThis.confirm('Email all participants that the gallery is live? (Sends once.)')) return;
    setActionError(null);
    const res = await fetch('/api/admin/wrapup.php', { method: 'POST' });
    const raw: unknown = await res.json();
    const msg = (raw as { message?: string })?.message ?? 'Wrap-up email processed.';
    if (res.ok) {
      globalThis.alert(msg);
    } else {
      setActionError(msg);
    }
  };

  if (isLoading) {
    return <Box display="flex" justifyContent="center" mt={2}><CircularProgress /></Box>;
  }

  return (
    <Box>
      {actionError && <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>}
      <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 2, flexWrap: 'wrap' }}>
        <Typography variant="h6">
          Submissions ({submissions.length} {submissions.length === 1 ? 'photo' : 'photos'})
        </Typography>
        <Button variant="contained" onClick={handleExport}>
          Export All
        </Button>
        <Button variant="outlined" onClick={handleExportCsv}>
          Export metadata (CSV)
        </Button>
        <Button variant="outlined" color="success" onClick={() => { void handleWrapUp(); }}>
          Email participants
        </Button>
      </Box>

      {submissions.length === 0 ? (
        <Typography color="text.secondary">No submissions yet.</Typography>
      ) : (
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell>Thumbnail</TableCell>
              <TableCell>Photographer</TableCell>
              <TableCell>Description</TableCell>
              <TableCell>Posted at</TableCell>
              <TableCell>Actions</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {submissions.map((s) => (
              <TableRow key={s.id}>
                <TableCell>
                  <Box
                    component="img"
                    src={s.thumb_url || `/uploads/${s.username}/${s.filename}`}
                    alt={s.name}
                    sx={{ width: 60, height: 60, objectFit: 'cover', borderRadius: 0.5 }}
                  />
                </TableCell>
                <TableCell>
                  <Typography variant="body2" fontWeight={600}>{s.name}</Typography>
                  <Typography variant="caption" color="text.secondary">@{s.username}</Typography>
                </TableCell>
                <TableCell>
                  {s.description.length > 80
                    ? `${s.description.slice(0, 80)}…`
                    : s.description}
                </TableCell>
                <TableCell>
                  {new Intl.DateTimeFormat(undefined, { dateStyle: 'short', timeStyle: 'short' }).format(
                    new Date(s.posted_at),
                  )}
                </TableCell>
              <TableCell>
                  <Box sx={{ display: 'flex', gap: 1 }}>
                    <Button
                      size="small"
                      variant={s.highlight ? 'contained' : 'outlined'}
                      color={s.highlight ? 'warning' : 'inherit'}
                      aria-label={s.highlight ? 'Remove highlight' : 'Add highlight'}
                      title="Toggle home-page highlight"
                      onClick={() => { void handleToggleHighlight(s); }}
                    >
                      {s.highlight ? '★' : '☆'}
                    </Button>
                    <Button
                      size="small"
                      variant={s.hidden ? 'contained' : 'outlined'}
                      color={s.hidden ? 'error' : 'inherit'}
                      aria-label={s.hidden ? 'Unhide photo' : 'Hide photo'}
                      title="Hide/unlist from public pages"
                      onClick={() => { void handleToggleHidden(s); }}
                    >
                      {s.hidden ? '🙈' : '👁'}
                    </Button>
                    <Button
                      size="small"
                      color="error"
                      variant="outlined"
                      onClick={() => { void handleDelete(s); }}
                    >
                      Delete
                    </Button>
                  </Box>
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      )}
    </Box>
  );
};
