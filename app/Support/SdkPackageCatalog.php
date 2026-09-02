<?php

namespace App\Support;

/**
 * 公开 SDK 包名 / 安装命令单一来源（config/sdk-docs.php）
 */
class SdkPackageCatalog
{
    /** @var array<string, string> */
    private const KEY_MAP = [
        'php' => 'php',
        'node' => 'node',
        'nodejs' => 'node',
        'javascript' => 'node',
        'js' => 'node',
        'python' => 'python',
        'py' => 'python',
        'go' => 'go',
        'golang' => 'go',
        'java' => 'java',
        'csharp' => 'csharp',
        'dotnet' => 'csharp',
        'cs' => 'csharp',
        'flutter' => 'flutter',
        'electron' => 'electron',
        'rust' => 'tauri',
        'tauri' => 'tauri',
    ];

    public static function resolveKey(string $language): ?string
    {
        $lang = strtolower(trim($language));
        if (isset(self::KEY_MAP[$lang])) {
            return self::KEY_MAP[$lang];
        }

        $aliases = config('sdk-docs.aliases', []);

        return is_array($aliases) ? ($aliases[$lang] ?? null) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function sdk(string $language): ?array
    {
        $key = self::resolveKey($language);
        if ($key === null) {
            return null;
        }

        $sdk = config("sdk-docs.sdks.{$key}");

        return is_array($sdk) ? $sdk : null;
    }

    public static function installCommand(string $language): ?string
    {
        $sdk = self::sdk($language);
        if ($sdk === null) {
            return null;
        }

        $command = $sdk['install_command'] ?? null;
        if (! is_string($command) || $command === '') {
            return null;
        }

        if (str_contains($command, '<dependency>')) {
            return is_string($sdk['install_alt'] ?? null) && $sdk['install_alt'] !== ''
                ? $sdk['install_alt']
                : 'implementation "com.huwutong:huwutong-sdk:1.0.0"';
        }

        if (str_starts_with(trim($command), '[') || str_starts_with(trim($command), 'dependencies:')) {
            $package = $sdk['package'] ?? null;
            $catalogKey = self::resolveKey($language);
            if ($catalogKey === 'flutter' && is_string($package) && $package !== '') {
                return "flutter pub add {$package}";
            }
            if ($catalogKey === 'tauri' && is_string($package) && $package !== '') {
                return "cargo add {$package}";
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($command)) ?: [];

        return $lines[0] !== '' ? $lines[0] : $command;
    }

    public static function packageName(string $language): ?string
    {
        $sdk = self::sdk($language);
        $package = $sdk['package'] ?? null;

        return is_string($package) && $package !== '' ? $package : null;
    }

    public static function docsPath(string $language): ?string
    {
        $sdk = self::sdk($language);
        $path = $sdk['docs_url'] ?? null;

        return is_string($path) && $path !== '' ? $path : null;
    }

    public static function publicDocsUrl(string $language): string
    {
        $path = self::docsPath($language);

        return $path ? url($path) : url('/docs');
    }
}
