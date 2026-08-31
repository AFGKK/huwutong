/**
 * D-31: 首页 - License 查询页
 */
const api = require('../../utils/api');
const auth = require('../../utils/auth');
const CONFIG = require('../../utils/config');

Page({
  data: {
    licenseKey: '',
    isLoading: false,
    isLoggedIn: false,
    userInfo: null,
    recentQueries: [],
    autoLoginPending: false,
    exampleKeys: CONFIG.EXAMPLE_KEYS || [],
  },

  onLoad(options) {
    this.checkLoginStatus();
    this.loadRecentQueries();

    // 分享进入：?key=HWT-xxx
    const keyFromShare = (options && (options.key || options.license_key)) || '';
    if (keyFromShare) {
      this.setData({ licenseKey: String(keyFromShare).toUpperCase() });
      this.onLookup();
    }

    // 结果页「去登录」：?auto_login=1
    if (options && options.auto_login === '1') {
      this.setData({ autoLoginPending: true });
      this.onLogin();
    }
  },

  onShow() {
    this.checkLoginStatus();
    this.loadRecentQueries();

    // 结果页「去登录」
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

    // 「我的」页点最近查询带回
    const pending = wx.getStorageSync('hwt_mini_pending_key');
    if (pending) {
      wx.removeStorageSync('hwt_mini_pending_key');
      this.setData({ licenseKey: String(pending).toUpperCase() });
      this.onLookup();
    }
  },

  checkLoginStatus() {
    const loggedIn = auth.checkLogin();
    const userInfo = wx.getStorageSync(CONFIG.STORAGE_KEYS.USER_INFO) || null;
    this.setData({ isLoggedIn: loggedIn, userInfo });
  },

  loadRecentQueries() {
    const recent = wx.getStorageSync('hwt_mini_recent_queries') || [];
    this.setData({ recentQueries: recent });
  },

  onKeyInput(e) {
    this.setData({ licenseKey: e.detail.value.toUpperCase() });
  },

  onScan() {
    wx.scanCode({
      onlyFromCamera: false,
      success: (res) => {
        const raw = res.result || '';
        const match = raw.match(/(HWT-[A-Z0-9-]+)/i);
        const key = match ? match[1].toUpperCase() : raw.toUpperCase();
        this.setData({ licenseKey: key });
        this.onLookup();
      },
      fail: () => {
        wx.showToast({ title: '扫码失败', icon: 'none' });
      },
    });
  },

  onTapRecent(e) {
    const key = e.currentTarget.dataset.key;
    this.setData({ licenseKey: key });
    this.onLookup();
  },

  onClearRecent() {
    wx.removeStorageSync('hwt_mini_recent_queries');
    this.setData({ recentQueries: [] });
  },

  onFillExample(e) {
    const key = e.currentTarget.dataset.key;
    if (!key) return;
    this.setData({ licenseKey: String(key).toUpperCase() });
    this.onLookup();
  },

  onClear() {
    this.setData({ licenseKey: '' });
  },

  onLookup() {
    const key = this.data.licenseKey.trim();
    if (!key) {
      wx.showToast({ title: '请输入 License Key', icon: 'none' });
      return;
    }

    if (key.length < 8) {
      wx.showToast({ title: 'License Key 格式不正确', icon: 'none' });
      return;
    }

    this.setData({ isLoading: true });

    api.post('/license/public-lookup', { license_key: key }, { showLoading: false })
      .then((res) => {
        this.setData({ isLoading: false });

        if (res.success && res.found) {
          this.addRecentQuery(key);
          wx.navigateTo({
            url: '/pages/result/result?data=' + encodeURIComponent(JSON.stringify(res.data)),
          });
        } else {
          wx.showModal({
            title: '未找到',
            content: res.message || '未找到该 License Key，请检查后重试',
            showCancel: false,
          });
        }
      })
      .catch(() => {
        this.setData({ isLoading: false });
      });
  },

  addRecentQuery(key) {
    let recent = wx.getStorageSync('hwt_mini_recent_queries') || [];
    recent = recent.filter((k) => k !== key);
    recent.unshift(key);
    if (recent.length > 20) recent = recent.slice(0, 20);
    wx.setStorageSync('hwt_mini_recent_queries', recent);
    this.setData({ recentQueries: recent });
  },

  onLogin() {
    wx.showLoading({ title: '登录中...', mask: true });
    return auth.wechatLogin().then((res) => {
      wx.hideLoading();
      this.setData({ autoLoginPending: false });
      if (res.success) {
        wx.showToast({ title: '登录成功', icon: 'success' });
        this.checkLoginStatus();
      } else {
        wx.showToast({ title: res.message || '登录失败', icon: 'none' });
      }
      return res;
    }).catch((err) => {
      wx.hideLoading();
      this.setData({ autoLoginPending: false });
      return err;
    });
  },

  onShareAppMessage() {
    const key = this.data.licenseKey.trim();
    return {
      title: key ? ('查询授权 ' + key) : '互物通授权查询',
      path: key ? ('/pages/index/index?key=' + encodeURIComponent(key)) : '/pages/index/index',
    };
  },
});
