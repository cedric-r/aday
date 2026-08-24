import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { PhotoCard } from './PhotoCard';
import type { Photo } from '@/schemas/photo.schema';

const photo: Photo = {
  id: 1,
  username: 'alice',
  name: 'Alice Example',
  substack_url: 'https://alice.substack.com',
  filename: 'abc123.jpg',
  description: 'Morning light in the garden',
  posted_at: '2026-08-24 09:00:00',
};

const renderCard = (p: Photo = photo) =>
  render(
    <MemoryRouter>
      <PhotoCard photo={p} />
    </MemoryRouter>,
  );

describe('PhotoCard', () => {
  it('renders the photo image with correct src', () => {
    renderCard();
    const img = screen.getByRole('img', { name: /morning light/i });
    expect(img).toHaveAttribute('src', '/uploads/alice/abc123.jpg');
  });

  it('renders photographer name as link to /photographers/alice', () => {
    renderCard();
    const link = screen.getByRole('link', { name: /alice example/i });
    expect(link).toHaveAttribute('href', '/photographers/alice');
  });

  it('renders substack link with target _blank and rel noopener', () => {
    renderCard();
    const link = screen.getByRole('link', { name: /substack/i });
    expect(link).toHaveAttribute('href', 'https://alice.substack.com');
    expect(link).toHaveAttribute('target', '_blank');
    expect(link).toHaveAttribute('rel', 'noopener noreferrer');
  });

  it('renders full description without truncation', () => {
    renderCard();
    expect(screen.getByText('Morning light in the garden')).toBeInTheDocument();
  });

  it('renders a formatted timestamp', () => {
    renderCard();
    // Just check some timestamp text is present (locale-dependent exact format)
    expect(screen.getByText(/2026|Aug/)).toBeInTheDocument();
  });

  it('shows placeholder on image load error', () => {
    renderCard();
    const img = screen.getByRole('img');
    fireEvent.error(img);
    expect(screen.queryByRole('img')).not.toBeInTheDocument();
    expect(screen.getByLabelText(/image unavailable/i)).toBeInTheDocument();
  });

  it('renders without substack link when substack_url is null', () => {
    renderCard({ ...photo, substack_url: null });
    expect(screen.queryByRole('link', { name: /substack/i })).not.toBeInTheDocument();
  });
});
