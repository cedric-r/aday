import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { EventDatePanel } from './EventDatePanel';

const mockFetch = vi.fn<typeof fetch>();

const settingsResponse = (event_date: string | null, allow_late: boolean) =>
  new Response(
    JSON.stringify({ event_date, allow_late_submissions: allow_late }),
    { status: 200 },
  );

const dateField = () => screen.getByLabelText('Event date');
const toggle = () => screen.getByRole('checkbox', { name: /^allow late submissions$/i });

describe('EventDatePanel', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('pre-fills existing event date', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ event_date: '2026-08-24' }), { status: 200 }),
    );

    render(<EventDatePanel />);

    await waitFor(() => {
      expect((dateField() as HTMLInputElement).value).toBe('2026-08-24');
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
    await waitFor(() => dateField());

    await userEvent.type(dateField(), '2026-09-01');

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

  it('renders the late-submissions toggle and reflects the loaded value', async () => {
    mockFetch.mockResolvedValueOnce(settingsResponse('2026-08-24', true));

    render(<EventDatePanel />);

    await waitFor(() => expect(dateField()).toBeInTheDocument());
    expect(toggle()).toBeChecked();
    expect(
      screen.getByText(/keep submissions open after the event date/i),
    ).toBeInTheDocument();
  });

  it('sends allow_late_submissions with the save request', async () => {
    mockFetch.mockResolvedValueOnce(settingsResponse('2026-08-24', false));

    render(<EventDatePanel />);

    await waitFor(() => expect(dateField()).toBeInTheDocument());

    await userEvent.click(toggle());

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'saved', event_date: '2026-08-24', allow_late_submissions: true }), {
        status: 200,
      }),
    );

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => expect(mockFetch).toHaveBeenCalledTimes(2));

    const [, init] = mockFetch.mock.calls[1];
    const body = JSON.parse((init?.body as string) ?? '{}');
    expect(body.allow_late_submissions).toBe(true);
    expect(body.event_date).toBe('2026-08-24');
  });

  it('defaults the toggle to off when the API omits the flag', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ event_date: '2026-08-24' }), { status: 200 }),
    );

    render(<EventDatePanel />);

    await waitFor(() => expect(dateField()).toBeInTheDocument());
    expect(toggle()).not.toBeChecked();
  });
});
