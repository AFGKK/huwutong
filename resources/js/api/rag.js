import apiClient from './client';

const ragApi = {
    /** 检索相关文档 */
    retrieve(query, params = {}) {
        return apiClient.get('/rag/retrieve', {
            params: { q: query, ...params },
        });
    },
    /** 提问（检索 + 回答） */
    ask(query, sessionId, params = {}) {
        return apiClient.post('/rag/ask', { q: query, session_id: sessionId, ...params });
    },
    /** 对话历史 */
    history(sessionId) {
        return apiClient.get('/rag/history', { params: { session_id: sessionId } });
    },
    /** 提交反馈 */
    feedback(messageId, wasHelpful) {
        return apiClient.post('/rag/feedback', { message_id: messageId, was_helpful: wasHelpful });
    },
    // ── 管理接口 ──
    /** 索引一篇文章到 RAG 知识库 */
    indexArticle(articleId) {
        return apiClient.post(`/rag/articles/${articleId}/index`);
    },
    /** 重建全部 RAG 索引 */
    rebuildIndex() {
        return apiClient.post('/rag/rebuild');
    },
    /** RAG 统计 */
    stats() {
        return apiClient.get('/rag/stats');
    },
};

export default ragApi;
