import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { TimezoneSelect } from './TimezoneSelect';

describe('TimezoneSelect', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });
  it('renders a select element', () => {
    render(
      <TimezoneSelect
        value="Europe/London"
        onChange={() => {}}
        id="tz"
      />,
    );
    expect(screen.getByRole('combobox')).toBeInTheDocument();
  });

  it('renders options grouped by continent', () => {
    render(
      <TimezoneSelect
        value="Europe/London"
        onChange={() => {}}
        id="tz"
      />,
    );
    const groups = screen.getAllByRole('group');
    expect(groups.length).toBeGreaterThan(0);
    // Europe group should exist
    const labels = groups.map((g) => g.getAttribute('label') ?? '');
    expect(labels).toContain('Europe');
  });

  it('has a selected value', () => {
    render(
      <TimezoneSelect
        value="America/New_York"
        onChange={() => {}}
        id="tz"
      />,
    );
    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(select.value).toBe('America/New_York');
  });

  it('renders an option for the browser default timezone', () => {
    // Stub the environment timezone so the test is deterministic everywhere.
    vi.spyOn(Intl.DateTimeFormat.prototype, 'resolvedOptions').mockReturnValue(
      { timeZone: 'Europe/Paris' } as ReturnType<typeof Intl.DateTimeFormat.prototype.resolvedOptions>,
    );
    const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    expect(browserTz).toBe('Europe/Paris');

    render(
      <TimezoneSelect
        value={browserTz}
        onChange={() => {}}
        id="tz"
      />,
    );
    const select = screen.getByRole('combobox') as HTMLSelectElement;
    expect(select.value).toBe(browserTz);
  });
});
