<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class PhoneFirmwareService
{
    public const MAX_UPLOAD_BYTES = 209715200; // 200 MB

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = [
        'z',
        'rom',
        'txt',
        'ld',
        'bin',
        'img',
        'tar',
        'gz',
        'zip',
        'fw',
    ];

    /** @var list<string> */
    private const DEFAULT_VENDOR_FOLDERS = [
        'intrade',
        'fanvil',
        'grandstream',
        'polycom',
        'yealink',
        'snom',
        'cisco',
        'algo',
        'avaya',
    ];

    public function rootPath(): string
    {
        return public_path('firmware');
    }

    public function ensureRoot(): void
    {
        $root = $this->rootPath();

        if (! File::isDirectory($root)) {
            File::makeDirectory($root, 0775, true);
        }

        $this->applyDirectoryPermissions($root);

        foreach (self::DEFAULT_VENDOR_FOLDERS as $vendor) {
            $vendorPath = $root . DIRECTORY_SEPARATOR . $vendor;
            if (! File::isDirectory($vendorPath)) {
                File::makeDirectory($vendorPath, 0775, true);
            }

            $this->applyDirectoryPermissions($vendorPath);
        }
    }

    private function applyDirectoryPermissions(string $path): void
    {
        @chmod($path, 0775);

        if (function_exists('posix_getgrnam')) {
            $group = posix_getgrnam('www-data');
            if ($group !== false) {
                @chgrp($path, 'www-data');
            }
        }
    }

    public function normalizeRelativePath(?string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', (string) $relativePath), '/');

        if ($relativePath === '') {
            return '';
        }

        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                throw new InvalidArgumentException('Invalid path.');
            }

            if ($segment === '..' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $segment)) {
                throw new InvalidArgumentException('Invalid path segment.');
            }
        }

        return $relativePath;
    }

    public function absolutePath(string $relativePath = ''): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $root = $this->rootPath();
        $absolutePath = $relativePath === ''
            ? $root
            : $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        $resolvedRoot = realpath($root) ?: $root;
        $resolvedPath = realpath($absolutePath);

        if ($resolvedPath === false) {
            $parent = dirname($absolutePath);
            $resolvedParent = realpath($parent);

            if ($resolvedParent === false || ! str_starts_with($resolvedParent . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('Path is outside the firmware directory.');
            }

            return $absolutePath;
        }

        if ($resolvedPath !== $resolvedRoot && ! str_starts_with($resolvedPath . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Path is outside the firmware directory.');
        }

        return $resolvedPath;
    }

  /**
     * @return array{
     *     path: string,
     *     public_url: string,
     *     breadcrumbs: list<array{name: string, path: string}>,
     *     items: list<array{
     *         name: string,
     *         path: string,
     *         type: string,
     *         size: int|null,
     *         modified_at: string|null
     *     }>
     * }
     */
    public function listDirectory(string $relativePath = '', string $publicBaseUrl = ''): array
    {
        $this->ensureRoot();
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->absolutePath($relativePath);

        if (! File::isDirectory($absolutePath)) {
            throw new InvalidArgumentException('Directory not found.');
        }

        $items = [];

        foreach (File::directories($absolutePath) as $directory) {
            $name = basename($directory);
            $itemPath = $relativePath === '' ? $name : $relativePath . '/' . $name;
            $items[] = [
                'name' => $name,
                'path' => $itemPath,
                'type' => 'directory',
                'size' => null,
                'modified_at' => $this->formatTimestamp(File::lastModified($directory)),
            ];
        }

        foreach (File::files($absolutePath) as $file) {
            $name = $file->getFilename();
            if ($name === '.gitignore') {
                continue;
            }

            $itemPath = $relativePath === '' ? $name : $relativePath . '/' . $name;
            $items[] = [
                'name' => $name,
                'path' => $itemPath,
                'type' => 'file',
                'size' => $file->getSize(),
                'modified_at' => $this->formatTimestamp($file->getMTime()),
            ];
        }

        usort($items, function (array $left, array $right): int {
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'directory' ? -1 : 1;
            }

            return strnatcasecmp($left['name'], $right['name']);
        });

        return [
            'path' => $relativePath,
            'public_url' => $this->publicUrl($relativePath, $publicBaseUrl),
            'breadcrumbs' => $this->breadcrumbs($relativePath),
            'items' => $items,
        ];
    }

    public function createDirectory(string $relativePath, string $name): array
    {
        $this->ensureRoot();
        $relativePath = $this->normalizeRelativePath($relativePath);
        $name = trim($name);

        if ($name === '' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            throw new InvalidArgumentException('Folder name may only contain letters, numbers, dots, dashes, and underscores.');
        }

        $parentPath = $this->absolutePath($relativePath);
        if (! File::isDirectory($parentPath)) {
            throw new InvalidArgumentException('Parent directory not found.');
        }

        $newRelativePath = $relativePath === '' ? $name : $relativePath . '/' . $name;
        $newAbsolutePath = $this->absolutePath($newRelativePath);

        if (File::exists($newAbsolutePath)) {
            throw new InvalidArgumentException('A file or folder with that name already exists.');
        }

        File::makeDirectory($newAbsolutePath, 0775, true);
        $this->applyDirectoryPermissions($newAbsolutePath);

        return [
            'path' => $newRelativePath,
            'name' => $name,
        ];
    }

    public function uploadFile(string $relativePath, UploadedFile $file): array
    {
        $this->ensureRoot();
        $relativePath = $this->normalizeRelativePath($relativePath);
        $directoryPath = $this->absolutePath($relativePath);

        if (! File::isDirectory($directoryPath)) {
            throw new InvalidArgumentException('Upload directory not found.');
        }

        $originalName = $file->getClientOriginalName();
        $sanitizedName = $this->sanitizeFileName($originalName);

        if ($file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw new InvalidArgumentException('File exceeds the 200 MB upload limit.');
        }

        $extension = strtolower((string) pathinfo($sanitizedName, PATHINFO_EXTENSION));
        if ($extension === '' || ! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Unsupported file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS) . '.');
        }

        try {
            $file->move($directoryPath, $sanitizedName);
        } catch (FileException $exception) {
            throw new RuntimeException('Could not save uploaded file.', 0, $exception);
        }

        $storedAbsolutePath = $directoryPath . DIRECTORY_SEPARATOR . $sanitizedName;
        @chmod($storedAbsolutePath, 0664);

        $storedPath = $relativePath === '' ? $sanitizedName : $relativePath . '/' . $sanitizedName;

        return [
            'name' => $sanitizedName,
            'path' => $storedPath,
        ];
    }

    public function deletePath(string $relativePath): void
    {
        $this->ensureRoot();
        $relativePath = $this->normalizeRelativePath($relativePath);

        if ($relativePath === '') {
            throw new InvalidArgumentException('The firmware root cannot be deleted.');
        }

        $absolutePath = $this->absolutePath($relativePath);

        if (! File::exists($absolutePath)) {
            throw new InvalidArgumentException('File or folder not found.');
        }

        if (File::isDirectory($absolutePath)) {
            File::deleteDirectory($absolutePath);

            return;
        }

        File::delete($absolutePath);
    }

    public function downloadAbsolutePath(string $relativePath): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->absolutePath($relativePath);

        if (! File::isFile($absolutePath)) {
            throw new InvalidArgumentException('File not found.');
        }

        return $absolutePath;
    }

    public function publicUrl(string $relativePath, string $publicBaseUrl): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $base = rtrim($publicBaseUrl, '/');

        return $relativePath === ''
            ? $base . '/firmware/'
            : $base . '/firmware/' . $relativePath . (str_contains($relativePath, '.') ? '' : '/');
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function breadcrumbs(string $relativePath): array
    {
        $crumbs = [
            ['name' => 'firmware', 'path' => ''],
        ];

        if ($relativePath === '') {
            return $crumbs;
        }

        $segments = explode('/', $relativePath);
        $current = '';

        foreach ($segments as $segment) {
            $current = $current === '' ? $segment : $current . '/' . $segment;
            $crumbs[] = [
                'name' => $segment,
                'path' => $current,
            ];
        }

        return $crumbs;
    }

    private function sanitizeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName) ?? '';

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            throw new InvalidArgumentException('Invalid file name.');
        }

        return $fileName;
    }

    private function formatTimestamp(int|false $timestamp): ?string
    {
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
