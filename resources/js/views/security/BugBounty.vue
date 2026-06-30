<script setup>
import { ref, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    getBugReports, getBugReportDetail, getBugBountyStats,
    reviewBugReport, confirmBugReport, markBugFixed,
    declineBugReport, markBugPaid, deleteBugReport,
    getHallOfFame, updateHallOfFameEntry,
} from '../../api/bugBounty.js'

// ─── 数据 ───
const stats = ref(null)
const reports = ref([])
const pagination = ref({ total: 0, current_page: 1, per_page: 15 })
const hallOfFame = ref([])
const loading = ref(false)
const detailLoading = ref(false)

// 筛选
const filters = ref({
    status: '',
    severity: '',
    search: '',
})

// 对话框
const detailVisible = ref(false)
const currentReport = ref(null)
const reviewDialogVisible = ref(false)
const confirmDialogVisible = ref(false)
const declineDialogVisible = ref(false)
const honorDialogVisible = ref(false)
const currentHonorEntry = ref(null)

// 表单
const reviewForm = ref({ assigned_to: '' })
const confirmForm = ref({ severity: '', bounty_amount: null, is_public: true, resolution_notes: '' })
const declineForm = ref({ reason: '' })

// ─── 常量 ───
const severityOptions = [
    { value: 'critical', label: '严重 Critical', color: '#e74c3c' },
    { value: 'high', label: '高危 High', color: '#e67e22' },
    { value: 'medium', label: '中危 Medium', color: '#f1c40f' },
    { value: 'low', label: '低危 Low', color: '#3498db' },
    { value: 'informational', label: '信息 Informational', color: '#95a5a6' },
]

const statusOptions = [
    { value: 'submitted', label: '已提交', type: 'info' },
    { value: 'under_review', label: '审核中', type: 'warning' },
    { value: 'confirmed', label: '已确认', type: 'danger' },
    { value: 'fixed', label: '已修复', type: 'success' },
    { value: 'declined', label: '已拒绝', type: 'info' },
    { value: 'paid', label: '已打款', type: 'success' },
]

const severityMap = severityOptions.reduce((m, o) => { m[o.value] = o; return m }, {})
const statusMap = statusOptions.reduce((m, o) => { m[o.value] = o; return m }, {})

const rankOptions = [
    { value: 'gold', label: '金牌 Gold' },
    { value: 'silver', label: '银牌 Silver' },
    { value: 'bronze', label: '铜牌 Bronze' },
    { value: 'honorable', label: '荣誉 Honorable' },
]

// ─── 计算属性 ───
const openCount = computed(() => {
    if (!stats.value) return 0
    const s = stats.value.by_status || {}
    return (s.submitted?.count || 0) + (s.under_review?.count || 0)
})

const statsData = computed(() => {
    if (!stats.value) return []
    return [
        { label: '总报告数', value: stats.value.total, color: '#409eff' },
        { label: '待处理', value: openCount.value, color: '#e6a23c' },
        { label: '已发放赏金', value: `$${stats.value.total_bounty_paid || 0}`, color: '#67c23a' },
        { label: '待支付赏金', value: `$${stats.value.total_bounty_pending || 0}`, color: '#f56c6c' },
    ]
})

// ─── 方法 ───
async function loadStats() {
    try {
        const res = await getBugBountyStats()
        stats.value = res.data
    } catch (e) {
        console.error('Failed to load stats:', e)
    }
}

async function loadReports(page = 1) {
    loading.value = true
    try {
        const params = { ...filters.value, page }
        const res = await getBugReports(params)
        reports.value = res.data.data || []
        pagination.value = {
            total: res.data.total || 0,
            current_page: res.data.current_page || page,
            per_page: res.data.per_page || 15,
        }
    } catch (e) {
        console.error('Failed to load reports:', e)
    } finally {
        loading.value = false
    }
}

async function loadHallOfFame() {
    try {
        const res = await getHallOfFame()
        hallOfFame.value = res.data || []
    } catch (e) {
        console.error('Failed to load hall of fame:', e)
    }
}

function handleSearch() {
    loadReports(1)
}

function resetFilters() {
    filters.value = { status: '', severity: '', search: '' }
    loadReports(1)
}

// ─── 详情 ───
async function openDetail(id) {
    detailLoading.value = true
    detailVisible.value = true
    try {
        const res = await getBugReportDetail(id)
        currentReport.value = res.data
    } catch (e) {
        ElMessage.error('Failed to load detail')
    } finally {
        detailLoading.value = false
    }
}

// ─── 审核 ───
function openReview(report) {
    reviewForm.value = { assigned_to: '' }
    currentReport.value = report
    reviewDialogVisible.value = true
}

