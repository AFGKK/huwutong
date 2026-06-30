<?php

namespace App\Services;

/**
 * 敏感数据脱敏服务
 *
 * 按角色分级对邮箱/手机号/IP/姓名等敏感字段进行脱敏处理。
 * 脱敏级别：
 *   - admin      超管：全量显示
 *   - operator   运营：部分脱敏（如邮箱显示首字母）
 *   - customer   客户：最小化脱敏（仅能确认归属）
 */
class DataMaskingService
{
    /**
     * 脱敏级别的key 模式匹配字段
     */
    const SENSITIVE_FIELDS = [
        'email' => 'maskEmail',
        'phone' => 'maskPhone',
        'mobile' => 'maskPhone',
        'ip' => 'maskIp',
        'ip_address' => 'maskIp',
        'real_name' => 'maskName',
        'contact_name' => 'maskName',
        'id_card' => 'maskIdCard',
        'id_number' => 'maskIdCard',
        'wechat' => 'maskWechat',
        'qq' => 'maskWechat',
        'address' => 'maskAddress',
        'full_address' => 'maskAddress',
        'token' => 'maskToken',
        'access_token' => 'maskToken',
        'refresh_token' => 'maskToken',
        'secret' => 'maskToken',
        'api_key' => 'maskToken',
        'password' => 'maskAll',
    ];

    /**
     * 对单个值按角色进行脱敏
     */
    public function mask(string $field, mixed $value, string $role = 'customer'): mixed
    {
        if ($value === null || $value === '' || ! is_string($value)) {
            return $value;
        }

        $fieldKey = strtolower(trim($field));
        $method = self::SENSITIVE_FIELDS[$fieldKey] ?? null;

        if (! $method) {
            return $value;
        }

        $level = $this->resolveLevel($role);

        // 超管（admin）始终全量显示
        if ($level === 'admin') {
            return $value;
        }

        // 运营（operator）部分脱敏
        if ($level === 'operator' && in_array($method, ['maskName', 'maskAddress'])) {
            return $value;
        }

        return $this->$method($value, $level);
    }

    /**
     * 递归遍历数组/对象，对敏感字段进行脱敏
     *
     * @param array  $data
     * @param string $role 角色级别
     * @return array
     */
    public function maskArray(array $data, string $role = 'customer'): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $result[$key] = $this->maskArray($value, $role);
            } elseif (is_string($value)) {
                $result[$key] = $this->mask($key, $value, $role);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * 解析角色对应的脱敏级别
     */
    protected function resolveLevel(string $role): string
    {
        return match ($role) {
            'super-admin', 'admin' => 'admin',
            'operator', 'support', 'agent' => 'operator',
            default => 'customer',
        };
    }

    // ─── 脱敏方法 ───

    /**
     * 邮箱脱敏
     * 客户：a***@example.com
     * 运营：a***b@example.com
     */
    protected function maskEmail(string $email, string $level): string
    {
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1];

        if ($level === 'operator' && strlen($name) > 1) {
            $masked = $name[0] . str_repeat('*', max(strlen($name) - 2, 1)) . substr($name, -1);
        } else {
            $masked = $name[0] . str_repeat('*', max(strlen($name) - 1, 1));
        }

        return $masked . '@' . $domain;
    }

    /**
     * 手机号脱敏
     * 客户：138****8000
     * 运营：138****8000 (相同)
     */
    protected function maskPhone(string $phone, string $level): string
    {
        if (strlen($phone) < 7) {
            return $phone;
        }
        return substr($phone, 0, 3) . '****' . substr($phone, -4);
    }

    /**
     * IP 脱敏
     * 客户：192.168.*.*
     * 运营：192.168.*.* (相同)
     */
    protected function maskIp(string $ip, string $level): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            return $parts[0] . '.' . $parts[1] . '.*.*';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // 手动展开 IPv6 地址，避免 inet_ntop 简写
            $bin = inet_pton($ip);
            if ($bin === false || strlen($bin) !== 16) {
                return $ip;
            }
            $parts = unpack('n8', $bin);
            if ($parts === false) {
                return $ip;
            }
            $hexParts = array_map(fn($v) => dechex($v), $parts);
            $seg0 = $hexParts[1] ?? '0';
            $seg1 = $hexParts[2] ?? '0';
            $seg2 = $hexParts[3] ?? '0';
            return $seg0 . ':' . $seg1 . ':' . $seg2 . ':****:****:****';
        }
        return $ip;
    }

    /**
     * 姓名脱敏
     * 客户：张*
     * 运营：显示全名（operator 级别不脱敏姓名）
     */
    protected function maskName(string $name, string $level): string
    {
        if (mb_strlen($name) <= 1) {
            return $name;
        }
        return mb_substr($name, 0, 1) . str_repeat('*', mb_strlen($name) - 1);
    }

    /**
     * 身份证号脱敏
     * 客户：110***********1234
     * 运营：110***********1234 (相同)
     */
    protected function maskIdCard(string $id, string $level): string
    {
        if (strlen($id) < 10) {
            return $id;
        }
        return substr($id, 0, 3) . str_repeat('*', strlen($id) - 7) . substr($id, -4);
    }

    /**
     * 微信/QQ 脱敏
     * 客户：wx***
     */
    protected function maskWechat(string $value, string $level): string
    {
        if (strlen($value) <= 2) {
            return $value[0] . '*';
        }
        return substr($value, 0, 2) . str_repeat('*', strlen($value) - 2);
    }

    /**
     * 地址脱敏
     * 客户：北京市海淀区******
     */
    protected function maskAddress(string $address, string $level): string
    {
        $encoding = 'UTF-8';
        $len = mb_strlen($address, $encoding);
        if ($len <= 6) {
            return mb_substr($address, 0, 3, $encoding) . '****';
        }
        $visible = 6;
        return mb_substr($address, 0, $visible, $encoding) . str_repeat('*', $len - $visible);
    }

    /**
     * Token/Secret 脱敏
     * 仅显示前 8 位
     */
    protected function maskToken(string $token, string $level): string
    {
        if (strlen($token) <= 8) {
            return $token;
        }
        return substr($token, 0, 8) . '***';
    }

    /**
     * 完全脱敏
     */
    protected function maskAll(string $value, string $level): string
    {
        return '********';
    }
}
