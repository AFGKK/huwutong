/**
 * 互物通 — 小程序 web-view 环境检测与支付外跳引导
 * 供 H5 公开页 / SPA 共用（public/js 静态引入，无构建依赖）
 */
(function (global) {
  'use strict';

  var FLAG = 'hwt_from_miniprogram';

  function isMiniProgram() {
    try {
      if (global.__wxjs_environment === 'miniprogram') return true;
    } catch (e) { /* ignore */ }

    var ua = (global.navigator && global.navigator.userAgent) || '';
    if (/miniProgram/i.test(ua) || /miniProgram/i.test(ua.replace(/\s/g, ''))) return true;

    try {
      var q = new URLSearchParams(global.location.search || '');
      if (q.get('from') === 'miniprogram') {
        try { global.sessionStorage.setItem(FLAG, '1'); } catch (e2) { /* ignore */ }
        return true;
      }
    } catch (e3) { /* ignore */ }

    try {
      if (global.sessionStorage && global.sessionStorage.getItem(FLAG) === '1') return true;
    } catch (e4) { /* ignore */ }

    return false;
  }

  function markFromMiniProgram() {
    try { global.sessionStorage.setItem(FLAG, '1'); } catch (e) { /* ignore */ }
  }

  function copyText(text) {
    return new Promise(function (resolve, reject) {
      if (global.navigator && global.navigator.clipboard && global.navigator.clipboard.writeText) {
        global.navigator.clipboard.writeText(text).then(resolve).catch(function () {
          fallbackCopy(text) ? resolve() : reject(new Error('copy failed'));
        });
        return;
      }
      fallbackCopy(text) ? resolve() : reject(new Error('copy failed'));
    });
  }

  function fallbackCopy(text) {
    try {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      var ok = document.execCommand('copy');
      document.body.removeChild(ta);
      return ok;
    } catch (e) {
      return false;
    }
  }

  function currentUrl() {
    return global.location.href.split('#')[0];
  }

  /**
   * 挂载顶部提示条
   */
  function mountBanner(opts) {
    opts = opts || {};
    if (!isMiniProgram()) return null;
    if (document.getElementById('hwt-mp-banner')) return document.getElementById('hwt-mp-banner');

    var bar = document.createElement('div');
    bar.id = 'hwt-mp-banner';
    bar.setAttribute('role', 'status');
    bar.style.cssText = [
      'position:sticky',
      'top:0',
      'z-index:9999',
      'display:flex',
      'align-items:center',
      'justify-content:space-between',
      'gap:10px',
      'padding:10px 14px',
      'background:#fff7e6',
      'border-bottom:1px solid #ffd591',
      'color:#ad6800',
      'font-size:13px',
      'line-height:1.4',
      'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif',
    ].join(';');

    var text = document.createElement('div');
    text.style.flex = '1';
    text.textContent = opts.message || '小程序内可浏览与加购；微信支付/支付宝请复制链接，在手机浏览器中完成支付。';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = opts.buttonText || '复制链接';
    btn.style.cssText = [
      'flex-shrink:0',
      'border:none',
      'background:#fa8c16',
      'color:#fff',
      'border-radius:16px',
      'padding:6px 12px',
      'font-size:12px',
      'cursor:pointer',
    ].join(';');
    btn.onclick = function () {
      var url = opts.url || currentUrl();
      copyText(url).then(function () {
        btn.textContent = '已复制';
        setTimeout(function () { btn.textContent = opts.buttonText || '复制链接'; }, 1500);
      }).catch(function () {
        btn.textContent = '复制失败';
        setTimeout(function () { btn.textContent = opts.buttonText || '复制链接'; }, 1500);
      });
    };

    bar.appendChild(text);
    bar.appendChild(btn);
    document.body.insertBefore(bar, document.body.firstChild);
    return bar;
  }

  /**
   * 支付前拦截：返回 true 表示应阻止内嵌支付，改为外跳引导
   */
  function shouldEscapePayment(method) {
    if (!isMiniProgram()) return false;
    var m = (method || '').toLowerCase();
    if (m === 'balance' || m === 'prepaid' || m === 'mock') return false;
    return m === 'wxpay' || m === 'wechat' || m === 'alipay' || m === 'stripe' || m === 'paypal' || !m;
  }

  global.HwtMiniProgram = {
    isMiniProgram: isMiniProgram,
    markFromMiniProgram: markFromMiniProgram,
    copyText: copyText,
    currentUrl: currentUrl,
    mountBanner: mountBanner,
    shouldEscapePayment: shouldEscapePayment,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      if (isMiniProgram()) mountBanner();
    });
  } else if (isMiniProgram()) {
    mountBanner();
  }
})(typeof window !== 'undefined' ? window : this);
