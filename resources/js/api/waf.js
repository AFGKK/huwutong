/**
 * WAF 基础防护 API
 * M1.3-18 WAF + DDoS 基础防护
 */

import request from '@/utils/request';

/** 仪表盘 */
export function getWafDashboard() {
  return request.get('/admin/waf/dashboard');
}

/** 规则列表 */
export function getWafRules(params) {
  return request.get('/admin/waf/rules', { params });
}

/** 创建规则 */
export function createWafRule(data) {
  return request.post('/admin/waf/rules', data);
}

/** 更新规则 */
export function updateWafRule(id, data) {
  return request.put(`/admin/waf/rules/${id}`, data);
}

/** 删除规则 */
export function deleteWafRule(id) {
  return request.delete(`/admin/waf/rules/${id}`);
}

/** 切换规则状态 */
export function toggleWafRule(id) {
  return request.post(`/admin/waf/rules/${id}/toggle`);
}

/** 导入默认规则 */
export function seedWafRules() {
  return request.post('/admin/waf/rules/seed');
}

/** IP 列表 */
export function getWafIpList(params) {
  return request.get('/admin/waf/ip-list', { params });
}

/** 添加 IP */
export function addWafIp(data) {
  return request.post('/admin/waf/ip-list', data);
}

/** 批量添加 IP */
export function batchAddWafIp(data) {
  return request.post('/admin/waf/ip-list/batch', data);
}

/** 删除 IP */
export function deleteWafIp(id) {
  return request.delete(`/admin/waf/ip-list/${id}`);
}

/** 检查 IP */
export function checkWafIp(ip) {
  return request.get('/admin/waf/ip-list/check', { params: { ip } });
}

/** 攻击日志 */
export function getWafLogs(params) {
  return request.get('/admin/waf/logs', { params });
}

/** 攻击趋势 */
export function getWafTrend(params) {
  return request.get('/admin/waf/trend', { params });
}

/** 清理日志 */
export function pruneWafLogs() {
  return request.post('/admin/waf/logs/prune');
}

/** 获取配置 */
export function getWafConfig() {
  return request.get('/admin/waf/config');
}

/** 更新配置 */
export function updateWafConfig(data) {
  return request.put('/admin/waf/config', data);
}
