import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { PhotoLightbox } from './PhotoLightbox';
import type { Photo } from '@/schemas/photo.schema';

const photo = (id: number): Photo => ({
  id,
  username: 'alice',
  name: 'Alice',
  substack_url: null,
  filename: `p${id}.jpg`,
  description: `Photo ${id}`,
  posted_at: '2026-08-24 09:00:00',
});

type Nav = (index: number) => void;

const renderBox = (photos: Photo[], index: number, nav: Nav) =>
  render(
    <MemoryRouter>
      <PhotoLightbox photos={photos} initialIndex={index} onClose={vi.fn()} onNavigate={nav} />
    </MemoryRouter>,
  );

describe('PhotoLightbox slideshow', () => {
  beforeEach(() => {
    vi.useFakeTimers();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('shows play button only for multiple photos', () => {
    const { unmount } = renderBox([photo(1)], 0, vi.fn());
    expect(screen.queryByRole('button', { name: /play slideshow/i })).not.toBeInTheDocument();
    unmount();

    renderBox([photo(1), photo(2), photo(3)], 0, vi.fn());
    expect(screen.getByRole('button', { name: /play slideshow/i })).toBeInTheDocument();
  });

  it('advances every 5s while playing and wraps at the end', () => {
    const nav = vi.fn();
    const { rerender } = renderBox([photo(1), photo(2)], 0, nav);

    fireEvent.click(screen.getByRole('button', { name: /play slideshow/i }));
    vi.advanceTimersByTime(5000);
    expect(nav).toHaveBeenCalledWith(1);

    // Move to the last index; next tick should wrap to 0.
    rerender(
      <MemoryRouter>
        <PhotoLightbox photos={[photo(1), photo(2)]} initialIndex={1} onClose={vi.fn()} onNavigate={nav} />
      </MemoryRouter>,
    );
    // Playing state persists across index changes within same mounted instance.
    vi.advanceTimersByTime(5000);
    expect(nav).toHaveBeenCalledWith(0);
  });

  it('pause stops advancing', () => {
    const nav = vi.fn();
    renderBox([photo(1), photo(2)], 0, nav);
    fireEvent.click(screen.getByRole('button', { name: /play slideshow/i })); // start
    fireEvent.click(screen.getByRole('button', { name: /pause slideshow/i })); // pause
    vi.advanceTimersByTime(20000);
    expect(nav).not.toHaveBeenCalled();
  });

  it('space bar toggles playback', () => {
    const nav = vi.fn();
    renderBox([photo(1), photo(2)], 0, nav);
    fireEvent.keyDown(window, { key: ' ' });
    vi.advanceTimersByTime(5000);
    expect(nav).toHaveBeenCalledTimes(1);
    fireEvent.keyDown(window, { key: ' ' });
    vi.advanceTimersByTime(20000);
    expect(nav).toHaveBeenCalledTimes(1);
  });
});
