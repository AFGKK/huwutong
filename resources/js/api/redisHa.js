/**
 * Redis 高可用管理 API
 * M1.3-17 Redis 高可用监控与管理
 */

import request from '@/utils/request';

/**
 * 获取 Redis HA 综合状态
 */
export function getRedisHaStatus() {
  return request.get('/admin/redis-ha/status');
}

/**
 * 健康检查
 */
export function getRedisHaHealth() {
  return request.get('/admin/redis-ha/health');
}

/**
 * Sentinel 哨兵状态
 */
export function getRedisHaSentinel() {
  return request.get('/admin/redis-ha/sentinel');
}

/**
 * 获取详细统计
 */
export function getRedisHaStats() {
  return request.get('/admin/redis-ha/stats');
}

/**
 * 触发故障转移
 */
export function triggerRedisFailover() {
  return request.post('/admin/redis-ha/failover');
}

/**
 * 清除 Redis 缓存
 */
export function flushRedisCache() {
  return request.post('/admin/redis-ha/flush');
}

/**
 * 重置熔断器
 */
export function resetRedisCircuitBreaker() {
  return request.post('/admin/redis-ha/reset-circuit-breaker');
}
