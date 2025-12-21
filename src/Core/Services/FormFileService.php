<?php

declare(strict_types=1);

namespace BuraqForms\Core\Services;

// Load Logger class directly to avoid autoloading issues
require_once __DIR__ . '/../Logger.php';

use BuraqForms\Core\Cache\FileCache;
use BuraqForms\Core\Exceptions\FileStorageException;
use finfo;
use PDO;

/**
 * Handles form file validation and safe storage.
 */
class FormFileService
{
    private PDO $pdo;
    private SystemSettingsService $settings;
    private Logger $logger;

    private string $projectRoot;

    public function __construct(PDO $pdo, ?SystemSettingsService $settings = null, ?Logger $logger = null, ?FileCache $cache = null)
    {
        $this->pdo = $pdo;
        $this->settings = $settings ?? new SystemSettingsService($pdo, $cache);
        $this->logger = $logger ?? new Logger();
        $this->projectRoot = dirname(__DIR__, 3);
    }

    /**
     * @param array{name:string,type?:string,tmp_name:string,error:int,size:int} $file
     * @return array{path:string,size:int,original_name:string,stored_name:string,extension:string,mime_type:string}
     */
    public function storeUploadedFile(int $formId, int $fieldId, array $file): array
    {
        $validated = $this->validateUploadedFile($file);

        $relativeBase = $this->settings->getString('forms_upload_path', 'storage/forms/') ?? 'storage/forms/';
        $relativeBase = trim($relativeBase);
        $relativeBase = $relativeBase === '' ? 'storage/forms/' : $relativeBase;

        $relativeDir = rtrim(trim($relativeBase, '/'), '/') . '/' . $formId . '/' . $fieldId;
        $absoluteDir = $this->projectRoot . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new FileStorageException('Unable to create upload directory.');
        }

        $storedName = $this->generateStoredName($validated['extension']);
        $absolutePath = $absoluteDir . '/' . $storedName;

        $moved = false;
        if (is_uploaded_file($validated['tmp_name'])) {
            $moved = @move_uploaded_file($validated['tmp_name'], $absolutePath);
        }

        if (!$moved) {
            $moved = @rename($validated['tmp_name'], $absolutePath);
        }

        if (!$moved) {
            $moved = @copy($validated['tmp_name'], $absolutePath);
            if ($moved) {
                @unlink($validated['tmp_name']);
            }
        }

        if (!$moved) {
            throw new FileStorageException('Failed to store uploaded file.');
        }

        $relativePath = $relativeDir . '/' . $storedName;

        $this->logger->info('File uploaded', [
            'form_id' => $formId,
            'field_id' => $fieldId,
            'path' => $relativePath,
            'size' => $validated['size'],
        ]);

        return [
            'path' => $relativePath,
            'size' => $validated['size'],
            'original_name' => $validated['original_name'],
            'stored_name' => $storedName,
            'extension' => $validated['extension'],
            'mime_type' => $validated['mime_type'],
        ];
    }

    public function deleteStoredFile(string $relativePath): void
    {
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '') {
            return;
        }

        $absolutePath = $this->projectRoot . '/' . $relativePath;
        $realRoot = realpath($this->projectRoot);
        $realFile = realpath($absolutePath);

        if ($realRoot === false) {
            throw new FileStorageException('Project root could not be resolved.');
        }

        if ($realFile === false || !str_starts_with($realFile, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new FileStorageException('Refusing to delete file outside project root.');
        }

        if (is_file($realFile)) {
            @unlink($realFile);
            $this->logger->info('File deleted', ['path' => $relativePath]);
        }
    }

    /**
     * @param array{name:string,type?:string,tmp_name:string,error:int,size:int} $file
     * @return array{tmp_name:string,original_name:string,extension:string,mime_type:string,size:int}
     */
    public function validateUploadedFile(array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new FileStorageException('Upload error code: ' . $error);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new FileStorageException('Temporary uploaded file is missing.');
        }

        $original = (string) ($file['name'] ?? '');
        if ($original === '') {
            throw new FileStorageException('Original file name is missing.');
        }

        $size = (int) ($file['size'] ?? 0);
        $maxMb = $this->settings->getInt('forms_max_upload_mb', 10);
        $maxBytes = $maxMb * 1024 * 1024;

        if ($size <= 0) {
            throw new FileStorageException('Uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new FileStorageException('Uploaded file exceeds maximum size of ' . $maxMb . ' MB.');
        }

        $extension = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: '';

        if ($extension === '') {
            throw new FileStorageException('Unable to determine file extension.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $mime = is_string($mime) ? $mime : 'application/octet-stream';

        $allowed = array_values(array_filter(array_map('strtolower', $this->settings->getJsonList('forms_allowed_mime', []))));
        if ($allowed !== []) {
            $allowedMimes = array_values(array_filter($allowed, static fn (string $v): bool => str_contains($v, '/')));
            $allowedExts = array_values(array_filter($allowed, static fn (string $v): bool => !str_contains($v, '/')));

            $mimeLc = strtolower($mime);

            $ok = false;
            if ($allowedMimes !== [] && in_array($mimeLc, $allowedMimes, true)) {
                $ok = true;
            }
            if ($allowedExts !== [] && in_array($extension, $allowedExts, true)) {
                $ok = true;
            }

            // If the setting only contains one type of values, enforce that list.
            if ($allowedMimes !== [] && $allowedExts === [] && !in_array($mimeLc, $allowedMimes, true)) {
                $ok = false;
            }
            if ($allowedExts !== [] && $allowedMimes === [] && !in_array($extension, $allowedExts, true)) {
                $ok = false;
            }

            if (!$ok) {
                throw new FileStorageException('File type not allowed: ' . $mime . ' (' . $extension . ')');
            }
        }

        return [
            'tmp_name' => $tmp,
            'original_name' => $original,
            'extension' => $extension,
            'mime_type' => $mime,
            'size' => $size,
        ];
    }

    private function generateStoredName(string $extension): string
    {
        $safeExt = preg_replace('/[^a-z0-9]+/i', '', strtolower($extension)) ?: 'bin';
        return sprintf('file_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(8)), $safeExt);
    }
}
