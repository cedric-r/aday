import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { IndexPage } from './IndexPage';

const mockFetch = vi.fn<typeof fetch>();

const photographers = [
  { username: 'alice', name: 'Alice Example', substack_url: 'https://alice.substack.com', photo_count: 3 },
  { username: 'bob', name: 'Bob Smith', substack_url: null, photo_count: 1 },
  { username: 'carol', name: 'Carol Jones', substack_url: 'https://carol.substack.com', photo_count: 0 },
];

const renderPage = () =>
  render(
    <MemoryRouter>
      <IndexPage />
    </MemoryRouter>,
  );

describe('IndexPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => vi.unstubAllGlobals());

  it('renders A–Z grouped section headers', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(photographers), { status: 200 }),
    );

    renderPage();

    await waitFor(() => expect(screen.getByText('Alice Example')).toBeInTheDocument());

    // A section header for names starting with A and B and C
    expect(screen.getByText('A')).toBeInTheDocument();
    expect(screen.getByText('B')).toBeInTheDocument();
    expect(screen.getByText('C')).toBeInTheDocument();
  });

  it('renders links to photographer pages', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(photographers), { status: 200 }),
    );

    renderPage();
    await waitFor(() => screen.getByText('Alice Example'));

    const link = screen.getByRole('link', { name: /alice example/i });
    expect(link).toHaveAttribute('href', '/photographers/alice');
  });

  it('renders substack links with target _blank', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(photographers), { status: 200 }),
    );

    renderPage();
    await waitFor(() => screen.getByText('Alice Example'));

    const substackLinks = screen.getAllByRole('link', { name: /substack/i });
    for (const link of substackLinks) {
      expect(link).toHaveAttribute('target', '_blank');
    }
  });

  it('shows empty state when no participants', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify([]), { status: 200 }),
    );

    renderPage();
    await waitFor(() =>
      expect(screen.getByText(/no participants yet/i)).toBeInTheDocument(),
    );
  });

  it('renders without error when substack_url is null', async () => {
    const withNull = [
      { username: 'alice', name: 'Alice Example', substack_url: null, photo_count: 2 },
    ];
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(withNull), { status: 200 }),
    );
    renderPage();
    await waitFor(() => expect(screen.getByText('Alice Example')).toBeInTheDocument());
    // No Substack link rendered when null
    expect(screen.queryByRole('link', { name: /substack/i })).toBeNull();
  });
});
