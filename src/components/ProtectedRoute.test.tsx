import { render, screen } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { ProtectedRoute } from './ProtectedRoute';
import * as AuthModule from '@/context/AuthContext';
import type { AuthUser } from '@/schemas/auth.schema';

const mockUseAuth = (user: AuthUser | null, isLoading = false) => {
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user,
    isLoading,
    login: vi.fn(),
    logout: vi.fn(),
  });
};

const renderInRouter = (requireAdmin = false, initialPath = '/protected') =>
  render(
    <MemoryRouter initialEntries={[initialPath]}>
      <Routes>
        <Route
          path="/protected"
          element={
            <ProtectedRoute requireAdmin={requireAdmin}>
              <span>Protected content</span>
            </ProtectedRoute>
          }
        />
        <Route path="/login" element={<span>Login page</span>} />
        <Route path="/" element={<span>Home page</span>} />
      </Routes>
    </MemoryRouter>,
  );

describe('ProtectedRoute', () => {
  beforeEach(() => vi.resetAllMocks());

  it('shows spinner while isLoading is true', () => {
    mockUseAuth(null, true);
    renderInRouter();
    expect(screen.getByRole('progressbar')).toBeInTheDocument();
    expect(screen.queryByText('Protected content')).not.toBeInTheDocument();
  });

  it('redirects to /login when unauthenticated', () => {
    mockUseAuth(null, false);
    renderInRouter();
    expect(screen.getByText('Login page')).toBeInTheDocument();
    expect(screen.queryByText('Protected content')).not.toBeInTheDocument();
  });

  it('renders children for authenticated user (no admin requirement)', () => {
    mockUseAuth({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' });
    renderInRouter(false);
    expect(screen.getByText('Protected content')).toBeInTheDocument();
  });

  it('redirects to / when non-admin accesses admin-only route', () => {
    mockUseAuth({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' });
    renderInRouter(true);
    expect(screen.getByText('Home page')).toBeInTheDocument();
    expect(screen.queryByText('Protected content')).not.toBeInTheDocument();
  });

  it('renders children for admin on admin-only route', () => {
    mockUseAuth({ authenticated: true, username: 'admin', name: 'Admin', is_admin: true, status: 'validated', timezone: 'Europe/London' });
    renderInRouter(true);
    expect(screen.getByText('Protected content')).toBeInTheDocument();
  });
});
