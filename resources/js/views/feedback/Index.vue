<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '../../api/feedback.js'

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
const tagForm = ref({ name: '', color: '#409eff' })
const voteStats = ref(null)

const statusOptions = ['new', 'under_review', 'acknowledged', 'in_progress', 'resolved', 'closed', 'wont_fix']
const typeOptions = ['general', 'bug', 'feature_request', 'performance', 'ui_ux', 'other']
const priorityOptions = ['low', 'normal', 'high', 'critical']

const statusLabels = { new:'新反馈',under_review:'审核中',acknowledged:'已确认',in_progress:'处理中',resolved:'已解决',closed:'已关闭',wont_fix:'不修复' }
const typeLabels = { general:'一般',bug:'Bug',feature_request:'功能建议',performance:'性能',ui_ux:'UI/UX',other:'其他' }
const priorityLabels = { low:'低',normal:'普通',high:'高',critical:'紧急' }
const statusTypes = { new:'warning',under_review:'',acknowledged:'primary',in_progress:'',resolved:'success',closed:'info',wont_fix:'danger' }
const priorityTypes = { low:'info',normal:'',high:'warning',critical:'danger' }

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

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

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
    try { await api.reply(currentFeedback.value.id, replyText.value); ElMessage.success('已回复'); replyDialog.value = false; loadFeedbacks(pagination.value.current_page) }
    catch (e) { ElMessage.error('回复失败') }
}

async function resolveFeedback(fb) {
    try {
        await api.resolve(fb.id, 'resolved')
        ElMessage.success('已标记解决')
        loadFeedbacks(pagination.value.current_page)
        loadStats()
    } catch (e) { ElMessage.error('操作失败') }
}

function openEdit(fb) {
    editForm.value = { priority: fb.priority, status: fb.status, tags: fb.tags?.map(t => t.id) || [] }
    currentFeedback.value = fb
    editDialog.value = true
}

async function submitEdit() {
    try {
        await api.update(currentFeedback.value.id, editForm.value)
        ElMessage.success('已更新')
        editDialog.value = false
        loadFeedbacks(pagination.value.current_page)
        loadStats()
    } catch (e) { ElMessage.error('更新失败') }
}

function openTagDialog() { tagForm.value = { name: '', color: '#409eff' }; tagDialog.value = true }

async function submitTag() {
    try { await api.createTag(tagForm.value); loadTags(); tagDialog.value = false; ElMessage.success('标签已创建') }
    catch (e) { ElMessage.error('创建失败') }
}

