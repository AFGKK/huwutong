/**
 * D-32 Electron 管理壳配置
 *
 * 环境变量:
 *   HWT_ADMIN_URL          管理后台地址，默认 http://127.0.0.1:8000/build
 *   HWT_ELECTRON_UPDATE_URL  自动更新 manifest 根 URL（打包后）
 *   HWT_ELECTRON_AUTO_UPDATE  true|false，默认打包后启用
 */

const path = require('path');

const adminUrl = (
  process.env.HWT_ADMIN_URL
  || process.env.ELECTRON_ADMIN_URL
  || 'http://127.0.0.1:8000/build'
).replace(/\/$/, '');

const loginUrl = `${adminUrl}/login`;
const dashboardUrl = `${adminUrl}/dashboard`;

module.exports = {
  adminUrl,
  loginUrl,
  dashboardUrl,
  updateUrl: process.env.HWT_ELECTRON_UPDATE_URL || '',
  autoUpdateEnabled: process.env.HWT_ELECTRON_AUTO_UPDATE !== 'false',
  window: {
    width: 1280,
    height: 840,
    minWidth: 960,
    minHeight: 640,
    title: '互物通 License 管理',
  },
  assetsDir: path.join(__dirname, 'assets'),
};
