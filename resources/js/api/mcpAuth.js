import client from './client';

export default {
    // 仪表盘
    dashboard() {
        return client.get('/admin/innovation/mcp/dashboard');
    },

    // MCP Server
    listServers(params = {}) {
        return client.get('/admin/innovation/mcp/servers', { params });
    },
    registerServer(data) {
        return client.post('/admin/innovation/mcp/servers', data);
    },

    // AI Agent
    listAgents(params = {}) {
        return client.get('/admin/innovation/mcp/agents', { params });
    },
    registerAgent(data) {
        return client.post('/admin/innovation/mcp/agents', data);
    },
    checkAgentQuota(agentId) {
        return client.get(`/admin/innovation/mcp/agents/${agentId}/quota`);
    },
};
