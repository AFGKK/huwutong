<template>
    <div class="channel-panel">
        <div class="sidebar-header">
            <h3>📡 圈子</h3>
            <div class="sidebar-header-actions">
                <el-button size="small" type="primary" @click="showCreateDialog = true">
                    <el-icon><Plus /></el-icon> 创建
                </el-button>
                <el-button size="small" text @click="$emit('refresh')" title="刷新">
                    <el-icon><RefreshLeft /></el-icon>
                </el-button>
            </div>
        </div>

        <!-- 我的频道 -->
        <div class="channel-section">
            <div class="channel-section-title" @click="showMyChannels = !showMyChannels">
                <el-icon><ArrowRight v-if="!showMyChannels" /><ArrowDown v-else /></el-icon>
                <span>我的圈子</span>
                <span class="channel-count">{{ channels.length }}</span>
            </div>
            <div v-show="showMyChannels" class="channel-list" v-loading="loading">
                <div v-for="ch in channels" :key="ch.id" class="channel-item" @click="$emit('select', ch)">
                    <div class="channel-icon">
                        <img v-if="ch.avatar" :src="ch.avatar" class="channel-avatar" />
                        <span v-else>{{ ch.icon || '#' }}</span>
                    </div>
                    <div class="channel-info">
                        <div class="channel-name">{{ ch.name }}</div>
                        <div class="channel-meta">
                            <el-tag v-if="ch.type === 'private'" size="small" type="warning">私密</el-tag>
                            <span class="channel-member-count">{{ ch.members_count || ch.members?.length || 0 }} 人</span>
                        </div>
                    </div>
                </div>
                <div v-if="!channels.length && !loading" class="empty-hint">还没有加入圈子</div>
            </div>
        </div>

        <!-- 发现公开频道 -->
        <div class="channel-section">
            <div class="channel-section-title" @click="showBrowse = !showBrowse">
                <el-icon><ArrowRight v-if="!showBrowse" /><ArrowDown v-else /></el-icon>
                <span>发现圈子</span>
            </div>
            <div v-show="showBrowse" class="channel-list">
                <div class="browse-search">
                    <el-input v-model="browseKeyword" size="small" placeholder="搜索圈子..." clearable
                        @keydown.enter="doBrowseSearch" @clear="doBrowseSearch">
                        <template #prefix><el-icon><Search /></el-icon></template>
                    </el-input>
                    <el-select v-model="browseCategory" size="small" placeholder="分类" clearable style="width:100%;margin-top:4px"
                        @change="doBrowseSearch">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
                </div>
                <div v-for="ch in browseChannels" :key="ch.id" class="channel-item" @click="$emit('select', ch)">
                    <div class="channel-icon">{{ ch.icon || '#' }}</div>
                    <div class="channel-info">
                        <div class="channel-name">{{ ch.name }}</div>
                        <div class="channel-meta">
                            <span class="channel-member-count">{{ ch.members_count || 0 }} 人</span>
                        </div>
                    </div>
                    <el-button size="small" type="primary" text @click.stop="joinChannel(ch)">加入</el-button>
                </div>
                <div v-if="!browseChannels.length" class="empty-hint">暂无公开圈子</div>
            </div>
        </div>

        <!-- 分类浏览 -->
        <div class="channel-section">
            <div class="channel-section-title" @click="showCategories = !showCategories">
                <el-icon><ArrowRight v-if="!showCategories" /><ArrowDown v-else /></el-icon>
                <span>分类</span>
                <el-button size="small" text @click.stop="openCategoryDialog(null)" title="管理分类">
                    <el-icon><Setting /></el-icon>
                </el-button>
            </div>
            <div v-show="showCategories" class="channel-list">
                <div v-for="cat in categories" :key="cat.id" class="channel-category-item" @click="browseByCategory(cat.id)">
                    <el-icon><FolderOpened /></el-icon>
                    <span>{{ cat.name }}</span>
                    <span class="channel-count">{{ cat.channels_count || 0 }}</span>
                </div>
                <div v-if="!categories.length" class="empty-hint">暂无分类</div>
            </div>
        </div>

        <!-- 分类管理对话框 -->
        <el-dialog v-model="showCategoryDialog" title="分类管理" width="420px">
            <div class="category-mgmt-header">
                <el-input v-model="newCategoryName" size="small" placeholder="新分类名称" style="flex:1" maxlength="50"
                    @keydown.enter="addCategory" />
                <el-button size="small" type="primary" :loading="addingCategory" @click="addCategory">添加</el-button>
            </div>
            <div class="category-list">
                <div v-for="cat in categories" :key="cat.id" class="category-mgmt-item">
                    <template v-if="editingCatId === cat.id">
                        <el-input v-model="editingCatName" size="small" style="flex:1" maxlength="50"
                            @keydown.enter="saveCategory(cat)" />
                        <el-button size="small" text @click="saveCategory(cat)"><el-icon><Select /></el-icon></el-button>
                        <el-button size="small" text @click="editingCatId = null"><el-icon><Close /></el-icon></el-button>
                    </template>
                    <template v-else>
                        <el-icon><FolderOpened /></el-icon>
                        <span class="category-mgmt-name">{{ cat.name }}</span>
                        <span class="category-mgmt-count">{{ cat.channels_count || 0 }} 圈子</span>
                        <el-button size="small" text @click="startEditCategory(cat)" title="编辑">
                            <el-icon><EditPen /></el-icon>
                        </el-button>
                        <el-button size="small" text type="danger" @click="deleteCategory(cat)" title="删除">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </template>
                </div>
                <div v-if="!categories.length" class="empty-hint">暂无分类，请添加</div>
            </div>
        </el-dialog>

        <!-- 创建频道对话框 -->
        <el-dialog v-model="showCreateDialog" title="创建圈子" width="420px" :close-on-click-modal="false">
            <el-form :model="createForm" label-width="80px" size="small">
                <el-form-item label="名称" required>
                    <el-input v-model="createForm.name" placeholder="圈子名称" maxlength="100" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="createForm.description" type="textarea" :rows="2" placeholder="圈子描述（可选）" maxlength="500" />
                </el-form-item>
                <el-form-item label="类型">
                    <el-radio-group v-model="createForm.type">
                        <el-radio value="public">公开</el-radio>
                        <el-radio value="private">私密</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="图标">
                    <div class="channel-icon-upload">
                        <div class="icon-preview">
                            <img v-if="createForm.avatar" :src="createForm.avatar" class="channel-avatar-preview" />
                            <span v-else class="icon-text-preview">{{ createForm.icon || '#' }}</span>
                        </div>
                        <div class="icon-inputs">
                            <el-input v-model="createForm.icon" placeholder="表情符号如 # 📢 💬" maxlength="10" style="margin-bottom:4px" />
                            <el-upload :show-file-list="false" :http-request="uploadChannelAvatar" accept="image/*">
                                <el-button size="small" type="success">上传头像</el-button>
                            </el-upload>
                        </div>
                    </div>
                </el-form-item>
                <el-form-item label="分类" v-if="categories.length">
                    <el-select v-model="createForm.category_id" placeholder="选择分类" clearable style="width:100%">
                        <el-option v-for="cat in categories" :key="cat.id" :label="cat.name" :value="cat.id" />
                    </el-select>
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
import { ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, RefreshLeft, Search, ArrowRight, ArrowDown, FolderOpened, Setting, Select, Close, EditPen, Delete } from '@element-plus/icons-vue'
import apiClient from '@/api/client'

