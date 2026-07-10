<template>
    <div class="forum-panel">
        <div class="sidebar-header">
            <h3>📋 广场</h3>
            <div class="sidebar-header-actions">
                <el-button size="small" type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 发帖
                </el-button>
                <el-button size="small" text @click="loadPosts" title="刷新">
                    <el-icon><RefreshLeft /></el-icon>
                </el-button>
            </div>
        </div>

        <div class="forum-toolbar">
            <el-select v-model="filterCategory" size="small" placeholder="全部分类" clearable style="width:110px" @change="loadPosts">
                <el-option label="全部分类" value="" />
                <el-option v-for="cat in categories" :key="cat.id" :label="cat.icon + ' ' + cat.name" :value="cat.id" />
            </el-select>
            <el-input v-model="searchKeyword" size="small" placeholder="搜索帖子..." clearable style="flex:1;margin-left:6px"
                @keydown.enter="loadPosts" @clear="loadPosts" />
        </div>

        <div class="forum-list" v-loading="loading">
            <div v-for="post in posts" :key="post.id" class="forum-post-item" @click="openPost(post)">
                <div class="forum-post-header">
                    <el-tag v-if="post.is_pinned" size="small" type="warning" style="margin-right:4px">📌 置顶</el-tag>
                    <span class="forum-post-title">{{ post.title }}</span>
                </div>
                <div class="forum-post-meta">
                    <img v-if="post.user?.avatar_url" :src="post.user.avatar_url" class="forum-post-avatar" />
                    <span v-else class="forum-post-avatar-text">{{ post.user?.name?.charAt(0) || '?' }}</span>
                    <span class="forum-post-author">{{ post.user?.name || '匿名' }}</span>
                    <span class="forum-post-stat">❤️ {{ post.likes_count }}</span>
                    <span class="forum-post-stat">💬 {{ post.replies_count || post.replies?.length || 0 }}</span>
                    <span class="forum-post-stat">👁️ {{ post.views_count }}</span>
                    <span class="forum-post-time">{{ formatTime(post.created_at) }}</span>
                </div>
                <div class="forum-post-preview">{{ post.content?.substring(0, 120) }}{{ post.content?.length > 120 ? '...' : '' }}</div>
            </div>
            <div v-if="!posts.length && !loading" class="empty-hint">暂无帖子，来发布第一条吧</div>
        </div>

        <!-- 创建帖子对话框 -->
        <el-dialog v-model="showCreate" title="📝 发布帖子" width="520px" :close-on-click-modal="false">
            <el-form :model="createForm" label-width="60px" size="small">
                <el-form-item label="分类" v-if="categories.length">
                    <el-select v-model="createForm.category_id" placeholder="选择分类" clearable style="width:100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.icon + ' ' + cat.name" :value="cat.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="标题" required>
                    <el-input v-model="createForm.title" placeholder="帖子标题" maxlength="200" />
                </el-form-item>
                <el-form-item label="内容" required>
                    <el-input v-model="createForm.content" type="textarea" :rows="8" placeholder="帖子内容..." maxlength="50000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showCreate = false">取消</el-button>
                <el-button size="small" type="primary" :loading="creating" @click="submitPost">发布</el-button>
            </template>
        </el-dialog>

        <!-- 帖子详情对话框 -->
        <el-dialog v-model="showDetail" :title="detailPost?.title || '帖子详情'" width="600px" top="5vh" :close-on-click-modal="false">
            <div v-if="detailPost" class="forum-detail">
                <div class="forum-detail-meta">
                    <img v-if="detailPost.user?.avatar_url" :src="detailPost.user.avatar_url" class="forum-detail-avatar" />
                    <span v-else class="forum-detail-avatar-text">{{ detailPost.user?.name?.charAt(0) || '?' }}</span>
                    <div class="forum-detail-author-info">
                        <span class="forum-detail-author">{{ detailPost.user?.name || '匿名' }}</span>
                        <span class="forum-detail-time">{{ formatFullTime(detailPost.created_at) }}</span>
                    </div>
                    <div class="forum-detail-stats">
                        <el-button text size="small" @click="togglePostLike(detailPost)" :type="detailPost.is_liked ? 'primary' : 'default'">
                            ❤️ {{ detailPost.likes_count }}
                        </el-button>
                        <span class="forum-stat-item">👁️ {{ detailPost.views_count }}</span>
                        <el-button v-if="detailPost.user_id === myId" text size="small" type="danger" @click="deletePost(detailPost)">
                            🗑️ 删除
                        </el-button>
                    </div>
                </div>
                <div class="forum-detail-content">{{ detailPost.content }}</div>

                <div class="forum-detail-replies" v-if="detailPost.replies?.length">
                    <h4>💬 回复 ({{ detailPost.replies.length }})</h4>
                    <div v-for="reply in detailPost.replies" :key="reply.id" class="forum-reply-item">
                        <img v-if="reply.user?.avatar_url" :src="reply.user.avatar_url" class="forum-reply-avatar" />
                        <span v-else class="forum-reply-avatar-text">{{ reply.user?.name?.charAt(0) || '?' }}</span>
                        <div class="forum-reply-body">
                            <div class="forum-reply-header">
                                <span class="forum-reply-author">{{ reply.user?.name || '匿名' }}</span>
                                <span class="forum-reply-time">{{ formatTime(reply.created_at) }}</span>
                            </div>
                            <div class="forum-reply-content">{{ reply.content }}</div>
                        </div>
                    </div>
                </div>
                <div v-else class="forum-no-replies">暂无回复</div>

                <!-- 回复输入 -->
                <div class="forum-reply-input" v-if="!detailPost.is_locked">
                    <el-input v-model="replyText" type="textarea" :rows="3" placeholder="写下你的回复..." maxlength="10000" />
                    <el-button size="small" type="primary" style="margin-top:8px" :loading="replying" @click="submitReply(detailPost)">发表回复</el-button>
                </div>
                <el-alert v-else title="帖子已锁定，无法回复" type="info" :closable="false" show-icon />
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, RefreshLeft } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const emit = defineEmits(['refresh'])
const props = defineProps({
    myId: { type: Number, default: 0 },
})

