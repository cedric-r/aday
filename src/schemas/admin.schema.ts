import { z } from 'zod';

export const AdminUserSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  email: z.string(),
  timezone: z.string(),
  status: z.enum(['pending', 'validated']),
  is_admin: z.boolean(),
  created_at: z.string(),
});

export const AdminUserListSchema = z.array(AdminUserSchema);

export const AdminSettingsSchema = z.object({
  event_date: z.string().nullable(),
});

export const SubmissionSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  filename: z.string(),
  description: z.string(),
  posted_at: z.string(),
});

export const SubmissionListSchema = z.array(SubmissionSchema);

export type AdminUser = z.infer<typeof AdminUserSchema>;
export type Submission = z.infer<typeof SubmissionSchema>;
