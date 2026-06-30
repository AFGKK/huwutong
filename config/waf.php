<?php

/**
 * WAF + DDoS 基础防护配置 (M1.3-18)
 *
 * 三层防护架构：
 *   网关层 (Cloudflare/AWS WAF/Kong) → 应用层 WAF 中间件 → 业务层限流
 *
 * 应用层实现：
 *   - OWASP Top 10 规则引擎（SQL注入/XSS/路径穿越/命令注入/LFI/RFI/SSRF）
 *   - CC 攻击防护（行为分析+频率限制+挑战）
 *   - IP 黑白名单（动态管理+CDN回源信任）
 *   - 请求体/头/参数校验
 */

return [

    /*
    |--------------------------------------------------------------------------
    | 全局开关
    |--------------------------------------------------------------------------
    */
    'enabled' => env('WAF_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | 防护模式
    |--------------------------------------------------------------------------
    | block:    拦截并记录（生产推荐）
    | detect:   仅记录不拦截（灰度观察期）
    | simulate: 模拟告警（兼容现有流量）
    */
    'mode' => env('WAF_MODE', 'block'),

    /*
    |--------------------------------------------------------------------------
    | OWASP Top 10 规则引擎
    |--------------------------------------------------------------------------
    */
    'rules' => [
        'enabled' => env('WAF_RULES_ENABLED', true),
        'mode' => env('WAF_RULES_MODE', 'block'),

        // SQL 注入检测
        'sql_injection' => [
            'enabled' => env('WAF_RULE_SQLI', true),
            'severity' => env('WAF_RULE_SQLI_SEVERITY', 'critical'),
            'patterns' => [
                // 经典 SQL 注入模式
                '/\b(union\s+.*\bselect\b)/i',
                '/\b(select\s+.*\bfrom\b.*\bwhere\b)/i',
                '/\b(insert\s+into\b.*\bvalues\b)/i',
                '/\b(drop\s+table\b|drop\s+database\b)/i',
                '/\b(delete\s+from\b)/i',
                '/\b(update\s+.*\bset\b)/i',
                '/\b(create\s+table\b|create\s+database\b)/i',
                '/\b(alter\s+table\b)/i',
                // 注释/编码绕过
                '/\/\*!/i',
                '/\b(0x[0-9a-f]{4,})\b/i',
                '/\b(char\s*\(\s*\d+\s*\))/i',
                '/\b(concat\s*\()/i',
                '/\b(benchmark\s*\()/i',
                '/\b(sleep\s*\()/i',
                // 时间盲注
                '/\b(waitfor\s+delay\b)/i',
                '/\b(pg_sleep\b)/i',
                '/\b(dbms_lock\.sleep\b)/i',
                // 联合查询绕过
                '/\b(information_schema\b)/i',
                '/\b(mysql\.user\b)/i',
                '/\b(sys\.schema\b)/i',
            ],
        ],

        // XSS 跨站脚本
        'xss' => [
            'enabled' => env('WAF_RULE_XSS', true),
            'severity' => env('WAF_RULE_XSS_SEVERITY', 'high'),
            'patterns' => [
                '/<script\b[^>]*>/i',
                '/javascript\s*:/i',
                '/onerror\s*=/i',
                '/onload\s*=/i',
                '/onclick\s*=/i',
                '/onmouseover\s*=/i',
                '/onfocus\s*=/i',
                '/onblur\s*=/i',
                '/onchange\s*=/i',
                '/onsubmit\s*=/i',
                '/onreset\s*=/i',
                '/onselect\s*=/i',
                '/onkeydown\s*=/i',
                '/onkeyup\s*=/i',
                '/<iframe\b/i',
                '/<embed\b/i',
                '/<object\b/i',
                '/<svg\b[^>]*>\s*<script/i',
                '/document\.cookie/i',
                '/document\.location/i',
                '/eval\s*\(/i',
                '/String\.fromCharCode/i',
                '/<img[^>]+src\s*=\s*["\']?javascript/i',
                '/alert\s*\(/i', // 安全场景可放行
            ],
        ],

        // 路径穿越
        'path_traversal' => [
            'enabled' => env('WAF_RULE_PATH_TRAVERSAL', true),
            'severity' => env('WAF_RULE_PATH_TRAVERSAL_SEVERITY', 'high'),
            'patterns' => [
                '/\.\.\/\.\.\//i',
                '/\.\.\\\.\.\\/i',
                '/%2e%2e%2f/i',
                '/%2e%2e\\/i',
                '/%c0%ae%c0%ae/i', // UTF-8 编码绕过
                '/%252e%252e%252f/i', // 双重编码
                '/\.\.%252f/i',
                '/%c0\.\.%c0/i',
            ],
        ],

        // 命令注入
        'command_injection' => [
            'enabled' => env('WAF_RULE_CMD_INJECTION', true),
            'severity' => env('WAF_RULE_CMD_INJECTION_SEVERITY', 'critical'),
            'patterns' => [
                '/\b(cmd\.exe|command\.com|powershell|pwsh)\b/i',
                '/\b(wget|curl)\s+[-a-zA-Z]/i',
                '/`[^`]+`/i',        // 反引号执行
                '/\$\([^\)]+\)/i',     // $() 子shell
                '/\|(\s*[\w])/i',      // 管道执行
                '/;\s*(rm|del|shutdown|reboot|mkfs|dd|format)\b/i',
                '/\|\|(\s*[\w])/i',
                '/&&(\s*[\w])/i',
                '/\b(nc|netcat|ncat)\b/i',
                '/\b(perl|python|ruby)\s+-[ei]/i',
            ],
        ],

        // 文件包含 (LFI/RFI)
        'file_inclusion' => [
            'enabled' => env('WAF_RULE_FILE_INCLUSION', true),
            'severity' => env('WAF_RULE_FILE_INCLUSION_SEVERITY', 'high'),
            'patterns' => [
                '/\.\.\/.*\.(php|php\d?|phtml|inc|conf|sql)/i',
                '/\b(include|require|include_once|require_once)\s*\(/i',
                '/\b(file_get_contents|file_put_contents|fopen|fwrite|unlink)\s*\(/i',
                '/\b(base64_decode|rawurldecode|urldecode)\s*\(/i',
                '/\b(allow_url_include|auto_prepend_file|auto_append_file)\b/i',
            ],
        ],

        // SSRF 服务端请求伪造
        'ssrf' => [
            'enabled' => env('WAF_RULE_SSRF', true),
            'severity' => env('WAF_RULE_SSRF_SEVERITY', 'high'),
            'patterns' => [
                '/\b(metadata\.google\.internal|169\.254\.169\.254)\b/i', // GCP/AWS 元数据
                '/\b(100\.100\.100\.200)\b/i',                            // 阿里云元数据
                '/\b(localhost|127\.0\.0\.1|0\.0\.0\.0)\b.*\b(meta-data|metadata)\b/i',
                '/\b(10\.\d{1,3}\.\d{1,3}\.\d{1,3})\b.*\bmeta/i',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CC 攻击防护
    |--------------------------------------------------------------------------
    */
    'cc' => [
        'enabled' => env('WAF_CC_ENABLED', true),
        'mode' => env('WAF_CC_MODE', 'block'), // block | challenge | rate_limit

        // 频率限制（每 IP）
        'rate_limit' => [
            'max_requests' => env('WAF_CC_MAX_REQUESTS', 300),    // 窗口内最大请求数
            'window_seconds' => env('WAF_CC_WINDOW', 60),        // 时间窗口（秒）
            'ban_duration' => env('WAF_CC_BAN_DURATION', 300),   // 封禁时长（秒）
        ],

        // 行为分析
        'behavior' => [
            'enabled' => env('WAF_CC_BEHAVIOR', true),
            'aggressive_threshold' => env('WAF_CC_AGGRESSIVE', 100), // 每分钟请求 > N 标记为攻击性
            'path_spread_threshold' => env('WAF_CC_PATH_SPREAD', 20), // 访问不同路径 > N 标记为扫描
            'user_agent_empty_block' => env('WAF_CC_BLOCK_EMPTY_UA', true),
        ],

        // 挑战（验证码/JS挑战）
        'challenge' => [
            'after_failures' => env('WAF_CC_CHALLENGE_AFTER', 3), // N 次触发后启用挑战
            'ttl_seconds' => env('WAF_CC_CHALLENGE_TTL', 600),   // 挑战通过后有效期
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP 黑白名单
    |--------------------------------------------------------------------------
    */
    'ip_list' => [
        // 默认动作：黑名单拦截，白名单放行（跳过所有检测）
        'blacklist_mode' => env('WAF_IP_BLACKLIST_MODE', 'block'),    // block | challenge
        'whitelist_bypass_all' => env('WAF_IP_WHITELIST_BYPASS', true), // 白名单绕过所有检查

        // CDN 回源信任 IP 段
        'trusted_cidr' => [
            'cloudflare' => [
                '173.245.48.0/20',
                '103.21.244.0/22',
                '103.22.200.0/22',
                '103.31.4.0/22',
                '141.101.64.0/18',
                '108.162.192.0/18',
                '190.93.240.0/20',
                '188.114.96.0/20',
                '197.234.240.0/22',
                '198.41.128.0/17',
                '162.158.0.0/15',
                '104.16.0.0/13',
                '104.24.0.0/14',
                '172.64.0.0/13',
                '131.0.72.0/22',
            ],
            'aliyun_cdn' => [
                '118.31.56.0/22',
                '203.107.192.0/24',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 请求校验
    |--------------------------------------------------------------------------
    */
    'inspection' => [
        'max_body_size' => env('WAF_MAX_BODY_SIZE', 1024 * 100), // 最大请求体大小（100KB）
        'max_url_length' => env('WAF_MAX_URL_LENGTH', 2048),     // 最大 URL 长度
        'max_headers' => env('WAF_MAX_HEADERS', 50),             // 最大请求头数量
        'max_header_name_length' => env('WAF_MAX_HEADER_NAME', 50),
        'max_header_value_length' => env('WAF_MAX_HEADER_VALUE', 500),

        // 禁止的 HTTP 方法（REST API 场景）
        'blocked_methods' => ['TRACE', 'TRACK', 'CONNECT'],

        // 禁止的 User-Agent 特征
        'blocked_user_agents' => [
            'sqlmap',
            'nmap',
            'nikto',
            'acunetix',
            'nessus',
            'openvas',
            'burpsuite',
            'gobuster',
            'dirbuster',
            'wfuzz',
            'hydra',
            'medusa',
            'aircrack',
            'zmap',
            'masscan',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare 集成
    |--------------------------------------------------------------------------
    */
    'cloudflare' => [
        'enabled' => env('WAF_CLOUDFLARE_ENABLED', false),
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
        'email' => env('CLOUDFLARE_EMAIL'),
        'auto_sync_rules' => env('WAF_CLOUDFLARE_AUTO_SYNC', false),
        'security_level' => env('CLOUDFLARE_SECURITY_LEVEL', 'medium'), // under_attack | high | medium | low | essentially_off
        'challenge_passage' => env('CLOUDFLARE_CHALLENGE_PASSAGE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | 攻击事件日志
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => env('WAF_LOG_ENABLED', true),
        'log_request_body' => env('WAF_LOG_BODY', false),       // 谨慎开启，可能记录敏感数据
        'log_headers' => env('WAF_LOG_HEADERS', true),
        'retention_days' => env('WAF_LOG_RETENTION', 30),
        'alert_threshold' => env('WAF_ALERT_THRESHOLD', 100),   // 每分钟攻击次数超过此值触发告警
    ],

    /*
    |--------------------------------------------------------------------------
    | 监控与告警
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'stats_ttl_seconds' => env('WAF_STATS_TTL', 300),
        'alert_channels' => explode(',', env('WAF_ALERT_CHANNELS', 'email,notification')),
    ],
];
