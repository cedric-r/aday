import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { PhotographerPage } from './PhotographerPage';
import * as AuthModule from '@/context/AuthContext';

const mockFetch = vi.fn<typeof fetch>();

// PhotographerPage uses useAuth() (own-profile download button).
vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
  user: null,
  isLoading: false,
  login: vi.fn(),
  logout: vi.fn(),
});

const profile = {
  username: 'alice',
  name: 'Alice Example',
  substack_url: 'https://alice.substack.com',
  photos: [
    { id: 1, filename: 'abc.jpg', description: 'Morning light', posted_at: '2026-08-24 10:00:00' },
    { id: 2, filename: 'def.jpg', description: 'Evening calm', posted_at: '2026-08-24 18:00:00' },
  ],
};

const renderPage = (username = 'alice') =>
  render(
    <MemoryRouter initialEntries={[`/photographers/${username}`]}>
      <Routes>
        <Route path="/photographers/:username" element={<PhotographerPage />} />
        <Route path="/" element={<span>Home</span>} />
      </Routes>
    </MemoryRouter>,
  );

describe('PhotographerPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => vi.unstubAllGlobals());

  it('renders photographer name and photos via PhotoCard', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(profile), { status: 200 }),
    );

    renderPage();

    await waitFor(() => expect(screen.getAllByText('Alice Example').length).toBeGreaterThan(0));
    expect(screen.getByText('Morning light')).toBeInTheDocument();
    expect(screen.getByText('Evening calm')).toBeInTheDocument();
  });

  it('renders substack link', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(profile), { status: 200 }),
    );

    renderPage();
    await waitFor(() => expect(screen.getAllByText('Alice Example').length).toBeGreaterThan(0));

    const link = screen.getAllByRole('link', { name: /substack/i })[0];
    expect(link).toHaveAttribute('href', 'https://alice.substack.com');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('renders empty gallery state when photographer has no photos', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ ...profile, photos: [] }),
        { status: 200 },
      ),
    );

    renderPage();
    await waitFor(() => screen.getByText('Alice Example'));
    expect(screen.getByText(/no photos yet/i)).toBeInTheDocument();
  });

  it('renders NotFoundPage on 404', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Not found' }), { status: 404 }),
    );

    renderPage('nobody');
    await waitFor(() =>
      expect(screen.getByText(/404/i)).toBeInTheDocument(),
    );
  });

  it('renders without error when substack_url is null', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({
          username: 'alice',
          name: 'Alice Example',
          substack_url: null,
          photos: [],
        }),
        { status: 200 },
      ),
    );
    renderPage();
    await waitFor(() => expect(screen.getByText('Alice Example')).toBeInTheDocument());
    expect(screen.queryByRole('link', { name: /substack/i })).toBeNull();
  });

  it('shows no delete buttons when viewing someone else profile', async () => {
    mockFetch.mockResolvedValueOnce(new Response(JSON.stringify(profile), { status: 200 }));

    renderPage(); // alice, but logged-out user
    await waitFor(() => expect(screen.getAllByText('Alice Example').length).toBeGreaterThan(0));

    expect(screen.queryAllByRole('button', { name: /delete photo/i })).toHaveLength(0);
  });

  it('shows delete buttons and deletes own photo on own profile', async () => {
    // Logged in as alice → own profile.
    vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
      user: { authenticated: true, status: 'validated', username: 'alice', name: 'Alice', is_admin: false, timezone: 'UTC' },
      isLoading: false,
      login: vi.fn(),
      logout: vi.fn(),
    });

    mockFetch
      .mockResolvedValueOnce(new Response(JSON.stringify(profile), { status: 200 })) // initial load
      .mockResolvedValueOnce(new Response(JSON.stringify({ message: 'Photo deleted.' }), { status: 200 })) // DELETE
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ ...profile, photos: [profile.photos[1]] }), { status: 200 }), // reload
      );

    renderPage();
    await waitFor(() => expect(screen.getAllByText('Alice Example').length).toBeGreaterThan(0));

    const deleteButtons = screen.getAllByRole('button', { name: /delete photo/i });
    expect(deleteButtons).toHaveLength(2);

    // First photo → confirm dialog.
    await deleteButtons[0].click();
    expect(screen.getByText(/delete this photo/i)).toBeInTheDocument();

    // Confirm.
    await screen.getByRole('button', { name: /^delete$/i }).click();

    await waitFor(() => expect(screen.getAllByText('Alice Example').length).toBeGreaterThan(0));
    const deleteCall = mockFetch.mock.calls.find((c) => c[1]?.method === 'DELETE');
    expect(deleteCall).toBeTruthy();
    expect(String(deleteCall?.[0])).toContain('id=1');
  });
});
