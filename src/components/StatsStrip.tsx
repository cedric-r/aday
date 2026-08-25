import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import type { StatsResponse } from '@/schemas/photo.schema';

/**
 * "Photos so far" count + a compact hourly pulse (UTC, last 48h).
 * Hidden entirely until there is at least one photo (the empty state handles
 * the zero case).
 */
export const StatsStrip = ({ stats }: { stats: StatsResponse | null }) => {
  if (!stats || stats.total === 0) return null;

  const max = Math.max(1, ...stats.by_hour.map((b) => b.count));

  return (
    <Box sx={{ mb: 3 }}>
      <Typography variant="subtitle2" color="text.secondary">
        {stats.total} {stats.total === 1 ? 'photo' : 'photos'} so far
      </Typography>
      <Box sx={{ display: 'flex', alignItems: 'flex-end', gap: 0.5, height: 48, mt: 1 }}>
        {stats.by_hour.map((b) => (
          <Box
            key={b.hour}
            title={`${b.hour} — ${b.count}`}
            sx={{
              flex: 1,
              minWidth: 2,
              bgcolor: 'primary.main',
              opacity: b.count === 0 ? 0.15 : 0.65 + (0.35 * b.count) / max,
              height: `${Math.max(4, (b.count / max) * 100)}%`,
              borderRadius: '2px 2px 0 0',
            }}
          />
        ))}
      </Box>
      <Typography variant="caption" color="text.disabled" display="block" mt={0.5}>
        Posting activity — last 48 hours (UTC)
      </Typography>
    </Box>
  );
};