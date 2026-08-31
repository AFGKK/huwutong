/**
 * 打开站内 H5（web-view）
 * 已登录时走一次性 SSO 桥接，把小程序账号同步到 H5 localStorage.auth_token
 *
 * 依赖：微信公众平台配置业务域名（与 SITE_URL 主机一致）
 */
const CONFIG = require('./config');
const api = require('./api');
const auth = require('./auth');

function getHost(url) {
  const m = String(url || '').match(/^https:\/\/([^\/?#]+)/i);
  return m ? m[1].toLowerCase() : '';
}

function siteHost() {
  return getHost(CONFIG.SITE_URL || CONFIG.PRICING_URL || '');
}

function isAllowedUrl(url) {
  if (!url || typeof url !== 'string') return false;
  if (url.indexOf('https://') !== 0) return false;
  const host = getHost(url);
  const allowed = siteHost();
  return !!host && (!allowed || host === allowed);
}

function withFromParam(url) {
  if (!url) return url;
  if (url.indexOf('from=') !== -1) return url;
  return url + (url.indexOf('?') === -1 ? '?' : '&') + 'from=miniprogram';
}

/**
 * 从完整 URL 抽出站内 path+query（供 bridge redirect）
 */
function toSitePath(url) {
  const host = siteHost();
  if (!host || !url) return '/products';
  const prefix = 'https://' + host;
  if (url.indexOf(prefix) === 0) {
    const path = url.slice(prefix.length) || '/';
    return path.charAt(0) === '/' ? path : '/' + path;
  }
  if (url.charAt(0) === '/') return url;
  return '/products';
}

function navigateWebview(url, title) {
  if (!isAllowedUrl(url)) {
    wx.showToast({ title: '链接无效', icon: 'none' });
    return;
  }

  const q = [
    'url=' + encodeURIComponent(url),
    'title=' + encodeURIComponent(title || '详情'),
  ].join('&');

  wx.navigateTo({
    url: '/pages/webview/webview?' + q,
    fail: () => {
      wx.setClipboardData({
        data: url,
        success: () => {
          wx.showModal({
            title: '链接已复制',
            content: '请在手机浏览器中打开：\n' + url,
            showCancel: false,
          });
        },
      });
    },
  });
}

/**
 * 打开站内页；已登录则先换 SSO code 再进 bridge
 * @param {string} url 完整 https URL
 * @param {string} [title]
 */
function open(url, title) {
  if (!isAllowedUrl(url)) {
    wx.showToast({ title: '链接无效', icon: 'none' });
    return;
  }

  const redirectPath = toSitePath(url);

  if (!auth.checkLogin()) {
    navigateWebview(withFromParam(url), title);
    return;
  }

  wx.showLoading({ title: '同步登录…', mask: true });
  api.post('/miniprogram/h5-sso', {})
    .then((res) => {
      wx.hideLoading();
      const data = (res && res.data) || {};
      const code = data.code;
      if (!code) {
        navigateWebview(withFromParam(url), title);
        return;
      }
      const bridge =
        (CONFIG.SITE_URL || '').replace(/\/$/, '') +
        '/miniprogram/bridge?code=' + encodeURIComponent(code) +
        '&redirect=' + encodeURIComponent(redirectPath);
      navigateWebview(bridge, title || '详情');
    })
    .catch(() => {
      wx.hideLoading();
      // SSO 失败仍打开目标页（游客）
      navigateWebview(withFromParam(url), title);
    });
}

function openPricing() {
  open(CONFIG.PRICING_URL, '购买 / 定价');
}

function openHelp() {
  open(CONFIG.HELP_URL, '帮助中心');
}

function openProducts() {
  open(CONFIG.PRODUCTS_URL || ((CONFIG.SITE_URL || '') + '/products'), '产品商城');
}

module.exports = {
  open,
  openPricing,
  openHelp,
  openProducts,
  isAllowedUrl,
  withFromParam,
  toSitePath,
};
