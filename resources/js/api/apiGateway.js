/**
 * API 网关统一层 API
 * M1.3-20 API 网关 (Kong/APISIX) 管理
 */

import request from '@/utils/request';

export function getApiGatewayDashboard() {
  return request.get('/admin/api-gateway/dashboard');
}

export function getApiGatewayHealth() {
  return request.get('/admin/api-gateway/health');
}

export function getApiGatewayInfo() {
  return request.get('/admin/api-gateway/info');
}

export function getApiGatewayRoutes() {
  return request.get('/admin/api-gateway/routes');
}

export function syncApiGatewayRoutes() {
  return request.post('/admin/api-gateway/routes/sync');
}

export function getApiGatewayServices() {
  return request.get('/admin/api-gateway/services');
}

export function getApiGatewayUpstreams() {
  return request.get('/admin/api-gateway/upstreams');
}

export function getApiGatewayPlugins() {
  return request.get('/admin/api-gateway/plugins');
}

export function getApiGatewayConfig() {
  return request.get('/admin/api-gateway/config');
}

export function exportApiGatewayConfig() {
  return request.get('/admin/api-gateway/export');
}

export function clearApiGatewayCache() {
  return request.post('/admin/api-gateway/clear-cache');
}
