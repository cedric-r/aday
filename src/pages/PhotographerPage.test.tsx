import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { PhotographerPage } from './PhotographerPage';

const mockFetch = vi.fn<typeof fetch>();

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
});