// ── 加载数据 ──
onMounted(() => {
    loadPosts()
    loadCategories()
})

// ── 帖子列表 ──
const posts = ref([])
const categories = ref([])
const loading = ref(false)
const filterCategory = ref('')
const searchKeyword = ref('')

// ── 创建帖子 ──
const showCreate = ref(false)
const creating = ref(false)
const createForm = ref({ title: '', content: '', category_id: null })

// ── 帖子详情 ──
const showDetail = ref(false)
const detailPost = ref(null)
const replyText = ref('')
const replying = ref(false)

async function loadPosts() {
    loading.value = true
    try {
        const params = {}
        if (filterCategory.value) params.category_id = filterCategory.value
        if (searchKeyword.value.trim()) params.q = searchKeyword.value.trim()
        const res = await apiClient.get('/forum', { params })
        posts.value = res.data?.data || []
    } catch { posts.value = [] }
    finally { loading.value = false }
}

async function loadCategories() {
    try {
        const res = await apiClient.get('/forum/categories')
        categories.value = res.data?.data || []
    } catch { categories.value = [] }
}

async function submitPost() {
    if (!createForm.value.title.trim()) { ElMessage.warning('请输入标题'); return }
    if (!createForm.value.content.trim()) { ElMessage.warning('请输入内容'); return }
    creating.value = true
    try {
        await apiClient.post('/forum', { ...createForm.value })
        ElMessage.success('帖子已发布')
        showCreate.value = false
        createForm.value = { title: '', content: '', category_id: null }
        await loadPosts()
    } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') }
    finally { creating.value = false }
}

async function openPost(post) {
    try {
        const res = await apiClient.get('/forum/' + post.id)
        detailPost.value = res.data?.data || post
        showDetail.value = true
    } catch { ElMessage.error('加载失败') }
}

