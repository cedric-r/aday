import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { NotificationsPage } from './NotificationsPage';

const mockFetch = vi.fn<typeof fetch>();

const messagesResponse = (messages: unknown[]) =>
  new Response(JSON.stringify({ messages }), { status: 200 });

describe('NotificationsPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders messages with subject and body', async () => {
    mockFetch.mockResolvedValueOnce(
      messagesResponse([
        { id: 2, subject: 'Event reminder', body: 'The event is on the 16th.', created_at: '2026-09-13 19:00:00' },
        { id: 1, subject: 'Welcome', body: 'Thanks for joining.', created_at: '2026-09-01 09:00:00' },
      ]),
    );

    render(<NotificationsPage />);

    await waitFor(() => expect(screen.getByText('Event reminder')).toBeTruthy());
    expect(screen.getByText('The event is on the 16th.')).toBeTruthy();
    expect(screen.getByText('Welcome')).toBeTruthy();
    expect(screen.getByText('Thanks for joining.')).toBeTruthy();
    expect(mockFetch).toHaveBeenCalledWith('/api/messages.php');
  });

  it('shows an empty state when there are no messages', async () => {
    mockFetch.mockResolvedValueOnce(messagesResponse([]));

    render(<NotificationsPage />);

    await waitFor(() => expect(screen.getByText(/no notifications yet/i)).toBeTruthy());
  });

  it('shows an error when the feed cannot be loaded', async () => {
    mockFetch.mockResolvedValueOnce(new Response('nope', { status: 401 }));

    render(<NotificationsPage />);

    await waitFor(() => expect(screen.getByText(/could not load notifications/i)).toBeTruthy());
  });

  it('tells a pending user their account is awaiting validation', async () => {
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify({ error: 'Account not validated' }), { status: 403 }));

    render(<NotificationsPage />);

    await waitFor(() => expect(screen.getByText(/awaiting validation/i)).toBeTruthy());
  });
});
