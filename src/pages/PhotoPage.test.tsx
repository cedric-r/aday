import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { PhotoPage } from './PhotoPage';

const mockFetch = vi.fn<typeof fetch>();

const photoResponse = (photo: unknown) =>
  new Response(JSON.stringify({ photos: photo ? [photo] : [], next_cursor: null }), { status: 200 });

const makePhoto = (overrides: Record<string, unknown> = {}) => ({
  id: 42,
  username: 'alice',
  name: 'Alice Smith',
  substack_url: null,
  filename: 'abc.jpg',
  description: 'Morning light',
  posted_at: '2026-08-24 09:00:00',
  ...overrides,
});

const renderPage = (id = '42') =>
  render(
    <MemoryRouter initialEntries={[`/photos/${id}`]}>
      <Routes>
        <Route path="/photos/:id" element={<PhotoPage />} />
      </Routes>
    </MemoryRouter>,
  );

describe('PhotoPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders the photo details for an existing photo', async () => {
    mockFetch.mockResolvedValueOnce(photoResponse(makePhoto()));

    renderPage();

    await waitFor(() => expect(screen.getByText('Alice Smith')).toBeInTheDocument());
    expect(screen.getByText('Morning light')).toBeInTheDocument();
    const img = screen.getByRole('img') as HTMLImageElement;
    expect(img.src).toContain('/uploads/alice/abc.jpg');
  });

  it('renders a not-found state when the photo is missing', async () => {
    mockFetch.mockResolvedValueOnce(photoResponse(null));

    renderPage('999');

    await waitFor(() => expect(screen.getByText(/photo not found/i)).toBeInTheDocument());
  });
});