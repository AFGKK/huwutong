<?php

return [
    'program_name' => '互物通漏洞奖励计划',
    'scope' => [
        'api.huwutong.com',
        '*.huwutong.com',
        'SDK 包（js-sdk、python-sdk、java-sdk）',
        '移动端 API 接口',
    ],
    'out_of_scope' => [
        '无法升级的 Self-XSS',
        '社会工程学攻击',
        '拒绝服务（DoS）攻击',
        '针对基础设施的物理攻击',
        '非互物通控制的第三方服务（如 GitHub、AWS）',
        '未造成实际危害的限流绕过',
        '无可利用性的缺失 HTTP 安全头',
    ],
    'rewards' => [
        'critical' => '严重：$500 – $2,000+',
        'high' => '高危：$200 – $500',
        'medium' => '中危：$100 – $200',
        'low' => '低危：$50 – $100',
        'informational' => '信息：无现金奖励（仅致谢）',
    ],
    'rules' => [
        '尽可能提供可复现步骤与 PoC',
        '给予合理修复时间（严重问题通常约 90 天）',
        '未经许可不得访问或修改用户数据',
        '私下报告漏洞，修复前勿公开披露',
        '仅在自有账户上测试',
        '同一漏洞一份报告（重复报告将标记为重复）',
        '未经事先批准不得进行自动化扫描',
    ],
    'response_time' => '我们力争在 48 小时内确认收到，并在 5 个工作日内给出初步评估。',
    'disclosure_policy' => '我们采用协同披露。修复上线后，通常在 30 天后允许公开披露。',
    'legal_safe_harbor' => '对善意并遵守本政策的研究人员，我们不会采取法律行动。',
    'severity' => [
        'critical' => '严重',
        'high' => '高危',
        'medium' => '中危',
        'low' => '低危',
        'informational' => '信息',
    ],
    'rank' => [
        'gold' => '金牌',
        'silver' => '银牌',
        'bronze' => '铜牌',
        'honorable' => '荣誉',
    ],
];
