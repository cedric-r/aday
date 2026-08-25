<?php

declare(strict_types=1);

/**
 * Camera EXIF extraction for uploaded images.
 *
 * Reads the standard EXIF blocks (IFD0 + EXIF + COMPUTED) from a JPEG and
 * returns a small, safe subset: make/model, focal length, aperture, shutter,
 * ISO. Values are raw strings from the file — not trusted input, so the UI
 * must escape/truncate where it displays them. If exif_read_data is missing
 * or the file has no EXIF, an empty array is returned.
 */
final class Exif
{
    /**
     * @return array{
     *   make: ?string, model: ?string, focal: ?string,
     *   aperture: ?string, shutter: ?string, iso: ?string
     * }
     */
    public static function extract(string $path): array
    {
        $empty = [
            'make'     => null,
            'model'    => null,
            'focal'    => null,
            'aperture' => null,
            'shutter'  => null,
            'iso'      => null,
        ];

        if (!function_exists('exif_read_data') || !is_file($path)) {
            return $empty;
        }

        $data = @exif_read_data($path, 'EXIF', true);
        if ($data === false) {
            return $empty;
        }

        $exif   = $data['EXIF'] ?? [];
        $computed = $data['COMPUTED'] ?? [];

        return [
            'make'     => self::clean($data['Make'] ?? null),
            'model'    => self::clean($data['Model'] ?? null),
            'focal'    => self::clean($exif['FocalLength'] ?? null),
            'aperture' => self::clean($computed['ApertureFNumber'] ?? null),
            'shutter'  => self::clean($exif['ExposureTime'] ?? null),
            'iso'      => self::clean($exif['ISOSpeedRatings'] ?? null),
        ];
    }

    private static function clean(mixed $value): ?string
    {
        $s = trim((string) $value);
        return $s === '' ? null : mb_substr($s, 0, 64);
    }
}