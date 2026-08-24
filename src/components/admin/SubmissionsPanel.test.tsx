import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { SubmissionsPanel } from './SubmissionsPanel';

const mockFetch = vi.fn<typeof fetch>();

const submissions = [
  { id: 1, username: 'alice', name: 'Alice Example', filename: 'abc.jpg', description: 'Morning light', posted_at: '2026-08-24 10:00:00' },
  { id: 2, username: 'bob', name: 'Bob Smith', filename: 'def.jpg', description: 'Evening calm', posted_at: '2026-08-24 18:00:00' },
];

describe('SubmissionsPanel', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders submission rows from API response', async () => {
    vi.spyOn(globalThis, 'setInterval').mockReturnValue(0 as unknown as ReturnType<typeof setInterval>);
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(submissions), { status: 200 }));
    render(<SubmissionsPanel />);
    await waitFor(() => expect(screen.getByText('Morning light')).toBeInTheDocument());
    expect(screen.getByText('Alice Example')).toBeInTheDocument();
  });

  it('shows total photo count', async () => {
    vi.spyOn(globalThis, 'setInterval').mockReturnValue(0 as unknown as ReturnType<typeof setInterval>);
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(submissions), { status: 200 }));
    render(<SubmissionsPanel />);
    await waitFor(() => screen.getByText('Morning light'));
    expect(screen.getByText(/2.*photo/i)).toBeInTheDocument();
  });

  it('Export All button is present', async () => {
    vi.spyOn(globalThis, 'setInterval').mockReturnValue(0 as unknown as ReturnType<typeof setInterval>);
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(submissions), { status: 200 }));
    render(<SubmissionsPanel />);
    await waitFor(() => screen.getByText('Morning light'));
    expect(screen.getByRole('button', { name: /export all/i })).toBeInTheDocument();
  });

  it('Export All button click does not throw', async () => {
    vi.spyOn(globalThis, 'setInterval').mockReturnValue(0 as unknown as ReturnType<typeof setInterval>);
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(submissions), { status: 200 }));
    const locationSpy = vi.spyOn(window, 'location', 'get').mockReturnValue({ ...window.location, href: '' });
    render(<SubmissionsPanel />);
    await waitFor(() => screen.getByText('Morning light'));
    await userEvent.click(screen.getByRole('button', { name: /export all/i }));
    locationSpy.mockRestore();
  });

  it('polls via setInterval and prepends new submissions', async () => {
    let pollCallback: (() => void) | null = null;
    vi.spyOn(globalThis, 'setInterval').mockImplementationOnce((fn) => {
      pollCallback = fn as () => void;
      return 999 as unknown as ReturnType<typeof setInterval>;
    });
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify([submissions[0]]), { status: 200 }));
    render(<SubmissionsPanel />);
    await waitFor(() => screen.getByText('Morning light'));
    expect(globalThis.setInterval).toHaveBeenCalledWith(expect.any(Function), 60_000);

    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(submissions), { status: 200 }));
    pollCallback!();
    await waitFor(() => expect(screen.getByText('Evening calm')).toBeInTheDocument());
    expect(screen.getByText('Morning light')).toBeInTheDocument();
  });

  it('poll guard prevents parallel fetch', async () => {
    let pollCallback: (() => void) | null = null;
    vi.spyOn(globalThis, 'setInterval').mockImplementationOnce((fn) => {
      pollCallback = fn as () => void;
      return 999 as unknown as ReturnType<typeof setInterval>;
    });
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify([submissions[0]]), { status: 200 }));
    render(<SubmissionsPanel />);
    await waitFor(() => screen.getByText('Morning light'));

    let resolveFirst!: (v: Response) => void;
    mockFetch.mockReturnValueOnce(new Promise<Response>((r) => { resolveFirst = r; }));
    pollCallback!();
    pollCallback!(); // second fire — guard blocks it

    resolveFirst(new Response(JSON.stringify(submissions), { status: 200 }));
    await waitFor(() => screen.getByText('Evening calm'));
    expect(mockFetch).toHaveBeenCalledTimes(2);
  });
});
