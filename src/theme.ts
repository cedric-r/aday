import { createTheme } from '@mui/material/styles';

export type ThemeMode = 'light' | 'dark';

/** GitHub-inspired palette, mirrored for dark mode (set via ?theme=dark). */
export const buildTheme = (mode: ThemeMode) =>
  createTheme({
    palette: {
      mode,
      primary: {
        main: '#0969da',
        contrastText: '#ffffff',
      },
      secondary: {
        main: '#6e7781',
      },
      background: {
        default: mode === 'light' ? '#f6f8fa' : '#0d1117',
        paper: mode === 'light' ? '#ffffff' : '#161b22',
      },
      text: {
        primary: mode === 'light' ? '#1f2328' : '#e6edf3',
        secondary: mode === 'light' ? '#656d76' : '#8b949e',
      },
      divider: mode === 'light' ? '#d0d7de' : '#30363d',
      action: {
        hover: mode === 'light' ? 'rgba(234,238,241,0.5)' : 'rgba(240,246,252,0.1)',
        disabled: mode === 'light' ? '#8c959f' : '#484f58',
      },
      error: {
        main: mode === 'light' ? '#cf222e' : '#f85149',
      },
    },
    shape: {
      borderRadius: 6,
    },
    typography: {
      fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif',
      fontSize: 14,
    },
    components: {
      MuiAppBar: {
        styleOverrides: {
          root: {
            backgroundColor: mode === 'light' ? '#24292f' : '#161b22',
            color: '#ffffff',
          },
        },
      },
      MuiButton: {
        styleOverrides: {
          root: {
            textTransform: 'none',
            borderRadius: 6,
            paddingTop: 5,
            paddingBottom: 5,
          },
        },
      },
      MuiCard: {
        styleOverrides: {
          root: {
            border: '1px solid ' + (mode === 'light' ? '#d0d7de' : '#30363d'),
            boxShadow: 'none',
          },
        },
      },
      MuiOutlinedInput: {
        styleOverrides: {
          notchedOutline: {
            borderColor: mode === 'light' ? '#d0d7de' : '#30363d',
          },
        },
      },
      MuiChip: {
        styleOverrides: {
          root: {
            borderRadius: 999,
            borderColor: mode === 'light' ? '#d0d7de' : '#30363d',
          },
        },
      },
      MuiLink: {
        defaultProps: { underline: 'hover' },
        styleOverrides: {
          root: {
            color: '#0969da',
          },
        },
      },
      MuiAlert: {
        styleOverrides: {
          standardSuccess: { backgroundColor: mode === 'light' ? '#dafbe1' : '#1f6f4b', color: mode === 'light' ? '#116329' : '#aff5b4' },
          standardError: { backgroundColor: mode === 'light' ? '#ffebe9' : '#6e1a1a', color: mode === 'light' ? '#82071e' : '#ffc1c0' },
          standardWarning: { backgroundColor: mode === 'light' ? '#fff8c5' : '#6e4d00', color: mode === 'light' ? '#7d4e00' : '#ffd9a0' },
          standardInfo: { backgroundColor: mode === 'light' ? '#ddf4ff' : '#1a4b6d', color: mode === 'light' ? '#0a3069' : '#bde5ff' },
        },
      },
      MuiCssBaseline: {
        styleOverrides: {
          body: {
            backgroundColor: mode === 'light' ? '#f6f8fa' : '#0d1117',
          },
        },
      },
    },
  });

export const theme = buildTheme('light');