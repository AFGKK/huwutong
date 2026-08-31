<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '../../api/feedback.js'

const { t, locale } = useI18n()

const loading = ref(false)
const stats = ref(null)
const feedbacks = ref([])
const tags = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const filters = ref({ status: '', type: '', priority: '', search: '', tag_id: '', sort: '' })
const detailVisible = ref(false)
const detailData = ref(null)
const replyDialog = ref(false)
const replyText = ref('')
const currentFeedback = ref(null)
const editDialog = ref(false)
const editForm = ref({ priority: '', status: '', tags: [] })
const tagDialog = ref(false)
const tagForm = ref({ name: '', color: '#0f172a' })
const voteStats = ref(null)

const statusOptions = ['new', 'under_review', 'acknowledged', 'in_progress', 'resolved', 'closed', 'wont_fix']
const typeOptions = ['general', 'bug', 'feature_request', 'performance', 'ui_ux', 'other']
const priorityOptions = ['low', 'normal', 'high', 'critical']

const statusLabels = computed(() => ({
    new: t('feedback_page.status.new'),
    under_review: t('feedback_page.status.under_review'),
    acknowledged: t('feedback_page.status.acknowledged'),
    in_progress: t('feedback_page.status.in_progress'),
    resolved: t('feedback_page.status.resolved'),
    closed: t('feedback_page.status.closed'),
    wont_fix: t('feedback_page.status.wont_fix'),
}))
const typeLabels = computed(() => ({
    general: t('feedback_page.types.general'),
    bug: t('feedback_page.types.bug'),
    feature_request: t('feedback_page.types.feature_request'),
    performance: t('feedback_page.types.performance'),
    ui_ux: t('feedback_page.types.ui_ux'),
    other: t('feedback_page.types.other'),
}))
const priorityLabels = computed(() => ({
    low: t('feedback_page.priority.low'),
    normal: t('feedback_page.priority.normal'),
    high: t('feedback_page.priority.high'),
    critical: t('feedback_page.priority.critical'),
}))
const statusTypes = { new:'warning',under_review:'',acknowledged:'primary',in_progress:'',resolved:'success',closed:'info',wont_fix:'danger' }
const priorityTypes = { low:'info',normal:'',high:'warning',critical:'danger' }

const sortOptions = computed(() => [
    { label: t('feedback_page.filters.sort_recent'), value: 'recent' },
    { label: t('feedback_page.filters.sort_votes'), value: 'votes' },
])

// 投票相关
const processingVotes = ref({})

async function loadStats() { try { const r = await api.stats(); stats.value = r.data.data } catch (e) {} }
async function loadVoteStats() { try { const r = await api.voteStats(); voteStats.value = r.data.data } catch (e) {} }
async function loadTags() { try { const r = await api.tags(); tags.value = r.data.data || [] } catch (e) {} }

