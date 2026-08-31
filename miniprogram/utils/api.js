/**
 * D-31: API 请求封装
 *
 * 提供统一的 wx.request 封装，自动注入 Token、处理错误。
 */

const CONFIG = require('./config');

/**
 * 发起 API 请求
 * @param {string} method  HTTP 方法
 * @param {string} path    API 路径（如 /license/public-lookup）
 * @param {object} data    请求体
 * @param {object} options 额外选项 {showLoading: false, loadingText: ''}
 * @returns {Promise<object>} 响应 data 字段
 */
function request(method, path, data = {}, options = {}) {
  const token = wx.getStorageSync(CONFIG.STORAGE_KEYS.TOKEN) || '';

  const header = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };
  if (token) {
    header['Authorization'] = 'Bearer ' + token;
  }

  if (options.showLoading) {
    wx.showLoading({ title: options.loadingText || '加载中...', mask: true });
  }

  return new Promise((resolve, reject) => {
    wx.request({
      url: CONFIG.API_BASE_URL + path,
      method: method,
      data: data,
      header: header,
      timeout: CONFIG.REQUEST_TIMEOUT,
      success: (res) => {
        if (options.showLoading) wx.hideLoading();

        const statusCode = res.statusCode || 0;
        const body = res.data || {};

        if (statusCode >= 200 && statusCode < 300) {
          resolve(body);
        } else if (statusCode === 401) {
          // Token 过期，清除登录状态
          clearLogin();
          wx.showToast({ title: '登录已过期，请重新登录', icon: 'none' });
          reject({ code: 401, message: '登录已过期，请重新登录' });
        } else {
          const errObj = body.error;
          const msg = body.message
            || (errObj && typeof errObj === 'object' ? errObj.message : errObj)
            || ('请求失败 (' + statusCode + ')');
          wx.showToast({ title: String(msg), icon: 'none' });
          reject({ code: statusCode, message: msg, body: body });
        }
      },
      fail: (err) => {
        if (options.showLoading) wx.hideLoading();
        const msg = '网络异常，请检查网络连接';
        wx.showToast({ title: msg, icon: 'none' });
        reject({ code: -1, message: msg, detail: err });
      },
    });
  });
}

/**
 * GET 请求
 */
function get(path, params = {}, options = {}) {
  // 将 params 拼接到 URL
  let url = path;
  const query = Object.keys(params)
    .map(k => encodeURIComponent(k) + '=' + encodeURIComponent(params[k]))
    .join('&');
  if (query) url += '?' + query;

  return request('GET', url, {}, options);
}

/**
 * POST 请求
 */
function post(path, data = {}, options = {}) {
  return request('POST', path, data, options);
}

/**
 * 清除登录状态
 */
function clearLogin() {
  wx.removeStorageSync(CONFIG.STORAGE_KEYS.TOKEN);
  wx.removeStorageSync(CONFIG.STORAGE_KEYS.USER_INFO);
  wx.removeStorageSync(CONFIG.STORAGE_KEYS.LOGIN_EXPIRES);
  if (CONFIG.STORAGE_KEYS.DEVICE_BIND_ID) {
    wx.removeStorageSync(CONFIG.STORAGE_KEYS.DEVICE_BIND_ID);
  }
}

/**
 * 检查登录是否过期
 */
function isLoggedIn() {
  const token = wx.getStorageSync(CONFIG.STORAGE_KEYS.TOKEN);
  const expires = wx.getStorageSync(CONFIG.STORAGE_KEYS.LOGIN_EXPIRES);
  if (!token || !expires) return false;
  return Date.now() < expires;
}

module.exports = {
  request,
  get,
  post,
  clearLogin,
  isLoggedIn,
};
