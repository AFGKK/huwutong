<?php

namespace App\Services;

use App\Models\MfaDevice;
use App\Models\MfaRecoveryAudit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * MFA 认证服务（TOTP + 恢复码 + IP 白名单）
 *
 * 功能：
 * - TOTP 密钥生成 & 验证
 * - 基于 HMAC-SHA1 的 RFC 6238 TOTP 实现（无外部依赖）
 * - 备用恢复码（10 个一次性码，BCrypt 哈希存储）
 * - 恢复码使用审计
 * - MFA 设备管理（绑定/重命名/删除）
 * - IP 白名单检查
 * - MFA 策略强制（按租户配置）
 */
class MfaService
{
    const RECOVERY_CODES_COUNT = 10;
    const RECOVERY_CODE_LENGTH = 16;
    const TOTP_INTERVAL = 30;
    const TOTP_DIGITS = 6;
    const TOTP_DRIFT = 1; // 允许前后各 1 个窗口的偏移

    // ─── TOTP ───

    /**
     * 生成 TOTP 密钥（Base32 编码 160 位随机数）
     */
    public function generateSecret(): string
    {
        $random = random_bytes(20);
        return $this->base32Encode($random);
    }

    /**
     * 获取 TOTP 配置信息（用于生成二维码）
     */
    public function getTOTPConfig(string $secret, string $email, string $issuer = 'HWT'): array
    {
        $uri = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($email),
            $secret,
            rawurlencode($issuer),
            self::TOTP_DIGITS,
            self::TOTP_INTERVAL,
        );

