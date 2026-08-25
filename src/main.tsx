import { StrictMode, useMemo } from 'react';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
import { CssBaseline } from '@mui/material';
import { ThemeProvider } from '@mui/material/styles';
import { store } from '@/store/store';
import { buildTheme } from './theme';
import { App } from './App';

const rootElement = document.getElementById('root');
if (!rootElement) throw new Error('Root element not found');

// Read ?theme=dark from the URL before React Router mounts, so the embed can
// be themed without JS state (works in an iframe and on first paint).
const preThemeMode = new URLSearchParams(window.location.search).get('theme') === 'dark' ? 'dark' : 'light';

const ThemedApp = () => {
  const theme = useMemo(() => buildTheme(preThemeMode), []);
  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <App />
    </ThemeProvider>
  );
};

createRoot(rootElement).render(
  <StrictMode>
    <Provider store={store}>
      <ThemedApp />
    </Provider>
  </StrictMode>,
);