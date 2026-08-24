import { render, screen } from '@testing-library/react';
import { describe, it, expect } from 'vitest';
import { TimezoneSelect } from './TimezoneSelect';

describe('TimezoneSelect', () => {
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
    const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
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
