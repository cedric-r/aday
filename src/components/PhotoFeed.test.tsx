import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { PhotoFeed } from './PhotoFeed';

const mockFetch = vi.fn<typeof fetch>();

const makePhoto = (id: number, posted_at: string) => ({
  id,
  username: `user${id}`,
  name: `User ${id}`,
  substack_url: '',
  filename: `photo${id}.jpg`,
  description: `Description ${id}`,
  posted_at,
});

const feedResponse = (photos: ReturnType<typeof makePhoto>[], next_cursor: string | null = null) =>
  new Response(JSON.stringify({ photos, next_cursor }), { status: 200 });

const renderFeed = () =>
  render(
    <MemoryRouter>
      <PhotoFeed />
    </MemoryRouter>,
  );

describe('PhotoFeed', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders cards from initial fetch', async () => {
    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(1, '2026-08-24 10:00:00'), makePhoto(2, '2026-08-24 09:00:00')]),
    );

    renderFeed();

    await waitFor(() => expect(screen.getByText('Description 1')).toBeInTheDocument());
    expect(screen.getByText('Description 2')).toBeInTheDocument();
  });

  it('poll prepends new cards without removing existing', async () => {
    // Capture the poll callback via setInterval spy
    let pollCallback: (() => void) | null = null;
    vi.spyOn(globalThis, 'setInterval').mockImplementationOnce((fn) => {
      pollCallback = fn as () => void;
      return 999 as unknown as ReturnType<typeof setInterval>;
    });

    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(1, '2026-08-24 10:00:00')]),
    );

    renderFeed();
    await waitFor(() => screen.getByText('Description 1'));

    // Trigger poll manually
    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(2, '2026-08-24 10:01:00')]),
    );

    pollCallback!();

    await waitFor(() => expect(screen.getByText('Description 2')).toBeInTheDocument());
    expect(screen.getByText('Description 1')).toBeInTheDocument();
  });

  it('shows Load more button and appends older cards', async () => {
    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(1, '2026-08-24 10:00:00')], '2026-08-24 10:00:00'),
    );

    renderFeed();
    await waitFor(() => screen.getByText('Description 1'));

    expect(screen.getByRole('button', { name: /load more/i })).toBeInTheDocument();

    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(2, '2026-08-24 09:00:00')], null),
    );

    await userEvent.click(screen.getByRole('button', { name: /load more/i }));

    await waitFor(() => expect(screen.getByText('Description 2')).toBeInTheDocument());
    expect(screen.getByText('Description 1')).toBeInTheDocument();
  });

  it('hides Load more button when next_cursor is null', async () => {
    mockFetch.mockResolvedValueOnce(
      feedResponse([makePhoto(1, '2026-08-24 10:00:00')], null),
    );

    renderFeed();
    await waitFor(() => screen.getByText('Description 1'));

    expect(screen.queryByRole('button', { name: /load more/i })).not.toBeInTheDocument();
  });

  it('shows empty state with large logo when no photos', async () => {
    mockFetch.mockResolvedValueOnce(feedResponse([]));

    renderFeed();

    await waitFor(() =>
      expect(screen.getByText('No photos yet. Check back soon!')).toBeInTheDocument(),
    );
    const logo = screen.getByRole('img', { name: /document your life/i });
    expect(logo).toHaveAttribute('src', '/documentyourlife.png');
  });

  it('shows error state when initial fetch fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network error'));
    renderFeed();
    await waitFor(() =>
      expect(screen.getByText(/failed to load photos/i)).toBeInTheDocument(),
    );
  });

  it('poll guard prevents second fetch while first is in-flight', async () => {
    let pollCallback: (() => void) | null = null;
    vi.spyOn(globalThis, 'setInterval').mockImplementationOnce((fn) => {
      pollCallback = fn as () => void;
      return 999 as unknown as ReturnType<typeof setInterval>;
    });

    mockFetch.mockResolvedValueOnce(feedResponse([makePhoto(1, '2026-08-24 10:00:00')]));

    renderFeed();
    await waitFor(() => screen.getByText('Description 1'));

    // First poll — hangs indefinitely
    let resolvePoll!: (v: Response) => void;
    const hangingPoll = new Promise<Response>((r) => { resolvePoll = r; });
    mockFetch.mockReturnValueOnce(hangingPoll);

    // Fire poll callback twice while first is in-flight
    pollCallback!();
    pollCallback!();

    // Resolve hanging poll
    resolvePoll(feedResponse([makePhoto(2, '2026-08-24 10:01:00')]));
    await waitFor(() => screen.getByText('Description 2'));

    // 1 initial + 1 poll = 2 total (not 3 — guard prevented second)
    expect(mockFetch).toHaveBeenCalledTimes(2);
  });
});
