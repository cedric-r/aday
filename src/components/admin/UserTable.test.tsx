import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { UserTable } from './UserTable';
import * as AuthModule from '@/context/AuthContext';

const mockFetch = vi.fn<typeof fetch>();

const mockAuth = (username = 'admin') => {
  vi.spyOn(AuthModule, 'useAuth').mockReturnValue({
    user: { authenticated: true, username, name: 'Admin', is_admin: true, status: 'validated' },
    isLoading: false,
    login: vi.fn(),
    logout: vi.fn(),
  });
};

const users = [
  { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', timezone: 'UTC', status: 'pending', is_admin: 0, created_at: '2026-01-01' },
  { id: 2, username: 'bob', name: 'Bob', email: 'b@b.com', timezone: 'UTC', status: 'validated', is_admin: 0, created_at: '2026-01-01' },
  { id: 3, username: 'admin', name: 'Admin', email: 'admin@a.com', timezone: 'UTC', status: 'validated', is_admin: 1, created_at: '2026-01-01' },
];

const renderTable = () => render(<UserTable onEdit={vi.fn()} />);

describe('UserTable', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
    mockAuth();
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
  });

  it('renders status badges correctly', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users), { status: 200 }),
    );

    renderTable();

    await waitFor(() => expect(screen.getByText('Alice')).toBeInTheDocument());
    expect(screen.getByText('pending')).toBeInTheDocument();
    // Bob is validated
    const validatedBadges = screen.getAllByText('validated');
    expect(validatedBadges.length).toBeGreaterThan(0);
  });

  it('Approve button absent for validated users', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users), { status: 200 }),
    );

    renderTable();
    await waitFor(() => screen.getByText('Alice'));

    // Alice (pending) has Approve; Bob (validated) does not
    const approveButtons = screen.getAllByRole('button', { name: /approve/i });
    expect(approveButtons).toHaveLength(1); // only Alice
  });

  it('Delete button disabled for own account', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users), { status: 200 }),
    );

    renderTable();
    await waitFor(() => screen.getByText('alice'));

    // Find delete buttons - admin's own row should be disabled
    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    // admin user row has username 'admin' in a cell
    const adminDeleteBtn = deleteButtons.find(
      (btn) => btn.closest('tr')?.textContent?.includes('admin@a.com'),
    );
    expect(adminDeleteBtn).toBeDisabled();
  });

  it('shows error message when fetch fails', async () => {
    mockFetch.mockRejectedValueOnce(new Error('network'));

    renderTable();
    await waitFor(() =>
      expect(screen.getByText(/failed to load users/i)).toBeInTheDocument(),
    );
  });

  it('calls DELETE when Delete confirmed', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users), { status: 200 }),
    );

    vi.spyOn(globalThis, 'confirm').mockReturnValueOnce(true);
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'User deleted.' }), { status: 200 }),
    );
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users.filter((u) => u.id !== 2)), { status: 200 }),
    );

    renderTable();
    await waitFor(() => screen.getByText('alice'));

    const deleteButtons = screen.getAllByRole('button', { name: /delete/i });
    // Click Bob's delete (not disabled)
    const bobDeleteBtn = deleteButtons.find((btn) => btn.closest('tr')?.textContent?.includes('b@b.com'));
    await userEvent.click(bobDeleteBtn!);

    await waitFor(() =>
      expect(mockFetch).toHaveBeenCalledWith(
        expect.stringContaining('users.php?id=2'),
        expect.objectContaining({ method: 'DELETE' }),
      ),
    );
  });

  it('calls fetch with PUT when Approve clicked', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users), { status: 200 }),
    );

    renderTable();
    await waitFor(() => screen.getByText('Alice'));

    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'User updated.' }), { status: 200 }),
    );
    // Reload after approve
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify(users.map((u) => u.id === 1 ? { ...u, status: 'validated' } : u)), { status: 200 }),
    );

    await userEvent.click(screen.getByRole('button', { name: /approve/i }));

    await waitFor(() => expect(mockFetch).toHaveBeenCalledWith(
      expect.stringContaining('users.php?id=1'),
      expect.objectContaining({ method: 'PUT' }),
    ));
  });
});
