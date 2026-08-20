<?php

declare(strict_types=1);

/**
 * File upload handling per Tech Spec §12. Files land in
 * storage/uploads/receipts/ — outside the web root, so no direct URL can ever
 * reach them (serving happens later via an authenticated download controller).
 * Validated on MIME type (PDF/JPG/PNG only) and size (max 10MB), and always
 * renamed to a generated UUID so path traversal / overwrite attacks can't use
 * the client-supplied filename.
 */
class Upload
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10MB

    private const MIME_TO_EXT = [
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
    ];

    /**
     * Validate and store an uploaded receipt.
     *
     * @param array<string, mixed> $file   one entry from $_FILES
     * @return string|null relative path under UPLOADS_PATH (e.g. "receipts/uuid.pdf"),
     *                      or null when the field was empty
     * @throws InvalidArgumentException with a user-facing message on any rejection
     */
    public static function receipt(array $file): ?string
    {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The file could not be uploaded. Please try again.');
        }

        if ($file['size'] > self::MAX_BYTES) {
            throw new InvalidArgumentException('The file is too large. Maximum size is 10MB.');
        }

        $detected = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = self::MIME_TO_EXT[$detected] ?? null;
        if ($extension === null) {
            throw new InvalidArgumentException('Unsupported file type. Only PDF, JPG, and PNG are allowed.');
        }

        // Never trust the client filename — always a fresh UUID + our own extension.
        $uuid = bin2hex(random_bytes(16));
        $dir = UPLOADS_PATH . '/receipts';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $storedName = $uuid . '.' . $extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $storedName)) {
            throw new InvalidArgumentException('The file could not be stored. Please try again.');
        }

        return 'receipts/' . $storedName;
    }
}