import client from './client';

export default {
  // 凭据管理
  list(params = {}) {
    return client.get('/secrets', { params });
  },
  create(data) {
    return client.post('/secrets', data);
  },
  show(id) {
    return client.get(`/secrets/${id}`, { params: { confirm: true } });
  },
  rotate(id, value) {
    return client.post(`/secrets/${id}/rotate`, { value });
  },
  revoke(id) {
    return client.post(`/secrets/${id}/revoke`);
  },
  restore(id) {
    return client.post(`/secrets/${id}/restore`);
  },

  // 审计日志
  logs(secretId = null, params = {}) {
    const path = secretId ? `/secrets/${secretId}/logs` : '/secrets/logs/all';
    return client.get(path, { params });
  },

  // 主密钥管理
  masterKeys() {
    return client.get('/secrets/master-keys');
  },
  generateMasterKey(label = '') {
    return client.post('/secrets/master-keys/generate', { label });
  },
  rotateMasterKey() {
    return client.post('/secrets/master-keys/rotate');
  },

  // 健康状态
  health() {
    return client.get('/secrets/health');
  },

  // 类型列表
  types() {
    return client.get('/secrets/types');
  },
};
