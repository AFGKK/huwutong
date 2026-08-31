/**
 * HWT License 查询 — 微信小程序入口文件
 * 注册小程序生命周期
 */
const auth = require('./utils/auth');

App({
  onLaunch() {
    try {
      const token = auth.getToken();
      this.globalData.isLoggedIn = !!token;
    } catch (e) {
      console.warn('[App] 读取登录状态失败', e);
    }
  },

  onShow() {
    // 小程序从后台切回前台时触发
  },

  onHide() {
    // 小程序切入后台时触发
  },

  globalData: {
    userInfo: null,
    isLoggedIn: false,
  },
});
