/**
 * D-31: 工具函数
 */

/**
 * 格式化日期
 * @param {string} dateStr ISO 日期字符串
 * @returns {string} 格式化后的日期
 */
function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

/**
 * 获取状态标签
 * @param {string} status License 状态
 * @returns {string} 中文标签
 */
function getStatusLabel(status) {
  const map = {
    active: '活跃',
    expired: '已过期',
    suspended: '已暂停',
    pending: '待激活',
    revoked: '已吊销',
  };
  return map[status] || status;
}

/**
 * 获取状态颜色
 */
function getStatusColor(status) {
  const map = {
    active: '#34a853',
    expired: '#ea4335',
    suspended: '#fbbc04',
    pending: '#1a73e8',
    revoked: '#ea4335',
  };
  return map[status] || '#9aa0a6';
}

/**
 * 判断是否过期
 */
function isExpired(expiresAt) {
  if (!expiresAt) return false;
  return new Date(expiresAt) < new Date();
}

module.exports = {
  formatDate,
  getStatusLabel,
  getStatusColor,
  isExpired,
};
