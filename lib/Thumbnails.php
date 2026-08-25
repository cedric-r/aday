<?php

declare(strict_types=1);

/**
 * Thumbnail generator for uploaded photos.
 *
 * Creates a square-cropped, max 320px thumbnail next to the original
 * (uploads/{username}/thumbs/{filename}) using GD. Best-effort: if GD is not
 * available or the image cannot be decoded, it silently does nothing — the app
 * falls back to the full-resolution file everywhere.
 */
final class Thumbnails
{
    public const THUMB_MAX = 320;

    /**
     * Generate a thumbnail for an uploaded image.
     *
     * @param  string $sourcePath  Full path to the original image.
     * @param  string $targetDir   Directory that will contain thumbs/ (the user uploads dir).
     * @param  string $filename    Base filename to use for the thumbnail.
     * @return bool  True when the thumbnail was written.
     */
    public static function generate(string $sourcePath, string $targetDir, string $filename): bool
    {
        if (!function_exists('imagecreatetruecolor') || !is_file($sourcePath)) {
            return false;
        }

        $sizes = @getimagesize($sourcePath);
        if ($sizes === false) {
            return false;
        }

        [$width, $height, $type] = $sizes;

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default        => false,
        };

        if (!$source) {
            return false;
        }

        $side  = min($width, $height);
        $scale = self::THUMB_MAX / $side;
        $tw    = (int) max(1, round($side * $scale));
        $thumb = imagecreatetruecolor($tw, $tw);
        if (!$thumb) {
            imagedestroy($source);
            return false;
        }

        $sx = (int) floor(($width - $side) / 2);
        $sy = (int) floor(($height - $side) / 2);

        imagecopyresampled($thumb, $source, 0, 0, $sx, $sy, $tw, $tw, $side, $side);

        $targetPath = $targetDir . '/thumbs/' . $filename;
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ok = imagejpeg($thumb, $targetPath, 82);

        imagedestroy($thumb);
        imagedestroy($source);

        return $ok;
    }
}