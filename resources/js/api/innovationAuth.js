import request from '@/utils/request';

// M3-14 Blockchain
export const blockchain = {
    dashboard: () => request.get('/admin/innovation/blockchain/dashboard'),
    list: (params) => request.get('/admin/innovation/blockchain/licenses', { params }),
    createChallenge: (wallet) => request.post('/admin/innovation/blockchain/challenge', { wallet_address: wallet }),
    verifyWallet: (data) => request.post('/admin/innovation/blockchain/verify-wallet', data),
};

// M3-15 MCP / AI Agent
export const mcp = {
    dashboard: () => request.get('/admin/innovation/mcp/dashboard'),
    servers: (params) => request.get('/admin/innovation/mcp/servers', { params }),
    registerServer: (data) => request.post('/admin/innovation/mcp/servers', data),
    agents: (params) => request.get('/admin/innovation/mcp/agents', { params }),
    registerAgent: (data) => request.post('/admin/innovation/mcp/agents', data),
    checkAgentQuota: (id) => request.get(`/admin/innovation/mcp/agents/${id}/quota`),
};

// M3-16 Serverless
export const serverless = {
    dashboard: () => request.get('/admin/innovation/serverless/dashboard'),
    functions: (params) => request.get('/admin/innovation/serverless/functions', { params }),
    register: (data) => request.post('/admin/innovation/serverless/functions', data),
    generateToken: (id) => request.post(`/admin/innovation/serverless/functions/${id}/token`),
};

// M3-17 Edge
export const edge = {
    dashboard: () => request.get('/admin/innovation/edge/dashboard'),
    nodes: (params) => request.get('/admin/innovation/edge/nodes', { params }),
    registerNode: (data) => request.post('/admin/innovation/edge/nodes', data),
};

// 通用
export function updateInnovationStatus(type, id, status) {
    return request.put('/admin/innovation/status', { type, id, status });
}
