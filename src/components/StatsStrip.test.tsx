import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { StatsStrip } from './StatsStrip';
import type { StatsResponse } from '@/schemas/photo.schema';

const base: StatsResponse = {
  total: 12,
  by_hour: Array.from({ length: 48 }, (_, i) => ({ hour: `h${i}`, count: i })),
};

describe('StatsStrip', () => {
  beforeEach(() => vi.clearAllMocks());

  it('shows photo count only when breadth fields are absent', () => {
    render(<StatsStrip stats={base} />);
    expect(screen.getByText(/12 photos so far/)).toBeInTheDocument();
    expect(screen.queryByText(/photographer/)).not.toBeInTheDocument();
    expect(screen.queryByText(/time zones/)).not.toBeInTheDocument();
  });

  it('shows photographers and time zones breadth', () => {
    render(<StatsStrip stats={{ ...base, photographers: 34, timezones: 19 }} />);
    expect(screen.getByText(/34 photographers · 19 time zones/)).toBeInTheDocument();
  });

  it('uses singular forms for one photographer / two time zones edge', () => {
    render(<StatsStrip stats={{ ...base, total: 1, photographers: 1, timezones: 1 }} />);
    const line = screen.getByText(/1 photo so far/).textContent ?? '';
    expect(line).toContain('1 photographer');
    // single time zone is not shown (needs >1)
    expect(line).not.toContain('time zone');
  });

  it('renders nothing at zero photos', () => {
    const { container } = render(<StatsStrip stats={{ ...base, total: 0 }} />);
    expect(container).toBeEmptyDOMElement();
  });
});
