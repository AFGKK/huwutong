<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IP 地理位置解析服务
 * 使用 ip-api.com 免费 API（无需 Key，限 45 次/分钟）
 */
class IpGeoService
{
    /**
     * 根据 IP 获取地理位置信息
     */
    public function locate(string $ip): array
    {
        // 内网 IP 不查询
        if ($this->isPrivateIp($ip)) {
            return [
                'ip' => $ip,
                'country' => '内网',
                'region' => '',
                'city' => '',
                'isp' => '',
                'lat' => null,
                'lon' => null,
            ];
        }

        $cacheKey = 'ip_geo_' . md5($ip);

        return Cache::remember($cacheKey, 86400, function () use ($ip) {
            try {
                $response = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?lang=zh-CN&fields=status,country,regionName,city,isp,lat,lon,query");

                if ($response->successful() && $response->json('status') === 'success') {
                    return [
                        'ip' => $response->json('query', $ip),
                        'country' => $response->json('country', ''),
                        'region' => $response->json('regionName', ''),
                        'city' => $response->json('city', ''),
                        'isp' => $response->json('isp', ''),
                        'lat' => $response->json('lat'),
                        'lon' => $response->json('lon'),
                    ];
                }

                Log::warning('[IpGeo] 查询失败', ['ip' => $ip, 'response' => $response->body()]);
            } catch (\Throwable $e) {
                Log::warning('[IpGeo] 查询异常', ['ip' => $ip, 'error' => $e->getMessage()]);
            }

            return [
                'ip' => $ip,
                'country' => '',
                'region' => '',
                'city' => '',
                'isp' => '',
                'lat' => null,
                'lon' => null,
            ];
        });
    }

    /**
     * 从 User-Agent 解析浏览器/操作系统/设备
     */
    public function parseUserAgent(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        $browser = '未知';
        if (preg_match('/Edg\/([\d.]+)/', $ua)) $browser = 'Edge';
        elseif (preg_match('/Chrome\/([\d.]+)/', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Firefox\/([\d.]+)/', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Safari\/([\d.]+)/', $ua)) $browser = 'Safari';
        elseif (preg_match('/Opera|OPR\//', $ua)) $browser = 'Opera';
        elseif (preg_match('/Trident|MSIE/', $ua)) $browser = 'IE';

        $os = '未知';
        if (preg_match('/Windows NT/', $ua)) $os = 'Windows';
        elseif (preg_match('/Mac OS X/', $ua)) $os = 'macOS';
        elseif (preg_match('/Linux/', $ua) && !preg_match('/Android/', $ua)) $os = 'Linux';
        elseif (preg_match('/Android/', $ua)) $os = 'Android';
        elseif (preg_match('/iOS|iPhone|iPad/', $ua)) $os = 'iOS';

        $device = '桌面';
        if (preg_match('/Mobile|Android|iPhone|iPad/', $ua)) $device = '移动端';
        if (preg_match('/iPad/', $ua)) $device = '平板';

        return compact('browser', 'os', 'device');
    }

    /**
     * 判断是否为内网 IP
     */
    protected function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) return true;

        // 10.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x0A000000) return true;
        // 172.16.0.0/12
        if (($ipLong & 0xFFF00000) === 0xAC100000) return true;
        // 192.168.0.0/16
        if (($ipLong & 0xFFFF0000) === 0xC0A80000) return true;
        // 127.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x7F000000) return true;

        return false;
    }
}
