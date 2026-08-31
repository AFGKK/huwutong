/**
 * 站内 H5 web-view（定价 / 帮助等）
 * 依赖：微信公众平台 → 开发 → 开发管理 → 开发设置 → 业务域名
 */
const webview = require('../../utils/webview');

Page({
  data: {
    src: '',
    rawUrl: '',
    showFallback: false,
  },

  onLoad(options) {
    const rawUrl = decodeURIComponent(options.url || '');
    const title = decodeURIComponent(options.title || '详情');

    wx.setNavigationBarTitle({ title: title });

    if (!webview.isAllowedUrl(rawUrl)) {
      this.setData({ rawUrl: rawUrl, showFallback: true, src: '' });
      return;
    }

    this.setData({
      src: webview.withFromParam(rawUrl),
      rawUrl: rawUrl,
      showFallback: false,
    });
  },

  onError() {
    this.setData({ showFallback: true });
  },

  onMessage() {
    // H5 postMessage 预留
  },

  onCopy() {
    const url = this.data.rawUrl || this.data.src;
    if (!url) return;
    wx.setClipboardData({
      data: url,
      success: () => wx.showToast({ title: '已复制', icon: 'success' }),
    });
  },

  onBack() {
    wx.navigateBack({ fail: () => wx.switchTab({ url: '/pages/mine/mine' }) });
  },
});
