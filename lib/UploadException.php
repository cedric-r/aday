<?php

declare(strict_types=1);

/**
 * Thrown by FileUpload::validate() when an uploaded file is rejected.
 *
 * Reasons include: wrong MIME type, file too large, or upload error code != OK.
 */
final class UploadException extends RuntimeException {}
