import apiClient from '@/api/client'

export function getBlogList(params = {}) {
    return apiClient.get('/blog', { params })
}

export function getBlogStats() {
    return apiClient.get('/blog/stats')
}

export function getBlogDetail(id) {
    return apiClient.get(`/blog/${id}`)
}

export function createBlog(data) {
    return apiClient.post('/blog', data)
}

export function updateBlog(id, data) {
    return apiClient.put(`/blog/${id}`, data)
}

export function deleteBlog(id) {
    return apiClient.delete(`/blog/${id}`)
}

export function togglePublish(id) {
    return apiClient.post(`/blog/${id}/toggle-publish`)
}

export function toggleFeatured(id) {
    return apiClient.post(`/blog/${id}/toggle-featured`)
}

// ─── 分类管理 ───
export function getCategories() {
    return apiClient.get('/blog/categories')
}

export function createCategory(data) {
    return apiClient.post('/blog/categories', data)
}

export function updateCategory(id, data) {
    return apiClient.put(`/blog/categories/${id}`, data)
}

export function deleteCategory(id) {
    return apiClient.delete(`/blog/categories/${id}`)
}

// ─── 批量操作 ───
export function batchDeletePosts(ids) {
    return apiClient.post('/blog/batch/delete', { ids })
}

export function batchPublishPosts(ids) {
    return apiClient.post('/blog/batch/publish', { ids })
}

export function batchCategoryPosts(ids, categoryId) {
    return apiClient.post('/blog/batch/category', { ids, category_id: categoryId })
}

// ─── 导出 ───
export function exportBlogCsv(params) {
    return apiClient.get('/blog/export/csv', { params, responseType: 'blob' })
}

// ─── 订阅管理 (M3-57) ───
export function getSubscriptionStats() {
    return apiClient.get('/blog/subscriptions/stats')
}

export function getSubscriptionList(params) {
    return apiClient.get('/blog/subscriptions', { params })
}

// ─── 公开 API ───
export function getPublished(params) {
    return apiClient.get('/public/blog/published', { params })
}

export function getBySlug(slug) {
    return apiClient.get(`/public/blog/${slug}`)
}

export function getChangelogByVersion() {
    return apiClient.get('/public/blog/changelog/versions')
}

export function subscribe(data) {
    return apiClient.post('/public/blog/subscriptions', data)
}

// ─── 关注功能 ───
export function followBlog() {
    return apiClient.post('/public/blog/follow')
}

export function unfollowBlog() {
    return apiClient.post('/public/blog/unfollow')
}

export function getFollowStatus() {
    return apiClient.get('/public/blog/follow-status')
}

export function getFollowerCount() {
    return apiClient.get('/public/blog/followers-count')
}
