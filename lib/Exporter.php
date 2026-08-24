<?php

declare(strict_types=1);

/**
 * Builds a ZIP archive of all submitted photos grouped by photographer.
 *
 * ZIP structure:
 *   {username}/
 *     {filename}
 *     descriptions.txt   ← "filename: description\n" per photo, posted_at ASC
 *
 * Missing files on disk are noted in descriptions.txt and silently skipped.
 */
final class Exporter
{
    /**
     * Build the ZIP archive and return the path to a temporary file.
     *
     * Caller is responsible for unlinking the file after streaming.
     *
     * @throws RuntimeException  If ZipArchive extension is unavailable.
     * @return string  Absolute path to the temp ZIP file.
     */
    public function buildZip(): string
    {
        if (!extension_loaded('zip')) {
            throw new RuntimeException('ZipArchive extension is not available.');
        }

        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'aday_export_' . bin2hex(random_bytes(8)) . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Failed to create ZIP archive.');
        }

        $photographers = $this->queryPhotographersWithPhotos();

        if ($photographers === []) {
            // ZipArchive does not write the file if no entries are added (Windows behaviour).
            // Write a minimal valid empty ZIP (22-byte end-of-central-directory record).
            $zip->close();
            file_put_contents($tempPath, "PK\x05\x06" . str_repeat("\x00", 18));
            return $tempPath;
        }

        foreach ($photographers as $username) {
            $photos = $this->queryPhotosByUser($username);
            if ($photos === []) {
                continue;
            }

            $descriptions = '';

            foreach ($photos as $photo) {
                $relativePath = "uploads/{$username}/{$photo['filename']}";
                $absPath      = dirname(__DIR__) . "/{$relativePath}";
                $zipPath      = "{$username}/{$photo['filename']}";

                if (file_exists($absPath)) {
                    $zip->addFile($absPath, $zipPath);
                    $descriptions .= "{$photo['filename']}: {$photo['description']}\n";
                } else {
                    error_log("[Exporter] Missing file on disk: {$absPath}");
                    $descriptions .= "{$photo['filename']}: [file missing] {$photo['description']}\n";
                }
            }

            $zip->addFromString("{$username}/descriptions.txt", $descriptions);
        }

        $zip->close();

        return $tempPath;
    }

    /**
     * Return usernames of all validated photographers who have at least one photo, A–Z.
     *
     * @return string[]
     */
    private function queryPhotographersWithPhotos(): array
    {
        $stmt = db()->query(
            'SELECT DISTINCT u.username
             FROM photos p
             JOIN users u ON u.id = p.user_id
             ORDER BY u.username ASC'
        );
        $rows = $stmt->fetchAll();
        return array_column($rows, 'username');
    }

    /**
     * Return all photos for a given username, ordered by posted_at ASC.
     *
     * @return array<int, array<string, mixed>>
     */
    private function queryPhotosByUser(string $username): array
    {
        $stmt = db()->prepare(
            'SELECT p.filename, p.description, p.posted_at
             FROM photos p
             JOIN users u ON u.id = p.user_id
             WHERE u.username = :username
             ORDER BY p.posted_at ASC, p.id ASC'
        );
        $stmt->execute([':username' => $username]);
        return $stmt->fetchAll();
    }
}
