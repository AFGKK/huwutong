import client from './client';

export default {
  stats() {
    return client.get('/backups/stats');
  },
  list(params = {}) {
    return client.get('/backups', { params });
  },
  config() {
    return client.get('/backups/config');
  },
  backupDatabase(name) {
    return client.post('/backups/database', { name });
  },
  backupFiles(name) {
    return client.post('/backups/files', { name });
  },
  download(id) {
    return client.get(`/backups/${id}/download`, { responseType: 'blob' });
  },
  destroy(id) {
    return client.delete(`/backups/${id}`);
  },
  restore(id) {
    return client.post(`/backups/${id}/restore`);
  },
};