async function loadFeedbacks(page = 1) {
    loading.value = true
    try {
        const params = { page, per_page: 15 }
        if (filters.value.status) params.status = filters.value.status
        if (filters.value.type) params.type = filters.value.type
        if (filters.value.priority) params.priority = filters.value.priority
        if (filters.value.search) params.search = filters.value.search
        if (filters.value.tag_id) params.tag_id = filters.value.tag_id
        if (filters.value.sort) params.sort = filters.value.sort
        const res = await api.list(params)
        const d = res.data.data
        feedbacks.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

function fmtDate(d) {
    return d ? new Date(d).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US') : '-'
}

async function showDetail(fb) {
    try {
        const res = await api.show(fb.id)
        detailData.value = res.data.data
        detailVisible.value = true
    } catch (e) {}
}

function openReply(fb) { currentFeedback.value = fb; replyText.value = ''; replyDialog.value = true }

async function submitReply() {
    if (!replyText.value) return
    try { await api.reply(currentFeedback.value.id, replyText.value); ElMessage.success(t('feedback_page.messages.replied')); replyDialog.value = false; loadFeedbacks(pagination.value.current_page) }
    catch (e) { ElMessage.error(t('feedback_page.messages.reply_failed')) }
}

async function resolveFeedback(fb) {
    try {
        await api.resolve(fb.id, 'resolved')
        ElMessage.success(t('feedback_page.messages.resolved'))
        loadFeedbacks(pagination.value.current_page)
        loadStats()
    } catch (e) { ElMessage.error(t('messages.failed')) }
}

function openEdit(fb) {
    editForm.value = { priority: fb.priority, status: fb.status, tags: fb.tags?.map(tg => tg.id) || [] }
    currentFeedback.value = fb
    editDialog.value = true
}

async function submitEdit() {
    try {
        await api.update(currentFeedback.value.id, editForm.value)
        ElMessage.success(t('feedback_page.messages.updated'))
        editDialog.value = false
        loadFeedbacks(pagination.value.current_page)
        loadStats()
    } catch (e) { ElMessage.error(t('feedback_page.messages.update_failed')) }
}

function openTagDialog() { tagForm.value = { name: '', color: '#0f172a' }; tagDialog.value = true }

async function submitTag() {
    try { await api.createTag(tagForm.value); loadTags(); tagDialog.value = false; ElMessage.success(t('feedback_page.messages.tag_created')) }
    catch (e) { ElMessage.error(t('feedback_page.messages.create_failed')) }
}

// ─── 投票操作 ───
async function handleVote(fb, vote) {
    processingVotes.value[fb.id] = true
    try {
        const res = await api.vote(fb.id, vote)
        const data = res.data.data
        fb.vote_count = data.vote_count
        fb.user_vote = data.user_vote
        const voteMsg = data.action === 'added'
            ? t('feedback_page.messages.vote_added')
            : data.action === 'removed'
                ? t('feedback_page.messages.vote_removed')
                : t('feedback_page.messages.vote_changed')
        ElMessage.success(voteMsg)
        loadVoteStats()
    } catch (e) {
        ElMessage.error(t('feedback_page.messages.vote_failed'))
    } finally {
        processingVotes.value[fb.id] = false
    }
}

function voteBtnType(fb) {
    if (fb.user_vote === 1) return 'primary'
    if (fb.user_vote === -1) return 'danger'
    return ''
}

onMounted(() => { loadStats(); loadVoteStats(); loadTags(); loadFeedbacks() })
</script>

<template>
    <div>
        <el-breadcrumb separator="/" class="mb-4">
            <el-breadcrumb-item :to="{ path: '/admin' }">{{ t('nav.home') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('feedback_page.breadcrumb.ops') }}</el-breadcrumb-item>
            <el-breadcrumb-item>{{ t('feedback_page.title') }}</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.total') }}</div><div class="stat-value">{{ stats.total }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.new_today') }}</div><div class="stat-value text-warning">{{ stats.new_today }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.unresolved') }}</div><div class="stat-value text-danger">{{ stats.unresolved }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.avg_rating') }}</div><div class="stat-value">{{ stats.avg_rating ? stats.avg_rating.toFixed(1) : '-' }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.type_distribution') }}</div><div class="stat-value text-sm">{{ Object.entries(stats.by_type || {}).map(([k,v]) => typeLabels[k]+':'+v).join(' | ') }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.total_votes') }}</div><div class="stat-value">{{ voteStats?.total_votes || 0 }} ↑{{ voteStats?.total_upvotes || 0 }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">{{ t('feedback_page.stats.feature_requests') }}</div><div class="stat-value text-sm">{{ t('feedback_page.stats.feature_requests_fmt', { count: stats.by_type?.feature_request || 0, votes: voteStats?.most_voted?.[0]?.votes_count || 0 }) }}</div></el-card></el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-3">
            <el-form :model="filters" inline size="small">
                <el-form-item :label="t('feedback_page.filters.status')"><el-select v-model="filters.status" clearable class="w-32"><el-option v-for="s in statusOptions" :key="s" :label="statusLabels[s]" :value="s" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.type')"><el-select v-model="filters.type" clearable class="w-28"><el-option v-for="tp in typeOptions" :key="tp" :label="typeLabels[tp]" :value="tp" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.priority')"><el-select v-model="filters.priority" clearable class="w-24"><el-option v-for="p in priorityOptions" :key="p" :label="priorityLabels[p]" :value="p" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.tag')"><el-select v-model="filters.tag_id" clearable class="w-32"><el-option v-for="tg in tags" :key="tg.id" :label="tg.name" :value="tg.id" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.sort')"><el-select v-model="filters.sort" clearable class="w-28"><el-option v-for="opt in sortOptions" :key="opt.value" :label="opt.label" :value="opt.value" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.search')"><el-input v-model="filters.search" :placeholder="t('feedback_page.filters.search_ph')" clearable class="w-40" /></el-form-item>
                <el-form-item><el-button @click="loadFeedbacks()">{{ t('actions.filter') }}</el-button><el-button text @click="filters = {status:'',type:'',priority:'',search:'',tag_id:'',sort:''};loadFeedbacks()">{{ t('actions.reset') }}</el-button></el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never" class="mb-3">
            <div class="flex justify-between items-center mb-2"><span class="font-bold">{{ t('feedback_page.tags.title') }}</span><el-button size="small" @click="openTagDialog">+ {{ t('feedback_page.tags.new_tag') }}</el-button></div>
            <el-tag v-for="tg in tags" :key="tg.id" :color="tg.color" class="mr-1 mb-1" style="color:#fff">{{ tg.name }}</el-tag>
            <span v-if="!tags.length" class="text-gray-400 text-sm">{{ t('feedback_page.tags.empty') }}</span>
        </el-card>

        <!-- 列表 -->
        <el-card shadow="never">
            <el-table :data="feedbacks" v-loading="loading" stripe>
                <el-table-column :label="t('feedback_page.cols.votes')" width="90">
                    <template #default="{ row }">
                        <div class="vote-group">
                            <el-button size="small" :type="row.user_vote === 1 ? 'primary' : 'default'" :loading="processingVotes[row.id]" circle @click="handleVote(row, 1)" :title="t('feedback_page.vote.up')" class="vote-btn">
                                <span>↑</span>
                            </el-button>
                            <span class="vote-count" :class="{ 'text-primary': row.vote_count > 0, 'text-danger': row.vote_count < 0 }">
                                {{ row.vote_count ?? 0 }}
                            </span>
                            <el-button size="small" :type="row.user_vote === -1 ? 'danger' : 'default'" :loading="processingVotes[row.id]" circle @click="handleVote(row, -1)" :title="t('feedback_page.vote.down')" class="vote-btn">
                                <span>↓</span>
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('feedback_page.cols.type')" width="80"><template #default="{ row }"><el-tag size="small">{{ typeLabels[row.type] }}</el-tag></template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.priority')" width="70"><template #default="{ row }"><el-tag :type="priorityTypes[row.priority]" size="small">{{ priorityLabels[row.priority] }}</el-tag></template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.subject')" min-width="160" show-overflow-tooltip><template #default="{ row }">{{ row.subject || row.message?.substring(0, 60) }}</template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.rating')" width="70"><template #default="{ row }"><span v-if="row.rating">{'★'.repeat(row.rating)}{'☆'.repeat(5-row.rating)}</span><span v-else>-</span></template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.tags')" width="140"><template #default="{ row }"><el-tag v-for="tg in row.tags" :key="tg.id" :color="tg.color" size="small" class="mr-1" style="color:#fff">{{ tg.name }}</el-tag></template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.status')" width="80"><template #default="{ row }"><el-tag :type="statusTypes[row.status]" size="small">{{ statusLabels[row.status] }}</el-tag></template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.customer')" width="110"><template #default="{ row }">{{ row.customer?.name || row.user?.name || '-' }}</template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.assignee')" width="90"><template #default="{ row }">{{ row.assignee?.name || '-' }}</template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.time')" width="140"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column :label="t('feedback_page.cols.actions')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">{{ t('feedback_page.actions.detail') }}</el-button>
                        <el-button size="small" text @click="openReply(row)">{{ t('feedback_page.actions.reply') }}</el-button>
                        <el-button size="small" text @click="openEdit(row)">{{ t('actions.edit') }}</el-button>
                        <el-button v-if="row.status !== 'resolved' && row.status !== 'closed'" size="small" text type="success" @click="resolveFeedback(row)">{{ t('feedback_page.actions.resolve') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadFeedbacks" /></div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" :title="t('feedback_page.detail.title')" width="650px">
            <div v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item :label="t('feedback_page.cols.type')">{{ typeLabels[detailData.type] }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.cols.priority')">{{ priorityLabels[detailData.priority] }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.cols.status')">{{ statusLabels[detailData.status] }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.cols.rating')"><span v-if="detailData.rating">{'★'.repeat(detailData.rating)}{'☆'.repeat(5-detailData.rating)}</span><span v-else>-</span></el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.cols.customer')">{{ detailData.customer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.detail.page')">{{ detailData.page_title || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.detail.browser')">{{ detailData.browser || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.detail.os')">{{ detailData.os || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.detail.resolution')">{{ detailData.screen_resolution || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('feedback_page.detail.ip')">{{ detailData.ip_address || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-3"><el-divider>{{ t('feedback_page.detail.content') }}</el-divider><p class="text-sm whitespace-pre-wrap">{{ detailData.message }}</p></div>
                <div v-if="detailData.admin_reply"><el-divider>{{ t('feedback_page.detail.admin_reply') }}</el-divider><div class="text-sm bg-gray-50 p-3 rounded">{{ detailData.admin_reply }}<div class="text-xs text-gray-400 mt-1">{{ fmtDate(detailData.replied_at) }}</div></div></div>
                <div class="mt-2"><el-tag v-for="tg in detailData.tags" :key="tg.id" :color="tg.color" size="small" class="mr-1" style="color:#fff">{{ tg.name }}</el-tag></div>
            </div>
        </el-dialog>

        <!-- 回复对话框 -->
        <el-dialog v-model="replyDialog" :title="t('feedback_page.reply_dialog.title')" width="500px">
            <el-input v-model="replyText" type="textarea" :rows="5" :placeholder="t('feedback_page.reply_dialog.placeholder')" />
            <template #footer><el-button @click="replyDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitReply">{{ t('feedback_page.actions.send_reply') }}</el-button></template>
        </el-dialog>

        <!-- 编辑对话框 -->
        <el-dialog v-model="editDialog" :title="t('feedback_page.edit_dialog.title')" width="400px">
            <el-form :model="editForm" label-width="80px">
                <el-form-item :label="t('feedback_page.filters.priority')"><el-select v-model="editForm.priority" class="w-full"><el-option v-for="p in priorityOptions" :key="p" :label="priorityLabels[p]" :value="p" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.status')"><el-select v-model="editForm.status" class="w-full"><el-option v-for="s in statusOptions" :key="s" :label="statusLabels[s]" :value="s" /></el-select></el-form-item>
                <el-form-item :label="t('feedback_page.filters.tag')"><el-select v-model="editForm.tags" multiple class="w-full"><el-option v-for="tg in tags" :key="tg.id" :label="tg.name" :value="tg.id" /></el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="editDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitEdit">{{ t('actions.save') }}</el-button></template>
        </el-dialog>

        <!-- 新建标签对话框 -->
        <el-dialog v-model="tagDialog" :title="t('feedback_page.tag_dialog.title')" width="350px">
            <el-form :model="tagForm" label-width="60px">
                <el-form-item :label="t('feedback_page.tag_dialog.name')"><el-input v-model="tagForm.name" /></el-form-item>
                <el-form-item :label="t('feedback_page.tag_dialog.color')"><el-color-picker v-model="tagForm.color" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="tagDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitTag">{{ t('actions.create') }}</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-primary { color: #0f172a; }
.text-sm { font-size: 13px; }
.w-32 { width: 130px; }
.w-28 { width: 115px; }
.w-24 { width: 100px; }
.w-40 { width: 165px; }
.whitespace-pre-wrap { white-space: pre-wrap; }

.vote-group { display: flex; align-items: center; gap: 2px; }
.vote-btn { padding: 4px; min-width: 24px; height: 24px; font-size: 12px; }
.vote-count { font-size: 13px; font-weight: 600; min-width: 20px; text-align: center; }
</style>
