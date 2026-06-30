<template>
    <div class="oa-panel">
        <div class="sidebar-header">
            <h3>📢 公众号</h3>
            <div class="sidebar-header-actions">
                <el-button size="small" text @click="emit('openDiscover')" title="发现">
                    <el-icon><Search /></el-icon>
                </el-button>
                <el-button size="small" text @click="showCreateDialog = true" title="创建公众号">
                    <el-icon><Plus /></el-icon>
                </el-button>
                <el-button size="small" text @click="loadData" title="刷新">
                    <el-icon><RefreshLeft /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 已关注的公众号 -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showFollowed = !showFollowed">
                <el-icon><ArrowRight v-if="!showFollowed" /><ArrowDown v-else /></el-icon>
                <span>我的关注</span>
                <span class="oa-count">{{ myAccounts.length }}</span>
            </div>
            <div v-show="showFollowed" class="oa-list" v-loading="loading">
                <div v-for="acc in myAccounts" :key="acc.id" class="oa-item" @click="selectAccount(acc)">
                    <div class="oa-avatar">
                        <img v-if="acc.avatar" :src="acc.avatar" class="oa-avatar-img" />
                        <span v-else class="oa-avatar-text">{{ acc.name?.charAt(0) || '?' }}</span>
                    </div>
                    <div class="oa-info">
                        <div class="oa-name">{{ acc.name }}</div>
                        <div class="oa-meta">
                            <span class="oa-followers">👥 {{ acc.followers_count || 0 }}</span>
                            <span class="oa-articles-count">📄 {{ acc.articles_count || 0 }}</span>
                        </div>
                        <div v-if="acc.latest_article" class="oa-latest">
                            📰 {{ acc.latest_article.title?.substring(0, 30) }}
                        </div>
                    </div>
                    <div class="oa-actions">
                        <el-tag size="small" type="success" style="margin-right:4px">已关注</el-tag>
                        <el-button size="small" text type="danger" @click.stop="unfollow(acc)" title="取消关注">
                            <el-icon><Close /></el-icon>
                        </el-button>
                    </div>
                </div>
                <div v-if="!myAccounts.length && !loading" class="empty-hint">还没有关注公众号</div>
            </div>
        </div>

        <!-- 我的公众号（我创建的） -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showOwned = !showOwned">
                <el-icon><ArrowRight v-if="!showOwned" /><ArrowDown v-else /></el-icon>
                <span>👑 我的公众号</span>
                <span class="oa-count">{{ ownedAccounts.length }}</span>
            </div>
            <div v-show="showOwned" class="oa-list">
                <div v-for="acc in ownedAccounts" :key="acc.id" class="oa-item" @click="selectAccount(acc)">
                    <div class="oa-avatar" style="background:#67c23a">
                        <img v-if="acc.avatar" :src="acc.avatar" />
                        <span v-else>{{ acc.name?.charAt(0) || '?' }}</span>
                    </div>
                    <div class="oa-info">
                        <div class="oa-name">{{ acc.name }}</div>
                        <div class="oa-meta">
                            <span>👥 {{ acc.followers_count || 0 }} 粉丝</span>
                            <span>📄 {{ acc.articles_count || 0 }} 文章</span>
                            <el-tag v-if="acc.pending_count > 0" type="danger" size="small" style="margin-left:auto">
                                {{ acc.pending_count }} 待审核
                            </el-tag>
                        </div>
                    </div>
                    <el-button size="small" text @click.stop="emit('viewPendingReviews', acc)" title="审核投稿">
                        <el-icon><Select /></el-icon>
                    </el-button>
                    <el-button size="small" text @click.stop="openEditDialog(acc)" title="设置">
                        <el-icon><Setting /></el-icon>
                    </el-button>
                </div>
                <div v-if="!ownedAccounts.length" class="empty-hint">还没有创建公众号</div>
            </div>
            <!-- 我的投稿折叠在公众号内部 -->
            <div class="oa-sub-section">
                <div class="oa-sub-title" @click="showMySubmissions = !showMySubmissions">
                    <el-icon><ArrowRight v-if="!showMySubmissions" /><ArrowDown v-else /></el-icon>
                    <span>📝 我的投稿</span>
                    <span class="oa-count">{{ mySubmissions.length }}</span>
                </div>
                <div v-show="showMySubmissions" class="oa-list" style="padding-left:12px">
                    <div v-for="s in mySubmissions" :key="s.id" class="oa-item" @click="emit('viewSubmission', s)" style="padding:6px 4px">
                        <div class="oa-avatar" style="width:28px;height:28px;font-size:10px;background:#e6a23c">✍️</div>
                        <div class="oa-info">
                            <div class="oa-name" style="font-size:12px">{{ s.title?.substring(0, 20) }}</div>
                            <div class="oa-meta" style="font-size:11px">
                                <el-tag :type="s.status === 'approved' ? 'success' : s.status === 'rejected' ? 'danger' : 'warning'" size="small" style="font-size:10px">
                                    {{ s.status === 'approved' ? '已通过' : s.status === 'rejected' ? '已拒绝' : '待审核' }}
                                </el-tag>
                                <span style="margin-left:4px">→ {{ s.account?.name }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!mySubmissions.length" class="empty-hint" style="font-size:12px">暂无投稿</div>
                </div>
            </div>
        </div>

        <!-- 审核管理 -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showReview = !showReview">
                <el-icon><ArrowRight v-if="!showReview" /><ArrowDown v-else /></el-icon>
                <span>🔍 审核管理</span>
                <span v-if="pendingReviewCount > 0" class="oa-count oa-count-warn">{{ pendingReviewCount }}</span>
            </div>
            <div v-show="showReview" class="oa-list">
                <template v-if="ownedAccounts.length">
                    <div v-for="acc in ownedAccounts" :key="acc.id" class="oa-item" @click="emit('viewPendingReviews', acc)">
                        <div class="oa-avatar" style="background:#67c23a;font-size:12px">📋</div>
                        <div class="oa-info">
                            <div class="oa-name">{{ acc.name }}</div>
                            <div class="oa-meta">
                                <span>待审核投稿</span>
                            </div>
                        </div>
                        <el-tag v-if="acc.pending_count > 0" type="danger" size="small">{{ acc.pending_count }}</el-tag>
                    </div>
                </template>
                <div v-else class="empty-hint">你还没有创建公众号</div>
            </div>
        </div>

        <!-- 阅读清单 -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showReadingList = !showReadingList">
                <el-icon><ArrowRight v-if="!showReadingList" /><ArrowDown v-else /></el-icon>
                <span>🔖 阅读清单</span>
                <span class="oa-count">{{ readingList.length }}</span>
            </div>
            <div v-show="showReadingList" class="oa-list" v-loading="loadingReadingList">
                <div v-for="item in readingList" :key="item.id" class="oa-item" @click="emit('viewOaArticle', item.article)">
                    <div class="oa-avatar" style="background:#e6a23c;font-size:16px">🔖</div>
                    <div class="oa-info">
                        <div class="oa-name" style="font-size:12px">{{ item.article?.title?.substring(0, 25) }}</div>
                        <div class="oa-meta" style="font-size:11px">
                            <span>{{ item.article?.account?.name }}</span>
                            <span style="margin-left:4px;color:#999">{{ formatTime(item.created_at) }}</span>
                        </div>
                    </div>
                    <el-button text size="small" type="danger" @click.stop="removeReadingItem(item)">✕</el-button>
                </div>
                <div v-if="!readingList.length && !loadingReadingList" class="empty-hint">阅读清单为空</div>
            </div>
        </div>

        <!-- 🏆 文章排行榜 -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showRanking = !showRanking">
                <el-icon><ArrowRight v-if="!showRanking" /><ArrowDown v-else /></el-icon>
                <span>🏆 文章排行榜</span>
            </div>
            <div v-show="showRanking" class="oa-list">
                <div class="oa-rank-tabs">
                    <el-radio-group v-model="rankingPeriod" size="small" @change="loadRanking">
                        <el-radio-button value="week">本周</el-radio-button>
                        <el-radio-button value="month">本月</el-radio-button>
                    </el-radio-group>
                    <el-radio-group v-model="rankingSort" size="small" @change="loadRanking" style="margin-left:4px">
                        <el-radio-button value="reads">阅读</el-radio-button>
                        <el-radio-button value="likes">点赞</el-radio-button>
                    </el-radio-group>
                </div>
                <div v-loading="loadingRanking" style="min-height:40px">
                    <div v-for="(art, i) in rankingList" :key="art.id" class="oa-rank-item" @click="emit('viewOaArticle', art)">
                        <span class="oa-rank-num" :class="'rank-' + (i + 1)">{{ i + 1 }}</span>
                        <div class="oa-rank-info">
                            <div class="oa-rank-title">{{ art.title }}</div>
                            <div class="oa-rank-meta">
                                <span>{{ art.account?.name }}</span>
                                <span>👁️ {{ art.reads_count }}</span>
                                <span>🤍 {{ art.likes_count }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-if="!rankingList.length && !loadingRanking" class="empty-hint">暂无数据</div>
                </div>
            </div>
        </div>

        <!-- 离线文章 -->
        <div class="oa-section">
            <div class="oa-section-title" @click="showOfflineArticles = !showOfflineArticles">
                <el-icon><ArrowRight v-if="!showOfflineArticles" /><ArrowDown v-else /></el-icon>
                <span>📥 离线文章</span>
                <span class="oa-count">{{ offlineArticles.length }}</span>
            </div>
            <div v-show="showOfflineArticles" class="oa-list">
                <div v-for="art in offlineArticles" :key="art.id" class="oa-item" @click="emit('viewOaArticle', art)">
                    <div class="oa-avatar" style="background:#67c23a;font-size:16px">📥</div>
                    <div class="oa-info">
                        <div class="oa-name" style="font-size:12px">{{ art.title?.substring(0, 25) }}</div>
                        <div class="oa-meta" style="font-size:11px;color:#67c23a">📶 可离线阅读</div>
                    </div>
                    <el-button text size="small" type="danger" @click.stop="removeOfflineArticle(art.id)">✕</el-button>
                </div>
                <div v-if="!offlineArticles.length" class="empty-hint">暂无离线文章</div>
            </div>
        </div>

        <!-- 编辑公众号对话框 -->
        <el-dialog v-model="showEditDialog" title="公众号设置" width="420px" :close-on-click-modal="false">
            <el-form :model="editForm" label-width="70px" size="small">
                <el-form-item label="头像">
                    <div class="avatar-upload-row">
                        <div class="avatar-preview" v-if="editForm.avatar">
                            <img :src="editForm.avatar" class="avatar-preview-img" />
                        </div>
                        <div class="avatar-placeholder" v-else>📷</div>
                        <div class="avatar-upload-actions">
                            <el-upload :show-file-list="false" :http-request="uploadEditAvatar" accept="image/*">
                                <el-button size="small" text>上传图片</el-button>
                            </el-upload>
                            <el-input v-model="editForm.avatar" placeholder="或输入图片URL" size="small" style="width:240px;margin-top:4px" />
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="名称" required>
                    <el-input v-model="editForm.name" placeholder="公众号名称" maxlength="100" />
                    <div style="font-size:11px;color:#999;margin-top:2px">
                        每年可修改 {{ editForm.name_change_limit }} 次，已用 {{ editForm.name_change_count }} 次
                    </div>
                </el-form-item>
                <el-form-item label="简介">
                    <el-input v-model="editForm.description" type="textarea" :rows="2" placeholder="公众号简介（不限制修改次数）" maxlength="500" />
                </el-form-item>
                <el-form-item label="分类">
                    <el-input :model-value="editForm.category?.name || '未设置'" disabled size="small">
                        <template #prefix>{{ editForm.category?.icon || '📁' }}</template>
                    </el-input>
                    <div style="font-size:11px;color:#999;margin-top:2px">分类创建后不可更改</div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showEditDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="saving" @click="doSaveEdit">保存</el-button>
            </template>
        </el-dialog>

        <!-- 创建公众号对话框 -->
        <el-dialog v-model="showCreateDialog" title="创建公众号" width="420px" :close-on-click-modal="false">
            <el-form :model="createForm" label-width="70px" size="small">
                <el-form-item label="名称" required>
                    <el-input v-model="createForm.name" placeholder="公众号名称" maxlength="100" />
                    <div style="font-size:11px;color:#999;margin-top:2px">创建后每年仅可修改3次</div>
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="createForm.description" type="textarea" :rows="2" placeholder="公众号简介" maxlength="500" />
                    <div style="font-size:11px;color:#999;margin-top:2px">简介不限制修改次数</div>
                </el-form-item>
                <el-form-item label="分类">
                    <el-select v-model="createForm.category_id" placeholder="选择分类..." clearable style="width:100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.icon + ' ' + c.name" :value="c.id" />
                    </el-select>
                    <div style="font-size:11px;color:#999;margin-top:2px">分类创建后不可更改</div>
                </el-form-item>
                <el-form-item label="头像">
                    <div class="avatar-upload-row">
                        <div class="avatar-preview" v-if="createForm.avatar">
                            <img :src="createForm.avatar" class="avatar-preview-img" />
                        </div>
                        <div class="avatar-placeholder" v-else>📷</div>
                        <div class="avatar-upload-actions">
                            <el-upload :show-file-list="false" :http-request="uploadAvatarFile" accept="image/*">
                                <el-button size="small" text>上传图片</el-button>
                            </el-upload>
                            <el-input v-model="createForm.avatar" placeholder="或输入图片URL" size="small" style="width:240px;margin-top:4px" />
                        </div>
                    </div>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showCreateDialog = false">取消</el-button>
                <el-button size="small" type="primary" :loading="creating" @click="doCreate">创建</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, Search, RefreshLeft, ArrowRight, ArrowDown, Close, Setting } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const emit = defineEmits(['selectAccount', 'viewSubmission', 'viewPendingReviews', 'openDiscover', 'refreshUnread', 'viewOaArticle'])

const myAccounts = ref([])
const allAccounts = ref([])
const mySubmissions = ref([])
const ownedAccounts = ref([])
const pendingReviewCount = ref(0)
const loading = ref(false)
const showFollowed = ref(true)
const showDiscover = ref(false)
const showMySubmissions = ref(false)
const showOwned = ref(true)
const showReview = ref(false)
const showReadingList = ref(false)
const readingList = ref([])
const loadingReadingList = ref(false)
const showOfflineArticles = ref(false)
const offlineArticles = ref([])
const showRanking = ref(false)
const rankingPeriod = ref('week')
const rankingSort = ref('reads')
const rankingList = ref([])
const loadingRanking = ref(false)

const showCreateDialog = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', description: '', avatar: '', category_id: null })
const categories = ref([])
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || ''

onMounted(() => {
    loadData()
    loadOfflineArticles()
    loadRanking()
    // 监听 SW 消息
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data?.type === 'CACHED_ARTICLES') {
                offlineArticles.value = event.data.articles || []
            }
        })
    }
})

