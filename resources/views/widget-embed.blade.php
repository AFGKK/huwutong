<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HWT License Widget</title>
    <script>window.WIDGET_I18N = @json(__('app.widget_embed'));</script>
    <style>
        :root {
            --primary: #1a73e8;
            --primary-light: #e8f0fe;
            --bg: #ffffff;
            --text: #202124;
            --text-secondary: #5f6368;
            --border: #dadce0;
            --success: #34a853;
            --warning: #fbbc04;
            --danger: #ea4335;
            --radius: 8px;
            --shadow: 0 1px 3px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            font-size: 14px;
            line-height: 1.5;
        }

        /* ─── Layout ─── */
        .widget-header {
            display: flex; align-items: center; gap: 12px;
            padding: 16px; border-bottom: 1px solid var(--border);
        }
        .widget-header .logo { width: 32px; height: 32px; border-radius: 6px; }
        .widget-header h1 { font-size: 16px; font-weight: 600; flex: 1; }
        .widget-header .brand-name { font-size: 12px; color: var(--text-secondary); }

        .widget-content { padding: 16px; }
        .widget-footer {
            padding: 12px 16px; border-top: 1px solid var(--border);
            text-align: center; font-size: 11px; color: var(--text-secondary);
        }

        /* ─── Stats ─── */
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
        .stat-card {
            padding: 12px; border-radius: var(--radius); border: 1px solid var(--border);
            text-align: center;
        }
        .stat-card .value { font-size: 24px; font-weight: 700; }
        .stat-card .label { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }

        /* ─── Table ─── */
        .section-title {
            font-size: 13px; font-weight: 600; margin: 16px 0 8px;
            display: flex; justify-content: space-between; align-items: center;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px 6px; text-align: left; border-bottom: 1px solid var(--border); font-size: 12px; }
        th { font-weight: 600; color: var(--text-secondary); font-size: 11px; text-transform: uppercase; }

        .status-badge {
            display: inline-block; padding: 2px 6px; border-radius: 4px;
            font-size: 11px; font-weight: 500;
        }
        .status-active { background: #e6f4ea; color: #1e7e34; }
        .status-expired { background: #fce8e6; color: #c5221f; }
        .status-pending { background: #fef7e0; color: #ea8600; }
        .status-suspended { background: #fef7e0; color: #ea8600; }

        .license-key { font-family: 'SF Mono', 'Cascadia Code', monospace; font-size: 11px; }

        /* ─── States ─── */
        .loading { display: flex; justify-content: center; align-items: center; min-height: 200px; }
        .spinner { width: 32px; height: 32px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .error-state { text-align: center; padding: 40px 20px; color: var(--danger); }
        .error-state .icon { font-size: 40px; margin-bottom: 8px; }

        .empty-state { text-align: center; padding: 40px 20px; color: var(--text-secondary); }

        /* ─── Responsive ─── */
        @media (max-width: 400px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat-card { padding: 8px; }
            .stat-card .value { font-size: 20px; }
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="widget-header" id="widget-header">
            <img class="logo" id="logo" src="/images/logo.svg" alt="Logo" onerror="this.style.display='none'">
            <div>
                <h1 id="brand-name">HWT License</h1>
                <span class="brand-name" id="customer-name"></span>
            </div>
        </div>
        <div class="widget-content" id="widget-content">
            <div class="loading" id="loading-state">
                <div class="spinner"></div>
            </div>
            <div class="error-state" id="error-state" style="display:none">
                <div class="icon">⚠️</div>
                <p id="error-message">{{ __('app.widget_embed.load_fail') }}</p>
            </div>
        </div>
        <div class="widget-footer">
            Powered by <strong>HWT License</strong>
        </div>
    </div>

    <script>
    // ─── Widget 主程序 ───
    (function() {
        'use strict';
        var I = window.WIDGET_I18N || {};

        // 从 URL 获取 token
        const params = new URLSearchParams(window.location.search);
        const TOKEN = params.get('token');

        if (!TOKEN) {
            showError(I.missing_token || '');
            return;
        }

        // ─── 解析品牌配置 ───
        const brandColor = params.get('color') || '#1a73e8';
        const brandName = params.get('brand') || 'HWT License';
        document.documentElement.style.setProperty('--primary', brandColor);
        document.getElementById('brand-name').textContent = brandName;

        // ─── 加载数据 ───
        loadWidgetData();

        async function loadWidgetData() {
            try {
                const headers = { 'Authorization': `Bearer ${TOKEN}`, 'Accept': 'application/json' };

                // 并行获取数据 + 配置
                const [dataRes, configRes] = await Promise.all([
                    fetch('/api/widget/data', { headers }),
                    fetch('/api/widget/config', { headers }),
                ]);

                if (!dataRes.ok) throw new Error((I.data_request_fail || '').replace(':status', dataRes.status));

                const data = await dataRes.json();
                const config = await configRes.json();

                // 应用品牌色
                if (config?.data?.primary_color) {
                    document.documentElement.style.setProperty('--primary', config.data.primary_color);
                }
                if (config?.data?.brand_name) {
                    document.getElementById('brand-name').textContent = config.data.brand_name;
                }

                renderWidget(data?.data);
            } catch (err) {
                showError(err.message);
                // 通知父页面
                notifyParent('error', err.message);
            }
        }

        function renderWidget(data) {
            if (!data) { showError(I.no_data || ''); return; }

            // 客户名称
            if (data.customer?.name) {
                document.getElementById('customer-name').textContent = data.customer.name;
            }

            const stats = data.stats || {};
            const licenses = data.licenses || [];
            const devices = data.devices || [];

            document.getElementById('loading-state').style.display = 'none';

            const content = document.getElementById('widget-content');

            // Stats
            let html = '<div class="stats-grid">';
            html += statCard(stats.total_licenses || 0, I.stat_all || '', 'var(--primary)');
            html += statCard(stats.active_licenses || 0, I.stat_active || '', 'var(--success)');
            html += statCard(stats.expiring_soon || 0, I.stat_expiring || '', 'var(--warning)');
            html += statCard(stats.expired || 0, I.stat_expired || '', 'var(--danger)');
            html += '</div>';

            // Licenses Table
            html += '<div class="section-title">License <span style="font-weight:400;font-size:11px;color:var(--text-secondary)">' + (I.licenses_recent || '') + '</span></div>';
            if (licenses.length > 0) {
                html += '<table><thead><tr><th>Key</th><th>' + (I.col_status || '') + '</th><th>' + (I.col_product || '') + '</th><th>' + (I.col_expires || '') + '</th></tr></thead><tbody>';
                licenses.forEach(l => {
                    const statusClass = `status-${l.status === 'active' ? 'active' : l.status === 'expired' ? 'expired' : 'pending'}`;
                    html += `<tr>
                        <td class="license-key">${l.license_key}</td>
                        <td><span class="status-badge ${statusClass}">${statusLabel(l.status)}</span></td>
                        <td>${l.product?.name || '-'}</td>
                        <td style="font-size:11px">${l.expires_at ? l.expires_at.substring(0,10) : (I.lifetime || '')}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="empty-state"><p style="font-size:13px">' + (I.no_licenses || '') + '</p></div>';
            }

            // Devices
            if (devices.length > 0) {
                html += '<div class="section-title" style="margin-top:20px">' + (I.devices || '') + ' <span style="font-weight:400;font-size:11px;color:var(--text-secondary)">' + (I.devices_recent || '').replace(':n', devices.length) + '</span></div>';
                html += '<table><thead><tr><th>' + (I.col_name || '') + '</th><th>' + (I.col_platform || '') + '</th><th>' + (I.col_last_seen || '') + '</th></tr></thead><tbody>';
                devices.forEach(d => {
                    html += `<tr>
                        <td>${d.name ||''}</td>
                        <td>${d.platform || '-'}</td>
                        <td style="font-size:11px">${d.last_seen_at ? new Date(d.last_seen_at).toLocaleDateString() : '-'}</td>
                    </tr>`;
                });
                html += '</tbody></table>';
            }

            content.innerHTML = html;

            // 通知父页面数据已就绪
            notifyParent('ready', { stats, licenseCount: licenses.length, deviceCount: devices.length });
        }

        function statCard(value, label, color) {
            return `<div class="stat-card">
                <div class="value" style="color:${color}">${value}</div>
                <div class="label">${label}</div>
            </div>`;
        }

        function statusLabel(status) {
            const map = {
                active: I.status_active,
                expired: I.status_expired,
                pending: I.status_pending,
                suspended: I.status_suspended,
                revoked: I.status_revoked,
                frozen: I.status_frozen,
            };
            return map[status] || status;
        }

        function showError(msg) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
            document.getElementById('error-message').textContent = msg;
        }

        // ─── postMessage 通信 ───
        function notifyParent(type, payload) {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    source: 'hwt-widget',
                    type: type,
                    payload: payload,
                }, '*');
            }
        }

        // ─── 监听父页面消息 ───
        window.addEventListener('message', function(event) {
            if (event.data?.source === 'hwt-host') {
                switch (event.data.type) {
                    case 'refresh':
                        loadWidgetData();
                        break;
                    case 'setColor':
                        if (event.data.color) {
                            document.documentElement.style.setProperty('--primary', event.data.color);
                        }
                        break;
                }
            }
        });

        // 告诉父页面 iframe 已就绪
        notifyParent('loaded', { height: document.body.scrollHeight });
    })();
    </script>
</body>
</html>
