import { z } from 'zod';

export const MessageSchema = z.object({
  id: z.number().int(),
  subject: z.string(),
  body: z.string(),
  created_at: z.string(),
});

export const MessageListSchema = z.object({
  messages: z.array(MessageSchema),
});

export const AdminMessageListSchema = z.object({
  messages: z.array(MessageSchema),
  recipients: z.number().int(),
});

export const AdminMessageCreateSchema = z.object({
  message: z.string(),
  notification: MessageSchema,
  email: z
    .object({
      recipients: z.number().int(),
      sent: z.number().int(),
      failed: z.number().int(),
    })
    .optional(),
});

export const MessageFormSchema = z.object({
  subject: z.string().min(1, 'Subject is required').max(200, 'Max 200 characters'),
  body: z.string().min(1, 'Message is required').max(5000, 'Max 5000 characters'),
});

export type Message = z.infer<typeof MessageSchema>;
export type MessageForm = z.infer<typeof MessageFormSchema>;
