/**
 * D-31: 我的
 */
const auth = require('../../utils/auth');
const api = require('../../utils/api');
const CONFIG = require('../../utils/config');
const webview = require('../../utils/webview');

Page({
  data: {
    isLoggedIn: false,
    userInfo: null,
    phoneMasked: '',
    recentQueries: [],
    apiBase: CONFIG.API_BASE_URL,
  },

  onShow() {
    this.refresh();

    // 结果页「去登录」跳转到我的
    if (wx.getStorageSync('hwt_mini_auto_login')) {
      wx.removeStorageSync('hwt_mini_auto_login');
      this.onLogin().then(() => {
        const activateKey = wx.getStorageSync('hwt_mini_pending_activate_key');
        if (activateKey && auth.checkLogin()) {
          wx.removeStorageSync('hwt_mini_pending_activate_key');
          wx.navigateTo({
            url: '/pages/activate/activate?key=' + encodeURIComponent(activateKey),
          });
        }
      });
    }
  },

  refresh() {
    const isLoggedIn = auth.checkLogin();
    const userInfo = wx.getStorageSync(CONFIG.STORAGE_KEYS.USER_INFO) || null;
    const recentQueries = wx.getStorageSync('hwt_mini_recent_queries') || [];
    const phoneMasked = (userInfo && userInfo.phone_masked) || '';

    this.setData({ isLoggedIn, userInfo, recentQueries, phoneMasked });

    if (isLoggedIn) {
      this.loadProfile();
    }
  },

  loadProfile() {
    api.get('/miniprogram/profile')
      .then((res) => {
        const data = (res && res.data) || {};
        const userInfo = Object.assign({}, this.data.userInfo || {}, {
          id: data.id,
          name: data.name,
          phone: data.phone,
          phone_masked: data.phone_masked,
        });
        wx.setStorageSync(CONFIG.STORAGE_KEYS.USER_INFO, userInfo);
        this.setData({
          userInfo,
          phoneMasked: data.phone_masked || '',
        });
      })
      .catch(() => { /* ignore */ });
  },

  onLogin() {
    wx.showLoading({ title: '登录中...', mask: true });
    return auth.wechatLogin().then((res) => {
      wx.hideLoading();
      if (res.success) {
        wx.showToast({ title: '登录成功', icon: 'success' });
        this.refresh();
      } else {
        wx.showToast({ title: res.message || '登录失败', icon: 'none' });
      }
      return res;
    }).catch((err) => {
      wx.hideLoading();
      return err;
    });
  },

  onGetPhoneNumber(e) {
    const detail = e.detail || {};
    if (!detail.code) {
      wx.showToast({ title: '未授权手机号', icon: 'none' });
      return;
    }

    wx.showLoading({ title: '绑定中...', mask: true });
    api.post('/miniprogram/bind-phone', { code: detail.code })
      .then((res) => {
        wx.hideLoading();
        const data = (res && res.data) || {};
        wx.showToast({ title: '绑定成功', icon: 'success' });
        const userInfo = Object.assign({}, this.data.userInfo || {}, {
          phone: data.phone,
          phone_masked: data.phone_masked,
        });
        wx.setStorageSync(CONFIG.STORAGE_KEYS.USER_INFO, userInfo);
        this.setData({ userInfo, phoneMasked: data.phone_masked || '' });
      })
      .catch(() => {
        wx.hideLoading();
      });
  },

  onGoActivations() {
    wx.navigateTo({ url: '/pages/activations/activations' });
  },

  onOpenPricing() {
    webview.openPricing();
  },

  onOpenProducts() {
    webview.openProducts();
  },

  onOpenHelp() {
    webview.openHelp();
  },

  onLogout() {
    wx.showModal({
      title: '退出登录',
      content: '确定退出当前微信账号？',
      success: (res) => {
        if (res.confirm) {
          auth.logout();
          this.setData({ isLoggedIn: false, userInfo: null, phoneMasked: '' });
          wx.showToast({ title: '已退出', icon: 'success' });
        }
      },
    });
  },

  onTapRecent(e) {
    const key = e.currentTarget.dataset.key;
    wx.setStorageSync('hwt_mini_pending_key', key);
    wx.switchTab({ url: '/pages/index/index' });
  },

  onClearRecent() {
    wx.removeStorageSync('hwt_mini_recent_queries');
    this.setData({ recentQueries: [] });
  },

  onOpenAgreement(e) {
    const type = e.currentTarget.dataset.type;
    wx.navigateTo({
      url: '/pages/agreement/agreement?type=' + type,
    });
  },

  onDevTap() {
    // 长按页脚显示 API（开发排查用，正式用户不可见列表项）
    wx.showModal({
      title: '当前 API',
      content: this.data.apiBase || '',
      confirmText: '复制',
      success: (res) => {
        if (res.confirm && this.data.apiBase) {
          wx.setClipboardData({ data: this.data.apiBase });
        }
      },
    });
  },
});