        return [
            'secret' => $secret,
            'uri' => $uri,
            'digits' => self::TOTP_DIGITS,
            'interval' => self::TOTP_INTERVAL,
        ];
    }

    /**
     * 验证 TOTP 一次性密码
     */
    public function verifyTOTP(string $secret, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $secretBin = $this->base32Decode($secret);
        if ($secretBin === false || strlen($secretBin) < 10) {
            return false;
        }

        $counter = floor(time() / self::TOTP_INTERVAL);

        // 检查当前窗口及前后各一个窗口
        for ($i = -self::TOTP_DRIFT; $i <= self::TOTP_DRIFT; $i++) {
            $expected = $this->generateOTP($secretBin, (int) ($counter + $i));
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成 TOTP 验证码内部实现（RFC 4226 / RFC 6238）
     */
    protected function generateOTP(string $keyBinary, int $counter): string
    {
        // 将 counter 编码为 8 字节大端序
        $counterBytes = pack('J', $counter); // PHP 8+ 支持 'J' 格式（64-bit big-endian unsigned）

        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $counterBytes, $keyBinary, true);

        // 动态截断（RFC 4226 Section 5.3）
        $offset = ord($hmac[19]) & 0x0f;

        $code = (
                ((ord($hmac[$offset]) & 0x7f) << 24) |
                ((ord($hmac[$offset + 1]) & 0xff) << 16) |
                ((ord($hmac[$offset + 2]) & 0xff) << 8) |
                (ord($hmac[$offset + 3]) & 0xff)
            ) % (10 ** self::TOTP_DIGITS);

        return str_pad((string) $code, self::TOTP_DIGITS, '0', STR_PAD_LEFT);
    }

    // ─── 用户 MFA 绑定 ───

    /**
     * 为用户启用 MFA（绑定首个设备）
     */
    public function enableMfa(User $user, string $deviceName, string $secret): MfaDevice
    {
        $device = MfaDevice::create([
            'user_id' => $user->id,
            'name' => $deviceName,
            'secret' => $secret,
            'type' => 'totp',
            'confirmed_at' => now(),
        ]);

        $user->update([
            'mfa_secret' => $secret,
            'mfa_enabled' => true,
        ]);

        Log::info('MFA 已启用', ['user_id' => $user->id, 'device_id' => $device->id]);

        return $device;
    }

    /**
     * 为用户禁用 MFA（删除所有设备和恢复码）
     */
    public function disableMfa(User $user): void
    {
        MfaDevice::where('user_id', $user->id)->delete();

        $user->update([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
            'mfa_recovery_used' => null,
        ]);

        Log::info('MFA 已禁用', ['user_id' => $user->id]);
    }

    /**
     * 获取用户绑定的 MFA 设备
     */
    public function getUserDevices(User $user): \Illuminate\Database\Eloquent\Collection
    {
        return MfaDevice::where('user_id', $user->id)->get();
    }

    /**
     * 重命名 MFA 设备
     */
    public function renameDevice(MfaDevice $device, string $newName): void
    {
        $device->update(['name' => $newName]);
    }

    /**
     * 删除 MFA 设备
     */
    public function deleteDevice(MfaDevice $device): void
    {
        $userId = $device->user_id;
        $device->delete();

        // 如果用户没有其他设备，自动禁用 MFA
        $remaining = MfaDevice::where('user_id', $userId)->count();
        if ($remaining === 0) {
            User::where('id', $userId)->update([
                'mfa_secret' => null,
                'mfa_enabled' => false,
                'mfa_recovery_codes' => null,
                'mfa_recovery_used' => null,
            ]);
        }
    }

    /**
     * 验证 MFA Code（TOTP 或恢复码）
     *
     * @return array ['verified' => bool, 'method' => 'totp'|'recovery'|null]
     */
    public function verifyMfa(User $user, string $code): array
    {
        // 1. TOTP 验证
        if ($user->mfa_secret && $this->verifyTOTP($user->mfa_secret, $code)) {
            return ['verified' => true, 'method' => 'totp'];
        }

        // 2. 恢复码验证
        if ($user->mfa_recovery_codes) {
            $result = $this->verifyRecoveryCode($user, $code);
            if ($result['verified']) {
                return ['verified' => true, 'method' => 'recovery'];
            }
        }

        return ['verified' => false, 'method' => null];
    }

    // ─── 恢复码 ───

    /**
     * 生成新的恢复码（10 个一次性码）
     * 返回明文码，数据库存储 BCrypt 哈希
     */
    public function generateRecoveryCodes(User $user): array
    {
        $codes = [];
        $hashed = [];

        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; $i++) {
            $code = strtoupper(bin2hex(random_bytes(self::RECOVERY_CODE_LENGTH / 2)));
            // 格式化成易读形式：XXXX-XXXX-XXXX-XXXX
            $formatted = strtolower(chunk_split($code, 4, '-'));
            $formatted = rtrim($formatted, '-');
            $codes[] = $formatted;
            $hashed[] = Hash::make($formatted);
        }

        $user->update([
            'mfa_recovery_codes' => $hashed,
            'mfa_recovery_used' => array_fill(0, self::RECOVERY_CODES_COUNT, false),
        ]);

        MfaRecoveryAudit::create([
            'user_id' => $user->id,
            'action' => 'generated',
            'ip' => request()->ip(),
        ]);

        Log::info('MFA 恢复码已生成', ['user_id' => $user->id]);

        return $codes;
    }

    /**
     * 验证恢复码并使用
     */
    public function verifyRecoveryCode(User $user, string $code): array
    {
        $codes = $user->mfa_recovery_codes;
        $used = $user->mfa_recovery_used;

        if (empty($codes) || ! is_array($codes)) {
            return ['verified' => false];
        }

        // 初始化 used 如果为 null
        if (! is_array($used)) {
            $used = array_fill(0, count($codes), false);
        }

        foreach ($codes as $i => $hashed) {
            // 跳过已使用的
            if (! empty($used[$i])) {
                continue;
            }

            if (Hash::check($code, $hashed)) {
                // 标记为已使用
                $used[$i] = true;
                $user->update(['mfa_recovery_used' => $used]);

                MfaRecoveryAudit::create([
                    'user_id' => $user->id,
                    'action' => 'used',
                    'ip' => request()->ip(),
                    'metadata' => ['code_index' => $i],
                ]);

                Log::info('MFA 恢复码已使用', ['user_id' => $user->id, 'remaining' => $this->countRemainingCodes($user)]);

                return ['verified' => true];
            }
        }

        return ['verified' => false];
    }

    /**
     * 统计剩余恢复码数量
     */
    public function countRemainingCodes(User $user): int
    {
        $used = $user->mfa_recovery_used ?? [];
        if (! is_array($used)) {
            return self::RECOVERY_CODES_COUNT;
        }
        return self::RECOVERY_CODES_COUNT - count(array_filter($used));
    }

    /**
     * 管理员强制重置用户的 MFA
     */
    public function adminResetMfa(User $user): void
    {
        MfaDevice::where('user_id', $user->id)->delete();

        $user->update([
            'mfa_secret' => null,
            'mfa_enabled' => false,
            'mfa_recovery_codes' => null,
            'mfa_recovery_used' => null,
        ]);

        MfaRecoveryAudit::create([
            'user_id' => $user->id,
            'action' => 'reset',
            'ip' => request()->ip(),
        ]);

        Log::warning('管理员强制重置 MFA', ['user_id' => $user->id]);
    }

    // ─── IP 白名单 ───

    /**
     * 检查 IP 是否在白名单中
     */
    public function isIpInWhitelist(string $ip, array $whitelist): bool
    {
        if (empty($whitelist)) {
            return true; // 白名单为空表示不限制
        }

        foreach ($whitelist as $rule) {
            if ($this->ipMatchesRule($ip, $rule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IP 匹配规则（支持 CIDR 和精确 IP）
     */
    protected function ipMatchesRule(string $ip, string $rule): bool
    {
        $rule = trim($rule);

        // 精确匹配
        if ($rule === $ip) {
            return true;
        }

        // CIDR 匹配
        if (str_contains($rule, '/')) {
            return $this->ipInCidr($ip, $rule);
        }

        // 通配符匹配：192.168.*.*
        $pattern = '/^' . str_replace('\*', '\d{1,3}', preg_quote($rule, '/')) . '$/';
        if (preg_match($pattern, $ip)) {
            return true;
        }

        return false;
    }

    /**
     * CIDR 范围匹配
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr);
        if (count($parts) !== 2) {
            return false;
        }

        $range = $parts[0];
        $prefix = (int) $parts[1];

        $ipLong = ip2long($ip);
        $rangeLong = ip2long($range);

        if ($ipLong === false || $rangeLong === false) {
            return false;
        }

        $mask = -1 << (32 - $prefix);

        return ($ipLong & $mask) === ($rangeLong & $mask);
    }

    // ─── MFA 策略 ───

    /**
     * 判断用户是否需要完成 MFA 验证
     */
    public function requiresMfa(User $user): bool
    {
        // 用户已启用 MFA—后续 API 需要 MFA code
        if ($user->mfa_enabled) {
            return true;
        }

        // 租户 MFA 策略检查
        $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();
        if (! $tenant) {
            return false;
        }

        return match ($tenant->mfa_policy) {
            'required_for_all' => true,
            'required_for_admin' => $user->hasRole('admin') || $user->hasRole('super-admin'),
            default => false,
        };
    }

    /**
     * 检查请求 IP 是否在管理后台 IP 白名单内
     */
    public function checkIpWhitelist(User $user, string $ip): bool
    {
        $tenant = $user->relationLoaded('tenant') ? $user->tenant : $user->tenant()->first();
        if (! $tenant || empty($tenant->allowed_ips)) {
            return true; // 未配置白名单 → 不限制
        }

        $allowedIps = $tenant->allowed_ips;
        if (! is_array($allowedIps)) {
            return true;
        }

        return $this->isIpInWhitelist($ip, $allowedIps);
    }

    // ─── Base32 编码工具 ───

    protected function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        for ($i = 0; $i < strlen($binary); $i += 5) {
            $chunk = substr($binary, $i, 5);
            $encoded .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    protected function base32Decode(string $data): string|false
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $data = str_replace('=', '', $data);

        $binary = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $pos = strpos($alphabet, $data[$i]);
            if ($pos === false) {
                return false;
            }
            $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        for ($i = 0; $i + 7 < strlen($binary); $i += 8) {
            $decoded .= chr(bindec(substr($binary, $i, 8)));
        }

        return $decoded;
    }
}
