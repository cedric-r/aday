import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { UserModal } from './UserModal';
import type { AdminUser } from '@/schemas/admin.schema';

const mockFetch = vi.fn<typeof fetch>();

const existingUser: AdminUser = {
  id: 1,
  username: 'alice',
  name: 'Alice Example',
  email: 'alice@example.com',
  timezone: 'UTC',
  status: 'validated',
  is_admin: false,
  created_at: '2026-01-01',
};

describe('UserModal', () => {
  beforeEach(() => {
    mockFetch.mockReset();
    vi.stubGlobal('fetch', mockFetch);
  });

  it('Add mode: shows username and password fields', () => {
    render(<UserModal mode="add" onClose={vi.fn()} onSaved={vi.fn()} />);
    expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument();
  });

  it('Edit mode: hides username and password fields', () => {
    render(<UserModal mode="edit" user={existingUser} onClose={vi.fn()} onSaved={vi.fn()} />);
    expect(screen.queryByLabelText(/^username$/i)).not.toBeInTheDocument();
    expect(screen.queryByLabelText(/^password$/i)).not.toBeInTheDocument();
  });

  it('Add mode: submits POST with correct payload', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'User created.' }), { status: 201 }),
    );

    const onSaved = vi.fn();
    render(<UserModal mode="add" onClose={vi.fn()} onSaved={onSaved} />);

    await userEvent.type(screen.getByLabelText(/username/i), 'newuser');
    await userEvent.type(screen.getByLabelText(/display name/i), 'New User');
    await userEvent.type(screen.getByLabelText(/email/i), 'new@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'secret123');

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/admin/users.php',
        expect.objectContaining({ method: 'POST' }),
      );
    });
    await waitFor(() => expect(onSaved).toHaveBeenCalled());
  });

  it('Edit mode: submits PUT with correct URL', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'User updated.' }), { status: 200 }),
    );

    const onSaved = vi.fn();
    render(<UserModal mode="edit" user={existingUser} onClose={vi.fn()} onSaved={onSaved} />);

    await userEvent.clear(screen.getByLabelText(/display name/i));
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice Updated');

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/admin/users.php?id=1',
        expect.objectContaining({ method: 'PUT' }),
      );
    });
    await waitFor(() => expect(onSaved).toHaveBeenCalled());
  });

  it('shows error message when POST fails', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Username taken' }), { status: 409 }),
    );

    const onClose = vi.fn();
    const onSaved = vi.fn();

    render(<UserModal mode="add" onClose={onClose} onSaved={onSaved} />);

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() =>
      expect(screen.getByText(/failed to create user/i)).toBeInTheDocument(),
    );
    expect(onSaved).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
  });

  it('shows error message when PUT fails', async () => {
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ error: 'Server error' }), { status: 500 }),
    );

    const onClose = vi.fn();
    const onSaved = vi.fn();
    const user = { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', timezone: 'Europe/London', status: 'validated' as const, is_admin: false, created_at: '2026-01-01' };

    render(<UserModal mode="edit" user={user} onClose={onClose} onSaved={onSaved} />);

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() =>
      expect(screen.getByText(/failed to update user/i)).toBeInTheDocument(),
    );
    expect(onSaved).not.toHaveBeenCalled();
    expect(onClose).not.toHaveBeenCalled();
  });
});
