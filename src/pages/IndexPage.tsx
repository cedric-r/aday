import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';
import Typography from '@mui/material/Typography';
import { PhotographerListSchema } from '@/schemas/photographer.schema';
import type { PhotographerSummary } from '@/schemas/photographer.schema';

type AlphaGroups = Record<string, PhotographerSummary[]>;

const groupByLetter = (list: PhotographerSummary[]): AlphaGroups => {
  const groups: AlphaGroups = {};
  for (const p of list) {
    const letter = p.name.charAt(0).toUpperCase();
    if (!groups[letter]) groups[letter] = [];
    groups[letter].push(p);
  }
  return groups;
};

export const IndexPage = () => {
  const [photographers, setPhotographers] = useState<PhotographerSummary[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const res = await fetch('/api/photographers.php');
        const raw: unknown = await res.json();
        setPhotographers(PhotographerListSchema.parse(raw));
      } catch {
        setError('Failed to load photographers.');
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

  if (error) return <Typography color="error">{error}</Typography>;

  if (photographers.length === 0) {
    return (
      <Typography color="text.secondary" sx={{ mt: 4 }}>
        No participants yet.
      </Typography>
    );
  }

  const groups = groupByLetter(photographers);
  const letters = Object.keys(groups).sort();

  return (
    <Box component="main" sx={{ maxWidth: 700, mx: 'auto', py: 4 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Photographer Index
      </Typography>

      {letters.map((letter) => (
        <Box key={letter} sx={{ mb: 3 }}>
          <Typography variant="h5" component="h2" sx={{ fontWeight: 700, mb: 1 }}>
            {letter}
          </Typography>

          {groups[letter].map((p) => (
            <Box key={p.username} sx={{ display: 'flex', alignItems: 'center', gap: 2, mb: 0.5 }}>
              <Typography
                component={Link}
                to={`/photographers/${p.username}`}
                sx={{ textDecoration: 'none', color: 'text.primary', '&:hover': { textDecoration: 'underline' } }}
              >
                {p.name}
              </Typography>

              {p.substack_url && (
                <Typography
                  component="a"
                  href={p.substack_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  variant="body2"
                  sx={{ color: 'primary.main' }}
                >
                  Substack
                </Typography>
              )}

              <Typography variant="caption" color="text.secondary">
                {p.photo_count} {p.photo_count === 1 ? 'photo' : 'photos'}
              </Typography>
            </Box>
          ))}
        </Box>
      ))}
    </Box>
  );
};
