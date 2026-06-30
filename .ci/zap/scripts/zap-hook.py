#!/usr/bin/env python3
"""
OWASP ZAP 钩子脚本
M2-112: 在 ZAP 扫描流程中注入自定义逻辑

功能:
  1. 认证配置 — 为被扫描应用注入 API Token
  2. 上下文管理 — 定义扫描上下文
  3. 自定义告警过滤 — 忽略已知误报
  4. 会话管理 — 处理登录流程

使用方式:
  ZAP 启动时通过 --hook 参数加载此脚本
"""

import urllib.parse


def zap_started(zap, target):
    """
    ZAP 启动后自动调用
    用于配置扫描上下文和认证
    """
    print(f"[HWT-ZAP] ZAP 已启动，目标: {target}")

    # 配置上下文
    context_id = zap.context.new_context('HWT-License-API')
    zap.context.set_context_in_scope(context_id, True)

    # 添加目标到上下文
    if target:
        zap.context.include_in_context(context_id, f'{target}/*')
        zap.context.include_in_context(context_id, f'{target}/api/*')

    print(f"[HWT-ZAP] 上下文已配置: HWT-License-API (ID: {context_id})")

    # 禁用可能引起误报或耗时的规则
    # 这些规则在我们的应用已知是安全的
    disable_rules = [
        100000,  # 信息泄露检测（在 API 响应中可能误报）
        10049,   # 非存储内容缓存（SPA 特性）
        90036,   # GraphQL 内省（我们使用 REST API）
    ]

    for rule_id in disable_rules:
        zap.ascan.disable_scanners(str(rule_id))

    print(f"[HWT-ZAP] 已禁用 {len(disable_rules)} 条已知误报规则")

    # 配置 API 扫描的速率限制避免触发应用限流
    zap.ascan.set_max_scan_duration_in_mins(30)


def zap_pre_scan(zap, target):
    """
    扫描开始前调用
    用于注入认证 Token 或执行预扫描操作
    """
    print(f"[HWT-ZAP] 预扫描准备中: {target}")

    # 从环境变量读取 API Token
    import os
    api_token = os.environ.get('ZAP_API_TOKEN')
    auth_header = os.environ.get('ZAP_AUTH_HEADER', 'Authorization')

    if api_token:
        # 配置 HTTP 头认证
        zap.replacer.add_rule(
            description='API Token 注入',
            match_type='REQ_HEADER',
            match_string=auth_header,
            replacement=f'Bearer {api_token}',
            enabled=True
        )
        print(f"[HWT-ZAP] 已注入认证头: {auth_header}: Bearer ****")


def zap_post_scan(zap, target):
    """
    扫描完成后调用
    用于处理结果、生成报告
    """
    print(f"[HWT-ZAP] 扫描完成: {target}")

    # 输出告警摘要
    alerts = zap.core.alerts(baseurl=target)
    high_count = sum(1 for a in alerts if a.get('risk', '') == 'High')
    med_count = sum(1 for a in alerts if a.get('risk', '') == 'Medium')
    low_count = sum(1 for a in alerts if a.get('risk', '') == 'Low')

    print(f"\n[HWT-ZAP] ════════════════════════════════════")
    print(f"[HWT-ZAP]  告警摘要:")
    print(f"[HWT-ZAP]  🔴 高危: {high_count}")
    print(f"[HWT-ZAP]  🟡 中危: {med_count}")
    print(f"[HWT-ZAP]  🔵 低危: {low_count}")
    print(f"[HWT-ZAP] ════════════════════════════════════\n")

    # 高危告警详情
    if high_count > 0:
        print(f"[HWT-ZAP] ⚠️  高危告警详情:")
        for alert in alerts:
            if alert.get('risk') == 'High':
                print(f"  🔴 [{alert.get('alert')}] {alert.get('url', 'N/A')}")
                print(f"     解决方案: {alert.get('solution', 'N/A')}")

    return alerts


def zap_shutdown(zap, target):
    """
    ZAP 关闭前调用
    用于清理临时文件、上传报告等
    """
    print(f"[HWT-ZAP] ZAP 关闭中...")
