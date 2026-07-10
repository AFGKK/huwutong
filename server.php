<?php
/**
 * PHP 内置开发服务器路由器
 * 解决 /build/* 路径因 public/build 目录存在而导致 404 的问题
 *
 * PHP 内置服务器的已知行为：当请求 URI 的某个父级路径对应到真实存在的目录时
 * （例如 /build/community，其中 public/build/ 是真实目录），
 * PHP 会先尝试在目录中查找 index.php/index.html，找不到则返回 404，
 * 而不会调用路由器脚本。
 *
 * 此路由器通过检测这种情况并强制路由到 Laravel 来解决该问题。
 */

if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $publicDir = __DIR__ . '/public';
    $file = $publicDir . $path;

    // 如果请求的是真实存在的静态文件，直接返回让 PHP 内置服务器处理
    if (is_file($file)) {
        return false;
    }

    // 检查 URI 路径的每一级，判断是否在某个真实目录下
    // 如果是，说明 PHP 内置服务器可能不会调用此路由器，需要强制路由
    $normalizedPath = '/' . trim(str_replace('\\', '/', $path), '/');
    $segments = explode('/', trim($normalizedPath, '/'));
    $currentPath = $publicDir;
    $hasRealParentDir = false;

    foreach ($segments as $segment) {
        if ($segment === '') {
            continue;
        }
        $currentPath .= '/' . $segment;
        if (is_dir($currentPath)) {
            $hasRealParentDir = true;
        } else {
            // 如果某段路径不存在，后续路径也不可能存在
            break;
        }
    }

    // 如果存在父级真实目录但目标文件不存在，强制路由到 Laravel
    if ($hasRealParentDir) {
        // 修正 SCRIPT_NAME，确保 Laravel 路由正确解析
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        require $publicDir . '/index.php';
        return;
    }
}

// 所有其他请求（包括 CLI 模式）转到 Laravel
require __DIR__ . '/public/index.php';
