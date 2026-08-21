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

        // Collapse empty segments from phone URLs like .../intrade//file.txt
        $segments = [];
        foreach (explode('/', $relativePath) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' || ! preg_match('/^[a-zA-Z0-9._-]+$/', $segment)) {
                throw new InvalidArgumentException('Invalid path segment.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
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

    public function uploadFile(string $relativePath, UploadedFile $file, array $options = []): array
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

        $result = [
            'name' => $sanitizedName,
            'path' => $storedPath,
        ];

        $generateManifest = ($options['generate_manifest'] ?? true) !== false;
        if ($extension === 'z' && $generateManifest && $this->isIntradeVendorPath($relativePath)) {
            $parsed = $this->parseIntradeFirmwarePackage($sanitizedName, [
                'model' => $options['model'] ?? null,
                'hw' => $options['hw'] ?? null,
            ]);

            if (! empty($options['version'])) {
                $parsed['version'] = trim((string) $options['version']);
            }

            $result['parse'] = $parsed;

            if (empty($parsed['model'])) {
                $result['warning'] = 'Uploaded .z but could not detect Intrade model. Rename like entry-2.12.21.19.1.z or choose a model when uploading.';
                $result['manifest'] = null;
            } else {
                try {
                    $result['manifest'] = $this->writeIntradeManifestForPackage($relativePath, $parsed);
                } catch (InvalidArgumentException $exception) {
                    $result['warning'] = $exception->getMessage();
                    $result['manifest'] = null;
                }
            }
        }

        return $result;
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

        if (File::isFile($absolutePath)) {
            return $absolutePath;
        }

        // Phones often request lowercase manifest names on case-sensitive volumes.
        $directory = dirname($absolutePath);
        $wanted = strtolower(basename($absolutePath));

        if (File::isDirectory($directory)) {
            foreach (File::files($directory) as $file) {
                if (strtolower($file->getFilename()) === $wanted) {
                    return $file->getPathname();
                }
            }
        }

        throw new InvalidArgumentException('File not found.');
    }

    public function isIntradeVendorPath(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $vendor = strtolower((string) (explode('/', $relativePath)[0] ?? ''));

        return $vendor === 'intrade';
    }

    /**
     * Normalize hardware revision token to hwvX_Y (default hwv1_0).
     */
    public function normalizeHwRevision(?string $raw): string
    {
        $s = strtolower(trim((string) ($raw ?: '1_0')));

        if (preg_match('/^hwv?(\d+)[._](\d+)$/', $s, $m) === 1) {
            return 'hwv' . $m[1] . '_' . $m[2];
        }

        if (preg_match('/^v?(\d+)[._](\d+)$/', $s, $m) === 1) {
            return 'hwv' . $m[1] . '_' . $m[2];
        }

        if (preg_match('/^(\d+)$/', $s, $m) === 1) {
            return 'hwv' . $m[1] . '_0';
        }

        return 'hwv1_0';
    }

    /**
     * Infer Intrade model + version + build time from a .z filename.
     *
     * @param  array{model?: string|null, hw?: string|null, now?: \DateTimeInterface|null}  $options
     * @return array{
     *     firmwareName: string,
     *     model: ?string,
     *     modelLabel: ?string,
     *     version: ?string,
     *     buildTime: string,
     *     hw: string,
     *     manifestName: ?string
     * }
     */
    public function parseIntradeFirmwarePackage(string $fileName, array $options = []): array
    {
        $name = $this->sanitizeFileName($fileName);
        $stem = (string) preg_replace('/\.z$/i', '', $name);
        // Underscores are word chars in JS \b, so normalize for alias matching.
        $hay = str_replace('_', ' ', $stem . ' ' . $name);

        $labels = [
            'entry' => 'Entry',
            'standard' => 'Standard',
            'advanced' => 'Advanced',
            'video' => 'Video',
        ];

        $model = strtolower(trim((string) ($options['model'] ?? '')));
        if (! isset($labels[$model])) {
            $model = '';
            $aliases = [
                'advanced' => '/\badvanced\b|j660\b/i',
                'standard' => '/\bstandard\b|j620g?\b/i',
                'video' => '/\bvideo\b|j308\b/i',
                'entry' => '/\bentry\b|j600g?\b/i',
            ];

            foreach ($aliases as $aliasModel => $pattern) {
                if (preg_match($pattern, $hay) === 1) {
                    $model = $aliasModel;
                    break;
                }
            }
        }

        // Prefer the longest dotted version (e.g. 2.12.21.19.1).
        preg_match_all('/\d+(?:\.\d+){2,}/', $stem, $versionMatches);
        $versions = $versionMatches[0] ?? [];
        usort($versions, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $version = $versions[0] ?? null;

        $buildTime = '';
        if (preg_match('/T(\d{4})[-_.]?(\d{2})[-_.]?(\d{2})[-_.]?(\d{2})[.:]?(\d{2})(?:[.:]?(\d{2}))?/i', $stem, $tMatch) === 1) {
            $buildTime = $tMatch[1] . '.' . $tMatch[2] . '.' . $tMatch[3] . ' ' . $tMatch[4] . ':' . $tMatch[5];
        } else {
            $now = $options['now'] ?? now();
            $buildTime = $now->format('Y.m.d H:i');
        }

        $hw = $this->normalizeHwRevision($options['hw'] ?? null);
        $label = $labels[$model] ?? '';
        // Phones request InTrade_<Model>_InTrade_<Model>_hwvX_Y.txt
        $manifestName = $label !== '' ? 'InTrade_' . $label . '_InTrade_' . $label . '_' . $hw . '.txt' : null;

        return [
            'firmwareName' => $name,
            'model' => $model !== '' ? $model : null,
            'modelLabel' => $label !== '' ? $label : null,
            'version' => $version,
            'buildTime' => $buildTime,
            'hw' => $hw,
            'manifestName' => $manifestName,
        ];
    }

    /**
     * UTF-8 manifest body (no # comments).
     *
     * @param  array{version?: string|null, firmwareName?: string|null, buildTime?: string|null}  $data
     */
    public function buildIntradeManifestContent(array $data): string
    {
        $ver = trim((string) ($data['version'] ?? '')) ?: '0.0.0';
        $fw = trim((string) ($data['firmwareName'] ?? ''));
        $bt = trim((string) ($data['buildTime'] ?? ''));

        return implode("\n", [
            'Version=' . $ver,
            'Firmware=' . $fw,
            'BuildTime=' . $bt,
            'Info=TXT',
            '',
        ]);
    }

    /**
     * Write (or overwrite) InTrade_<Model>_InTrade_<Model>_hwvX_Y.txt next to a .z package.
     *
     * @param  array{
     *     firmwareName?: string|null,
     *     model?: string|null,
     *     version?: string|null,
     *     buildTime?: string|null,
     *     manifestName?: string|null
     * }  $parsed
     * @return array{name: string, path: string, content: string}|null
     */
    public function writeIntradeManifestForPackage(string $relativeDir, array $parsed): ?array
    {
        if (empty($parsed['model']) || empty($parsed['manifestName']) || empty($parsed['firmwareName'])) {
            return null;
        }

        if (empty($parsed['version'])) {
            throw new InvalidArgumentException(
                'Could not detect firmware Version from the filename. Rename like entry-2.12.21.19.1.z or pass version.'
            );
        }

        $relative = $this->normalizeRelativePath($relativeDir);
        $directoryPath = $this->absolutePath($relative);

        if (! File::isDirectory($directoryPath)) {
            throw new InvalidArgumentException('Upload directory not found.');
        }

        $content = $this->buildIntradeManifestContent([
            'version' => $parsed['version'],
            'firmwareName' => $parsed['firmwareName'],
            'buildTime' => $parsed['buildTime'] ?? null,
        ]);

        $dest = $directoryPath . DIRECTORY_SEPARATOR . $parsed['manifestName'];
        File::put($dest, $content);
        @chmod($dest, 0664);

        $storedPath = $relative === ''
            ? (string) $parsed['manifestName']
            : $relative . '/' . $parsed['manifestName'];

        return [
            'name' => (string) $parsed['manifestName'],
            'path' => $storedPath,
            'content' => $content,
        ];
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
