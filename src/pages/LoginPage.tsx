import { useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import { useAuth } from '@/context/AuthContext';

const LoginFormSchema = z.object({
  username: z.string().min(1, 'Username is required'),
  password: z.string().min(1, 'Password is required'),
});

type LoginFormValues = z.infer<typeof LoginFormSchema>;

export const LoginPage = () => {
  const { login } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const from = (location.state as { from?: string } | null)?.from ?? '/';

  const [apiError, setApiError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(LoginFormSchema),
  });

  const onSubmit = async (values: LoginFormValues) => {
    setApiError(null);
    try {
      await login(values.username, values.password);
      navigate(from, { replace: true });
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Login failed';
      if (/pending/i.test(message)) {
        setApiError('Your account is pending approval.');
      } else {
        setApiError('Invalid username or password.');
      }
    }
  };

  return (
    <Box
      component="main"
      sx={{ maxWidth: 400, mx: 'auto', mt: 8, px: 2 }}
    >
      <Typography variant="h4" component="h1" gutterBottom>
        Log in
      </Typography>

      {apiError && (
        <Alert severity="error" sx={{ mb: 2 }}>
          {apiError}
        </Alert>
      )}

      <Box
        component="form"
        onSubmit={handleSubmit(onSubmit)}
        noValidate
        sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}
      >
        <TextField
          {...register('username')}
          label="Username"
          id="username"
          autoComplete="username"
          error={!!errors.username}
          helperText={errors.username?.message}
          inputProps={{ 'aria-label': 'Username' }}
          InputLabelProps={{ htmlFor: 'username' }}
        />
        <TextField
          {...register('password')}
          label="Password"
          id="password"
          type="password"
          autoComplete="current-password"
          error={!!errors.password}
          helperText={errors.password?.message}
          inputProps={{ 'aria-label': 'Password' }}
          InputLabelProps={{ htmlFor: 'password' }}
        />
        <Button
          type="submit"
          variant="contained"
          disabled={isSubmitting}
        >
          Log in
        </Button>
      </Box>
    </Box>
  );
};
