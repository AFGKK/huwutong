/**
 * 设备激活页
 */
const api = require('../../utils/api');
const auth = require('../../utils/auth');

Page({
  data: {
    licenseKey: '',
    deviceName: '',
    deviceFingerprint: '',
    isActivating: false,
    isLoggedIn: false,
    isActivated: false,
  },

  onLoad(options) {
    const isLoggedIn = auth.checkLogin();
    this.setData({
      licenseKey: options.key || '',
      isLoggedIn,
    });

    if (!isLoggedIn) {
      wx.showModal({
        title: '需要登录',
        content: '激活设备需要先登录微信账号',
        showCancel: false,
        success: () => {
          wx.setStorageSync('hwt_mini_auto_login', '1');
          if (options.key) {
            wx.setStorageSync('hwt_mini_pending_activate_key', options.key);
          }
          wx.switchTab({ url: '/pages/mine/mine' });
        },
      });
      return;
    }

    this.generateFingerprint();
  },

  generateFingerprint() {
    const bindId = auth.getDeviceBindId();
    if (bindId) {
      this.setData({ deviceFingerprint: bindId });
      return;
    }

    try {
      const sysInfo = wx.getSystemInfoSync();
      const fp = [
        'wx_mini',
        sysInfo.platform || '',
        sysInfo.model || '',
        sysInfo.system || '',
      ].join('|');
      this.setData({ deviceFingerprint: this.simpleHash(fp) });
    } catch (e) {
      this.setData({ deviceFingerprint: 'wx_' + Date.now() });
    }
  },

  simpleHash(str) {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
      const char = str.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash;
    }
    return 'wx_' + Math.abs(hash).toString(36);
  },

  onDeviceNameInput(e) {
    this.setData({ deviceName: e.detail.value });
  },

  onActivate() {
    if (this.data.isActivating) return;

    if (!this.data.deviceName.trim()) {
      wx.showToast({ title: '请输入设备名称', icon: 'none' });
      return;
    }

    this.setData({ isActivating: true });

    api.post('/license/miniprogram/activate', {
      license_key: this.data.licenseKey,
      fingerprint: this.data.deviceFingerprint,
      device_name: this.data.deviceName.trim(),
      platform: 'wechat_miniprogram',
    }, { showLoading: false })
      .then((res) => {
        this.setData({ isActivating: false });

        if (res.success) {
          this.setData({ isActivated: true });
          wx.vibrateShort({ type: 'light' });
        } else {
          wx.showToast({ title: res.message || '激活失败', icon: 'none' });
        }
      })
      .catch(() => {
        this.setData({ isActivating: false });
      });
  },

  onGoActivations() {
    wx.redirectTo({ url: '/pages/activations/activations' });
  },

  onGoQuery() {
    wx.switchTab({ url: '/pages/index/index' });
  },

  onBackToResult() {
    wx.navigateBack({ delta: 1 });
  },

  onBack() {
    wx.navigateBack();
  },
});
