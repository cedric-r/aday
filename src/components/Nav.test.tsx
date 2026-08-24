import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import { Nav } from './Nav';
import * as AuthModule from '@/context/AuthContext';
import type { AuthUser } from '@/schemas/auth.schema';

const mockLogout = vi.fn();

const mockUseAuth = (user: AuthUser | null) => {
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user,
    isLoading: false,
    login: vi.fn(),
    logout: mockLogout,
  });
};

const renderNav = () =>
  render(
    <MemoryRouter>
      <Nav />
    </MemoryRouter>,
  );

describe('Nav', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    mockLogout.mockResolvedValue(undefined);
  });

  it('shows Log in link when unauthenticated', () => {
    mockUseAuth(null);
    renderNav();
    expect(screen.getByRole('link', { name: /log in/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /post/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /admin/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /log out/i })).not.toBeInTheDocument();
  });

  it('shows Home and Index links always', () => {
    mockUseAuth(null);
    renderNav();
    expect(screen.getByRole('link', { name: /home/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /index/i })).toBeInTheDocument();
  });

  it('shows Post and Log out for authenticated non-admin; hides Log in and Admin', () => {
    mockUseAuth({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' });
    renderNav();
    expect(screen.getByRole('link', { name: /post/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /log out/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /log in/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /admin/i })).not.toBeInTheDocument();
  });

  it('shows Admin and Log out for admin user; hides Post and Log in', () => {
    mockUseAuth({ authenticated: true, username: 'admin', name: 'Admin', is_admin: true, status: 'validated', timezone: 'Europe/London' });
    renderNav();
    expect(screen.getByRole('link', { name: /admin/i })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /log out/i })).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /post/i })).not.toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /log in/i })).not.toBeInTheDocument();
  });

  it('calls logout() when Log out button clicked', async () => {
    mockUseAuth({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' });
    renderNav();
    await userEvent.click(screen.getByRole('button', { name: /log out/i }));
    expect(mockLogout).toHaveBeenCalledOnce();
  });
});
