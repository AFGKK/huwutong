<?php

// M3-48 客户上传文件云存储配置

return [
    'storage' => [
        'disk' => env('CLOUD_STORAGE_DISK', 's3'), // s3/cos/oss
        'prefix' => 'uploads/{tenant_id}/{type}',
        'max_file_size_kb' => 20480, // 20MB
        'allowed_types' => [
            'image' => ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/bmp', 'image/tiff', 'image/x-ms-bmp'],
            'audio' => ['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/ogg', 'audio/vnd.wave', 'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/webm', 'audio/aac', 'audio/x-aac', 'audio/flac', 'audio/x-flac', 'audio/x-ms-wma', 'audio/x-pn-realaudio', 'audio/x-realaudio'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo', 'video/x-ms-wmv', 'video/x-matroska', 'video/x-flv', 'video/3gpp', 'video/3gpp2', 'video/mpeg', 'video/x-ms-asf'],
            'file' => ['application/zip', 'application/x-zip-compressed', 'application/x-rar-compressed', 'application/x-7z-compressed', 'application/x-tar', 'application/gzip', 'application/x-gzip', 'application/x-bzip2', 'application/x-xz', 'application/octet-stream'],
            'document' => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'text/plain', 'text/csv', 'text/markdown', 'application/json', 'application/rtf', 'text/html', 'text/xml', 'application/xml'],
            'other' => ['*'],
        ],
        'url_expiry_minutes' => 60, // 临时URL过期时间
    ],

    'cdn' => [
        'enabled' => env('CDN_ENABLED', true),
        'domain' => env('CDN_DOMAIN', ''),
        'cache_ttl_seconds' => 86400,
        'purge_on_update' => true,
    ],

    'image_processing' => [
        'resize' => [
            'logo' => ['width' => 400, 'height' => 200],
            'thumbnail' => ['width' => 200, 'height' => 200],
        ],
        'optimize' => true,
        'quality' => 85,
    ],

    'security' => [
        'allowed_origins' => ['*'],
        'require_auth' => true,
        'max_uploads_per_minute' => 10,
        'virus_scan' => env('VIRUS_SCAN_ENABLED', false),
    ],

    'types' => [
        'image' => ['max_files' => 100, 'public' => true],
        'audio' => ['max_files' => 50, 'public' => false],
        'video' => ['max_files' => 30, 'public' => false],
        'file' => ['max_files' => 50, 'public' => false],
        'document' => ['max_files' => 50, 'public' => false],
        'other' => ['max_files' => 50, 'public' => false],
    ],
];
