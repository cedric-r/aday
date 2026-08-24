import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { AuthProvider, useAuth } from './AuthContext';

// Helper component that exercises the context
const TestConsumer = () => {
  const { user, login, logout, isLoading } = useAuth();
  if (isLoading) return <div>loading</div>;
  if (!user) return (
    <div>
      <span>unauthenticated</span>
      <button onClick={() => { void login('alice', 'pass'); }}>login</button>
    </div>
  );
  return (
    <div>
      <span>authenticated:{user.username}</span>
      <span>admin:{String(user.is_admin)}</span>
      <button onClick={() => { void logout(); }}>logout</button>
    </div>
  );
};

const renderWithProvider = () =>
  render(<AuthProvider><TestConsumer /></AuthProvider>);

const mockFetch = vi.fn<typeof fetch>();

describe('AuthContext', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('shows loading state on mount then resolves unauthenticated', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ authenticated: false }), { status: 200 }),
    );

    renderWithProvider();
    expect(screen.getByText('loading')).toBeInTheDocument();
    await waitFor(() => expect(screen.getByText('unauthenticated')).toBeInTheDocument());
  });

  it('sets user when me.php returns authenticated', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' }),
        { status: 200 },
      ),
    );

    renderWithProvider();
    await waitFor(() => expect(screen.getByText('authenticated:alice')).toBeInTheDocument());
  });

  it('login() posts credentials, updates user state on 200', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ authenticated: false }), { status: 200 }),
    );

    renderWithProvider();
    await waitFor(() => screen.getByText('unauthenticated'));

    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' }),
        { status: 200 },
      ),
    );

    await act(async () => {
      await userEvent.click(screen.getByRole('button', { name: 'login' }));
    });

    expect(screen.getByText('authenticated:alice')).toBeInTheDocument();
  });

  it('login() throws with error message on 401', async () => {
    mockFetch
      .mockResolvedValueOnce(new Response(JSON.stringify({ authenticated: false }), { status: 200 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ error: 'Invalid credentials' }), { status: 401 }),
      );

    let thrown: unknown;
    const CaptureLogin = () => {
      const { login } = useAuth();
      const handleClick = async () => {
        try { await login('x', 'y'); } catch (e) { thrown = e; }
      };
      return <button onClick={() => { void handleClick(); }}>trylogin</button>;
    };

    const { getByRole } = render(<AuthProvider><CaptureLogin /></AuthProvider>);
    await waitFor(() => {});

    await act(async () => {
      await userEvent.click(getByRole('button', { name: 'trylogin' }));
    });

    expect(thrown).toBeInstanceOf(Error);
    expect((thrown as Error).message).toBe('Invalid credentials');
  });

  it('login() throws on 403 pending account', async () => {
    mockFetch
      .mockResolvedValueOnce(new Response(JSON.stringify({ authenticated: false }), { status: 200 }))
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ error: 'Account pending approval' }), { status: 403 }),
      );

    let thrown: unknown;
    const CaptureLogin = () => {
      const { login } = useAuth();
      const handleClick = async () => {
        try { await login('pending', 'pass'); } catch (e) { thrown = e; }
      };
      return <button onClick={() => { void handleClick(); }}>trylogin</button>;
    };

    const { getByRole } = render(<AuthProvider><CaptureLogin /></AuthProvider>);
    await waitFor(() => {});

    await act(async () => {
      await userEvent.click(getByRole('button', { name: 'trylogin' }));
    });

    expect(thrown).toBeInstanceOf(Error);
    expect((thrown as Error).message).toMatch(/pending/i);
  });

  it('logout() posts to logout.php and clears user', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(
        JSON.stringify({ authenticated: true, username: 'alice', name: 'Alice', is_admin: false, status: 'validated', timezone: 'Europe/London' }),
        { status: 200 },
      ),
    );

    renderWithProvider();
    await waitFor(() => screen.getByText('authenticated:alice'));

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Logged out' }), { status: 200 }),
    );

    await act(async () => {
      await userEvent.click(screen.getByRole('button', { name: 'logout' }));
    });

    expect(screen.getByText('unauthenticated')).toBeInTheDocument();
  });
});
