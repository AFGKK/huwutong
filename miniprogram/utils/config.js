/**
 * D-31: API 配置
 *
 * 小程序发布前需修改以下配置：
 *   - API_BASE_URL: 后端 API 地址
 *   - WX_APP_ID: 微信小程序 AppID
 */

const CONFIG = {
  // API 基础地址（开发可改为本机；正式环境用 HTTPS 域名）
  // 例: 'http://127.0.0.1:8000/api' 或 'https://88.huwutong.com/api'
  API_BASE_URL: 'https://88.huwutong.com/api',

  // 微信小程序 AppID（与 project.config.json / 公众平台一致）
  WX_APP_ID: 'wx4cab3d98eaf9106b',

  // 请求超时时间 (ms)
  REQUEST_TIMEOUT: 10000,

  // 存储 key
  STORAGE_KEYS: {
    TOKEN: 'hwt_mini_token',
    USER_INFO: 'hwt_mini_user_info',
    LOGIN_EXPIRES: 'hwt_mini_login_expires',
    DEVICE_BIND_ID: 'hwt_mini_device_bind_id',
  },

  // 登录过期时间 (1天)
  LOGIN_EXPIRE_MS: 24 * 60 * 60 * 1000,

  // A4: 订阅消息模板 ID（可留空，运行时从后端 /miniprogram/subscribe-config 拉取）
  SUBSCRIBE_TEMPLATE_ID: '',

  // 示例 License Key（与 H5 /license/query 一致，便于演示）
  EXAMPLE_KEYS: [
    'HWT-DEMO-A1B2-C3D4',
    'HWT-ENTERPRISE-E5F6-G7H8',
  ],

  // 官网链接（过期续费 / 帮助 / 商城）
  SITE_URL: 'https://88.huwutong.com',
  PRICING_URL: 'https://88.huwutong.com/pricing',
  PRODUCTS_URL: 'https://88.huwutong.com/products',
  HELP_URL: 'https://88.huwutong.com/help',
  CONTACT_URL: 'https://88.huwutong.com/contact',
};

module.exports = CONFIG;
