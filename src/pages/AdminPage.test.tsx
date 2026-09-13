import { render, screen, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { AdminPage } from './AdminPage';
import * as AuthModule from '@/context/AuthContext';

const mockFetch = vi.fn<typeof fetch>();

// The admin panels (and UserTable) consume useAuth().
const mockAuth = () =>
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user: { authenticated: true, status: 'validated', username: 'admin', name: 'Admin', is_admin: true, timezone: 'UTC' },
    isLoading: false,
    login: vi.fn(),
    logout: vi.fn(),
  });

describe('AdminPage', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
    mockAuth();
    // The default tab renders UserTable, which loads the user list.
    mockFetch.mockResolvedValue(new Response(JSON.stringify([]), { status: 200 }));
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders every admin tab, including Messages', async () => {
    render(<AdminPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeTruthy());

    for (const label of ['Users', 'Event Date', 'Submissions', 'Embed', 'Email', 'Messages']) {
      expect(screen.getByRole('tab', { name: label })).toBeTruthy();
    }
  });

  it('uses a scrollable tab strip so all tabs stay reachable on narrow screens', async () => {
    // Regression guard: the default (standard) MUI Tabs variant does NOT
    // scroll, so on mobile widths the trailing tabs — Embed, Email, Messages —
    // were clipped and unreachable.
    render(<AdminPage />);

    await waitFor(() => expect(screen.getByRole('tablist')).toBeTruthy());

    const tablist = screen.getByRole('tablist');
    // MUI marks the horizontal scroller (the tablist's parent) with
    // MuiTabs-scrollableX when variant="scrollable".
    const scroller = tablist.parentElement;
    expect(scroller).not.toBeNull();
    expect(scroller?.className).toMatch(/MuiTabs-scrollableX/);
  });
});