async function doReview() {
    try {
        await reviewBugReport(currentReport.value.id, reviewForm.value)
        ElMessage.success('已标记为审核中')
        reviewDialogVisible.value = false
        loadReports(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 确认 ───
function openConfirm(report) {
    confirmForm.value = {
        severity: report.severity,
        bounty_amount: null,
        is_public: true,
        resolution_notes: '',
    }
    currentReport.value = report
    confirmDialogVisible.value = true
}

async function doConfirm() {
    try {
        await confirmBugReport(currentReport.value.id, confirmForm.value)
        ElMessage.success('已确认漏洞')
        confirmDialogVisible.value = false
        loadReports(pagination.value.current_page)
        loadHallOfFame()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 修复 ───
async function handleMarkFixed(id) {
    try {
        await ElMessageBox.confirm('确认此漏洞已修复？', '确认')
        await markBugFixed(id)
        ElMessage.success('已标记为已修复')
        loadReports(pagination.value.current_page)
        loadHallOfFame()
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 拒绝 ───
function openDecline(report) {
    declineForm.value = { reason: '' }
    currentReport.value = report
    declineDialogVisible.value = true
}

async function doDecline() {
    try {
        await declineBugReport(currentReport.value.id, declineForm.value)
        ElMessage.success('已拒绝报告')
        declineDialogVisible.value = false
        loadReports(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 打款 ───
async function handleMarkPaid(id) {
    try {
        await ElMessageBox.confirm('确认已完成打款？', '确认')
        await markBugPaid(id)
        ElMessage.success('已标记为已打款')
        loadReports(pagination.value.current_page)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 删除 ───
async function handleDelete(id) {
    try {
        await ElMessageBox.confirm('删除后不可恢复，确定删除？', '警告', { confirmButtonClass: 'el-button--danger' })
        await deleteBugReport(id)
        ElMessage.success('已删除')
        loadReports(pagination.value.current_page)
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

// ─── 致谢墙 ───
function openEditHonor(entry) {
    currentHonorEntry.value = { ...entry }
    honorDialogVisible.value = true
}

async function saveHonorEntry() {
    try {
        await updateHallOfFameEntry(currentHonorEntry.value.id, currentHonorEntry.value)
        ElMessage.success('已更新')
        honorDialogVisible.value = false
        loadHallOfFame()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

function severityTag(sev) {
    return severityMap[sev] || { label: sev, color: '#ccc' }
}

function statusTag(st) {
    return statusMap[st] || { label: st, type: 'info' }
}

function formatDate(d) {
    if (!d) return '-'
    return new Date(d).toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

function handlePageChange(page) {
    loadReports(page)
}

onMounted(() => {
    loadStats()
    loadReports()
    loadHallOfFame()
})
</script>

<template>
    <div>
        <!-- 面包屑 -->
        <el-breadcrumb separator="/">
            <el-breadcrumb-item :to="{ path: '/admin' }">首页</el-breadcrumb-item>
            <el-breadcrumb-item>安全中心</el-breadcrumb-item>
            <el-breadcrumb-item>Bug Bounty 漏洞报告</el-breadcrumb-item>
        </el-breadcrumb>

        <el-card class="mt-4">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-semibold">🛡️ Bug Bounty 安全漏洞披露计划</span>
                    <el-button type="primary" @click="loadStats(); loadReports(); loadHallOfFame()" :icon="'Refresh'" circle />
                </div>
            </template>

            <!-- 统计数据 -->
            <el-row :gutter="20" class="mb-4">
                <el-col :span="6" v-for="item in statsData" :key="item.label">
                    <el-card shadow="never" class="stat-card">
                        <div class="text-sm text-gray-500">{{ item.label }}</div>
                        <div class="text-2xl font-bold mt-1" :style="{ color: item.color }">{{ item.value }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <!-- Tab 切换 -->
            <el-tabs v-model="activeTab" type="border-card">
                <!-- Tab 1: 报告列表 -->
                <el-tab-pane label="📋 漏洞报告" name="reports">
                    <!-- 筛选栏 -->
                    <div class="flex gap-3 mb-4 flex-wrap items-center">
                        <el-select v-model="filters.status" placeholder="按状态筛选" clearable style="width:140px">
                            <el-option v-for="o in statusOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select>
                        <el-select v-model="filters.severity" placeholder="按严重级别" clearable style="width:150px">
                            <el-option v-for="o in severityOptions" :key="o.value" :label="o.label" :value="o.value" />
                        </el-select>
                        <el-input v-model="filters.search" placeholder="搜索标题/报告人/类型" clearable style="width:260px" @keyup.enter="handleSearch" />
                        <el-button type="primary" @click="handleSearch">搜索</el-button>
                        <el-button @click="resetFilters">重置</el-button>
                    </div>

                    <el-table :data="reports" v-loading="loading" stripe style="width:100%">
                        <el-table-column prop="id" label="ID" width="70" />
                        <el-table-column label="严重级别" width="100">
                            <template #default="{ row }">
                                <el-tag :color="severityTag(row.severity).color" effect="dark" size="small">
                                    {{ severityTag(row.severity).label }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag :type="statusTag(row.status).type" size="small">{{ statusTag(row.status).label }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="vulnerability_type" label="漏洞类型" width="120" />
                        <el-table-column prop="reporter_email" label="报告人" width="180" show-overflow-tooltip />
                        <el-table-column label="赏金" width="100">
                            <template #default="{ row }">
                                <span v-if="row.bounty_amount">${{ row.bounty_amount }}</span>
                                <span v-else class="text-gray-400">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="提交时间" width="160">
                            <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="360" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openDetail(row.id)">详情</el-button>
                                <el-button v-if="row.status === 'submitted'" size="small" type="warning" @click="openReview(row)">审核</el-button>
                                <el-button v-if="row.status === 'under_review'" size="small" type="danger" @click="openConfirm(row)">确认</el-button>
                                <el-button v-if="row.status === 'confirmed'" size="small" type="success" @click="handleMarkFixed(row.id)">修复</el-button>
                                <el-button v-if="row.status === 'fixed'" size="small" type="success" @click="handleMarkPaid(row.id)">打款</el-button>
                                <el-popconfirm v-if="row.status === 'submitted' || row.status === 'under_review'"
                                    title="拒绝此报告？" @confirm="openDecline(row)">
                                    <template #reference>
                                        <el-button size="small" type="info">拒绝</el-button>
                                    </template>
                                </el-popconfirm>
                                <el-popconfirm title="删除报告？" @confirm="handleDelete(row.id)">
                                    <template #reference>
                                        <el-button size="small" type="danger">删除</el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <!-- 分页 -->
                    <div class="flex justify-center mt-4">
                        <el-pagination
                            v-model:current-page="pagination.current_page"
                            :page-size="pagination.per_page"
                            :total="pagination.total"
                            layout="prev, pager, next, total"
                            @current-change="handlePageChange"
                        />
                    </div>
                </el-tab-pane>

                <!-- Tab 2: 致谢墙 -->
                <el-tab-pane label="🏆 致谢墙" name="hall-of-fame">
                    <div class="mb-4">
                        <el-button type="primary" @click="loadHallOfFame()" :icon="'Refresh'">刷新</el-button>
                    </div>
                    <el-table :data="hallOfFame" stripe v-if="hallOfFame.length">
                        <el-table-column prop="hacker_name" label="白帽子" width="180" />
                        <el-table-column prop="hacker_handle" label="平台用户名" width="180" />
                        <el-table-column label="等级" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.rank === 'gold' ? 'warning' : row.rank === 'silver' ? '' : row.rank === 'bronze' ? 'danger' : 'info'" effect="dark" size="small">
                                    {{ row.rank }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="reports_count" label="有效报告" width="100" />
                        <el-table-column label="总赏金" width="120">
                            <template #default="{ row }">${{ row.total_bounty || 0 }}</template>
                        </el-table-column>
                        <el-table-column label="重点展示" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.is_featured ? 'success' : 'info'" size="small">{{ row.is_featured ? '是' : '否' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="bio" label="简介" min-width="200" show-overflow-tooltip />
                        <el-table-column label="操作" width="120" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="openEditHonor(row)">编辑</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无致谢记录" />
                </el-tab-pane>

                <!-- Tab 3: 按严重级别统计 -->
                <el-tab-pane label="📊 统计" name="stats">
                    <div v-if="stats" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <el-card shadow="never">
                            <template #header><span class="font-semibold">按严重级别</span></template>
                            <div v-for="(item, key) in stats.by_severity" :key="key" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <el-tag :color="severityMap[key]?.color || '#ccc'" effect="dark" size="small">{{ item.label }}</el-tag>
                                <span>{{ item.count }} 个报告</span>
                            </div>
                        </el-card>
                        <el-card shadow="never">
                            <template #header><span class="font-semibold">按状态</span></template>
                            <div v-for="(item, key) in stats.by_status" :key="key" class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                                <el-tag :type="statusMap[key]?.type || 'info'" size="small">{{ item.label }}</el-tag>
                                <span>{{ item.count }} 个</span>
                            </div>
                        </el-card>
                    </div>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="漏洞报告详情" width="700px">
            <div v-loading="detailLoading">
                <el-descriptions :column="2" border v-if="currentReport">
                    <el-descriptions-item label="ID" :span="1">{{ currentReport.id }}</el-descriptions-item>
                    <el-descriptions-item label="严重级别" :span="1">
                        <el-tag :color="severityTag(currentReport.severity).color" effect="dark" size="small">
                            {{ severityTag(currentReport.severity).label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="标题" :span="2">{{ currentReport.title }}</el-descriptions-item>
                    <el-descriptions-item label="漏洞类型" :span="1">{{ currentReport.vulnerability_type || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="受影响端点" :span="1">{{ currentReport.affected_endpoint || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="报告人" :span="1">{{ currentReport.reporter_name || currentReport.reporter_email || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="邮箱" :span="1">{{ currentReport.reporter_email || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="状态" :span="1">
                        <el-tag :type="statusTag(currentReport.status).type" size="small">{{ statusTag(currentReport.status).label }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="赏金" :span="1">{{ currentReport.bounty_amount ? `$${currentReport.bounty_amount} ${currentReport.bounty_currency || 'USD'}` : '-' }}</el-descriptions-item>
                    <el-descriptions-item label="描述" :span="2">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.description }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item label="复现步骤" :span="2" v-if="currentReport.steps_to_reproduce">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.steps_to_reproduce }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item label="影响分析" :span="2" v-if="currentReport.impact">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.impact }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item label="处理备注" :span="2" v-if="currentReport.resolution_notes">
                        <pre class="whitespace-pre-wrap bg-gray-50 p-3 rounded text-sm">{{ currentReport.resolution_notes }}</pre>
                    </el-descriptions-item>
                    <el-descriptions-item label="提交时间" :span="1">{{ formatDate(currentReport.created_at) }}</el-descriptions-item>
                    <el-descriptions-item label="确认时间" :span="1">{{ formatDate(currentReport.confirmed_at) }}</el-descriptions-item>
                    <el-descriptions-item label="修复时间" :span="1">{{ formatDate(currentReport.fixed_at) }}</el-descriptions-item>
                    <el-descriptions-item label="打款时间" :span="1">{{ formatDate(currentReport.paid_at) }}</el-descriptions-item>
                </el-descriptions>
            </div>
        </el-dialog>

        <!-- 审核对话框 -->
        <el-dialog v-model="reviewDialogVisible" title="审核报告" width="400px">
            <el-form :model="reviewForm" label-position="top">
                <el-form-item label="分配给" required>
                    <el-input v-model="reviewForm.assigned_to" placeholder="输入处理人姓名" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="reviewDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="doReview">提交审核</el-button>
            </template>
        </el-dialog>

        <!-- 确认漏洞对话框 -->
        <el-dialog v-model="confirmDialogVisible" title="确认漏洞" width="500px">
            <el-form :model="confirmForm" label-position="top">
                <el-form-item label="严重级别">
                    <el-select v-model="confirmForm.severity" style="width:100%">
                        <el-option v-for="o in severityOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="赏金金额 (USD)">
                    <el-input-number v-model="confirmForm.bounty_amount" :min="0" :step="50" style="width:100%" />
                </el-form-item>
                <el-form-item label="公开致谢">
                    <el-switch v-model="confirmForm.is_public" active-text="是" inactive-text="否" />
                </el-form-item>
                <el-form-item label="处理备注">
                    <el-input v-model="confirmForm.resolution_notes" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="confirmDialogVisible = false">取消</el-button>
                <el-button type="danger" @click="doConfirm">确认漏洞</el-button>
            </template>
        </el-dialog>

        <!-- 拒绝对话框 -->
        <el-dialog v-model="declineDialogVisible" title="拒绝报告" width="400px">
            <el-form :model="declineForm" label-position="top">
                <el-form-item label="拒绝原因" required>
                    <el-input v-model="declineForm.reason" type="textarea" :rows="3" placeholder="请填写拒绝原因（将通知报告人）" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="declineDialogVisible = false">取消</el-button>
                <el-button type="info" @click="doDecline">确认拒绝</el-button>
            </template>
        </el-dialog>

        <!-- 编辑致谢墙 -->
        <el-dialog v-model="honorDialogVisible" title="编辑致谢墙" width="500px">
            <el-form :model="currentHonorEntry" label-position="top" v-if="currentHonorEntry">
                <el-form-item label="白帽子名称">
                    <el-input v-model="currentHonorEntry.hacker_name" />
                </el-form-item>
                <el-form-item label="等级">
                    <el-select v-model="currentHonorEntry.rank" style="width:100%">
                        <el-option v-for="o in rankOptions" :key="o.value" :label="o.label" :value="o.value" />
                    </el-select>
                </el-form-item>
                <el-form-item label="简介">
                    <el-input v-model="currentHonorEntry.bio" type="textarea" :rows="2" />
                </el-form-item>
                <el-form-item label="重点展示">
                    <el-switch v-model="currentHonorEntry.is_featured" active-text="是" inactive-text="否" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="currentHonorEntry.sort_order" :min="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="honorDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="saveHonorEntry">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.stat-card {
    border-radius: 8px;
}
.stat-card:hover {
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}
pre {
    margin: 0;
    line-height: 1.5;
}
</style>
