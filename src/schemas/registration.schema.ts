import { z } from 'zod';

export const CaptchaQuestionSchema = z.object({
  index: z.number().int().min(0).max(49),
  question: z.string(),
});

export const RegisterFormSchema = z.object({
  username: z.string().min(3, 'Min 3 characters').max(30, 'Max 30 characters').regex(/^[a-zA-Z0-9_]+$/, 'Alphanumeric and underscores only'),
  name: z.string().min(1, 'Name is required'),
  substack_url: z.string().url('Must be a valid URL').or(z.literal('')).optional(),
  password: z.string().min(8, 'Min 8 characters'),
  email: z.string().email('Must be a valid email'),
  timezone: z.string().min(1, 'Timezone is required'),
  captcha_answer: z.string().min(1, 'Answer is required'),
});

export const RegisterResponseSchema = z.object({
  message: z.string(),
});

// 422 validation errors envelope
export const RegisterValidationErrorSchema = z.object({
  errors: z.record(z.string(), z.string()),
});

// 409 conflict
export const RegisterConflictErrorSchema = z.object({
  field: z.enum(['username', 'email']),
  error: z.string(),
});

export type CaptchaQuestion = z.infer<typeof CaptchaQuestionSchema>;
export type RegisterFormValues = z.infer<typeof RegisterFormSchema>;
