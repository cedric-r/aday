import { createTheme } from '@mui/material/styles';

export const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#0969da',
      contrastText: '#ffffff',
    },
    secondary: {
      main: '#6e7781',
    },
    background: {
      default: '#f6f8fa',
      paper: '#ffffff',
    },
    text: {
      primary: '#1f2328',
      secondary: '#656d76',
    },
    divider: '#d0d7de',
    action: {
      hover: 'rgba(234,238,241,0.5)',
      disabled: '#8c959f',
    },
    error: {
      main: '#cf222e',
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
          backgroundColor: '#24292f',
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
          border: '1px solid #d0d7de',
          boxShadow: 'none',
        },
      },
    },
    MuiOutlinedInput: {
      styleOverrides: {
        notchedOutline: {
          borderColor: '#d0d7de',
        },
      },
    },
    MuiChip: {
      styleOverrides: {
        root: {
          borderRadius: 999,
          borderColor: '#d0d7de',
        },
      },
    },
    MuiLink: {
      defaultProps: {
        underline: 'hover',
      },
      styleOverrides: {
        root: {
          color: '#0969da',
        },
      },
    },
    MuiAlert: {
      styleOverrides: {
        standardSuccess: { backgroundColor: '#dafbe1', color: '#116329' },
        standardError: { backgroundColor: '#ffebe9', color: '#82071e' },
        standardWarning: { backgroundColor: '#fff8c5', color: '#7d4e00' },
        standardInfo: { backgroundColor: '#ddf4ff', color: '#0a3069' },
      },
    },
    MuiCssBaseline: {
      styleOverrides: {
        body: {
          backgroundColor: '#f6f8fa',
        },
      },
    },
  },
});