// ─── 投票操作 ───
async function handleVote(fb, vote) {
    processingVotes.value[fb.id] = true
    try {
        const res = await api.vote(fb.id, vote)
        const data = res.data.data
        // 更新本地数据
        fb.vote_count = data.vote_count
        fb.user_vote = data.user_vote
        ElMessage.success(data.action === 'added' ? '投票成功' : data.action === 'removed' ? '已取消投票' : '已更改投票')
        loadVoteStats()
    } catch (e) {
        ElMessage.error('投票失败')
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
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>运营</el-breadcrumb-item>
            <el-breadcrumb-item>客户反馈</el-breadcrumb-item>
        </el-breadcrumb>

        <!-- 统计 -->
        <el-row :gutter="12" class="mb-5" v-if="stats">
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">总反馈</div><div class="stat-value">{{ stats.total }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">今日新</div><div class="stat-value text-warning">{{ stats.new_today }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">未解决</div><div class="stat-value text-danger">{{ stats.unresolved }}</div></el-card></el-col>
            <el-col :span="3"><el-card shadow="never"><div class="stat-label">平均评分</div><div class="stat-value">{{ stats.avg_rating ? stats.avg_rating.toFixed(1) : '-' }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">类型分布</div><div class="stat-value text-sm">{{ Object.entries(stats.by_type || {}).map(([k,v]) => typeLabels[k]+':'+v).join(' | ') }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">总投票</div><div class="stat-value">{{ voteStats?.total_votes || 0 }} ↑{{ voteStats?.total_upvotes || 0 }}</div></el-card></el-col>
            <el-col :span="4"><el-card shadow="never"><div class="stat-label">功能建议</div><div class="stat-value text-sm">{{ (stats.by_type?.feature_request || 0) + '条 / 最高' + (voteStats?.most_voted?.[0]?.votes_count || 0) + '票' }}</div></el-card></el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-3">
            <el-form :model="filters" inline size="small">
                <el-form-item label="状态"><el-select v-model="filters.status" clearable class="w-32"><el-option v-for="s in statusOptions" :key="s" :label="statusLabels[s]" :value="s" /></el-select></el-form-item>
                <el-form-item label="类型"><el-select v-model="filters.type" clearable class="w-28"><el-option v-for="t in typeOptions" :key="t" :label="typeLabels[t]" :value="t" /></el-select></el-form-item>
                <el-form-item label="优先级"><el-select v-model="filters.priority" clearable class="w-24"><el-option v-for="p in priorityOptions" :key="p" :label="priorityLabels[p]" :value="p" /></el-select></el-form-item>
                <el-form-item label="标签"><el-select v-model="filters.tag_id" clearable class="w-32"><el-option v-for="t in tags" :key="t.id" :label="t.name" :value="t.id" /></el-select></el-form-item>
                <el-form-item label="排序"><el-select v-model="filters.sort" clearable class="w-28"><el-option label="最新" value="recent" /><el-option label="最多票" value="votes" /></el-select></el-form-item>
                <el-form-item label="搜索"><el-input v-model="filters.search" placeholder="内容/主题" clearable class="w-40" /></el-form-item>
                <el-form-item><el-button @click="loadFeedbacks()">筛选</el-button><el-button text @click="filters = {status:'',type:'',priority:'',search:'',tag_id:'',sort:''};loadFeedbacks()">重置</el-button></el-form-item>
            </el-form>
        </el-card>

        <el-card shadow="never" class="mb-3">
            <div class="flex justify-between items-center mb-2"><span class="font-bold">标签管理</span><el-button size="small" @click="openTagDialog">+ 新建标签</el-button></div>
            <el-tag v-for="t in tags" :key="t.id" :color="t.color" class="mr-1 mb-1" style="color:#fff">{{ t.name }}</el-tag>
            <span v-if="!tags.length" class="text-gray-400 text-sm">暂无标签</span>
        </el-card>

        <!-- 列表 -->
        <el-card shadow="never">
            <el-table :data="feedbacks" v-loading="loading" stripe>
                <el-table-column label="投票" width="90">
                    <template #default="{ row }">
                        <div class="vote-group">
                            <el-button size="small" :type="row.user_vote === 1 ? 'primary' : 'default'" :loading="processingVotes[row.id]" circle @click="handleVote(row, 1)" title="点赞" class="vote-btn">
                                <span>↑</span>
                            </el-button>
                            <span class="vote-count" :class="{ 'text-primary': row.vote_count > 0, 'text-danger': row.vote_count < 0 }">
                                {{ row.vote_count ?? 0 }}
                            </span>
                            <el-button size="small" :type="row.user_vote === -1 ? 'danger' : 'default'" :loading="processingVotes[row.id]" circle @click="handleVote(row, -1)" title="点踩" class="vote-btn">
                                <span>↓</span>
                            </el-button>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="80"><template #default="{ row }"><el-tag size="small">{{ typeLabels[row.type] }}</el-tag></template></el-table-column>
                <el-table-column label="优先级" width="70"><template #default="{ row }"><el-tag :type="priorityTypes[row.priority]" size="small">{{ priorityLabels[row.priority] }}</el-tag></template></el-table-column>
                <el-table-column label="主题" min-width="160" show-overflow-tooltip><template #default="{ row }">{{ row.subject || row.message?.substring(0, 60) }}</template></el-table-column>
                <el-table-column label="评分" width="70"><template #default="{ row }"><span v-if="row.rating">{'★'.repeat(row.rating)}{'☆'.repeat(5-row.rating)}</span><span v-else>-</span></template></el-table-column>
                <el-table-column label="标签" width="140"><template #default="{ row }"><el-tag v-for="t in row.tags" :key="t.id" :color="t.color" size="small" class="mr-1" style="color:#fff">{{ t.name }}</el-tag></template></el-table-column>
                <el-table-column label="状态" width="80"><template #default="{ row }"><el-tag :type="statusTypes[row.status]" size="small">{{ statusLabels[row.status] }}</el-tag></template></el-table-column>
                <el-table-column label="客户" width="110"><template #default="{ row }">{{ row.customer?.name || row.user?.name || '-' }}</template></el-table-column>
                <el-table-column label="处理人" width="90"><template #default="{ row }">{{ row.assignee?.name || '-' }}</template></el-table-column>
                <el-table-column label="时间" width="140"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button size="small" text @click="openReply(row)">回复</el-button>
                        <el-button size="small" text @click="openEdit(row)">编辑</el-button>
                        <el-button v-if="row.status !== 'resolved' && row.status !== 'closed'" size="small" text type="success" @click="resolveFeedback(row)">解决</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadFeedbacks" /></div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="反馈详情" width="650px">
            <div v-if="detailData">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="类型">{{ typeLabels[detailData.type] }}</el-descriptions-item>
                    <el-descriptions-item label="优先级">{{ priorityLabels[detailData.priority] }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ statusLabels[detailData.status] }}</el-descriptions-item>
                    <el-descriptions-item label="评分"><span v-if="detailData.rating">{'★'.repeat(detailData.rating)}{'☆'.repeat(5-detailData.rating)}</span><span v-else>-</span></el-descriptions-item>
                    <el-descriptions-item label="客户">{{ detailData.customer?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="页面">{{ detailData.page_title || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="浏览器">{{ detailData.browser || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="操作系统">{{ detailData.os || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="分辨率">{{ detailData.screen_resolution || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="IP">{{ detailData.ip_address || '-' }}</el-descriptions-item>
                </el-descriptions>
                <div class="mt-3"><el-divider>反馈内容</el-divider><p class="text-sm whitespace-pre-wrap">{{ detailData.message }}</p></div>
                <div v-if="detailData.admin_reply"><el-divider>管理员回复</el-divider><div class="text-sm bg-gray-50 p-3 rounded">{{ detailData.admin_reply }}<div class="text-xs text-gray-400 mt-1">{{ fmtDate(detailData.replied_at) }}</div></div></div>
                <div class="mt-2"><el-tag v-for="t in detailData.tags" :key="t.id" :color="t.color" size="small" class="mr-1" style="color:#fff">{{ t.name }}</el-tag></div>
            </div>
        </el-dialog>

        <!-- 回复对话框 -->
        <el-dialog v-model="replyDialog" title="回复反馈" width="500px">
            <el-input v-model="replyText" type="textarea" :rows="5" placeholder="请输入回复内容..." />
            <template #footer><el-button @click="replyDialog = false">取消</el-button><el-button type="primary" @click="submitReply">发送回复</el-button></template>
        </el-dialog>

        <!-- 编辑对话框 -->
        <el-dialog v-model="editDialog" title="编辑反馈" width="400px">
            <el-form :model="editForm" label-width="80px">
                <el-form-item label="优先级"><el-select v-model="editForm.priority" class="w-full"><el-option v-for="p in priorityOptions" :key="p" :label="priorityLabels[p]" :value="p" /></el-select></el-form-item>
                <el-form-item label="状态"><el-select v-model="editForm.status" class="w-full"><el-option v-for="s in statusOptions" :key="s" :label="statusLabels[s]" :value="s" /></el-select></el-form-item>
                <el-form-item label="标签"><el-select v-model="editForm.tags" multiple class="w-full"><el-option v-for="t in tags" :key="t.id" :label="t.name" :value="t.id" /></el-select></el-form-item>
            </el-form>
            <template #footer><el-button @click="editDialog = false">取消</el-button><el-button type="primary" @click="submitEdit">保存</el-button></template>
        </el-dialog>

        <!-- 新建标签对话框 -->
        <el-dialog v-model="tagDialog" title="新建标签" width="350px">
            <el-form :model="tagForm" label-width="60px">
                <el-form-item label="名称"><el-input v-model="tagForm.name" /></el-form-item>
                <el-form-item label="颜色"><el-color-picker v-model="tagForm.color" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="tagDialog = false">取消</el-button><el-button type="primary" @click="submitTag">创建</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-label { font-size: 12px; color: #909399; }
.stat-value { font-size: 20px; font-weight: 700; }
.text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; }
.text-primary { color: #409eff; }
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
