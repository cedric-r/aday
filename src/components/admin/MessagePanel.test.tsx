import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MessagePanel } from './MessagePanel';

const mockFetch = vi.fn<typeof fetch>();

describe('MessagePanel', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  const listResponse = (messages: unknown[], recipients = 8) =>
    new Response(JSON.stringify({ messages, recipients }), { status: 200 });

  it('shows the recipient count and existing notifications', async () => {
    mockFetch.mockResolvedValueOnce(
      listResponse([{ id: 1, subject: 'Old note', body: 'body', created_at: '2026-09-01 09:00:00' }], 8),
    );

    render(<MessagePanel />);

    await waitFor(() => expect(screen.getByText(/8 validated participant/i)).toBeTruthy());
    expect(screen.getByText('Old note')).toBeTruthy();
  });

  it('disables posting when fields are empty', async () => {
    mockFetch.mockResolvedValueOnce(listResponse([], 3));

    render(<MessagePanel />);
    await waitFor(() => expect(screen.getByText(/3 validated participant/i)).toBeTruthy());

    expect(screen.getByRole('button', { name: 'Post notification' })).toBeDisabled();
  });

  it('posts the notification via POST on confirm', async () => {
    mockFetch
      .mockResolvedValueOnce(listResponse([], 5))
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            message: 'Message sent.',
            notification: { id: 9, subject: 'Reminder', body: 'Event on the 16th.', created_at: '2026-09-13 19:00:00' },
            email: { recipients: 5, sent: 5, failed: 0 },
          }),
          { status: 201 },
        ),
      )
      .mockResolvedValueOnce(listResponse([{ id: 9, subject: 'Reminder', body: 'Event on the 16th.', created_at: '2026-09-13 19:00:00' }], 5));

    const user = userEvent.setup();
    render(<MessagePanel />);
    await waitFor(() => expect(screen.getByText(/5 validated participant/i)).toBeTruthy());

    await user.type(screen.getByLabelText('Subject'), 'Reminder');
    await user.type(screen.getByLabelText('Message'), 'Event on the 16th.');
    await user.click(screen.getByRole('button', { name: 'Post notification' }));
    await user.click(await screen.findByRole('button', { name: 'Post & email' }));

    await waitFor(() => expect(screen.getByText(/emailed to 5 recipient/i)).toBeTruthy());

    const postCall = mockFetch.mock.calls.find((c) => c[1]?.method === 'POST');
    expect(postCall).toBeTruthy();
    const [url, init] = postCall as [string, RequestInit];
    expect(url).toBe('/api/admin/messages.php');
    expect(JSON.parse(String(init.body))).toEqual({ subject: 'Reminder', body: 'Event on the 16th.' });
  });

  it('reports partially failed emails', async () => {
    mockFetch
      .mockResolvedValueOnce(listResponse([], 3))
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            message: 'Message sent.',
            notification: { id: 11, subject: 'Odd', body: 'x', created_at: '2026-09-13 19:00:00' },
            email: { recipients: 3, sent: 2, failed: 1 },
          }),
          { status: 201 },
        ),
      )
      .mockResolvedValueOnce(listResponse([], 3));

    const user = userEvent.setup();
    render(<MessagePanel />);
    await waitFor(() => expect(screen.getByText(/3 validated participant/i)).toBeTruthy());

    await user.type(screen.getByLabelText('Subject'), 'Odd');
    await user.type(screen.getByLabelText('Message'), 'x');
    await user.click(screen.getByRole('button', { name: 'Post notification' }));
    await user.click(await screen.findByRole('button', { name: 'Post & email' }));

    await waitFor(() => expect(screen.getByText(/1 email\(s\) failed/i)).toBeTruthy());
  });

  it('deletes a notification after confirmation', async () => {
    mockFetch
      .mockResolvedValueOnce(listResponse([{ id: 4, subject: 'Delete me', body: 'x', created_at: '2026-09-01 09:00:00' }], 2))
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Message deleted.', id: 4 }), { status: 200 }))
      .mockResolvedValueOnce(listResponse([], 2));

    const user = userEvent.setup();
    render(<MessagePanel />);
    await waitFor(() => expect(screen.getByText('Delete me')).toBeTruthy());

    await user.click(screen.getByRole('button', { name: /delete notification delete me/i }));
    await user.click(await screen.findByRole('button', { name: 'Delete' }));

    await waitFor(() => expect(screen.getByText(/notification deleted/i)).toBeTruthy());
    const delCall = mockFetch.mock.calls.find((c) => c[1]?.method === 'DELETE');
    expect(delCall).toBeTruthy();
    expect(String(delCall?.[0])).toContain('id=4');
  });

  it('shows an error when posting fails', async () => {
    mockFetch
      .mockResolvedValueOnce(listResponse([], 1))
      .mockResolvedValueOnce(new Response('nope', { status: 422 }));

    const user = userEvent.setup();
    render(<MessagePanel />);
    await waitFor(() => expect(screen.getByText(/1 validated participant/i)).toBeTruthy());

    await user.type(screen.getByLabelText('Subject'), 'x');
    await user.type(screen.getByLabelText('Message'), 'y');
    await user.click(screen.getByRole('button', { name: 'Post notification' }));
    await user.click(await screen.findByRole('button', { name: 'Post & email' }));

    await waitFor(() => expect(screen.getByText(/failed to post the notification/i)).toBeTruthy());
  });
});
