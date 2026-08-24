import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EventDatePanel } from './EventDatePanel';

const mockFetch = vi.fn<typeof fetch>();

describe('EventDatePanel', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => vi.unstubAllGlobals());

  it('pre-fills existing event date', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ event_date: '2026-08-24' }), { status: 200 }),
    );

    render(<EventDatePanel />);

    await waitFor(() => {
      const input = screen.getByLabelText(/event date/i) as HTMLInputElement;
      expect(input.value).toBe('2026-08-24');
    });
  });

  it('shows past-date warning when saved date is in the past', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ event_date: '2020-01-01' }), { status: 200 }),
    );

    render(<EventDatePanel />);

    await waitFor(() =>
      expect(screen.getByText(/date is in the past/i)).toBeInTheDocument(),
    );
  });

  it('calls POST when Save clicked', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ event_date: null }), { status: 200 }),
    );

    render(<EventDatePanel />);
    await waitFor(() => screen.getByLabelText(/event date/i));

    await userEvent.type(screen.getByLabelText(/event date/i), '2026-09-01');

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Event date saved.', event_date: '2026-09-01' }), { status: 200 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/admin/settings.php',
        expect.objectContaining({ method: 'POST' }),
      );
    });
  });
});
