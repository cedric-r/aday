import { useCallback, useEffect, useState } from 'react';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';
import Link from '@mui/material/Link';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import { AdminUserListSchema } from '@/schemas/admin.schema';
import type { AdminUser } from '@/schemas/admin.schema';
import { useAuth } from '@/context/AuthContext';

interface Props {
  onEdit: (user: AdminUser) => void;
}

export const UserTable = ({ onEdit }: Props) => {
  const { user: currentUser } = useAuth();
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);

  const loadUsers = useCallback(async () => {
    try {
      const res = await fetch('/api/admin/users.php');
      const raw: unknown = await res.json();
      setUsers(AdminUserListSchema.parse(raw));
    } catch {
      setError('Failed to load users.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => { void loadUsers(); }, [loadUsers]);

  const handleApprove = async (user: AdminUser) => {
    setActionError(null);
    const res = await fetch(`/api/admin/users.php?id=${user.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ status: 'validated' }),
    });
    if (!res.ok) {
      setActionError('Failed to approve user. Please try again.');
      return;
    }
    void loadUsers();
  };

  const handleDelete = async (user: AdminUser) => {
    if (!globalThis.confirm(`Delete user "${user.username}"?`)) return;
    setActionError(null);
    const res = await fetch(`/api/admin/users.php?id=${user.id}`, { method: 'DELETE' });
    if (!res.ok) {
      setActionError('Failed to delete user. Please try again.');
      return;
    }
    void loadUsers();
  };

  if (isLoading) return <Box display="flex" justifyContent="center" mt={2}><CircularProgress /></Box>;
  if (error) return <Typography color="error">{error}</Typography>;

  return (
    <Box>
      {actionError && (
        <Alert severity="error" sx={{ mb: 2 }}>{actionError}</Alert>
      )}
      <Table size="small">
      <TableHead>
        <TableRow>
          <TableCell>Username</TableCell>
          <TableCell>Name</TableCell>
          <TableCell>Email</TableCell>
          <TableCell>Substack URL</TableCell>
          <TableCell>Timezone</TableCell>
          <TableCell>Status</TableCell>
          <TableCell>Admin</TableCell>
          <TableCell>Actions</TableCell>
        </TableRow>
      </TableHead>
      <TableBody>
        {users.map((user) => {
          const isSelf = user.username === currentUser?.username;
          return (
            <TableRow key={user.id}>
              <TableCell>{user.username}</TableCell>
              <TableCell>{user.name}</TableCell>
              <TableCell>{user.email}</TableCell>
              <TableCell>
                {user.substack_url ? (
                  <Link
                    href={user.substack_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    title={user.substack_url}
                    underline="hover"
                    sx={{ fontSize: 'inherit' }}
                  >
                    {user.substack_url.replace(/^https?:\/\//, '')}
                  </Link>
                ) : (
                  '—'
                )}
              </TableCell>
              <TableCell>{user.timezone}</TableCell>
              <TableCell>
                <Chip
                  label={user.status}
                  color={user.status === 'validated' ? 'success' : 'warning'}
                  size="small"
                />
              </TableCell>
              <TableCell>{user.is_admin ? 'Yes' : 'No'}</TableCell>
              <TableCell sx={{ display: 'flex', gap: 1 }}>
                {user.status === 'pending' && (
                  <Button
                    size="small"
                    variant="outlined"
                    color="success"
                    onClick={() => { void handleApprove(user); }}
                  >
                    Approve
                  </Button>
                )}
                <Button
                  size="small"
                  variant="outlined"
                  onClick={() => onEdit(user)}
                >
                  Edit
                </Button>
                <Button
                  size="small"
                  variant="outlined"
                  color="error"
                  disabled={isSelf}
                  onClick={() => { void handleDelete(user); }}
                >
                  Delete
                </Button>
              </TableCell>
            </TableRow>
          );
        })}
      </TableBody>
    </Table>
    </Box>
  );
};
