import { useEffect, useState } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import FormControl from '@mui/material/FormControl';
import FormLabel from '@mui/material/FormLabel';
import FormHelperText from '@mui/material/FormHelperText';
import {
  CaptchaQuestionSchema,
  RegisterFormSchema,
  RegisterConflictErrorSchema,
  RegisterValidationErrorSchema,
} from '@/schemas/registration.schema';
import type { CaptchaQuestion, RegisterFormValues } from '@/schemas/registration.schema';
import { TimezoneSelect } from '@/components/TimezoneSelect';

type PageState = 'idle' | 'success' | 'closed';

export const RegisterPage = () => {
  const [captcha, setCaptcha] = useState<CaptchaQuestion | null>(null);
  const [pageState, setPageState] = useState<PageState>('idle');
  const [apiError, setApiError] = useState<string | null>(null);

  const defaultTz = Intl.DateTimeFormat().resolvedOptions().timeZone;

  const {
    register,
    handleSubmit,
    control,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(RegisterFormSchema),
    defaultValues: { timezone: defaultTz },
  });

  useEffect(() => {
    const fetchCaptcha = async () => {
      try {
        const res = await fetch('/api/captcha-question.php');
        const raw: unknown = await res.json();
        const parsed = CaptchaQuestionSchema.parse(raw);
        setCaptcha(parsed);
      } catch {
        setApiError('Failed to load captcha. Please refresh.');
      }
    };
    void fetchCaptcha();
  }, []);

  const onSubmit = async (values: RegisterFormValues) => {
    if (!captcha) return;
    setApiError(null);

    const payload = { ...values, captcha_index: captcha.index };

    const res = await fetch('/api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (res.status === 201) {
      setPageState('success');
      return;
    }

    if (res.status === 423) {
      setPageState('closed');
      return;
    }

    const raw: unknown = await res.json();

    if (res.status === 409) {
      const parsed = RegisterConflictErrorSchema.safeParse(raw);
      if (parsed.success) {
        setError(parsed.data.field, { message: parsed.data.error });
      } else {
        setApiError('A conflict occurred. Please check your details.');
      }
      return;
    }

    if (res.status === 422) {
      const parsed = RegisterValidationErrorSchema.safeParse(raw);
      if (parsed.success) {
        for (const [field, message] of Object.entries(parsed.data.errors)) {
          setError(field as keyof RegisterFormValues, { message });
        }
      } else {
        setApiError('Validation failed. Please check your details.');
      }
      return;
    }

    setApiError('An unexpected error occurred. Please try again.');
  };

  if (pageState === 'success') {
    return (
      <Box sx={{ maxWidth: 500, mx: 'auto', mt: 8, px: 2 }}>
        <Alert severity="success">
          Registration submitted. Awaiting admin approval. Check your email.
        </Alert>
      </Box>
    );
  }

  if (pageState === 'closed') {
    return (
      <Box sx={{ maxWidth: 500, mx: 'auto', mt: 8, px: 2 }}>
        <Alert severity="warning">
          Registration is closed — today is the event day!
        </Alert>
      </Box>
    );
  }

  return (
    <Box component="main" sx={{ maxWidth: 500, mx: 'auto', mt: 6, px: 2 }}>
      <Typography variant="h4" component="h1" gutterBottom>
        Register
      </Typography>

      {apiError && <Alert severity="error" sx={{ mb: 2 }}>{apiError}</Alert>}

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
          required
          error={!!errors.username}
          helperText={errors.username?.message}
          inputProps={{ 'aria-label': 'Username' }}
          InputLabelProps={{ htmlFor: 'username' }}
        />

        <TextField
          {...register('name')}
          label="Display name"
          id="name"
          required
          error={!!errors.name}
          helperText={errors.name?.message}
          inputProps={{ 'aria-label': 'Display name' }}
          InputLabelProps={{ htmlFor: 'name' }}
        />

        <TextField
          {...register('email')}
          label="Email"
          id="email"
          type="email"
          required
          error={!!errors.email}
          helperText={errors.email?.message}
          inputProps={{ 'aria-label': 'Email' }}
          InputLabelProps={{ htmlFor: 'email' }}
        />

        <TextField
          {...register('substack_url')}
          label="Substack URL (optional)"
          id="substack_url"
          error={!!errors.substack_url}
          helperText={errors.substack_url?.message}
          inputProps={{ 'aria-label': 'Substack URL' }}
          InputLabelProps={{ htmlFor: 'substack_url' }}
        />

        <TextField
          {...register('password')}
          label="Password"
          id="password"
          type="password"
          required
          error={!!errors.password}
          helperText={errors.password?.message}
          inputProps={{ 'aria-label': 'Password' }}
          InputLabelProps={{ htmlFor: 'password' }}
        />

        <Controller
          name="timezone"
          control={control}
          render={({ field }) => (
            <FormControl error={!!errors.timezone}>
              <FormLabel htmlFor="timezone">Timezone</FormLabel>
              <TimezoneSelect
                id="timezone"
                value={field.value ?? defaultTz}
                onChange={field.onChange}
                required
              />
              {errors.timezone && (
                <FormHelperText>{errors.timezone.message}</FormHelperText>
              )}
            </FormControl>
          )}
        />

        {captcha && (
          <Box>
            <Typography variant="body2" sx={{ mb: 0.5 }}>
              {captcha.question}
            </Typography>
            <TextField
              {...register('captcha_answer')}
              label="Captcha answer"
              id="captcha_answer"
              required
              error={!!errors.captcha_answer}
              helperText={errors.captcha_answer?.message}
              inputProps={{ 'aria-label': 'Captcha answer' }}
              InputLabelProps={{ htmlFor: 'captcha_answer' }}
            />
          </Box>
        )}

        <Button
          type="submit"
          variant="contained"
          disabled={isSubmitting || !captcha}
        >
          Register
        </Button>
      </Box>
    </Box>
  );
};
