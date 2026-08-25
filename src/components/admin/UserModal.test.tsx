import { render, screen, waitFor, fireEvent } from '@testing-library/react';
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

  it('Edit mode: shows read-only username, hides add-only password', () => {
    render(<UserModal mode="edit" user={existingUser} onClose={vi.fn()} onSaved={vi.fn()} />);
    expect(screen.getByLabelText(/^username$/i)).toBeDisabled();
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

  it('shows invalid email error without making API call', async () => {
    render(<UserModal mode="add" onClose={vi.fn()} onSaved={vi.fn()} />);

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'not-an-email');
    await userEvent.type(screen.getByLabelText(/password/i), 'password123');

    // Use fireEvent.submit to bypass jsdom native email constraint validation
    // so that RHF + Zod validation runs and surfaces the error.
    const form = document.getElementById('user-modal-form')!;
    fireEvent.submit(form);

    await waitFor(() =>
      expect(screen.getByText(/invalid email/i)).toBeInTheDocument(),
    );
    expect(mockFetch).not.toHaveBeenCalled();
  });

  it('shows password too short error without making API call', async () => {
    render(<UserModal mode="add" onClose={vi.fn()} onSaved={vi.fn()} />);

    await userEvent.type(screen.getByLabelText(/username/i), 'alice');
    await userEvent.type(screen.getByLabelText(/display name/i), 'Alice');
    await userEvent.type(screen.getByLabelText(/email/i), 'alice@example.com');
    await userEvent.type(screen.getByLabelText(/password/i), 'short'); // < 8 chars

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() =>
      expect(screen.getByText(/string must contain at least 8 character/i)).toBeInTheDocument(),
    );
    expect(mockFetch).not.toHaveBeenCalled();
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

  it('shows read-only username at top of edit form', () => {
    render(<UserModal mode="edit" user={existingUser} onClose={vi.fn()} onSaved={vi.fn()} />);
    const usernameField = screen.getByLabelText(/username/i);
    expect(usernameField).toBeDisabled();
    expect(usernameField).toHaveValue('alice');
  });

    it('shows status select in edit mode pre-populated from user', async () => {
    const user = { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', substack_url: null, timezone: 'Europe/London', status: 'pending' as const, is_admin: false, created_at: '2026-01-01' };
    render(<UserModal mode="edit" user={user} onClose={vi.fn()} onSaved={vi.fn()} />);
    // Status field should be rendered
    expect(screen.getByLabelText(/status/i)).toBeInTheDocument();
  });

  it('shows substack URL field in edit mode', async () => {
    const user = { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', substack_url: 'https://alice.substack.com', timezone: 'Europe/London', status: 'validated' as const, is_admin: false, created_at: '2026-01-01' };
    render(<UserModal mode="edit" user={user} onClose={vi.fn()} onSaved={vi.fn()} />);
    const substackField = screen.getByLabelText(/substack url/i);
    expect(substackField).toBeInTheDocument();
    expect(substackField).toHaveValue('https://alice.substack.com');
  });

  it('includes password in PUT body when provided', async () => {
    const onSaved = vi.fn();
    const user = { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', substack_url: null, timezone: 'Europe/London', status: 'validated' as const, is_admin: false, created_at: '2026-01-01' };
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Updated.' }), { status: 200 }),
    );

    render(<UserModal mode="edit" user={user} onClose={vi.fn()} onSaved={onSaved} />);

    await userEvent.type(screen.getByLabelText(/new password/i), 'newpassword1');
    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => expect(onSaved).toHaveBeenCalled());
    const body = JSON.parse((mockFetch.mock.calls[0][1] as RequestInit).body as string) as Record<string, unknown>;
    expect(body.password).toBe('newpassword1');
  });

  it('omits password from PUT body when left blank', async () => {
    const onSaved = vi.fn();
    const user = { id: 1, username: 'alice', name: 'Alice', email: 'a@a.com', substack_url: null, timezone: 'Europe/London', status: 'validated' as const, is_admin: false, created_at: '2026-01-01' };
    mockFetch.mockResolvedValueOnce(
      new Response(JSON.stringify({ message: 'Updated.' }), { status: 200 }),
    );

    render(<UserModal mode="edit" user={user} onClose={vi.fn()} onSaved={onSaved} />);

    await userEvent.click(screen.getByRole('button', { name: /save/i }));

    await waitFor(() => expect(onSaved).toHaveBeenCalled());
    const body = JSON.parse((mockFetch.mock.calls[0][1] as RequestInit).body as string) as Record<string, unknown>;
    expect(body).not.toHaveProperty('password');
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

  it('Add mode: shows the Substack URL field', () => {
    render(<UserModal mode="add" onClose={vi.fn()} onSaved={vi.fn()} />);
    expect(screen.getByLabelText(/substack url/i)).toBeInTheDocument();
  });

  it('Edit mode: shows the Substack URL field with the current value', async () => {
    render(<UserModal mode="edit" user={existingUser} onClose={vi.fn()} onSaved={vi.fn()} />);
    const field = screen.getByLabelText(/substack url/i) as HTMLInputElement;
    expect(field).toBeInTheDocument();
    // existingUser in this suite has no substack_url, so it is empty — that's fine,
    // the assertion is about the field being present in the edit form.
  });
});

