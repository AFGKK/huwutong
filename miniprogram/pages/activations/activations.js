/**
 * 我的激活列表
 */
const api = require('../../utils/api');
const auth = require('../../utils/auth');

Page({
  data: {
    isLoggedIn: false,
    loading: true,
    items: [],
  },

  onShow() {
    this.refresh();
  },

  refresh() {
    const isLoggedIn = auth.checkLogin();
    this.setData({ isLoggedIn, loading: isLoggedIn });

    if (!isLoggedIn) {
      this.setData({ items: [], loading: false });
      return;
    }

    api.get('/miniprogram/my-activations')
      .then((res) => {
        const data = (res && res.data) || {};
        this.setData({
          items: data.items || [],
          loading: false,
        });
      })
      .catch(() => {
        this.setData({ loading: false, items: [] });
      });
  },

  onTapItem(e) {
    const key = e.currentTarget.dataset.key;
    if (!key) return;
    wx.setStorageSync('hwt_mini_pending_key', key);
    wx.switchTab({ url: '/pages/index/index' });
  },

  onGoMine() {
    wx.switchTab({ url: '/pages/mine/mine' });
  },

  onGoQuery() {
    wx.switchTab({ url: '/pages/index/index' });
  },
});
