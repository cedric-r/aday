import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { PostingWindowBanner } from './PostingWindowBanner';

describe('PostingWindowBanner', () => {
  it('shows closed message when window is closed', () => {
    render(
      <PostingWindowBanner
        eventDate="2026-08-24"
        userTimezone="Europe/London"
        windowOpen={false}
      />,
    );
    expect(screen.getByText(/posting window is closed/i)).toBeInTheDocument();
  });

  it('shows a countdown when window is open', () => {
    render(
      <PostingWindowBanner
        eventDate="2026-08-24"
        userTimezone="Europe/London"
        windowOpen={true}
      />,
    );
    // Should not show the closed message
    expect(screen.queryByText(/posting window is closed/i)).not.toBeInTheDocument();
    // Should show some countdown/open indicator
    expect(screen.getAllByText(/open|closes|midnight|remaining/i).length).toBeGreaterThan(0);
  });

  it('shows closed message when windowOpen is null (unauthenticated)', () => {
    render(
      <PostingWindowBanner
        eventDate="2026-08-24"
        userTimezone="Europe/London"
        windowOpen={null}
      />,
    );
    expect(screen.getByText(/posting window is closed/i)).toBeInTheDocument();
  });
});
