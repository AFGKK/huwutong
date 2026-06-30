/**
 * gRPC 服务间通信 API
 * M1.3-28 gRPC 服务间通信实现
 */

import request from '@/utils/request';

export function getGrpcDashboard() {
  return request.get('/admin/grpc/dashboard');
}

export function getGrpcHealth() {
  return request.get('/admin/grpc/health');
}

export function getGrpcConfig() {
  return request.get('/admin/grpc/config');
}

export function getGrpcEndpoints() {
  return request.get('/admin/grpc/endpoints');
}

export function getGrpcCircuitBreaker() {
  return request.get('/admin/grpc/circuit-breaker');
}

export function resetGrpcCircuitBreaker() {
  return request.post('/admin/grpc/reset-circuit-breaker');
}
