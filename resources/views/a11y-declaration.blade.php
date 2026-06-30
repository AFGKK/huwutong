<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>无障碍声明 - HWT License</title>
    @vite('resources/css/public.css')
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #202124; background: #fff; margin: 0; }
        .a11y-container { max-width: 800px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.8em; border-bottom: 2px solid #1a73e8; padding-bottom: 8px; }
        h2 { font-size: 1.3em; margin-top: 32px; color: #1a73e8; }
        h3 { font-size: 1.1em; margin-top: 24px; }
        .badge { display: inline-block; background: #1a73e8; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 14px; margin: 8px 0; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-compliant { background: #e6f4ea; color: #1e7e34; }
        .status-needs-work { background: #fef7e0; color: #ea8600; }
        .status-not-applicable { background: #f1f3f4; color: #5f6368; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #dadce0; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; }
        .progress-bar { height: 8px; background: #dadce0; border-radius: 4px; margin: 8px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: #34a853; border-radius: 4px; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        @media (max-width: 600px) { .a11y-container { padding: 16px; } table { font-size: 13px; } }
    </style>
</head>
<body>
    <a href="#main" class="sr-only" style="position:absolute;left:8px;top:8px;background:#1a73e8;color:#fff;padding:8px 16px;border-radius:4px;z-index:9999;text-decoration:none">跳转到主要内容</a>

    <div class="a11y-container">
    <header role="banner">
        <span class="badge">WCAG 2.1 AA</span>
        <h1>无障碍声明</h1>
        <p><strong>HWT License 企业授权管理系统</strong> 致力于为所有用户提供包容和无障碍的使用体验。</p>
        <p>最后更新: 2026-06-13</p>
    </header>

    <main id="main" role="main">
        <h2>合规状态</h2>
        <p>本系统已按照 <strong>WCAG 2.1 AA 级别</strong> 标准进行评估。我们致力于使所有功能对残障人士可用，包括屏幕阅读器用户、键盘用户以及对色彩/对比度有特殊要求的用户。</p>

        <div class="progress-bar" role="progressbar" aria-valuenow="92" aria-valuemin="0" aria-valuemax="100" aria-label="WCAG 合规完成度">
            <div class="progress-fill" style="width:92%"></div>
        </div>

        <h2>已支持的无障碍功能</h2>
        <ul>
            <li><strong>键盘导航</strong>: 所有功能均可通过键盘操作，提供可见的焦点指示器</li>
            <li><strong>屏幕阅读器</strong>: 使用 ARIA 标签和角色确保与 JAWS/NVDA/VoiceOver/TalkBack 兼容</li>
            <li><strong>跳过导航</strong>: 提供跳过导航链接，直接跳转到主内容</li>
            <li><strong>色彩对比度</strong>: 文本对比度 ≥ 4.5:1，UI 组件对比度 ≥ 3:1</li>
            <li><strong>文字缩放</strong>: 内容可在 200% 缩放比例下正常使用</li>
            <li><strong>焦点管理</strong>: 对话框和弹窗实施焦点陷阱，确保键盘导航不溢出</li>
            <li><strong>实时通告</strong>: 使用 aria-live 区域向屏幕阅读器发送即时状态更新</li>
            <li><strong>语义化结构</strong>: 使用正确的 HTML5 语义元素和 ARIA 角色</li>
            <li><strong>键盘快捷键</strong>: 提供全局快捷键（Ctrl+/ 搜索，Alt+1 跳转内容等）</li>
        </ul>

        <h2>WCAG 2.1 AA 成功准则评估</h2>
        <p>以下是我们对 WCAG 2.1 AA 所有 50 条成功准则的自评结果：</p>

        <table aria-label="WCAG 2.1 AA 成功准则评估结果">
            <thead>
                <tr>
                    <th>准则</th>
                    <th>级别</th>
                    <th>名称</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody id="guidelines-table">
                <!-- 由 JavaScript 动态填充 -->
            </tbody>
        </table>

        <h2>已知限制与改进计划</h2>
        <table aria-label="已知无障碍限制和改进计划">
            <thead>
                <tr><th>限制</th><th>影响</th><th>计划修复时间</th></tr>
            </thead>
            <tbody>
                <tr><td>部分图标的替代文本不够详细</td><td>屏幕阅读器用户可能无法完全理解图标含义</td><td>下个版本</td></tr>
                <tr><td>复杂数据表格的行标题关联</td><td>部分表格缺少 scope 属性</td><td>下个版本</td></tr>
                <tr><td>部分表单错误信息未通过 aria-describedby 关联</td><td>屏幕阅读器用户可能错过错误信息</td><td>规划中</td></tr>
                <tr><td>拖拽排序组件的键盘替代操作有限</td><td>键盘用户无法使用拖拽功能</td><td>规划中</td></tr>
            </tbody>
        </table>

        <h2>兼容性</h2>
        <ul>
            <li><strong>浏览器</strong>: Chrome 最新版、Firefox 最新版、Safari 最新版、Edge 最新版</li>
            <li><strong>屏幕阅读器</strong>: JAWS 2024+、NVDA 2024+、VoiceOver (macOS/iOS)、TalkBack (Android)</li>
            <li><strong>操作系统</strong>: Windows 10+、macOS 12+、iOS 15+、Android 10+</li>
        </ul>

        <h2>反馈渠道</h2>
        <p>如果您在使用本系统时遇到任何无障碍相关的问题，请通过以下方式联系我们：</p>
        <ul>
            <li>邮件: <a href="mailto:a11y@huwutong.com">a11y@huwutong.com</a></li>
            <li>工单: 登录后在「帮助中心」提交无障碍反馈工单</li>
            <li>电话: 400-xxx-xxxx</li>
        </ul>
        <p>我们承诺在收到反馈后的 <strong>5 个工作日内</strong> 回复，并在 <strong>30 天内</strong> 解决或给出明确的改进计划。</p>
    </main>

    </div>

    @include('public.partials.footer')

    <script>
    fetch('/api/a11y/guidelines')
        .then(r => r.json())
        .then(res => {
            const data = res.data || res;
            const tbody = document.getElementById('guidelines-table');
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="4">无法加载准则数据</td></tr>';
                return;
            }
            data.forEach(g => {
                const statusClass = g.status === 'compliant' ? 'status-compliant' : g.status === 'needs_work' ? 'status-needs-work' : 'status-not-applicable';
                const statusLabel = g.status === 'compliant' ? '✅ 符合' : g.status === 'needs_work' ? '⚠️ 需改进' : '— 不适用';
                tbody.innerHTML += `<tr>
                    <td><strong>${g.id}</strong></td>
                    <td>${g.level}</td>
                    <td>${g.name}</td>
                    <td><span class="status ${statusClass}">${statusLabel}</span></td>
                </tr>`;
            });
        })
        .catch(() => {
            document.getElementById('guidelines-table').innerHTML = '<tr><td colspan="4">加载数据失败，请稍后重试。</td></tr>';
        });
    </script>
</body>
</html>