async function loadData() {
    loading.value = true
    try {
        const [myRes, allRes, subRes, ownedRes, catRes] = await Promise.all([
            apiClient.get('/official-accounts/my'),
            apiClient.get('/official-accounts'),
            apiClient.get('/official-accounts/my-submissions'),
            apiClient.get('/official-accounts/my-owned'),
            apiClient.get('/official-accounts/categories'),
        ])
        myAccounts.value = myRes.data?.data || []
        allAccounts.value = allRes.data?.data || []
        mySubmissions.value = subRes.data?.data || []
        ownedAccounts.value = (ownedRes.data?.data || []).map(a => ({ ...a, pending_count: 0 }))
        categories.value = catRes.data?.data || []
        // 获取每个公众号的待审核数
        for (const acc of ownedAccounts.value) {
            try {
                const pRes = await apiClient.get(`/official-accounts/${acc.id}/submissions/pending`)
                const pData = pRes.data?.data || {}
                const submissions = pData.submissions || pData.data || pData || []
                acc.pending_count = Array.isArray(submissions) ? submissions.length : 0
            } catch { /* ignore */ }
        }
        pendingReviewCount.value = ownedAccounts.value.reduce((s, a) => s + (a.pending_count || 0), 0)
        loadReadingList()
    } catch { /* ignore */ }
    finally { loading.value = false }
}

