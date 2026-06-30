import apiClient from '@/api/client';

export default {
  dashboard() {
    return apiClient.get('/admin/istio/dashboard');
  },

  topology() {
    return apiClient.get('/admin/istio/topology');
  },

  trafficRules() {
    return apiClient.get('/admin/istio/traffic-rules');
  },

  security() {
    return apiClient.get('/admin/istio/security');
  },

  observability() {
    return apiClient.get('/admin/istio/observability');
  },

  canaryDeployments() {
    return apiClient.get('/admin/istio/canary');
  },

  canaryDeploy(data) {
    return apiClient.post('/admin/istio/canary/deploy', data);
  },

  promoteCanary(service) {
    return apiClient.post(`/admin/istio/canary/${service}/promote`);
  },

  rollbackCanary(service) {
    return apiClient.post(`/admin/istio/canary/${service}/rollback`);
  },

  deploymentGuide() {
    return apiClient.get('/admin/istio/deployment-guide');
  },
};
