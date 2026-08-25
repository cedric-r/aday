import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { HomePage } from './HomePage';

const mockFetch = vi.fn<typeof fetch>();

const statusResponse = (event_date: string | null) =>
  new Response(
    JSON.stringify({
      window_open: false,
      event_date,
      allow_late_submissions: false,
      message: 'x',
    }),
    { status: 200 },
  );

const emptyFeedResponse = () =>
  new Response(JSON.stringify({ photos: [], next_cursor: null }), { status: 200 });

describe('HomePage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('shows the event date on the home page when it is before the event date', async () => {
    mockFetch.mockImplementation((input: unknown) => {
      const url = String(input);
      if (url.includes('/api/status.php')) {
        return Promise.resolve(statusResponse('2099-01-01'));
      }
      return Promise.resolve(emptyFeedResponse());
    });

    render(
      <MemoryRouter>
        <HomePage />
      </MemoryRouter>,
    );

    await waitFor(() =>
      expect(screen.getByText(/event date: 2099-01-01/i)).toBeInTheDocument(),
    );
  });

  it('does not show the event date when none is defined', async () => {
    mockFetch.mockImplementation((input: unknown) => {
      const url = String(input);
      if (url.includes('/api/status.php')) {
        return Promise.resolve(statusResponse(null));
      }
      return Promise.resolve(emptyFeedResponse());
    });

    render(
      <MemoryRouter>
        <HomePage />
      </MemoryRouter>,
    );

    await waitFor(() =>
      expect(screen.getByText('No photos yet. Check back soon!')).toBeInTheDocument(),
    );
    expect(screen.queryByText(/event date:/i)).not.toBeInTheDocument();
  });
});