function selectAccount(acc) {
    emit('selectAccount', acc)
}

async function doFollow(acc) {
    try {
        await apiClient.post('/official-accounts/' + acc.id + '/follow')
        ElMessage.success('关注成功')
        await loadData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '关注失败')
    }
}

async function unfollow(acc) {
    try {
        await apiClient.post('/official-accounts/' + acc.id + '/unfollow')
        ElMessage.success('已取消关注')
        await loadData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function doCreate() {
    if (!createForm.value.name.trim()) { ElMessage.warning('请输入名称'); return }
    creating.value = true
    try {
        await apiClient.post('/official-accounts', { ...createForm.value })
        ElMessage.success('公众号已创建')
        showCreateDialog.value = false
        createForm.value = { name: '', description: '', avatar: '', category_id: null }
        await loadData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
    } finally {
        creating.value = false
    }
}

async function uploadAvatarFile(options) {
    const formData = new FormData();
    formData.append('file', options.file);
    try {
        const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.data?.url) {
            createForm.value.avatar = res.data.data.url;
            ElMessage.success('头像已上传');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败');
    }
}

async function loadCategories() {
    try { const r = await apiClient.get('/official-accounts/categories'); categories.value = r.data?.data || [] } catch { categories.value = [] }
}

// ── 编辑公众号 ──
const showEditDialog = ref(false)
const editForm = ref({ name: '', description: '', avatar: '', category: null, category_id: null, name_change_count: 0, name_change_limit: 3 })
const editAccountId = ref(null)
const saving = ref(false)

async function openEditDialog(acc) {
    try {
        const r = await apiClient.get(`/official-accounts/${acc.id}/edit-info`)
        const data = r.data?.data || {}
        editForm.value = {
            name: data.name || '',
            description: data.description || '',
            avatar: data.avatar || '',
            category: data.category || null,
            category_id: data.category_id || null,
            name_change_count: data.name_change_count || 0,
            name_change_limit: data.name_change_limit || 3,
        }
        editAccountId.value = acc.id
        showEditDialog.value = true
    } catch (e) {
        ElMessage.error('获取编辑信息失败')
    }
}

async function doSaveEdit() {
    if (!editForm.value.name.trim()) { ElMessage.warning('请输入名称'); return }
    saving.value = true
    try {
        await apiClient.put(`/official-accounts/${editAccountId.value}`, {
            name: editForm.value.name,
            description: editForm.value.description,
            avatar: editForm.value.avatar,
        })
        ElMessage.success('已保存')
        showEditDialog.value = false
        await loadData()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败')
    } finally {
        saving.value = false
    }
}

async function uploadEditAvatar(options) {
    const formData = new FormData();
    formData.append('file', options.file);
    try {
        const res = await apiClient.post('/official-accounts/upload-avatar', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        if (res.data?.data?.url) {
            editForm.value.avatar = res.data.data.url;
            ElMessage.success('头像已上传');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败');
    }
}

async function loadReadingList() {
    loadingReadingList.value = true
    try {
        const res = await apiClient.get('/official-accounts/reading-list')
        readingList.value = res.data?.data || []
    } catch { readingList.value = [] }
    finally { loadingReadingList.value = false }
}

async function removeReadingItem(item) {
    try {
        await apiClient.delete('/official-accounts/reading-list/' + item.article_id)
        readingList.value = readingList.value.filter(x => x.id !== item.id)
        ElMessage.success('已移除')
    } catch { ElMessage.error('操作失败') }
}

function loadOfflineArticles() {
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'GET_CACHED_ARTICLES' })
    }
}

function removeOfflineArticle(articleId) {
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'UNCACHE_ARTICLE', articleId })
        offlineArticles.value = offlineArticles.value.filter(a => a.id !== articleId)
        ElMessage.success('已移除离线缓存')
    }
}

