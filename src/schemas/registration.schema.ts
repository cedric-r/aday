import { z } from 'zod';

export const CaptchaQuestionSchema = z.object({
  index: z.number().int().min(0).max(49),
  question: z.string(),
});

export const RegisterFormSchema = z.object({
  username: z.string().min(3).max(30).regex(/^[a-zA-Z0-9_]+$/, 'Alphanumeric and underscores only'),
  name: z.string().min(1),
  substack_url: z.string().url().or(z.literal('')),
  password: z.string().min(8),
  email: z.string().email(),
  timezone: z.string().min(1),
  captcha_answer: z.string().min(1),
});

export const RegisterResponseSchema = z.object({
  message: z.string(),
});

export const RegisterErrorSchema = z.object({
  error: z.string(),
  field: z.string().optional(),
});

export type CaptchaQuestion = z.infer<typeof CaptchaQuestionSchema>;
export type RegisterFormValues = z.infer<typeof RegisterFormSchema>;
