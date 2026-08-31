/**
 * D-31: 微信登录工具
 *
 * 通过 wx.login 获取 code，发送到后端换取自定义 Token。
 */

const CONFIG = require('./config');
const api = require('./api');

function getToken() {
  return wx.getStorageSync(CONFIG.STORAGE_KEYS.TOKEN) || '';
}

/**
 * A5: 稳定设备绑定 ID（后端由 openid 派生）
 */
function getDeviceBindId() {
  return wx.getStorageSync(CONFIG.STORAGE_KEYS.DEVICE_BIND_ID) || '';
}

function wechatLogin() {
  return new Promise(function (resolve) {
    wx.login({
      success: function (loginRes) {
        if (!loginRes.code) {
          resolve({ success: false, message: '获取微信登录凭证失败' });
          return;
        }

        api.post('/miniprogram/login', { code: loginRes.code })
          .then(function (res) {
            if (res.success && res.data && res.data.token) {
              wx.setStorageSync(CONFIG.STORAGE_KEYS.TOKEN, res.data.token);
              wx.setStorageSync(CONFIG.STORAGE_KEYS.USER_INFO, res.data.user || {});
              wx.setStorageSync(CONFIG.STORAGE_KEYS.LOGIN_EXPIRES, Date.now() + CONFIG.LOGIN_EXPIRE_MS);
              if (res.data.device_bind_id) {
                wx.setStorageSync(CONFIG.STORAGE_KEYS.DEVICE_BIND_ID, res.data.device_bind_id);
              }
              resolve({
                success: true,
                user: res.data.user || null,
                device_bind_id: res.data.device_bind_id || '',
              });
              return;
            }
            resolve({ success: true, user: null });
          })
          .catch(function (err) {
            console.warn('[wechatLogin] 后端登录接口未可用，降级为匿名模式', err);
            resolve({ success: false, message: '登录服务暂不可用' });
          });
      },
      fail: function (err) {
        console.error('[wechatLogin] wx.login 失败', err);
        resolve({ success: false, message: '微信登录失败' });
      },
    });
  });
}

function checkLogin() {
  return api.isLoggedIn();
}

function logout() {
  api.clearLogin();
  try {
    wx.removeStorageSync(CONFIG.STORAGE_KEYS.DEVICE_BIND_ID);
  } catch (e) { /* ignore */ }
}

module.exports = {
  getToken: getToken,
  getDeviceBindId: getDeviceBindId,
  wechatLogin: wechatLogin,
  checkLogin: checkLogin,
  logout: logout,
};
