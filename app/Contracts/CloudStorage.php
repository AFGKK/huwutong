<?php

namespace App\Contracts;

/**
 * 云存储统一适配层接口
 *
 * 支持：阿里云OSS / 腾讯云COS / 华为云OBS / Amazon S3 / Cloudflare R2 / Backblaze B2
 * 统一接口，自动切换
 */
interface CloudStorage
{
    /**
     * 上传文件
     *
     * @param string $path 存储路径
     * @param string|resource $contents 文件内容或可读流
     * @param array $options 可选参数（visibility, contentType, metadata 等）
     * @return string 文件的公开URL
     */
    public function upload(string $path, mixed $contents, array $options = []): string;

    /**
     * 下载文件内容
     *
     * @param string $path 存储路径
     * @return string 文件内容
     */
    public function download(string $path): string;

    /**
     * 获取文件流式读取
     *
     * @param string $path 存储路径
     * @return resource 可读流
     */
    public function stream(string $path): mixed;

    /**
     * 删除文件
     *
     * @param string $path 存储路径
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * 判断文件是否存在
     *
     * @param string $path 存储路径
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * 获取文件的公开URL（带CDN加速域名）
     *
     * @param string $path 存储路径
     * @param int $expires 签名URL有效期（秒），0 表示永久公开
     * @return string
     */
    public function url(string $path, int $expires = 0): string;

    /**
     * 获取文件的临时签名URL（私有文件适用）
     *
     * @param string $path 存储路径
     * @param int $expires 有效期（秒）
     * @return string
     */
    public function temporaryUrl(string $path, int $expires): string;

    /**
     * 复制文件
     *
     * @param string $from 源路径
     * @param string $to 目标路径
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * 移动文件
     *
     * @param string $from 源路径
     * @param string $to 目标路径
     * @return bool
     */
    public function move(string $from, string $to): bool;

    /**
     * 批量删除
     *
     * @param array $paths 路径数组
     * @return int 成功删除数量
     */
    public function deleteMultiple(array $paths): int;

    /**
     * 列出目录下的文件
     *
     * @param string $directory 目录路径
     * @param bool $recursive 是否递归
     * @return array 文件路径数组
     */
    public function listFiles(string $directory = '', bool $recursive = false): array;

    /**
     * 获取文件元信息
     *
     * @param string $path 存储路径
     * @return array{size: int, mimeType: string, lastModified: int}
     */
    public function getMetadata(string $path): array;

    /**
     * 获取文件大小
     *
     * @param string $path 存储路径
     * @return int 字节数
     */
    public function size(string $path): int;

    /**
     * 获取当前存储驱动名称
     *
     * @return string
     */
    public function driver(): string;

    /**
     * 切换存储驱动
     *
     * @param string $driver 驱动名称（oss, cos, obs, s3, r2, b2）
     * @return self
     */
    public function setDriver(string $driver): self;
}
