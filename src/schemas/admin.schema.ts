import { z } from 'zod';

export const AdminUserSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  email: z.string(),
  substack_url: z.string().nullable().optional(),
  bio: z.string().nullable().optional(),
  timezone: z.string(),
  status: z.enum(['pending', 'validated']),
  // API returns 0/1 integers — coerce to boolean
  is_admin: z.union([z.literal(0), z.literal(1)]).transform(Boolean),
  created_at: z.string(),
});

export const AdminUserListSchema = z.array(AdminUserSchema);

export const AdminSettingsSchema = z.object({
  event_date: z.string().nullable(),
  allow_late_submissions: z.boolean().optional(),
});

export const AdminSettingsSaveResponseSchema = z.object({
  message: z.string(),
  event_date: z.string(),
  allow_late_submissions: z.boolean().optional(),
});

export const SubmissionSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  filename: z.string(),
  description: z.string(),
  posted_at: z.string(),
  highlight: z.union([z.literal(0), z.literal(1)]).transform(Boolean).optional(),
  hidden: z.union([z.literal(0), z.literal(1)]).transform(Boolean).optional(),
  thumb_url: z.string().nullable().optional(),
});

export const SubmissionListSchema = z.array(SubmissionSchema);

export const AdminMessageResponseSchema = z.object({
  message: z.string(),
});

export const AdminValidateResponseSchema = z.object({
  message: z.string(),
  username: z.string(),
});

export type AdminUser = z.infer<typeof AdminUserSchema>;
export type Submission = z.infer<typeof SubmissionSchema>;
