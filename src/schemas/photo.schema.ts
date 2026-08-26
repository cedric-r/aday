import { z } from 'zod';

export const PhotoSchema = z.object({
  id: z.number().int(),
  username: z.string(),
  name: z.string(),
  substack_url: z.string().nullable(),
  filename: z.string(),
  description: z.string(),
  posted_at: z.string(),
  // 0/1 integer from SQLite — coerce to boolean
  highlight: z.union([z.literal(0), z.literal(1)]).transform(Boolean).optional(),
  gear: z.string().nullable().optional(),
  exif_make: z.string().nullable().optional(),
  exif_model: z.string().nullable().optional(),
  exif_focal: z.string().nullable().optional(),
  exif_aperture: z.string().nullable().optional(),
  exif_shutter: z.string().nullable().optional(),
  exif_iso: z.string().nullable().optional(),
  thumb_url: z.string().nullable().optional(),
  local_time: z.string().optional(),
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
  allow_late_submissions: z.boolean().optional(),
  message: z.string(),
});

export const StatsResponseSchema = z.object({
  total: z.number().int(),
  photographers: z.number().int().optional(),
  timezones: z.number().int().optional(),
  by_hour: z.array(
    z.object({
      hour: z.string(),
      count: z.number().int(),
    }),
  ),
});

export type StatsResponse = z.infer<typeof StatsResponseSchema>;

export type Photo = z.infer<typeof PhotoSchema>;
export type PhotoFeedResponse = z.infer<typeof PhotoFeedResponseSchema>;
export type StatusResponse = z.infer<typeof StatusResponseSchema>;
