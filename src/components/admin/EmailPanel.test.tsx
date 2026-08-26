import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EmailPanel } from './EmailPanel';

const mockFetch = vi.fn<typeof fetch>();

describe('EmailPanel', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  const previewResponse = (recipients: number) =>
    new Response(JSON.stringify({ scope: 'validated', recipients }), { status: 200 });

  it('shows recipient count from preview', async () => {
    mockFetch.mockResolvedValueOnce(previewResponse(3));

    render(<EmailPanel />);

    await waitFor(() => {
      expect(screen.getByText(/will be sent to 3 recipient/i)).toBeTruthy();
    });
    expect(mockFetch).toHaveBeenCalledWith('/api/admin/email.php?scope=validated');
  });

  it('switching scope refetches the preview count', async () => {
    mockFetch
      .mockResolvedValueOnce(previewResponse(3)) // validated
      .mockResolvedValueOnce(new Response(JSON.stringify({ scope: 'all', recipients: 5 }), { status: 200 }));

    render(<EmailPanel />);
    await waitFor(() => expect(screen.getByText(/3 recipient/i)).toBeTruthy());

    await userEvent.click(screen.getByLabelText('Recipients'));
    await userEvent.click(await screen.findByText('All registered users'));

    await waitFor(() => {
      expect(screen.getByText(/5 recipient/i)).toBeTruthy();
    });
  });

  it('sends subject and body via POST on confirm', async () => {
    mockFetch
      .mockResolvedValueOnce(previewResponse(1))
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({ status: 'sent', recipients: 1, sent: 1, failed: 0 }),
          { status: 200 },
        ),
      );

    const user = userEvent.setup();
    render(<EmailPanel />);
    await waitFor(() => expect(screen.getByText(/1 recipient/i)).toBeTruthy());

    await user.type(screen.getByLabelText('Subject'), 'Event reminder');
    await user.type(screen.getByLabelText('Message'), 'The event is on the 14th.');

    await user.click(screen.getByRole('button', { name: 'Send email' }));
    await user.click(await screen.findByRole('button', { name: 'Send' }));

    await waitFor(() => {
      expect(screen.getByRole('alert').textContent).toMatch(/sent to 1 recipient/i);
    });

    const postCall = mockFetch.mock.calls.find((c) => c[1]?.method === 'POST');
    expect(postCall).toBeTruthy();
    const [url, init] = postCall as [string, RequestInit];
    expect(url).toBe('/api/admin/email.php');
    expect(JSON.parse(String(init.body))).toEqual({
      scope: 'validated',
      subject: 'Event reminder',
      body: 'The event is on the 14th.',
    });
  });

  it('disables send when fields are empty', async () => {
    mockFetch.mockResolvedValueOnce(previewResponse(1));

    render(<EmailPanel />);
    await waitFor(() => expect(screen.getByText(/1 recipient/i)).toBeTruthy());

    expect(screen.getByRole('button', { name: 'Send email' })).toBeDisabled();
  });

  it('shows a failure alert when sending errors', async () => {
    mockFetch
      .mockResolvedValueOnce(previewResponse(1))
      .mockResolvedValueOnce(new Response('nope', { status: 500 }));

    const user = userEvent.setup();
    render(<EmailPanel />);
    await waitFor(() => expect(screen.getByText(/1 recipient/i)).toBeTruthy());

    await user.type(screen.getByLabelText('Subject'), 'Hello');
    await user.type(screen.getByLabelText('Message'), 'World');
    await user.click(screen.getByRole('button', { name: 'Send email' }));
    await user.click(await screen.findByRole('button', { name: 'Send' }));

    await waitFor(() => {
      expect(screen.getByText('Failed to send email.')).toBeTruthy();
    });
  });
});