const props = defineProps({
    channels: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    browseChannels: { type: Array, default: () => [] },
    channelCategories: { type: Array, default: () => [] },
})

const emit = defineEmits(['select', 'refresh', 'browse', 'loadCategories'])

const showMyChannels = ref(true)
const showBrowse = ref(false)
const showCategories = ref(false)
const browseKeyword = ref('')
const browseCategory = ref('')
const categories = ref([])

const showCreateDialog = ref(false)
const creating = ref(false)
const createForm = ref({ name: '', description: '', type: 'public', icon: '#', category_id: null })
const pendingAvatarFile = ref(null)

// ── 分类管理 ──
const showCategoryDialog = ref(false)
const newCategoryName = ref('')
const addingCategory = ref(false)
const editingCatId = ref(null)
const editingCatName = ref('')

watch(() => props.channelCategories, (val) => { categories.value = val || [] }, { immediate: true })
watch(showCreateDialog, (v) => { if (!v) { pendingAvatarFile.value = null } })
watch(showBrowse, (v) => { if (v) { emit('browse'); emit('loadCategories') } })
watch(showCategories, (v) => { if (v) emit('loadCategories') })

function doBrowseSearch() {
    emit('browse', { q: browseKeyword.value, category_id: browseCategory.value || undefined })
}

function browseByCategory(catId) {
    browseCategory.value = catId
    showBrowse.value = true
    doBrowseSearch()
}

async function joinChannel(ch) {
    try {
        await apiClient.post('/channels/' + ch.id + '/join')
        ElMessage.success('已加入圈子')
        emit('refresh')
        emit('select', ch)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '加入失败')
    }
}

async function doCreate() {
    if (!createForm.value.name.trim()) { ElMessage.warning('请输入圈子名称'); return }
    creating.value = true
    try {
        const res = await apiClient.post('/channels', {
            name: createForm.value.name,
            description: createForm.value.description,
            type: createForm.value.type,
            icon: createForm.value.icon,
            category_id: createForm.value.category_id || undefined,
        })
        const channel = res.data?.data
        ElMessage.success('圈子已创建')

        // 有暂存头像文件则创建后自动上传
        if (pendingAvatarFile.value && channel?.id) {
            try {
                const fd = new FormData()
                fd.append('avatar', pendingAvatarFile.value)
                fd.append('channel_id', channel.id)
                await apiClient.post('/channels/upload-avatar', fd)
                ElMessage.success('头像已上传')
            } catch (uploadErr) {
                ElMessage.warning('圈子已创建，但头像上传失败，可在编辑圈子中重新上传')
            }
            pendingAvatarFile.value = null
        }

        showCreateDialog.value = false
        createForm.value = { name: '', description: '', type: 'public', icon: '#', avatar: '', category_id: null }
        emit('refresh')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '创建失败')
    } finally {
        creating.value = false
    }
}

