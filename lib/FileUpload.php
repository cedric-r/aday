<?php

declare(strict_types=1);

/**
 * Handles file upload validation and storage for photo submissions.
 *
 * All public methods are static so they can be called without instantiation.
 * File system operations (mkdir, move_uploaded_file) are real in production;
 * tests use temp files to exercise the validate() path without HTTP context.
 */
final class FileUpload
{
    private const MAX_BYTES = 15 * 1024 * 1024; // 15 MB

    /** @var array<string, string> MIME type → file extension */
    private const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Validate an entry from $_FILES.
     *
     * @param  array<string, mixed> $fileEntry  Single entry from $_FILES['photo'].
     * @throws UploadException  On any validation failure.
     */
    public static function validate(array $fileEntry): void
    {
        if ((int) ($fileEntry['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UploadException('File upload failed (error code: ' . ($fileEntry['error'] ?? '?') . ').');
        }

        if ((int) ($fileEntry['size'] ?? 0) > self::MAX_BYTES) {
            throw new UploadException('File exceeds the 15 MB size limit.');
        }

        $mime = self::mimeFromFile((string) ($fileEntry['tmp_name'] ?? ''));
        if (!array_key_exists($mime, self::ALLOWED_MIMES)) {
            throw new UploadException("File type '{$mime}' is not allowed. Upload JPEG, PNG, or WEBP.");
        }
    }

    /**
     * Move the uploaded file into the per-user uploads directory.
     *
     * @param  array<string, mixed> $fileEntry  Single entry from $_FILES['photo'].
     * @param  string               $username   Photographer's username (used as subfolder).
     * @return string  The generated filename (e.g. "abc123.jpg").
     * @throws UploadException  If the file cannot be moved.
     */
    public static function save(array $fileEntry, string $username): string
    {
        $mime      = self::mimeFromFile((string) ($fileEntry['tmp_name'] ?? ''));
        $ext       = self::ALLOWED_MIMES[$mime] ?? 'bin';
        $filename  = bin2hex(random_bytes(16)) . '.' . $ext;
        $targetDir = dirname(__DIR__) . '/uploads/' . $username;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $target = $targetDir . '/' . $filename;

        // In production: move_uploaded_file enforces the upload security model.
        // In tests (no real HTTP upload): fall back to rename() for temp files.
        $moved = is_uploaded_file((string) ($fileEntry['tmp_name'] ?? ''))
            ? move_uploaded_file((string) $fileEntry['tmp_name'], $target)
            : rename((string) $fileEntry['tmp_name'], $target);

        if (!$moved) {
            throw new UploadException('Failed to save uploaded file.');
        }

        return $filename;
    }

    /**
     * Detect the MIME type of a file using finfo (reads magic bytes, not extension).
     */
    private static function mimeFromFile(string $path): string
    {
        if ($path === '' || !file_exists($path)) {
            return 'application/octet-stream';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mime === false ? 'application/octet-stream' : $mime;
    }
}
