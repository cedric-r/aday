import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { EmbedPage } from './EmbedPage';

const mockFetch = vi.fn<typeof fetch>();

const statusResponse = () =>
  new Response(
    JSON.stringify({ window_open: false, event_date: '2026-09-14', allow_late_submissions: false, message: 'x' }),
    { status: 200 },
  );

const feedResponse = (photos: unknown[]) =>
  new Response(JSON.stringify({ photos, next_cursor: null }), { status: 200 });

const renderEmbed = (initialPath = '/embed') =>
  render(
    <MemoryRouter initialEntries={[initialPath]}>
      <Routes>
        <Route path="/embed" element={<EmbedPage />} />
      </Routes>
    </MemoryRouter>,
  );

describe('EmbedPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('shows the whole gallery heading when no photographer is set', async () => {
    mockFetch
      .mockImplementation((input: unknown) => {
        const url = String(input);
        if (url.includes('/api/status.php')) return Promise.resolve(statusResponse());
        return Promise.resolve(feedResponse([]));
      });

    renderEmbed();
    await waitFor(() => expect(screen.getByText(/one day\. many photographers/i)).toBeInTheDocument());
  });

  it('shows a photographer-specific message when ?photographer is set', async () => {
    mockFetch.mockImplementation((input: unknown) => {
      const url = String(input);
      if (url.includes('/api/status.php')) return Promise.resolve(statusResponse());
      return Promise.resolve(feedResponse([]));
    });

    renderEmbed('/embed?photographer=alice');
    await waitFor(() =>
      expect(screen.getByText(/photos by alice/i)).toBeInTheDocument(),
    );
  });

  it('passes the photographer filter to the feed fetch', async () => {
    const calls: string[] = [];
    mockFetch.mockImplementation((input: unknown) => {
      const url = String(input);
      calls.push(url);
      if (url.includes('/api/status.php')) return Promise.resolve(statusResponse());
      return Promise.resolve(feedResponse([]));
    });

    renderEmbed('/embed?photographer=bob');
    await waitFor(() => expect(calls.some((u) => u.includes('photographer=bob'))).toBe(true));
  });

  it('toggles dark theme state on click', async () => {
    mockFetch.mockImplementation((input: unknown) => {
      const url = String(input);
      if (url.includes('/api/status.php')) return Promise.resolve(statusResponse());
      return Promise.resolve(feedResponse([]));
    });

    renderEmbed();
    await waitFor(() => screen.getByRole('button', { name: /toggle theme/i }));

    // Starts light — shows the moon icon (click to go dark).
    const btn = screen.getByRole('button', { name: /toggle theme/i });
    expect(btn.textContent).toContain('🌙');

    await userEvent.click(btn);

    // After toggling to dark, the icon switches to the sun.
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /toggle theme/i }).textContent).toContain('☀️');
    });
  });
});