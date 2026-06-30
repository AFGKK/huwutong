<?php

// M3-38 AI 合规报告生成配置

return [
    'frameworks' => [
        'gdpr' => [
            'name' => 'GDPR',
            'full_name' => 'General Data Protection Regulation',
            'sections' => [
                'data_processing' => '数据处理活动记录',
                'data_subject_rights' => '数据主体权利',
                'consent_management' => '同意管理',
                'data_breach' => '数据泄露通知',
                'dpa' => '数据处理协议',
                'cross_border' => '跨境数据传输',
                'data_protection_officer' => '数据保护官',
                'privacy_notice' => '隐私声明',
            ],
        ],
        'soc2' => [
            'name' => 'SOC 2',
            'full_name' => 'Service Organization Control 2',
            'sections' => [
                'security' => '安全(CC): 防火墙/WAF/IDS/IPS/漏洞扫描/渗透测试',
                'availability' => '可用性(A): 灾备/冗余/监控/SLA/故障恢复',
                'processing_integrity' => '处理完整性(PI): 数据验证/事务完整性',
                'confidentiality' => '保密性(C): 加密/访问控制/数据分类',
                'privacy' => '隐私(P): PII处理/同意/披露',
                'vendor_management' => '供应商管理',
                'incident_response' => '事件响应',
                'change_management' => '变更管理',
            ],
        ],
        'iso27001' => [
            'name' => 'ISO 27001',
            'full_name' => 'ISO/IEC 27001',
            'sections' => [
                'context' => '组织环境',
                'leadership' => '领导作用',
                'planning' => '规划(风险评估/风险处置)',
                'support' => '支持(资源/能力/意识/沟通/文件化)',
                'operation' => '运行(风险评估/风险处置/变更/外包)',
                'performance' => '绩效评价(监视/测量/内审/管理评审)',
                'improvement' => '改进(不符合/纠正/持续改进)',
                'annex_a_controls' => '附录A控制项(93项/A.5-A.18)',
            ],
        ],
    ],

    'ai' => [
        'provider' => env('COMPLIANCE_AI_PROVIDER', 'openai'),
        'model' => env('COMPLIANCE_AI_MODEL', 'gpt-4'),
        'temperature' => 0.3,
        'max_tokens' => 4000,
        'prompt_template' => '基于以下数据生成合规报告：{context}',
    ],

    'report' => [
        'formats' => ['html', 'pdf', 'json'],
        'include_evidence_count' => true,
        'include_gap_analysis' => true,
        'include_recommendations' => true,
        'auto_generate_cover' => true,
        'default_language' => 'zh-CN',
    ],
];
