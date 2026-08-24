import { useEffect } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Dialog from '@mui/material/Dialog';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContent from '@mui/material/DialogContent';
import DialogActions from '@mui/material/DialogActions';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import Box from '@mui/material/Box';
import { TimezoneSelect } from '@/components/TimezoneSelect';
import type { AdminUser } from '@/schemas/admin.schema';

const AddSchema = z.object({
  username: z.string().min(3).max(30).regex(/^[a-zA-Z0-9_]+$/),
  name: z.string().min(1),
  email: z.string().email(),
  substack_url: z.string().optional(),
  password: z.string().min(8),
  timezone: z.string().min(1),
  is_admin: z.boolean(),
});

const EditSchema = z.object({
  name: z.string().min(1),
  email: z.string().email(),
  substack_url: z.string().optional(),
  timezone: z.string().min(1),
  status: z.enum(['pending', 'validated']),
  is_admin: z.boolean(),
});

type AddValues = z.infer<typeof AddSchema>;
type EditValues = z.infer<typeof EditSchema>;

interface AddProps {
  mode: 'add';
  user?: undefined;
  onClose: () => void;
  onSaved: () => void;
}

interface EditProps {
  mode: 'edit';
  user: AdminUser;
  onClose: () => void;
  onSaved: () => void;
}

type Props = AddProps | EditProps;

export const UserModal = ({ mode, user, onClose, onSaved }: Props) => {
  const defaultTz = Intl.DateTimeFormat().resolvedOptions().timeZone;

  const addForm = useForm<AddValues>({
    resolver: zodResolver(AddSchema),
    defaultValues: { timezone: defaultTz, is_admin: false },
  });

  const editForm = useForm<EditValues>({
    resolver: zodResolver(EditSchema),
    defaultValues: {
      name: user?.name ?? '',
      email: user?.email ?? '',
      timezone: user?.timezone ?? defaultTz,
      status: user?.status ?? 'pending',
      is_admin: user?.is_admin ?? false,
    },
  });

  useEffect(() => {
    if (mode === 'edit' && user) {
      editForm.reset({
        name: user.name,
        email: user.email,
        timezone: user.timezone,
        status: user.status,
        is_admin: user.is_admin,
      });
    }
  }, [mode, user, editForm]);

  const onSubmitAdd = async (values: AddValues) => {
    await fetch('/api/admin/users.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...values, is_admin: values.is_admin ? 1 : 0 }),
    });
    onSaved();
    onClose();
  };

  const onSubmitEdit = async (values: EditValues) => {
    await fetch(`/api/admin/users.php?id=${user!.id}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...values, is_admin: values.is_admin ? 1 : 0 }),
    });
    onSaved();
    onClose();
  };

  const isAdd = mode === 'add';

  return (
    <Dialog open onClose={onClose} maxWidth="sm" fullWidth>
      <DialogTitle>{isAdd ? 'Add user' : 'Edit user'}</DialogTitle>
      <DialogContent>
        {isAdd ? (
          <Box
            component="form"
            id="user-modal-form"
            onSubmit={addForm.handleSubmit(onSubmitAdd)}
            sx={{ display: 'flex', flexDirection: 'column', gap: 2, pt: 1 }}
          >
            <TextField
              {...addForm.register('username')}
              label="Username"
              id="modal-username"
              required
              error={!!addForm.formState.errors.username}
              helperText={addForm.formState.errors.username?.message}
              inputProps={{ 'aria-label': 'Username' }}
              InputLabelProps={{ htmlFor: 'modal-username' }}
            />
            <TextField
              {...addForm.register('name')}
              label="Display name"
              id="modal-name-add"
              required
              inputProps={{ 'aria-label': 'Display name' }}
              InputLabelProps={{ htmlFor: 'modal-name-add' }}
            />
            <TextField
              {...addForm.register('email')}
              label="Email"
              id="modal-email-add"
              type="email"
              required
              inputProps={{ 'aria-label': 'Email' }}
              InputLabelProps={{ htmlFor: 'modal-email-add' }}
            />
            <TextField
              {...addForm.register('password')}
              label="Password"
              id="modal-password"
              type="password"
              required
              inputProps={{ 'aria-label': 'Password' }}
              InputLabelProps={{ htmlFor: 'modal-password' }}
            />
            <Controller
              name="timezone"
              control={addForm.control}
              render={({ field }) => (
                <TimezoneSelect
                  id="modal-tz-add"
                  value={field.value}
                  onChange={field.onChange}
                />
              )}
            />
            <Controller
              name="is_admin"
              control={addForm.control}
              render={({ field }) => (
                <FormControlLabel
                  control={<Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)} />}
                  label="Admin"
                />
              )}
            />
          </Box>
        ) : (
          <Box
            component="form"
            id="user-modal-form"
            onSubmit={editForm.handleSubmit(onSubmitEdit)}
            sx={{ display: 'flex', flexDirection: 'column', gap: 2, pt: 1 }}
          >
            <TextField
              {...editForm.register('name')}
              label="Display name"
              id="modal-name-edit"
              required
              inputProps={{ 'aria-label': 'Display name' }}
              InputLabelProps={{ htmlFor: 'modal-name-edit' }}
            />
            <TextField
              {...editForm.register('email')}
              label="Email"
              id="modal-email-edit"
              type="email"
              required
              inputProps={{ 'aria-label': 'Email' }}
              InputLabelProps={{ htmlFor: 'modal-email-edit' }}
            />
            <Controller
              name="timezone"
              control={editForm.control}
              render={({ field }) => (
                <TimezoneSelect
                  id="modal-tz-edit"
                  value={field.value}
                  onChange={field.onChange}
                />
              )}
            />
            <Controller
              name="is_admin"
              control={editForm.control}
              render={({ field }) => (
                <FormControlLabel
                  control={<Checkbox checked={field.value} onChange={(e) => field.onChange(e.target.checked)} />}
                  label="Admin"
                />
              )}
            />
          </Box>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={onClose}>Cancel</Button>
        <Button type="submit" form="user-modal-form" variant="contained">
          Save
        </Button>
      </DialogActions>
    </Dialog>
  );
};
