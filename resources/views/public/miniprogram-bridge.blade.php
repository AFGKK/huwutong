<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.miniprogram_bridge.title') }}</title>
    <script>window.MP_BRIDGE_I18N = @json(__('app.miniprogram_bridge'));</script>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Microsoft YaHei", sans-serif;
            background: #f5f5f5;
            color: #1f1f1f;
        }
        .box { text-align: center; padding: 24px; max-width: 320px; }
        .title { font-size: 16px; font-weight: 600; margin-bottom: 8px; }
        .hint { font-size: 13px; color: #5f6368; line-height: 1.5; }
        .err { color: #ea4335; margin-top: 12px; font-size: 13px; }
        .btn {
            margin-top: 16px;
            display: inline-block;
            padding: 10px 20px;
            background: #1a73e8;
            color: #fff;
            border-radius: 22px;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="box">
    <div class="title" id="title">{{ __('app.miniprogram_bridge.syncing') }}</div>
    <div class="hint" id="hint">{{ __('app.miniprogram_bridge.please_wait') }}</div>
    <div class="err" id="err" hidden></div>
    <a class="btn" id="fallback" href="/products" hidden>{{ __('app.miniprogram_bridge.enter_store') }}</a>
</div>
<script>
(function () {
    var I = window.MP_BRIDGE_I18N || {};
    function qs(name) {
        var m = new RegExp('[?&]' + name + '=([^&#]*)').exec(window.location.search);
        return m ? decodeURIComponent(m[1].replace(/\+/g, ' ')) : '';
    }

    function safeRedirect(raw) {
        if (!raw) return '/products';
        if (raw.charAt(0) !== '/' || raw.indexOf('//') === 0 || raw.indexOf('://') !== -1) {
            return '/products';
        }
        if (raw.indexOf('/miniprogram/bridge') === 0) {
            return '/products';
        }
        return raw;
    }

    var code = qs('code');
    var redirect = safeRedirect(qs('redirect') || '/products');
    var titleEl = document.getElementById('title');
    var hintEl = document.getElementById('hint');
    var errEl = document.getElementById('err');
    var fallbackEl = document.getElementById('fallback');

    function fail(msg) {
        titleEl.textContent = I.fail_title ||'';
        hintEl.textContent = I.fail_hint ||'';
        errEl.hidden = false;
        errEl.textContent = msg || I.invalid ||'';
        fallbackEl.hidden = false;
        fallbackEl.href = redirect;
    }

    if (!code) {
        try { sessionStorage.setItem('hwt_from_miniprogram', '1'); } catch (e) {}
        window.location.replace(redirect);
        return;
    }

    fetch('/api/miniprogram/h5-sso/exchange', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ code: code })
    })
        .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
        .then(function (r) {
            var data = (r.body && r.body.data) || {};
            if (!r.ok || !data.token) {
                var msg = (r.body && r.body.error && r.body.error.message) || (r.body && r.body.message) || (I.exchange_fail ||'');
                fail(msg);
                return;
            }
            try {
                localStorage.setItem('auth_token', data.token);
                if (data.user) {
                    localStorage.setItem('user', JSON.stringify(data.user));
                }
                sessionStorage.setItem('hwt_from_miniprogram', '1');
            } catch (e) { /* private mode */ }
            window.location.replace(redirect);
        })
        .catch(function () {
            fail(I.network ||'');
        });
})();
</script>
</body>
</html>
