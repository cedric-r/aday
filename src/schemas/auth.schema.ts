import { z } from 'zod';

export const MeAuthenticatedSchema = z.object({
  authenticated: z.literal(true),
  username: z.string(),
  name: z.string(),
  is_admin: z.boolean(),
  status: z.string(),
  timezone: z.string(),
});

export const MeUnauthenticatedSchema = z.object({
  authenticated: z.literal(false),
});

export const MeResponseSchema = z.discriminatedUnion('authenticated', [
  MeAuthenticatedSchema,
  MeUnauthenticatedSchema,
]);

export const LoginResponseSchema = z.object({
  username: z.string(),
  name: z.string(),
  is_admin: z.boolean(),
  status: z.string(),
  timezone: z.string(),
});

export const LogoutResponseSchema = z.object({
  message: z.string(),
});

export const ApiErrorSchema = z.object({
  error: z.string(),
});

export type MeResponse = z.infer<typeof MeResponseSchema>;
export type AuthUser = z.infer<typeof MeAuthenticatedSchema>;
export type LoginResponse = z.infer<typeof LoginResponseSchema>;
