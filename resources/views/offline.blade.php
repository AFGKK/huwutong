<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>离线 - HWT License</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1d1e1f;
            color: #bfcbd9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .offline-card {
            text-align: center;
            max-width: 400px;
            padding: 40px;
            background: #2c2c2d;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .offline-icon { font-size: 64px; margin-bottom: 16px; }
        h1 { font-size: 24px; margin-bottom: 8px; color: #e6e6e6; }
        p { font-size: 14px; line-height: 1.6; color: #909399; margin-bottom: 24px; }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background: #409eff;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #66b1ff; }
        .hint { font-size: 12px; color: #606266; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon">📡</div>
        <h1>网络连接已断开</h1>
        <p>当前处于离线状态，部分功能可能不可用。<br>请检查网络连接后重试。</p>
        <button class="btn" onclick="window.location.reload()">重新连接</button>
        <div class="hint">HWT License - PWA 离线模式</div>
    </div>
</body>
</html>
