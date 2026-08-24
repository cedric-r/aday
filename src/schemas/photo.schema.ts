import { z } from 'zod';

export const PhotoSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  substack_url: z.string().nullable(),
  filename: z.string(),
  description: z.string(),
  posted_at: z.string(),
});

export const PhotoFeedResponseSchema = z.object({
  photos: z.array(PhotoSchema),
  next_cursor: z.string().nullable(),
});

export const PostPhotoResponseSchema = z.object({
  id: z.number().int(),
  filename: z.string(),
  posted_at: z.string(),
});

export const StatusResponseSchema = z.object({
  window_open: z.boolean().nullable(),
  event_date: z.string().nullable(),
  message: z.string(),
});

export type Photo = z.infer<typeof PhotoSchema>;
export type PhotoFeedResponse = z.infer<typeof PhotoFeedResponseSchema>;
export type StatusResponse = z.infer<typeof StatusResponseSchema>;
