import { z } from 'zod';

const PhotoSummarySchema = z.object({
  id: z.number().int(),
  filename: z.string(),
  description: z.string(),
  posted_at: z.string(),
});

export const PhotographerSummarySchema = z.object({
  username: z.string(),
  name: z.string(),
  substack_url: z.string(),
  photo_count: z.number().int(),
});

export const PhotographerListSchema = z.array(PhotographerSummarySchema);

export const PhotographerDetailSchema = z.object({
  username: z.string(),
  name: z.string(),
  substack_url: z.string(),
  photos: z.array(PhotoSummarySchema),
});

export type PhotographerSummary = z.infer<typeof PhotographerSummarySchema>;
export type PhotographerDetail = z.infer<typeof PhotographerDetailSchema>;
