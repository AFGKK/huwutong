{{-- 官网公告横幅：读取 /api/announce-banners/active --}}
<div id="hwt-announce-root" class="hwt-announce-root" aria-live="polite"></div>
<style>
.hwt-announce-root {
    position: fixed;
    top: 4rem;
    left: 0;
    right: 0;
    z-index: 45;
}
@media (min-width: 768px) {
    .hwt-announce-root { top: 5rem; }
}
.hwt-announce {
    display: flex; align-items: center; justify-content: center; gap: 12px;
    padding: 10px 40px 10px 16px; font-size: 13px; line-height: 1.5; position: relative;
}
.hwt-announce--info { background: #f8fafc; color: var(--pg-primary); border-bottom: 1px solid #e2e8f0; }
.hwt-announce--warning { background: #fffbeb; color: #92400e; border-bottom: 1px solid #fde68a; }
.hwt-announce--danger { background: #fef2f2; color: #991b1b; border-bottom: 1px solid #fecaca; }
.hwt-announce--success { background: #ecfdf5; color: #065f46; border-bottom: 1px solid #a7f3d0; }
.hwt-announce__title { font-weight: 600; margin-right: 4px; }
.hwt-announce__link { color: inherit; text-decoration: underline; font-weight: 500; white-space: nowrap; }
.hwt-announce__close {
    position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
    border: 0; background: transparent; cursor: pointer; font-size: 18px; line-height: 1;
    color: inherit; opacity: 0.7; padding: 4px 8px;
}
.hwt-announce__close:hover { opacity: 1; }
@media (max-width: 640px) {
    .hwt-announce { padding-right: 36px; font-size: 12px; text-align: center; flex-wrap: wrap; }
}
</style>
<script>
(function () {
    var root = document.getElementById('hwt-announce-root');
    if (!root) return;
    var oneDay = 24 * 60 * 60 * 1000;
    function dismissed(id) {
        try {
            var ts = parseInt(localStorage.getItem('banner_dismissed_' + id) || '0', 10);
            return ts && (Date.now() - ts < oneDay);
        } catch (e) { return false; }
    }
    function dismiss(id) {
        try { localStorage.setItem('banner_dismissed_' + id, String(Date.now())); } catch (e) {}
        var el = document.getElementById('hwt-announce-' + id);
        if (el) el.remove();
    }
    fetch('/api/announce-banners/active', { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (payload) {
            var list = (payload && (payload.data || payload)) || [];
            if (!Array.isArray(list)) list = [];
            list.filter(function (b) { return b && b.id && !dismissed(b.id); }).forEach(function (b) {
                var type = (b.type || 'info').toLowerCase();
                if (['info', 'warning', 'danger', 'success'].indexOf(type) < 0) type = 'info';
                var wrap = document.createElement('div');
                wrap.id = 'hwt-announce-' + b.id;
                wrap.className = 'hwt-announce hwt-announce--' + type;
                var html = '';
                if (b.title) html += '<span class="hwt-announce__title">' + String(b.title).replace(/</g, '&lt;') + '：</span>';
                html += '<span>' + (b.content || '') + '</span>';
                if (b.link_url) {
                    html += ' <a class="hwt-announce__link" href="' + String(b.link_url).replace(/"/g, '') + '" target="_blank" rel="noopener noreferrer">'
                        + (b.link_text || @json(__('app.actions.view'))) + '</a>';
                }
                wrap.innerHTML = html;
                if (b.can_close !== false && b.can_close !== 0) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'hwt-announce__close';
                    btn.setAttribute('aria-label', @json(__('app.actions.close')));
                    btn.textContent = '×';
                    btn.onclick = function () { dismiss(b.id); };
                    wrap.appendChild(btn);
                }
                root.appendChild(wrap);
            });
        })
        .catch(function () {});
})();
</script>
