import apiClient from './client';

export const licenseNoteApi = {
  /**
   * 获取 License 的所有备注
   */
  list(licenseId) {
    return apiClient.get(`/licenses/${licenseId}/notes`);
  },

  /**
   * 添加备注
   */
  create(licenseId, data) {
    return apiClient.post(`/licenses/${licenseId}/notes`, {
      content: data.content,
      mentions: data.mentions || [],
    });
  },

  /**
   * 删除备注
   */
  destroy(licenseId, noteId) {
    return apiClient.delete(`/licenses/${licenseId}/notes/${noteId}`);
  },

  /**
   * @mention 搜索用户
   */
  searchUsers(query) {
    return apiClient.get('/users/search', { params: { q: query } });
  },
};
