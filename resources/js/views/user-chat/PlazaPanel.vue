<template>
    <div class="plaza-panel">
        <div class="sidebar-header">
            <h3>🌐 广场</h3>
            <div class="sidebar-header-actions">
                <el-button size="small" type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon> 发表
                </el-button>
                <el-button size="small" text @click="loadMyPosts" title="刷新">
                    <el-icon><RefreshLeft /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 分类标签 -->
        <div class="plaza-categories">
            <div v-for="cat in staticCategories" :key="cat.key"
                class="plaza-cat-item"
                :class="{ active: activeCategory === cat.key }"
                @click="selectCategory(cat.key)">
                <span class="plaza-cat-icon">{{ cat.icon }}</span>
                <span class="plaza-cat-label">{{ cat.label }}</span>
            </div>
        </div>
        <!-- 话题分类 -->
        <div v-if="forumCategories.length" class="plaza-topic-categories">
            <div v-for="cat in forumCategories" :key="'fc_'+cat.id"
                class="plaza-cat-item plaza-topic-item"
                :class="{ active: activeCategory === 'cat_' + cat.id }"
                @click="selectCategory('cat_' + cat.id, cat.id)">
                <span class="plaza-cat-icon">{{ cat.icon || '📁' }}</span>
                <span class="plaza-cat-label">{{ cat.name }}</span>
            </div>
        </div>

        <!-- 搜索框 -->
        <div class="plaza-search">
            <el-input v-model="searchKeyword" size="small" placeholder="搜索广场..." clearable
                @keydown.enter="doSearch" @clear="doSearch">
                <template #prefix><el-icon><Search /></el-icon></template>
            </el-input>
        </div>

        <!-- 📊 我的数据 -->
        <div class="plaza-stats-section">
            <div class="plaza-section-title"><span>📊 我的数据</span></div>
            <div class="plaza-stats-list">
                <div class="plaza-stat-item">
                    <span class="plaza-stat-icon">📝</span>
                    <span class="plaza-stat-label">发布</span>
                    <span class="plaza-stat-value">{{ myStats.posts_count }}</span>
                    <span class="plaza-stat-unit">篇</span>
                </div>
                <div class="plaza-stat-item">
                    <span class="plaza-stat-icon">👍</span>
                    <span class="plaza-stat-label">获得赞</span>
                    <span class="plaza-stat-value">{{ myStats.total_likes }}</span>
                    <span class="plaza-stat-unit">个</span>
                </div>
                <div class="plaza-stat-item">
                    <span class="plaza-stat-icon">⭐</span>
                    <span class="plaza-stat-label">被收藏</span>
                    <span class="plaza-stat-value">{{ myStats.total_favorites }}</span>
                    <span class="plaza-stat-unit">次</span>
                </div>
                <div class="plaza-stat-item">
                    <span class="plaza-stat-icon">💬</span>
                    <span class="plaza-stat-label">收到评论</span>
                    <span class="plaza-stat-value">{{ myStats.total_replies }}</span>
                    <span class="plaza-stat-unit">条</span>
                </div>
                <div class="plaza-stat-item">
                    <span class="plaza-stat-icon">👁️</span>
                    <span class="plaza-stat-label">总浏览</span>
                    <span class="plaza-stat-value">{{ myStats.total_views }}</span>
                    <span class="plaza-stat-unit">次</span>
                </div>
            </div>
        </div>

        <!-- 热门话题标签 -->
        <div v-if="trendingTags.length" class="plaza-tags-section">
            <div class="plaza-section-title"><span>🔥 热门话题</span></div>
            <div class="plaza-tags-cloud">
                <span v-for="tag in trendingTags" :key="tag.id"
                    class="plaza-tag-chip"
                    :class="{ active: activeTag === tag.slug }"
                    @click="selectTag(tag.slug)">
                    #{{ tag.name }}
                </span>
            </div>
        </div>

        <el-divider style="margin:8px 0" />

        <!-- 我的发布 -->
        <div class="plaza-my-posts">
            <div class="plaza-section-title" @click="showMyPosts = !showMyPosts">
                <el-icon><ArrowRight v-if="!showMyPosts" /><ArrowDown v-else /></el-icon>
                <span>📋 我的发布</span>
                <span class="plaza-count">{{ myPostsList.length }}</span>
                <el-tag v-if="draftCount > 0" size="small" type="warning" style="margin-left:4px;cursor:pointer" @click.stop="showDrafts = !showDrafts">草稿 {{ draftCount }}</el-tag>
            </div>
            <div v-show="showMyPosts" class="plaza-my-list" v-loading="loadingMy">
                <div v-for="p in myPostsList" :key="p.id" class="plaza-my-item" @click="emit('viewMyPost', p)">
                    <div class="plaza-my-thumb" v-if="p.images?.length">
                        <img :src="p.images[0]" class="plaza-my-img" />
                    </div>
                    <div class="plaza-my-thumb plaza-my-thumb-text" v-else>📝</div>
                    <div class="plaza-my-info">
                        <div class="plaza-my-content">{{ stripHtml(p.content).substring(0, 30) }}{{ (stripHtml(p.content) || '').length > 30 ? '...' : '' }}</div>
                        <div class="plaza-my-meta">
                            <span>🤍 {{ p.likes_count || 0 }}</span>
                            <span style="margin-left:8px">💬 {{ p.replies_count || 0 }}</span>
                            <span v-if="p.status === 'scheduled'" style="margin-left:8px;color:#e6a23c">⏰ {{ formatTime(p.scheduled_at) }}</span>
                            <span v-else style="margin-left:8px" class="plaza-my-time">{{ formatTime(p.created_at) }}</span>
                        </div>
                    </div>
                    <el-button v-if="p.user_id === myId || p.user?.id === myId" text size="small" type="danger"
                        @click.stop="deleteMyPost(p)" title="删除">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
                <div v-if="!myPostsList.length && !loadingMy" class="empty-hint" style="padding:16px;font-size:12px">
                    还没有发布内容
                </div>
            </div>
        </div>

        <!-- 草稿箱 -->
        <div v-if="draftCount > 0" class="plaza-my-posts">
            <div class="plaza-section-title" @click="showDrafts = !showDrafts">
                <el-icon><ArrowRight v-if="!showDrafts" /><ArrowDown v-else /></el-icon>
                <span>📝 草稿箱</span>
                <span class="plaza-count">{{ draftCount }}</span>
            </div>
            <div v-show="showDrafts" class="plaza-my-list" v-loading="loadingDrafts">
                <div v-for="p in draftList" :key="p.id" class="plaza-my-item" @click="editDraft(p)">
                    <div class="plaza-my-thumb plaza-my-thumb-text" v-if="!p.images?.length">📝</div>
                    <div class="plaza-my-thumb" v-else><img :src="p.images[0]" class="plaza-my-img" /></div>
                    <div class="plaza-my-info">
                        <div class="plaza-my-content">{{ stripHtml(p.content).substring(0, 30) }}{{ (stripHtml(p.content) || '').length > 30 ? '...' : '' }}</div>
                        <div class="plaza-my-meta">
                            <el-tag size="small" type="warning" style="font-size:10px;padding:0 4px;height:18px">草稿</el-tag>
                            <span style="margin-left:4px" class="plaza-my-time">{{ formatTime(p.updated_at) }}</span>
                        </div>
                    </div>
                    <el-button text size="small" type="danger" @click.stop="deleteDraft(p)" title="删除">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
            </div>
        </div>

        <!-- 我的收藏 -->
        <div class="plaza-my-posts">
            <div class="plaza-section-title" @click="showFavorites = !showFavorites">
                <el-icon><ArrowRight v-if="!showFavorites" /><ArrowDown v-else /></el-icon>
                <span>⭐ 我的收藏</span>
                <span class="plaza-count">{{ favoritesCount }}</span>
            </div>
            <div v-show="showFavorites" class="plaza-my-list" v-loading="loadingFavorites">
                <!-- 收藏夹快捷筛选 -->
                <div v-if="favoriteCollections.length" class="plaza-fav-collections">
                    <div v-for="col in favoriteCollections" :key="col.id" class="plaza-fav-col-item" @click="emit('selectCategory', 'all', undefined, '', '', col.id)">
                        <span>{{ col.icon || '📁' }}</span>
                        <span>{{ col.name }}</span>
                        <span class="plaza-count">{{ col.favorites_count }}</span>
                    </div>
                    <div v-if="uncategorizedCount > 0" class="plaza-fav-col-item" @click="emit('selectCategory', 'all', undefined, '', '', 'uncategorized')">
                        <span>📦</span>
                        <span>未分类</span>
                        <span class="plaza-count">{{ uncategorizedCount }}</span>
                    </div>
                </div>
                <div v-for="p in favoritesList" :key="p.id" class="plaza-my-item" @click="emit('viewMyPost', p.post)">
                    <div class="plaza-my-thumb" v-if="p.post?.images?.length">
                        <img :src="p.post.images[0]" class="plaza-my-img" />
                    </div>
                    <div class="plaza-my-thumb plaza-my-thumb-text" v-else>⭐</div>
                    <div class="plaza-my-info">
                        <div class="plaza-my-content">{{ stripHtml(p.post?.content).substring(0, 30) }}{{ (stripHtml(p.post?.content) || '').length > 30 ? '...' : '' }}</div>
                        <div class="plaza-my-meta">
                            <span>{{ p.collection?.icon || '📦' }} {{ p.collection?.name || '未分类' }}</span>
                            <span style="margin-left:8px" class="plaza-my-time">{{ formatTime(p.created_at) }}</span>
                        </div>
                    </div>
                    <el-button text size="small" type="danger" @click.stop="removeFav(p.post_id)" title="取消收藏">
                        <el-icon><Delete /></el-icon>
                    </el-button>
                </div>
                <div v-if="!favoritesList.length && !loadingFavorites" class="empty-hint" style="padding:16px;font-size:12px">
                    还没有收藏内容
                </div>
            </div>
        </div>

        <!-- 发表对话框 -->
        <el-dialog v-model="showCreate" title="🌐 发表到广场" width="520px" :close-on-click-modal="false">
            <el-form label-width="0">
                <PlazaEditor v-model="createContent" placeholder="分享新鲜事..." :height="250" />
                <div style="margin-top:8px">
                    <el-select v-model="createCategoryId" placeholder="选择话题（可选）" clearable style="width:100%">
                        <el-option v-for="c in forumCategories" :key="c.id" :label="(c.icon || '📁') + ' ' + c.name" :value="c.id" />
                    </el-select>
                </div>
                <div class="plaza-upload-area" v-if="createImages.length">
                    <div v-for="(img, i) in createImages" :key="i" class="plaza-preview-img-wrap">
                        <img :src="img" class="plaza-preview-img" />
                        <el-button class="plaza-remove-img" text size="small" type="danger" @click="createImages.splice(i, 1)">×</el-button>
                    </div>
                </div>
                <div style="margin-top:8px;display:flex;gap:8px">
                    <el-upload :show-file-list="false" :http-request="uploadImage" accept="image/*">
                        <el-button size="small"><el-icon><Picture /></el-icon> 添加图片</el-button>
                    </el-upload>
                    <el-upload :show-file-list="false" :http-request="uploadVideoFile" accept="video/*">
                        <el-button size="small"><el-icon><VideoCamera /></el-icon> 添加视频</el-button>
                    </el-upload>
                </div>
                <div v-if="createVideoUrl" style="margin-top:6px;display:flex;align-items:center;gap:6px">
                    <video :src="createVideoUrl" style="max-width:120px;max-height:80px;border-radius:4px" controls />
                    <span style="font-size:12px;color:#666">视频已添加</span>
                    <el-button size="small" text type="danger" @click="createVideoUrl = ''; createVideoFile = null">× 移除</el-button>
                </div>

                <!-- 帖子模板 -->
                <el-divider style="margin:12px 0" />
                <div class="plaza-template-selector">
                    <div style="margin-bottom:6px;font-size:12px;color:#909399">🎨 帖子模板</div>
                    <div class="plaza-template-grid">
                        <div v-for="tpl in postTemplates" :key="tpl.key"
                            class="plaza-template-item"
                            :class="{ active: createTemplate === tpl.key }"
                            @click="selectTemplate(tpl.key)">
                            <span class="plaza-tpl-icon">{{ tpl.icon }}</span>
                            <span class="plaza-tpl-label">{{ tpl.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- 投票 -->
                <el-divider style="margin:12px 0" />
                <div class="plaza-poll-creator">
                    <el-checkbox v-model="enablePoll" style="margin-bottom:6px">📊 添加投票</el-checkbox>
                    <template v-if="enablePoll">
                        <el-input v-model="pollQuestion" size="small" placeholder="投票问题..." style="margin-bottom:6px" />
                        <div v-for="(opt, i) in pollOptions" :key="i" style="display:flex;gap:4px;margin-bottom:4px">
                            <el-input v-model="pollOptions[i]" size="small" :placeholder="'选项 ' + (i+1)" style="flex:1" />
                            <el-button v-if="pollOptions.length > 2" size="small" text type="danger" @click="pollOptions.splice(i, 1)">×</el-button>
                        </div>
                        <el-button v-if="pollOptions.length < 10" size="small" text @click="pollOptions.push('')">+ 添加选项</el-button>
                    </template>
                </div>

                <!-- 标签 -->
                <el-divider style="margin:12px 0" />
                <div class="plaza-tag-creator">
                    <div style="margin-bottom:4px;font-size:12px;color:#909399">🏷️ 标签（回车添加，最多5个）
                        <el-button v-if="createContent.trim().length > 10 && !suggestTagsLoading" text size="small" style="font-size:11px;padding:0 4px" @click="loadTagSuggestions">✨ 智能推荐</el-button>
                        <el-icon v-if="suggestTagsLoading" class="is-loading" :size="14"><Loading /></el-icon>
                    </div>
                    <div class="plaza-tag-input-wrap">
                        <el-input v-model="tagInput" size="small" placeholder="输入标签后按回车" @keydown.enter.prevent="addTag" style="flex:1" />
                        <el-button size="small" text @click="addTag">添加</el-button>
                    </div>
                    <!-- 推荐标签 -->
                    <div v-if="suggestedTags.length" class="plaza-suggest-tags">
                        <span style="font-size:11px;color:#909399;margin-right:4px">推荐：</span>
                        <span v-for="tag in suggestedTags" :key="tag.name" class="plaza-suggest-tag"
                            @click="addSuggestedTag(tag.name)">
                            ✨ {{ tag.name }}
                        </span>
                    </div>
                    <div v-if="createTags.length" class="plaza-create-tags-list">
                        <el-tag v-for="(t, i) in createTags" :key="i" closable size="small" @close="createTags.splice(i, 1)" style="margin:2px">#{{ t }}</el-tag>
                    </div>
                </div>

                <!-- 定时发布 -->
                <el-divider style="margin:12px 0" />
                <div class="plaza-schedule-creator">
                    <el-checkbox v-model="enableSchedule" style="margin-bottom:6px">⏰ 定时发布</el-checkbox>
                    <template v-if="enableSchedule">
                        <el-date-picker v-model="scheduledTime" type="datetime" placeholder="选择发布时间" style="width:100%" :disabled-date="d => d <= new Date()" default-time="09:00:00" />
                    </template>
                </div>
            </el-form>
            <template #footer>
                <el-button size="small" @click="showCreate = false">取消</el-button>
                <el-button size="small" text @click="submitAsDraft" :loading="creating">存草稿</el-button>
                <el-button size="small" type="primary" :loading="creating" @click="submitPost">{{ enableSchedule ? '定时发表' : '发表' }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, onUnmounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, RefreshLeft, Search, Picture, ArrowRight, ArrowDown, Delete, VideoCamera, Loading } from '@element-plus/icons-vue'
import apiClient from '@/api/client'
import PlazaEditor from '@/components/PlazaEditor.vue'

const props = defineProps({ myId: { type: Number, default: 0 } })
const emit = defineEmits(['viewMyPost', 'selectCategory'])

const staticCategories = [
    { key: 'all', icon: '🌐', label: '全部' },
    { key: 'following', icon: '❤️', label: '关注' },
    { key: 'hot', icon: '🔥', label: '热门' },
    { key: 'trending', icon: '⚡', label: '推荐' },
]
const forumCategories = ref([])
const activeCategory = ref('all')
const activeTag = ref(null)
const activeCollectionId = ref(null)
const searchKeyword = ref('')

const myPostsList = ref([])
const loadingMy = ref(false)
const showMyPosts = ref(true)

const showFavorites = ref(false)
const favoritesList = ref([])
const loadingFavorites = ref(false)
const favoriteCollections = ref([])
const uncategorizedCount = ref(0)
const favoritesCount = ref(0)

const showCreate = ref(false)
const creating = ref(false)
const createContent = ref('')
const createImages = ref([])
const createCategoryId = ref(null)
const createVideoUrl = ref('')
const createVideoFile = ref(null)
const enablePoll = ref(false)
const pollQuestion = ref('')
const pollOptions = ref(['', ''])
const tagInput = ref('')
const createTags = ref([])
const suggestedTags = ref([])
const suggestTagsLoading = ref(false)
const createTemplate = ref('discuss')
const postTemplates = [
    { key: 'discuss', icon: '💬', label: '自由讨论' },
    { key: 'poll', icon: '📊', label: '投票' },
    { key: 'qa', icon: '❓', label: '问答' },
    { key: 'checkin', icon: '✅', label: '打卡' },
    { key: 'announce', icon: '📢', label: '公告' },
]
const enableSchedule = ref(false)
const scheduledTime = ref(null)
const trendingTags = ref([])
const myStats = ref({ posts_count: 0, total_likes: 0, total_favorites: 0, total_replies: 0, total_views: 0 })
const draftCount = ref(0)
const showDrafts = ref(false)
const draftList = ref([])
const loadingDrafts = ref(false)

onMounted(() => {
    loadMyPosts()
    loadForumCategories()
    loadTrendingTags()
    loadMyStats()
    loadCollections()
    loadDrafts()

    // 监听快速发帖"更多"事件
    window.addEventListener('plaza-open-create', onOpenCreate)
})

onUnmounted(() => {
    window.removeEventListener('plaza-open-create', onOpenCreate)
})

function onOpenCreate() {
    showCreate.value = true
}

watch(showFavorites, (v) => { if (v) loadFavorites() })

function stripHtml(html) {
  var d = document.createElement('div')
  d.innerHTML = html || ''
  return d.textContent || ''
}

async function loadForumCategories() {
    try {
        const res = await apiClient.get('/moments/categories')
        forumCategories.value = res.data?.data || []
    } catch { forumCategories.value = [] }
}

async function loadTrendingTags() {
    try {
        const res = await apiClient.get('/moments/tags')
        trendingTags.value = res.data?.data || []
    } catch { trendingTags.value = [] }
}

async function loadMyStats() {
    try {
        const res = await apiClient.get('/moments/my-stats')
        myStats.value = res.data?.data || { posts_count: 0, total_likes: 0, total_favorites: 0, total_replies: 0, total_views: 0 }
    } catch { /* ignore */ }
}

async function loadCollections() {
    try {
        const res = await apiClient.get('/moments/favorites/collections')
        const d = res.data?.data || {}
        favoriteCollections.value = d.collections || []
        uncategorizedCount.value = d.uncategorized_count || 0
        favoritesCount.value = (d.collections || []).reduce((s, c) => s + (c.favorites_count || 0), 0) + (d.uncategorized_count || 0)
    } catch { favoriteCollections.value = []; uncategorizedCount.value = 0; favoritesCount.value = 0 }
}

async function loadFavorites() {
    if (!showFavorites.value) return
    loadingFavorites.value = true
    try {
        const res = await apiClient.get('/moments/favorites')
        favoritesList.value = res.data?.data || []
    } catch { favoritesList.value = [] }
    finally { loadingFavorites.value = false }
}

async function removeFav(postId) {
    try {
        await apiClient.post('/moments/' + postId + '/favorite')
        favoritesList.value = favoritesList.value.filter(x => x.post_id !== postId)
        ElMessage.success('已取消收藏')
        loadCollections()
    } catch { ElMessage.error('操作失败') }
}

function selectCategory(key, categoryId) {
    activeCategory.value = key
    activeTag.value = null
    activeCollectionId.value = null
    searchKeyword.value = ''
    emit('selectCategory', key, categoryId || undefined, '', '', null)
}

function selectTag(slug) {
    activeTag.value = activeTag.value === slug ? null : slug
    activeCategory.value = 'all'
    activeCollectionId.value = null
    searchKeyword.value = ''
    emit('selectCategory', 'all', undefined, '', activeTag.value, null)
}

function doSearch() {
    const catId = activeCategory.value.startsWith('cat_')
        ? parseInt(activeCategory.value.replace('cat_', ''))
        : undefined
    activeTag.value = null
    activeCollectionId.value = null
    emit('selectCategory', activeCategory.value, catId, searchKeyword.value, '', null)
}

function selectTemplate(key) {
    createTemplate.value = key
    if (key === 'poll') {
        enablePoll.value = true
    } else if (key === 'qa') {
        createContent.value = '❓ **问题：**\n\n\n**回答：**'
    } else if (key === 'checkin') {
        createContent.value = '✅ **打卡**\n日期：' + new Date().toLocaleDateString('zh-CN') + '\n状态：已完成\n备注：'
    } else if (key === 'announce') {
        createContent.value = '📢 **公告**\n\n'
    }
}

function addTag() {
    const t = tagInput.value.trim()
    if (!t) return
    if (createTags.value.length >= 5) { ElMessage.warning('最多5个标签'); return }
    if (createTags.value.includes(t)) { ElMessage.warning('标签已存在'); return }
    createTags.value.push(t)
    tagInput.value = ''
    // 从推荐列表移除已添加的
    suggestedTags.value = suggestedTags.value.filter(s => s.name !== t)
}
async function loadTagSuggestions() {
    const content = createContent.value.trim()
    if (content.length < 10) { ElMessage.info('再多写点内容，方便智能推荐'); return }
    suggestTagsLoading.value = true
    try {
        const r = await apiClient.get('/moments/tag-suggestions', { params: { content } })
        const tags = r.data?.data?.tags || []
        // 过滤已添加的
        suggestedTags.value = tags.filter(t => !createTags.value.includes(t.name))
    } catch { /* ignore */ }
    finally { suggestTagsLoading.value = false }
}
function addSuggestedTag(name) {
    if (createTags.value.length >= 5) { ElMessage.warning('最多5个标签'); return }
    if (createTags.value.includes(name)) return
    createTags.value.push(name)
    suggestedTags.value = suggestedTags.value.filter(s => s.name !== name)
}

async function loadMyPosts() {
    loadingMy.value = true
    try {
        const res = await apiClient.get('/moments/my')
        myPostsList.value = res.data?.data || []
    } catch { myPostsList.value = [] }
    finally { loadingMy.value = false }
}

async function submitPost() {
    if (!createContent.value.trim()) { ElMessage.warning('写点内容吧'); return }
    creating.value = true
    try {
        let video = undefined
        // 如果有视频文件，先上传
        if (createVideoFile.value) {
            const formData = new FormData()
            formData.append('file', createVideoFile.value)
            const uploadRes = await apiClient.post('/moments/upload-video', formData)
            video = uploadRes.data?.data?.url
        } else if (createVideoUrl.value) {
            video = createVideoUrl.value
        }
        const payload = {
            content: createContent.value.trim(),
            images: createImages.value.length ? createImages.value : undefined,
            video: video,
            category_id: createCategoryId.value || undefined,
            template: createTemplate.value || 'discuss',
            poll: enablePoll.value ? {
                question: pollQuestion.value.trim(),
                options: pollOptions.value.filter(o => o.trim()),
            } : undefined,
            tags: createTags.value.length ? createTags.value : undefined,
        }
        // 定时发布
        if (enableSchedule.value && scheduledTime.value) {
            payload.status = 'scheduled'
            payload.scheduled_at = scheduledTime.value.toISOString()
        }
        const res = await apiClient.post('/moments', payload)
        const msg = enableSchedule.value ? '已定时，到期自动发布' : '已发布到广场'
        ElMessage.success(msg)
        showCreate.value = false
        createContent.value = ''
        createImages.value = []
        createCategoryId.value = null
        createVideoUrl.value = ''
        createVideoFile.value = null
        enablePoll.value = false
        pollQuestion.value = ''
        pollOptions.value = ['', '']
        enableSchedule.value = false
        scheduledTime.value = null
        createTags.value = []
        tagInput.value = ''
        await loadMyPosts()
        await loadDrafts()
        const catId = activeCategory.value.startsWith('cat_')
            ? parseInt(activeCategory.value.replace('cat_', ''))
            : undefined
        emit('selectCategory', activeCategory.value, catId, '', '', null) // refresh feed
    } catch (e) { ElMessage.error(e.response?.data?.message || '发布失败') }
    finally { creating.value = false }
}

async function submitAsDraft() {
    if (!createContent.value.trim()) { ElMessage.warning('写点内容吧'); return }
    creating.value = true
    try {
        await apiClient.post('/moments', {
            content: createContent.value.trim(),
            images: createImages.value.length ? createImages.value : undefined,
            category_id: createCategoryId.value || undefined,
            tags: createTags.value.length ? createTags.value : undefined,
            status: 'draft',
        })
        ElMessage.success('已保存到草稿箱')
        showCreate.value = false
        createContent.value = ''
        createImages.value = []
        createCategoryId.value = null
        createTags.value = []
        tagInput.value = ''
        await loadDrafts()
    } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败') }
    finally { creating.value = false }
}

async function loadDrafts() {
    try {
        const res = await apiClient.get('/moments/drafts')
        const data = res.data?.data || []
        draftList.value = data
        draftCount.value = data.length || 0
    } catch { draftCount.value = 0; draftList.value = [] }
}

function editDraft(p) {
    // 打开创建对话框并填充内容
    showCreate.value = true
    createContent.value = p.content || ''
    createImages.value = p.images || []
    createCategoryId.value = p.category_id || null
    createTags.value = (p.tags || []).map(t => t.name)
    // 从草稿列表移除
    draftList.value = draftList.value.filter(x => x.id !== p.id)
    draftCount.value = draftList.value.length
}

async function deleteDraft(p) {
    try {
        await apiClient.delete('/moments/' + p.id)
        draftList.value = draftList.value.filter(x => x.id !== p.id)
        draftCount.value = draftList.value.length
        ElMessage.success('草稿已删除')
    } catch { ElMessage.error('删除失败') }
}

function uploadImage(options) {
    const formData = new FormData()
    formData.append('file', options.file)
    apiClient.post('/moments/upload', formData)
        .then(res => {
            const url = res.data?.data?.url
            if (url) {
                createImages.value.push(url)
            }
        })
        .catch(() => ElMessage.error('图片上传失败'))
}function uploadVideoFile(options) {
    createVideoFile.value = options.file
    // 创建本地预览 URL
    createVideoUrl.value = URL.createObjectURL(options.file)
}
async function deleteMyPost(p) {
    try {
        await ElMessageBox.confirm('确定删除此帖子？', '确认', { type: 'warning' })
        await apiClient.delete('/moments/' + p.id)
        ElMessage.success('已删除')
        myPostsList.value = myPostsList.value.filter(x => x.id !== p.id)
        const catId = activeCategory.value.startsWith('cat_')
            ? parseInt(activeCategory.value.replace('cat_', ''))
            : undefined
        emit('selectCategory', activeCategory.value, catId, '', '', null) // refresh feed
    } catch { /* ignore */ }
}

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
.plaza-template-grid { display: flex; gap: 6px; flex-wrap: wrap; }
.plaza-template-item {
    display: flex; align-items: center; gap: 4px;
    padding: 6px 12px; border: 1px solid #e4e7ed; border-radius: 6px;
    cursor: pointer; font-size: 12px; transition: all .15s; background: #fff;
}
.plaza-template-item:hover { border-color: #409eff; color: #409eff; }
.plaza-template-item.active { border-color: #409eff; background: #ecf5ff; color: #409eff; font-weight: 600; }
.plaza-tpl-icon { font-size: 14px; }
.plaza-tpl-label { font-size: 12px; }
.plaza-suggest-tags { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 6px; }
.plaza-suggest-tag {
    font-size: 11px; color: #409eff; cursor: pointer; padding: 2px 6px;
    border: 1px dashed #409eff; border-radius: 4px; transition: all .15s;
}
.plaza-suggest-tag:hover { background: #ecf5ff; }
.plaza-panel { display: flex; flex-direction: column; height: 100%; }

/* ── 分类标签 ── */
.plaza-categories { display: flex; gap: 4px; padding: 8px; flex-wrap: wrap; }
.plaza-cat-item { display: flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 16px; cursor: pointer; font-size: 13px; background: #f5f5f5; color: #666; transition: all .2s; user-select: none; }
.chat-dark-mode .plaza-cat-item { background: #1f1f3a; color: #999; }
.plaza-cat-item:hover { background: #e8e8e8; }
.chat-dark-mode .plaza-cat-item:hover { background: #2a2a4a; }
.plaza-cat-item.active { background: #409eff; color: #fff; font-weight: 600; }
.chat-dark-mode .plaza-cat-item.active { background: #409eff; color: #fff; }
.plaza-cat-icon { font-size: 14px; }
.plaza-cat-label { font-size: 12px; }

/* 话题分类 */
.plaza-topic-categories { display: flex; gap: 4px; padding: 0 8px 6px; flex-wrap: wrap; }
.plaza-topic-item { background: #f0f7ff; color: #409eff; }
.chat-dark-mode .plaza-topic-item { background: #1a2744; color: #66b1ff; }
.plaza-topic-item.active { background: #409eff; color: #fff; }

/* ── 搜索 ── */
.plaza-search { padding: 0 8px 4px; }

/* ── 我的发布 ── */
.plaza-my-posts { flex: 1; overflow-y: auto; padding: 0 4px; }
.plaza-section-title { display: flex; align-items: center; gap: 4px; padding: 6px 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #555; border-radius: 4px; }
.chat-dark-mode .plaza-section-title { color: #aaa; }
.plaza-section-title:hover { background: #f0f0f0; }
.chat-dark-mode .plaza-section-title:hover { background: #1f1f3a; }
.plaza-count { margin-left: auto; font-size: 11px; color: #999; background: #f0f0f0; border-radius: 10px; padding: 0 8px; }
.chat-dark-mode .plaza-count { background: #2a2a4a; color: #aaa; }

.plaza-my-list { padding: 0 4px; }
.plaza-my-item { display: flex; align-items: center; gap: 6px; padding: 6px 4px; border-radius: 6px; cursor: pointer; transition: background .15s; }
.plaza-my-item:hover { background: #f5f5f5; }
.chat-dark-mode .plaza-my-item:hover { background: #1a1a2e; }
.plaza-my-thumb { width: 36px; height: 36px; border-radius: 6px; overflow: hidden; flex-shrink: 0; background: #f0f0f0; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.chat-dark-mode .plaza-my-thumb { background: #1f1f3a; }
.plaza-my-img { width: 100%; height: 100%; object-fit: cover; }
.plaza-my-info { flex: 1; min-width: 0; }
.plaza-my-content { font-size: 12px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.chat-dark-mode .plaza-my-content { color: #ccc; }
.plaza-my-meta { font-size: 10px; color: #999; margin-top: 1px; }
.plaza-my-time { font-size: 10px; }

/* ── 发表 ── */
.plaza-upload-area { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.plaza-preview-img-wrap { position: relative; width: 80px; height: 80px; border-radius: 6px; overflow: hidden; }
.plaza-preview-img { width: 100%; height: 100%; object-fit: cover; }
.plaza-remove-img { position: absolute; top: 0; right: 0; min-width: auto !important; padding: 0 4px !important; height: 20px !important; font-size: 14px; }

.empty-hint { text-align: center; color: #999; font-size: 13px; }

/* ── 热门话题 ── */
.plaza-tags-section { padding: 4px 8px; }
.plaza-tags-cloud { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.plaza-tag-chip { font-size: 11px; color: #409eff; background: #ecf5ff; padding: 2px 8px; border-radius: 10px; cursor: pointer; transition: all .15s; }
.chat-dark-mode .plaza-tag-chip { background: #1a2a4e; color: #66b1ff; }
.plaza-tag-chip:hover { background: #409eff; color: #fff; }
.plaza-tag-chip.active { background: #409eff; color: #fff; font-weight: 600; }

/* ── 我的数据 ── */
.plaza-stats-section { padding: 4px 8px; }
.plaza-stats-list { display: flex; flex-direction: column; gap: 2px; margin-top: 4px; padding: 6px 8px; background: #f8f9fa; border-radius: 8px; }
.chat-dark-mode .plaza-stats-list { background: #1f1f3a; }
.plaza-stat-item { display: flex; align-items: center; gap: 4px; padding: 3px 0; font-size: 12px; }
.plaza-stat-icon { font-size: 13px; }
.plaza-stat-label { color: #909399; flex: 1; }
.plaza-stat-value { font-weight: 600; color: #409eff; font-size: 14px; }
.chat-dark-mode .plaza-stat-value { color: #66b1ff; }
.plaza-stat-unit { color: #bbb; font-size: 11px; }

/* ── 标签输入 ── */
.plaza-tag-input-wrap { display: flex; gap: 4px; }
.plaza-create-tags-list { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }

/* ── 定时发布 ── */
.plaza-schedule-creator { }

/* ── 收藏夹 ── */
.plaza-fav-collections { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px 0; }
.plaza-fav-col-item { display: flex; align-items: center; gap: 4px; padding: 3px 8px; border-radius: 12px; font-size: 11px; background: #fef5e7; color: #e6a23c; cursor: pointer; transition: all .15s; }
.chat-dark-mode .plaza-fav-col-item { background: #2a2010; color: #e6a23c; }
.plaza-fav-col-item:hover { background: #e6a23c; color: #fff; }
</style>
