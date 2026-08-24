import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { PostPage } from './PostPage';
import * as AuthModule from '@/context/AuthContext';

const mockFetch = vi.fn<typeof fetch>();

const mockAuth = () => {
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user: { authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated' },
    isLoading: false,
    login: vi.fn(),
    logout: vi.fn(),
  });
};

const statusOpen = () =>
  new Response(
    JSON.stringify({ window_open: true, event_date: '2026-08-24', message: 'Open' }),
    { status: 200 },
  );

const statusClosed = () =>
  new Response(
    JSON.stringify({ window_open: false, event_date: '2026-08-24', message: 'Closed' }),
    { status: 200 },
  );

const renderPage = () =>
  render(
    <MemoryRouter>
      <PostPage />
    </MemoryRouter>,
  );

describe('PostPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
    mockAuth();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders PostingWindowBanner and form when window open', async () => {
    mockFetch.mockResolvedValueOnce(statusOpen());
    renderPage();

    await waitFor(() =>
      expect(screen.getAllByText(/posting window is open/i).length).toBeGreaterThan(0),
    );
    expect(screen.getByLabelText(/description/i)).toBeInTheDocument();
  });

  it('hides form when posting window is closed', async () => {
    mockFetch.mockResolvedValueOnce(statusClosed());
    renderPage();

    await waitFor(() =>
      expect(screen.getByText(/posting window is closed/i)).toBeInTheDocument(),
    );
    expect(screen.queryByLabelText(/description/i)).not.toBeInTheDocument();
  });

  it('shows client-side warning for file over 15 MB', async () => {
    mockFetch.mockResolvedValueOnce(statusOpen());
    renderPage();
    await waitFor(() => screen.getAllByText(/open/i));

    const bigFile = new File(['x'.repeat(16 * 1024 * 1024)], 'big.jpg', { type: 'image/jpeg' });
    const input = screen.getByLabelText(/photo/i);
    await userEvent.upload(input, bigFile);

    expect(screen.getByText(/file is too large/i)).toBeInTheDocument();
  });

  it('shows success flash on 201', async () => {
    mockFetch.mockResolvedValueOnce(statusOpen());
    renderPage();
    await waitFor(() => screen.getAllByText(/open/i));

    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
    await userEvent.upload(screen.getByLabelText(/photo/i), file);
    await userEvent.type(screen.getByLabelText(/description/i), 'My caption');

    // Wait for button to be enabled (state update after upload)
    await waitFor(() => expect(screen.getByRole('button', { name: /submit/i })).not.toBeDisabled());

    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ id: 1, filename: 'abc.jpg', posted_at: '2026-08-24 10:00:00' }),
        { status: 201 },
      ),
    );

    await userEvent.click(screen.getByRole('button', { name: /submit/i }));

    await waitFor(() =>
      expect(screen.getByText(/photo posted/i)).toBeInTheDocument(),
    );
  });

  it('refreshes banner on 403 posting window closed', async () => {
    mockFetch.mockResolvedValueOnce(statusOpen());
    renderPage();
    await waitFor(() => screen.getAllByText(/open/i));

    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
    await userEvent.upload(screen.getByLabelText(/photo/i), file);
    await userEvent.type(screen.getByLabelText(/description/i), 'test');
    await waitFor(() => expect(screen.getByRole('button', { name: /submit/i })).not.toBeDisabled());

    // POST returns 403, then status refresh returns closed
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Posting window closed' }), { status: 403 }),
    );
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ window_open: false, event_date: '2026-08-24', message: 'Closed' }), { status: 200 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /submit/i }));

    await waitFor(() =>
      expect(screen.getByText(/posting window is closed/i)).toBeInTheDocument(),
    );
  });

  it('shows API error message on 422', async () => {
    mockFetch.mockResolvedValueOnce(statusOpen());
    renderPage();
    await waitFor(() => screen.getAllByText(/open/i));

    // Upload a valid JPEG — the 422 comes from the API (e.g. server-side MIME check), not browser filter
    const file = new File(['img'], 'photo.jpg', { type: 'image/jpeg' });
    await userEvent.upload(screen.getByLabelText(/photo/i), file);
    await userEvent.type(screen.getByLabelText(/description/i), 'test');

    await waitFor(() => expect(screen.getByRole('button', { name: /submit/i })).not.toBeDisabled());

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Invalid file type.' }), { status: 422 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /submit/i }));

    await waitFor(() =>
      expect(screen.getByText(/invalid file type/i)).toBeInTheDocument(),
    );
  });
});