async function loadRanking() {
    loadingRanking.value = true
    try {
        const res = await apiClient.get('/official-accounts/ranking', {
            params: { period: rankingPeriod.value, sort: rankingSort.value }
        })
        rankingList.value = res.data?.data || []
    } catch { rankingList.value = [] }
    finally { loadingRanking.value = false }
}

defineExpose({ openEditDialog });

function formatTime(date) {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const pad = n => String(n).padStart(2, '0')
  const diff = Math.floor((now - d) / 1000)
  if (diff < 60) return '刚刚'
  if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
  if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
  if (diff < 172800) return '昨天'
  if (d.getFullYear() === now.getFullYear()) return (d.getMonth() + 1) + '月' + d.getDate() + '日'
  return d.getFullYear() + '年' + (d.getMonth() + 1) + '月' + d.getDate() + '日'
}
</script>

<style scoped>
.oa-panel { display: flex; flex-direction: column; height: 100%; }
.oa-section { border-bottom: 1px solid #eee; }
.chat-dark-mode .oa-section { border-bottom-color: #2a2a3e; }
.oa-section-title {
    display: flex; align-items: center; gap: 4px; padding: 8px 12px; font-size: 13px; color: #666; cursor: pointer; user-select: none;
}
.avatar-upload-row { display: flex; align-items: flex-start; gap: 12px; }
.avatar-preview, .avatar-placeholder {
    width: 60px; height: 60px; border-radius: 50%; overflow: hidden;
    border: 1px solid #e0e0e0; display: flex; align-items: center; justify-content: center;
    background: #f5f7fa; flex-shrink: 0;
}
.avatar-preview-img { width: 100%; height: 100%; object-fit: cover; }
.avatar-placeholder { font-size: 24px; }
.avatar-upload-actions { display: flex; flex-direction: column; }
.oa-count { margin-left: auto; font-size: 11px; color: #999; background: #eee; padding: 1px 6px; border-radius: 8px; }
.oa-count-warn { background: #f56c6c; color: #fff; }
.chat-dark-mode .oa-count { background: #3a3a4e; color: #aaa; }
.oa-list { padding: 0 8px 8px; }
.oa-item {
    display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;
}
.oa-item:hover { background: #f0f0f0; }
.chat-dark-mode .oa-item:hover { background: #2a2a3e; }
.oa-avatar {
    width: 40px; height: 40px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; background: #409eff; color: #fff; font-size: 16px;
}
.oa-avatar-img { width: 100%; height: 100%; object-fit: cover; }
.oa-info { flex: 1; min-width: 0; }
.oa-name { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-meta { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #999; margin-top: 2px; }
.oa-desc { font-size: 12px; color: #999; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-latest { font-size: 11px; color: #999; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-actions { display: flex; align-items: center; flex-shrink: 0; }
.oa-sub-section { border-top: 1px dashed #eee; margin-top: 4px; }
.chat-dark-mode .oa-sub-section { border-top-color: #2a2a3e; }
.oa-sub-title { display: flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 12px; color: #888; cursor: pointer; user-select: none; }
.oa-sub-title:hover { background: #f5f5f5; }
.chat-dark-mode .oa-sub-title:hover { background: #2a2a3e; }
.empty-hint { padding: 20px; text-align: center; color: #999; font-size: 13px; }

/* ── 排行榜 ── */
.oa-rank-tabs { display: flex; gap: 4px; padding: 4px 8px; flex-wrap: wrap; }
.oa-rank-item { display: flex; align-items: center; gap: 6px; padding: 6px 8px; border-radius: 6px; cursor: pointer; transition: background .15s; }
.oa-rank-item:hover { background: #f5f5f5; }
.chat-dark-mode .oa-rank-item:hover { background: #1a1a2e; }
.oa-rank-num { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; flex-shrink: 0; background: #f0f0f0; color: #999; }
.chat-dark-mode .oa-rank-num { background: #2a2a3e; color: #aaa; }
.oa-rank-num.rank-1 { background: #f56c6c; color: #fff; }
.oa-rank-num.rank-2 { background: #e6a23c; color: #fff; }
.oa-rank-num.rank-3 { background: #409eff; color: #fff; }
.oa-rank-info { flex: 1; min-width: 0; }
.oa-rank-title { font-size: 12px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.oa-rank-meta { display: flex; gap: 6px; font-size: 10px; color: #999; margin-top: 1px; }
</style>
