<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/FileUpload.php (US-4).
 * Path A — uses temp files to test MIME-based validation.
 */
final class FileUploadTest extends TestCase
{
    /**
     * Create a fake $_FILES entry from a real temp file containing the given bytes.
     *
     * @param  string $content   Raw file bytes.
     * @param  int    $size      Reported file size (default: strlen($content)).
     * @return array<string, mixed>
     */
    private function makeFakeUpload(string $content, ?int $size = null): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aday_test_');
        file_put_contents($tmp, $content);

        return [
            'error'    => UPLOAD_ERR_OK,
            'size'     => $size ?? strlen($content),
            'tmp_name' => $tmp,
        ];
    }

    /** Minimal valid JPEG magic bytes */
    private function jpegBytes(): string
    {
        return "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16);
    }

    /** Minimal valid 1×1 PNG (base64-decoded) */
    private function pngBytes(): string
    {
        // 1×1 pixel transparent PNG — valid enough for finfo MIME detection.
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk'
            . '+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );
    }

    /** Minimal valid WebP bytes */
    private function webpBytes(): string
    {
        return "RIFF\x24\x00\x00\x00WEBPVP8 " . str_repeat("\x00", 16);
    }

    /** Minimal GIF bytes */
    private function gifBytes(): string
    {
        return "GIF89a" . str_repeat("\x00", 10);
    }

    // -----------------------------------------------------------------------

    public function test_valid_jpeg_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        FileUpload::validate($this->makeFakeUpload($this->jpegBytes()));
    }

    public function test_valid_png_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        FileUpload::validate($this->makeFakeUpload($this->pngBytes()));
    }

    public function test_valid_webp_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        FileUpload::validate($this->makeFakeUpload($this->webpBytes()));
    }

    public function test_gif_throws_upload_exception(): void
    {
        $this->expectException(UploadException::class);
        FileUpload::validate($this->makeFakeUpload($this->gifBytes()));
    }

    public function test_exactly_15mb_accepted(): void
    {
        $this->expectNotToPerformAssertions();
        $bytes = str_repeat("\xFF\xD8\xFF\xE0", 1) . str_repeat("\x00", 15 * 1024 * 1024 - 4);
        // Use size override to simulate exactly 15 MB without actually writing 15 MB.
        $entry = $this->makeFakeUpload($this->jpegBytes(), 15 * 1024 * 1024);
        FileUpload::validate($entry);
    }

    public function test_15mb_plus_one_byte_throws_upload_exception(): void
    {
        $this->expectException(UploadException::class);
        $entry = $this->makeFakeUpload($this->jpegBytes(), 15 * 1024 * 1024 + 1);
        FileUpload::validate($entry);
    }

    public function test_spoofed_extension_gif_mime_throws(): void
    {
        // GIF content saved as "photo.jpg" — validate() checks MIME, not extension.
        $entry = $this->makeFakeUpload($this->gifBytes());
        $this->expectException(UploadException::class);
        FileUpload::validate($entry);
    }

    public function test_upload_error_code_throws(): void
    {
        $entry = [
            'error'    => UPLOAD_ERR_INI_SIZE,
            'size'     => 0,
            'tmp_name' => '',
        ];
        $this->expectException(UploadException::class);
        FileUpload::validate($entry);
    }

    public function test_save_moves_file_to_uploads_directory(): void
    {
        $entry = $this->makeFakeUpload($this->jpegBytes());
        $filename = FileUpload::save($entry, 'testuser');

        $this->assertStringEndsWith('.jpg', $filename);
        $targetPath = dirname(__DIR__) . '/uploads/testuser/' . $filename;
        $this->assertFileExists($targetPath);

        // Cleanup
        unlink($targetPath);
    }
}
