/**
 * M2-34: 错误码标准化 API 客户端
 */
import client from './client';

export default {
  /**
   * 获取所有错误码
   * @param {Object} params - { locale?, grouped? }
   */
  getAll(params = {}) {
    return client.get('/error-codes', { params });
  },

  /**
   * 按域分组获取
   * @param {Object} params - { locale? }
   */
  getByDomain(params = {}) {
    return client.get('/error-codes/by-domain', { params });
  },

  /**
   * 搜索错误码
   * @param {string} query
   * @param {Object} params - { locale? }
   */
  search(query, params = {}) {
    return client.get('/error-codes/search', { params: { q: query, ...params } });
  },

  /**
   * 查询单个错误码
   * @param {string} code
   */
  show(code) {
    return client.get(`/error-codes/${code}`);
  },

  /**
   * 获取统计概览（管理）
   */
  getStats() {
    return client.get('/error-codes/stats');
  },

  /**
   * 获取完整参考（管理）
   * @param {Object} params - { locale?, grouped? }
   */
  getReference(params = {}) {
    return client.get('/error-codes/reference', { params });
  },
};
