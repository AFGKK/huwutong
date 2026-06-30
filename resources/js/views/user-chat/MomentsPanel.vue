<template>
    <div class="moments-panel">
        <div class="sidebar-header">
            <h3>📸 朋友圈</h3>
            <div class="sidebar-header-actions">
                <el-button size="small" type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 发表
                </el-button>
                <el-button size="small" text @click="loadMoments" title="刷新">
                    <el-icon><RefreshLeft /></el-icon>
                </el-button>
            </div>
        </div>

        <div class="moments-feed" v-loading="loading">
            <div v-for="m in moments" :key="m.id" class="moment-card">
                <!-- 头部：头像+名称+时间 -->
                <div class="moment-header">
                    <img v-if="m.user?.avatar_url" :src="m.user.avatar_url" class="moment-avatar" />
                    <span v-else class="moment-avatar-text">{{ m.user?.name?.charAt(0) || '?' }}</span>
                    <div class="moment-user-info">
                        <span class="moment-username">{{ m.user?.name || '用户' }}</span>
                        <span class="moment-time">{{ formatTime(m.created_at) }}</span>
                    </div>
                    <el-button v-if="m.user_id === myId" text size="small" type="danger" @click.stop="deleteMoment(m)" style="margin-left:auto">删除</el-button>
                </div>

                <!-- 内容 -->
                <div class="moment-content">{{ m.content }}</div>

                <!-- 图片 -->
                <div v-if="m.images?.length" class="moment-images" :class="'img-count-' + Math.min(m.images.length, 9)">
                    <div v-for="(img, i) in m.images.slice(0, 9)" :key="i" class="moment-img-wrap" @click="previewImage(img)">
                        <img :src="img" class="moment-img" />
                    </div>
                </div>

                <!-- 统计栏 -->
                <div class="moment-stats">
                    <span v-if="m.likes_count > 0">❤️ {{ m.likes_count }}</span>
                    <span v-if="m.replies_count > 0" style="margin-left:12px">💬 {{ m.replies_count }}</span>
                </div>

                <!-- 操作栏 -->
                <div class="moment-actions">
                    <el-button text size="small" @click.stop="toggleLike(m)" :type="m._liked ? 'primary' : 'default'">
                        {{ m._liked ? '❤️ 已赞' : '👍 赞' }}
                    </el-button>
                    <el-button text size="small" @click.stop="toggleComments(m)">
                        💬 评论({{ m.replies_count || 0 }})
                    </el-button>
                </div>

                <!-- 评论区域 -->
                <div v-if="m._showComments" class="moment-comments">
                    <div v-if="m._loadingComments" style="text-align:center;padding:8px;color:#999">加载中...</div>
                    <div v-else-if="m._comments?.length" class="comment-list">
                        <div v-for="c in m._comments" :key="c.id" class="comment-item">
                            <img v-if="c.user?.avatar_url" :src="c.user.avatar_url" class="comment-avatar" />
                            <span v-else class="comment-avatar-text">{{ c.user?.name?.charAt(0) || '?' }}</span>
                            <div class="comment-body">
                                <span class="comment-author">{{ c.user?.name || '用户' }}</span>
                                <span class="comment-text">{{ c.content }}</span>
                                <span class="comment-time">{{ formatTime(c.created_at) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else style="text-align:center;padding:8px;color:#999">暂无评论</div>
                    <div class="comment-input-row">
                        <el-input v-model="m._commentText" size="small" placeholder="写评论..." @keydown.enter.prevent="submitComment(m)" />
                        <el-button size="small" type="primary" @click="submitComment(m)" :loading="m._submitting">发送</el-button>
                    </div>
                </div>
            </div>

            <div v-if="!moments.length && !loading" class="empty-hint">暂无朋友圈，来发表第一条吧</div>
        </div>

        <!-- 发表对话框 -->
        <el-dialog v-model="showCreate" title="📸 发表朋友圈" width="480px" :close-on-click-modal="false">
            <el-form label-width="0">
                <el-input v-model="createContent" type="textarea" :rows="5" placeholder="说点什么..." maxlength="5000" />
                <div class="moment-upload-area" v-if="createImages.length">
                    <div v-for="(img, i) in createImages" :key="i" class="moment-preview-img-wrap">
                        <img :src="img" class="moment-preview-img" />
                        <el-button class="moment-remove-img" text size="small" type="danger" @click="createImages.splice(i, 1)">×</el-button>
                    </div>
                </div>
                <div style="margin-top:8px">
                    <el-upload :show-file-list="false" :http-request="uploadMomentImage" accept="image/*">
                        <el-button size="small"><el-icon><Picture /></el-icon> 添加图片</el-button>
                    </el-upload>
                </div>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showCreate = false">取消</el-button>
                <el-button size="small" type="primary" :loading="creating" @click="submitMoment">发表</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, RefreshLeft, Picture } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const props = defineProps({ myId: { type: Number, default: 0 } })

const moments = ref([])
const loading = ref(false)
const showCreate = ref(false)
const creating = ref(false)
const createContent = ref('')
const createImages = ref([])

onMounted(() => { loadMoments() })

async function loadMoments() {
    loading.value = true
    try {
        const res = await apiClient.get('/moments')
        const list = res.data?.data || []
        for (const m of list) {
            m._showComments = false
            m._loadingComments = false
            m._comments = []
            m._commentText = ''
            m._submitting = false
            // 检查是否已点赞
            m._liked = false
        }
        moments.value = list
    } catch { moments.value = [] }
    finally { loading.value = false }
}

async function submitMoment() {
    if (!createContent.value.trim()) { ElMessage.warning('说点什么吧'); return }
    creating.value = true
    try {
        await apiClient.post('/moments', {
            content: createContent.value.trim(),
            images: createImages.value.length ? createImages.value : undefined,
        })
        ElMessage.success('已发布')
        showCreate.value = false
        createContent.value = ''
        createImages.value = []
        await loadMoments()
    } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') }
    finally { creating.value = false }
}

function uploadMomentImage(options) {
    const reader = new FileReader()
    reader.onload = (e) => {
        createImages.value.push(e.target.result)
        ElMessage.success('已添加图片')
    }
    reader.readAsDataURL(options.file)
}

async function toggleLike(m) {
    try {
        const res = await apiClient.post('/moments/' + m.id + '/like')
        m._liked = res.data?.data?.liked
        m.likes_count = res.data?.data?.likes_count
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败') }
}

async function toggleComments(m) {
    m._showComments = !m._showComments
    if (m._showComments && !m._comments?.length) {
        m._loadingComments = true
        try {
            const res = await apiClient.get('/moments/' + m.id + '/comments')
            m._comments = res.data?.data || []
        } catch { m._comments = [] }
        finally { m._loadingComments = false }
    }
}

async function submitComment(m) {
    const text = m._commentText?.trim()
    if (!text) return
    m._submitting = true
    try {
        const res = await apiClient.post('/moments/' + m.id + '/comment', { content: text })
        const comment = res.data?.data
        if (comment) {
            if (!m._comments) m._comments = []
            m._comments.push(comment)
            m.replies_count = (m.replies_count || 0) + 1
            m._commentText = ''
        }
    } catch (e) { ElMessage.error(e.response?.data?.message || '评论失败') }
    finally { m._submitting = false }
}

async function deleteMoment(m) {
    try {
        await ElMessageBox.confirm('确定删除此朋友圈？', '确认', { type: 'warning' })
        await apiClient.delete('/moments/' + m.id)
        ElMessage.success('已删除')
        moments.value = moments.value.filter(x => x.id !== m.id)
    } catch { /* ignore */ }
}

function previewImage(url) { window.open(url, '_blank') }

function formatTime(t) {
    if (!t) return ''
    const d = new Date(t)
    const now = new Date()
    const pad = n => String(n).padStart(2, '0')
    const diff = Math.floor((now - d) / 1000)
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    if (diff < 172800) return '昨天 ' + pad(d.getHours()) + ':' + pad(d.getMinutes())
    return pad(d.getMonth() + 1) + '/' + pad(d.getDate()) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes())
}
</script>

<style scoped>
.moments-panel { display: flex; flex-direction: column; height: 100%; }
.moments-feed { flex: 1; overflow-y: auto; padding: 8px; }

/* ── 朋友圈卡片 ── */
.moment-card { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 10px; border: 1px solid #eee; }
.chat-dark-mode .moment-card { background: #1a1a2e; border-color: #2a2a3e; }
.moment-header { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.moment-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; }
.moment-avatar-text { width: 40px; height: 40px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.moment-user-info { display: flex; flex-direction: column; }
.moment-username { font-size: 14px; font-weight: 600; color: #409eff; }
.moment-time { font-size: 11px; color: #999; }
.moment-content { font-size: 14px; line-height: 1.7; color: #333; white-space: pre-wrap; margin-bottom: 8px; }
.chat-dark-mode .moment-content { color: #ccc; }

/* ── 图片 ── */
.moment-images { display: grid; gap: 4px; margin-bottom: 8px; }
.img-count-1 { grid-template-columns: 1fr; }
.img-count-2 { grid-template-columns: 1fr 1fr; }
.img-count-3 { grid-template-columns: 1fr 1fr 1fr; }
.img-count-4, .img-count-5, .img-count-6 { grid-template-columns: 1fr 1fr; }
.img-count-7, .img-count-8, .img-count-9 { grid-template-columns: 1fr 1fr 1fr; }
.moment-img-wrap { border-radius: 6px; overflow: hidden; cursor: pointer; aspect-ratio: 1; }
.moment-img { width: 100%; height: 100%; object-fit: cover; }

/* ── 统计 ── */
.moment-stats { font-size: 12px; color: #999; padding: 6px 0; border-bottom: 1px solid #f0f0f0; margin-bottom: 4px; }
.chat-dark-mode .moment-stats { border-bottom-color: #2a2a3e; }

/* ── 操作栏 ── */
.moment-actions { display: flex; gap: 8px; padding: 4px 0; border-bottom: 1px solid #f0f0f0; }
.chat-dark-mode .moment-actions { border-bottom-color: #2a2a3e; }

/* ── 评论 ── */
.moment-comments { padding: 8px 0; }
.comment-list { margin-bottom: 8px; }
.comment-item { display: flex; gap: 6px; padding: 4px 0; align-items: flex-start; }
.comment-avatar { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.comment-avatar-text { width: 24px; height: 24px; border-radius: 50%; background: #67c23a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0; }
.comment-body { flex: 1; font-size: 13px; line-height: 1.5; }
.comment-author { font-weight: 600; color: #409eff; margin-right: 4px; }
.comment-text { color: #333; }
.chat-dark-mode .comment-text { color: #ccc; }
.comment-time { display: block; font-size: 10px; color: #999; margin-top: 1px; }
.comment-input-row { display: flex; gap: 6px; align-items: center; }
.comment-input-row .el-input { flex: 1; }

/* ── 发表 ── */
.moment-upload-area { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.moment-preview-img-wrap { position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; }
.moment-preview-img { width: 100%; height: 100%; object-fit: cover; }
.moment-remove-img { position: absolute; top: 0; right: 0; min-width: auto !important; padding: 0 4px !important; height: 20px !important; font-size: 14px; }

.empty-hint { padding: 40px; text-align: center; color: #999; font-size: 13px; }
</style>