// ── 频道头像上传 ──
async function uploadChannelAvatar(options) {
    // 暂存文件，创建频道后再上传
    pendingAvatarFile.value = options.file
    // 本地预览
    const reader = new FileReader()
    reader.onload = (e) => { createForm.value.avatar = e.target.result }
    reader.readAsDataURL(options.file)
    ElMessage.success('已选择头像，创建圈子后自动上传')
}

// ── 分类管理方法 ──
function openCategoryDialog() {
    showCategoryDialog.value = true
    emit('loadCategories')
}

async function addCategory() {
    const name = newCategoryName.value.trim()
    if (!name) { ElMessage.warning('请输入分类名称'); return }
    addingCategory.value = true
    try {
        await apiClient.post('/channels/categories', { name })
        ElMessage.success('分类已添加')
        newCategoryName.value = ''
        emit('loadCategories')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '添加失败')
    } finally {
        addingCategory.value = false
    }
}

function startEditCategory(cat) {
    editingCatId.value = cat.id
    editingCatName.value = cat.name
}

async function saveCategory(cat) {
    const name = editingCatName.value.trim()
    if (!name) { ElMessage.warning('请输入分类名称'); return }
    try {
        await apiClient.put('/channels/categories/' + cat.id, { name })
        ElMessage.success('分类已更新')
        editingCatId.value = null
        emit('loadCategories')
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '更新失败')
    }
}

async function deleteCategory(cat) {
    try {
        await ElMessageBox.confirm(`确定删除分类「${cat.name}」？`, '确认', { type: 'warning' })
        await apiClient.delete('/channels/categories/' + cat.id)
        ElMessage.success('分类已删除')
        emit('loadCategories')
    } catch (e) {
        if (e?.response?.data?.message) ElMessage.error(e.response.data.message)
        // 用户取消对话框不做任何操作
    }
}
</script>

<style scoped>
.channel-panel { display: flex; flex-direction: column; height: 100%; }
.channel-section { border-bottom: 1px solid #eee; }
.chat-dark-mode .channel-section { border-bottom-color: #2a2a3e; }
.channel-section-title {
    display: flex; align-items: center; gap: 4px; padding: 8px 12px; cursor: pointer; font-size: 13px; color: #666; user-select: none;
}
.chat-dark-mode .channel-section-title { color: #999; }
.channel-section-title:hover { background: #f5f5f5; }
.chat-dark-mode .channel-section-title:hover { background: #2a2a3e; }
.channel-count { margin-left: auto; font-size: 11px; color: #999; background: #eee; padding: 1px 6px; border-radius: 8px; }
.chat-dark-mode .channel-count { background: #3a3a4e; color: #aaa; }
.channel-list { padding: 0 8px 8px; }
.channel-item {
    display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 6px; cursor: pointer; transition: background 0.2s;
}
.channel-item:hover { background: #f0f0f0; }
.chat-dark-mode .channel-item:hover { background: #2a2a3e; }
.channel-icon {
    width: 36px; height: 36px; border-radius: 8px; background: #409eff; color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; overflow: hidden;
}
.channel-avatar { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; }
.channel-avatar-preview { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; }
.icon-text-preview { width: 48px; height: 48px; border-radius: 8px; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 24px; }
.channel-icon-upload { display: flex; gap: 12px; align-items: flex-start; }
.icon-preview { flex-shrink: 0; }
.icon-inputs { flex: 1; }
.channel-info { flex: 1; min-width: 0; }
.channel-name { font-size: 14px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.channel-meta { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #999; margin-top: 2px; }
.channel-member-count::before { content: '👥 '; }
.empty-hint { padding: 20px; text-align: center; color: #999; font-size: 13px; }
.browse-search { padding: 8px 0; }
.channel-category-item {
    display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 4px; cursor: pointer; font-size: 13px;
}
.channel-category-item:hover { background: #f0f0f0; }
.chat-dark-mode .channel-category-item:hover { background: #2a2a3e; }

/* ── 分类管理 ── */
.category-mgmt-header { display: flex; gap: 8px; margin-bottom: 12px; }
.category-list { max-height: 320px; overflow-y: auto; }
.category-mgmt-item {
    display: flex; align-items: center; gap: 6px; padding: 8px 4px; border-bottom: 1px solid #f0f0f0;
}
.chat-dark-mode .category-mgmt-item { border-bottom-color: #2a2a3e; }
.category-mgmt-name { flex: 1; font-size: 13px; }
.category-mgmt-count { font-size: 11px; color: #999; margin-right: auto; }
</style>
