/**
 * D-31: License 查询结果页
 * A1: 按状态展示「下一步」引导（对齐 H5）
 * A3: 客服入口
 */
const { formatDate, getStatusLabel, getStatusColor } = require('../../utils/helpers');
const auth = require('../../utils/auth');
const api = require('../../utils/api');
const CONFIG = require('../../utils/config');
const webview = require('../../utils/webview');

Page({
  data: {
    license: null,
    isLoggedIn: false,
    statusLabel: '',
    statusColor: '',
    isExpired: false,
    canActivate: false,
    createdAtText: '—',
    expiresAtText: '永久有效',
    devices: [],
    // A1 引导类型: active | pending | expired | suspended | revoked
    guideType: 'active',
    guideSummary: '',
    pricingUrl: CONFIG.PRICING_URL || '',
    helpUrl: CONFIG.HELP_URL || '',
    contactUrl: CONFIG.CONTACT_URL || '',
    // A4
    subscribeEnabled: false,
    subscribeTemplateId: '',
    canSubscribe: false,
    subscribeBusy: false,
  },

  onLoad(options) {
    this.applyLicenseFromOptions(options);
    this.loadSubscribeConfig();
  },

  onShow() {
    this.setData({ isLoggedIn: auth.checkLogin() });
    this.refreshCanSubscribe();
  },

  loadSubscribeConfig() {
    api.get('/miniprogram/subscribe-config')
      .then((res) => {
        const data = (res && res.data) || res || {};
        const enabled = !!(data.enabled && data.template_id);
        this.setData({
          subscribeEnabled: enabled,
          subscribeTemplateId: data.template_id || CONFIG.SUBSCRIBE_TEMPLATE_ID || '',
        });
        this.refreshCanSubscribe();
      })
      .catch(() => {
        // 后端未配置时静默
      });
  },

  refreshCanSubscribe() {
    const lic = this.data.license;
    const can = this.data.subscribeEnabled
      && this.data.isLoggedIn
      && lic
      && lic.expires_at
      && !lic.is_expired
      && (lic.status === 'active' || !lic.status);
    this.setData({ canSubscribe: !!can });
  },

  onSubscribeExpiry() {
    if (this.data.subscribeBusy || !this.data.canSubscribe) return;
    const tmplId = this.data.subscribeTemplateId;
    if (!tmplId) {
      wx.showToast({ title: '未配置提醒模板', icon: 'none' });
      return;
    }

    this.setData({ subscribeBusy: true });
    const that = this;

    wx.requestSubscribeMessage({
      tmplIds: [tmplId],
      success(r) {
        const status = r[tmplId];
        if (status !== 'accept') {
          wx.showToast({ title: '未授权提醒', icon: 'none' });
          that.setData({ subscribeBusy: false });
          return;
        }
        api.post('/miniprogram/subscribe-expiry', {
          license_key: that.data.license.license_key,
          remind_days: 7,
        }).then(() => {
          wx.showToast({ title: '已订阅过期提醒', icon: 'success' });
        }).catch(() => {
          // api 已 toast
        }).finally(() => {
          that.setData({ subscribeBusy: false });
        });
      },
      fail() {
        wx.showToast({ title: '订阅调起失败', icon: 'none' });
        that.setData({ subscribeBusy: false });
      },
    });
  },

  resolveGuideType(license, isExpired) {
    const status = String(license.status || '').toLowerCase();
    if (isExpired || status === 'expired') return 'expired';
    if (status === 'suspended' || status === 'frozen') return 'suspended';
    if (status === 'revoked') return 'revoked';
    if (!license.activated || status === 'pending') return 'pending';
    return 'active';
  },

  resolveGuideSummary(guideType) {
    const map = {
      active: '授权状态正常。可在新设备上激活，或订阅到期提醒。',
      pending: '尚未完成设备激活。登录后点击「激活设备」即可占用名额。',
      expired: '授权已过期，部分功能可能受限。可续费或联系客服处理。',
      suspended: '授权已暂停，请联系客服了解原因并申请恢复。',
      revoked: '授权已吊销且不可恢复。如有疑问请联系客服。',
    };
    return map[guideType] || '';
  },

  applyLicenseFromOptions(options) {
    this.setData({ isLoggedIn: auth.checkLogin() });

    if (!options.data) return;

    try {
      const license = JSON.parse(decodeURIComponent(options.data));
      const isExpired = !!license.is_expired;
      const max = Number(license.max_devices) || 0;
      const used = Number(license.activated_devices) || 0;
      const hasSlot = max === 0 || used < max;
      const status = String(license.status || '').toLowerCase();
      const canActivate = !isExpired
        && (status === 'active' || status === 'pending' || !status)
        && hasSlot;
      const devices = Array.isArray(license.devices) ? license.devices : [];
      const guideType = this.resolveGuideType(license, isExpired);

      this.setData({
        license,
        statusLabel: license.status_label || getStatusLabel(license.status),
        statusColor: getStatusColor(license.status),
        isExpired,
        canActivate,
        createdAtText: formatDate(license.created_at),
        expiresAtText: license.expires_at ? formatDate(license.expires_at) : '永久有效',
        devices,
        guideType,
        guideSummary: this.resolveGuideSummary(guideType),
      });
      this.refreshCanSubscribe();
    } catch (e) {
      wx.showToast({ title: '数据解析失败', icon: 'none' });
      wx.navigateBack();
    }
  },

  onCopyKey() {
    if (!this.data.license) return;
    wx.setClipboardData({
      data: this.data.license.license_key,
      success: () => wx.showToast({ title: '已复制', icon: 'success' }),
    });
  },

  goLoginThenActivate() {
    wx.setStorageSync('hwt_mini_auto_login', '1');
    if (this.data.license && this.data.license.license_key) {
      wx.setStorageSync('hwt_mini_pending_activate_key', this.data.license.license_key);
    }
    // 登录入口在「我的」
    wx.switchTab({ url: '/pages/mine/mine' });
  },

  onActivate() {
    if (!this.data.isLoggedIn) {
      wx.showModal({
        title: '需要登录',
        content: '激活设备需要先登录微信账号',
        confirmText: '去登录',
        success: (res) => {
          if (res.confirm) this.goLoginThenActivate();
        },
      });
      return;
    }

    wx.navigateTo({
      url: '/pages/activate/activate?key=' + encodeURIComponent(this.data.license.license_key),
    });
  },

  onPrimaryRenew() {
    webview.openPricing();
  },

  onOpenHelp() {
    webview.openHelp();
  },

  onCopyLink(e) {
    const url = e.currentTarget.dataset.url;
    if (!url) return;
    if (webview.isAllowedUrl(url)) {
      const title = url.indexOf('/pricing') !== -1
        ? '购买 / 定价'
        : (url.indexOf('/help') !== -1 ? '帮助中心' : '详情');
      webview.open(url, title);
      return;
    }
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

  onShareAppMessage() {
    const lic = this.data.license;
    if (!lic) {
      return { title: '互物通授权查询', path: '/pages/index/index' };
    }
    return {
      title: '授权 ' + lic.license_key + (lic.product_name ? (' · ' + lic.product_name) : ''),
      path: '/pages/index/index?key=' + encodeURIComponent(lic.license_key),
    };
  },

  onNewQuery() {
    wx.switchTab({ url: '/pages/index/index' });
  },
});