async function togglePostLike(post) {
    try {
        const res = await apiClient.post('/forum/' + post.id + '/like')
        post.is_liked = res.data?.data?.liked
        post.likes_count += post.is_liked ? 1 : -1
        if (detailPost.value?.id === post.id) {
            detailPost.value.is_liked = post.is_liked
            detailPost.value.likes_count = post.likes_count
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

async function submitReply(post) {
    if (!replyText.value.trim()) { ElMessage.warning('请输入回复内容'); return }
    replying.value = true
    try {
        const res = await apiClient.post('/forum/' + post.id + '/reply', { content: replyText.value })
        const reply = res.data?.data
        if (reply) {
            if (!detailPost.value.replies) detailPost.value.replies = []
            detailPost.value.replies.push(reply)
            detailPost.value.replies_count = (detailPost.value.replies_count || 0) + 1
            replyText.value = ''
            ElMessage.success('回复已发布')
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '回复失败') }
    finally { replying.value = false }
}

async function deletePost(post) {
    try {
        await ElMessageBox.confirm('确定删除此帖子？', '确认', { type: 'warning' })
        await apiClient.delete('/forum/' + post.id)
        ElMessage.success('帖子已删除')
        showDetail.value = false
        await loadPosts()
    } catch { /* ignore */ }
}

function formatTime(t) {
    if (!t) return ''
    const d = new Date(t)
    const pad = n => String(n).padStart(2, '0')
    return pad(d.getMonth() + 1) + '/' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes())
}
function formatFullTime(t) {
    if (!t) return ''
    const d = new Date(t)
    const pad = n => String(n).padStart(2, '0')
    return d.getFullYear() + '/' + pad(d.getMonth() + 1) + '/' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes())
}
</script>

<style scoped>
.forum-panel { display: flex; flex-direction: column; height: 100%; }
.forum-toolbar { display: flex; padding: 8px 12px; border-bottom: 1px solid #eee; align-items: center; }
.chat-dark-mode .forum-toolbar { border-bottom-color: #2a2a3e; }
.forum-list { flex: 1; overflow-y: auto; padding: 4px 8px; }
.forum-post-item { padding: 10px 8px; border-bottom: 1px solid #f0f0f0; cursor: pointer; transition: background 0.15s; }
.forum-post-item:hover { background: #f5f5f5; }
.chat-dark-mode .forum-post-item { border-bottom-color: #2a2a3e; }
.chat-dark-mode .forum-post-item:hover { background: #2a2a3e; }
.forum-post-header { display: flex; align-items: center; margin-bottom: 4px; }
.forum-post-title { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.forum-post-meta { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #999; margin-bottom: 4px; flex-wrap: wrap; }
.forum-post-avatar { width: 18px; height: 18px; border-radius: 50%; object-fit: cover; }
.forum-post-avatar-text { width: 18px; height: 18px; border-radius: 50%; background: #409eff; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; }
.forum-post-author { color: #409eff; font-weight: 500; }
.forum-post-stat { white-space: nowrap; }
.forum-post-time { margin-left: auto; }
.forum-post-preview { font-size: 12px; color: #888; line-height: 1.5; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chat-dark-mode .forum-post-preview { color: #999; }
.empty-hint { padding: 40px; text-align: center; color: #999; font-size: 13px; }

/* ── 帖子详情 ── */
.forum-detail { max-height: 65vh; overflow-y: auto; }
.forum-detail-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #eee; }
.chat-dark-mode .forum-detail-meta { border-bottom-color: #2a2a3e; }
.forum-detail-avatar { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; }
.forum-detail-avatar-text { width: 36px; height: 36px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.forum-detail-author-info { display: flex; flex-direction: column; flex: 1; }
.forum-detail-author { font-size: 14px; font-weight: 600; }
.forum-detail-time { font-size: 11px; color: #999; }
.forum-detail-stats { display: flex; align-items: center; gap: 8px; }
.forum-stat-item { font-size: 12px; color: #999; }
.forum-detail-content { font-size: 14px; line-height: 1.8; color: #333; white-space: pre-wrap; margin-bottom: 16px; }
.chat-dark-mode .forum-detail-content { color: #ccc; }
.forum-detail-replies { border-top: 1px solid #eee; padding-top: 12px; }
.chat-dark-mode .forum-detail-replies { border-top-color: #2a2a3e; }
.forum-detail-replies h4 { font-size: 14px; margin-bottom: 8px; }
.forum-no-replies { text-align: center; padding: 20px; color: #999; font-size: 13px; border-top: 1px solid #eee; }
.chat-dark-mode .forum-no-replies { border-top-color: #2a2a3e; }
.forum-reply-item { display: flex; gap: 8px; padding: 8px 0; border-bottom: 1px solid #f5f5f5; }
.chat-dark-mode .forum-reply-item { border-bottom-color: #2a2a3e; }
.forum-reply-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.forum-reply-avatar-text { width: 28px; height: 28px; border-radius: 50%; background: #67c23a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
.forum-reply-body { flex: 1; min-width: 0; }
.forum-reply-header { display: flex; align-items: center; gap: 6px; margin-bottom: 2px; }
.forum-reply-author { font-size: 12px; font-weight: 600; color: #67c23a; }
.forum-reply-time { font-size: 10px; color: #999; }
.forum-reply-content { font-size: 13px; color: #333; line-height: 1.6; }
.chat-dark-mode .forum-reply-content { color: #ccc; }
.forum-reply-input { margin-top: 12px; padding-top: 12px; border-top: 1px solid #eee; }
.chat-dark-mode .forum-reply-input { border-top-color: #2a2a3e; }
</style>
