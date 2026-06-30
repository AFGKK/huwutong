<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>📋 账号申诉审核</h2>
                <p class="text-gray-500 text-sm">管理用户提交的账号申诉，审核通过后自动恢复账号</p>
            </div>
            <el-button @click="refresh" :loading="loading">
                <el-icon><Refresh /></el-icon> 刷新
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never" @click="filterStatus = ''; loadList()" style="cursor:pointer" :class="{ 'card-active': !filterStatus }">
                    <div class="stat-item"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">全部</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'pending'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'pending' }">
                    <div class="stat-item"><div class="stat-value text-warning">{{ stats.pending }}</div><div class="stat-label">待处理</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'approved'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'approved' }">
                    <div class="stat-item"><div class="stat-value text-success">{{ stats.approved }}</div><div class="stat-label">已通过</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'rejected'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'rejected' }">
                    <div class="stat-item"><div class="stat-value text-danger">{{ stats.rejected }}</div><div class="stat-label">已驳回</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.today }}</div><div class="stat-label">今日提交</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 趋势图 -->
        <el-card shadow="never" class="mb-4">
            <template #header><span>📈 近 7 天趋势</span></template>
            <div ref="trendChartRef" style="height:200px"></div>
        </el-card>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item label="状态">
                    <el-select v-model="filterStatus" placeholder="全部" clearable @change="loadList" style="width:130px">
                        <el-option label="待处理" value="pending" />
                        <el-option label="审核中" value="reviewing" />
                        <el-option label="已通过" value="approved" />
                        <el-option label="已驳回" value="rejected" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.q" placeholder="用户名/邮箱/手机" clearable @clear="loadList" @keyup.enter="loadList" style="width:240px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 数据表格 -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column label="用户" min-width="160">
                    <template #default="{ row }">
                        <div class="user-info">
                            <span class="user-avatar">{{ row.user?.name?.charAt(0) || '?' }}</span>
                            <div>
                                <div class="user-name">{{ row.user?.name || '用户 #'+row.user_id }}</div>
                                <div class="user-email">{{ row.user?.email || '-' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" label="申诉原因" width="130">
                    <template #default="{ row }">{{ reasonLabel(row.reason) }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="提交时间" width="170">
                    <template #default="{ row }">{{ row.appealed_at || row.created_at }}</template>
                </el-table-column>
                <el-table-column label="审核人" width="120">
                    <template #default="{ row }">{{ row.reviewer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="审核时间" width="170">
                    <template #default="{ row }">{{ row.reviewed_at || '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'pending' || row.status === 'reviewing'" text size="small" type="primary" @click="openReview(row)">审核</el-button>
                        <el-button text size="small" @click="openDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="20" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetail" title="申诉详情" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item label="用户">{{ detailData.user?.name }} ({{ detailData.user?.email }})</el-descriptions-item>
                    <el-descriptions-item label="状态"><el-tag :type="statusTagType(detailData.status)" size="small">{{ statusLabel(detailData.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item label="申诉原因">{{ reasonLabel(detailData.reason) }}</el-descriptions-item>
                    <el-descriptions-item label="详细说明">{{ detailData.explanation || '无' }}</el-descriptions-item>
                    <el-descriptions-item label="联系电话">{{ detailData.contact_phone || '无' }}</el-descriptions-item>
                    <el-descriptions-item label="联系邮箱">{{ detailData.contact_email || '无' }}</el-descriptions-item>
                    <el-descriptions-item label="提交时间">{{ detailData.appealed_at || detailData.created_at }}</el-descriptions-item>
                    <el-descriptions-item label="审核人">{{ detailData.reviewer?.name || '未审核' }}</el-descriptions-item>
                    <el-descriptions-item label="审核意见">{{ detailData.review_comment || '无' }}</el-descriptions-item>
                    <el-descriptions-item label="审核时间">{{ detailData.reviewed_at || '-' }}</el-descriptions-item>
                </el-descriptions>
                <!-- 证明材料 -->
                <div v-if="detailData.attachments?.length" class="mt-4">
                    <h4>证明材料</h4>
                    <div class="attach-grid">
                        <div v-for="(att, i) in detailData.attachments" :key="i" class="attach-item">
                            <img v-if="isImage(att)" :src="att" class="attach-img" @click="previewUrl = att; showPreview = true" />
                            <a v-else :href="att" target="_blank" class="attach-link">📎 附件 {{ i + 1 }}</a>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <el-button @click="showDetail = false">关闭</el-button>
                <el-button v-if="detailData?.status === 'pending' || detailData?.status === 'reviewing'" type="primary" @click="showDetail = false; openReview(detailData)">去审核</el-button>
            </template>
        </el-dialog>

        <!-- 审核对话框 -->
        <el-dialog v-model="showReview" title="审核申诉" width="500px">
            <div class="review-info">
                <p><strong>用户：</strong>{{ reviewData?.user?.name }} ({{ reviewData?.user?.email }})</p>
                <p><strong>申诉原因：</strong>{{ reasonLabel(reviewData?.reason) }}</p>
                <p><strong>详细说明：</strong>{{ reviewData?.explanation || '无' }}</p>
            </div>
            <el-divider />
            <el-form :model="reviewForm" label-position="top" size="small">
                <el-form-item label="审核结果">
                    <el-radio-group v-model="reviewForm.action">
                        <el-radio value="approve" class="review-approve">✅ 通过 - 恢复账号</el-radio>
                        <el-radio value="reject" class="review-reject">❌ 驳回 - 维持封禁</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="审核意见">
                    <el-input v-model="reviewForm.comment" type="textarea" :rows="4" maxlength="2000" placeholder="请输入审核意见，将通知用户" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showReview = false">取消</el-button>
                <el-button type="primary" :loading="reviewing" @click="doReview">提交审核</el-button>
            </template>
        </el-dialog>

        <!-- 图片预览 -->
        <el-image-viewer v-if="showPreview" :url-list="[previewUrl]" @close="showPreview = false" />
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, nextTick } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const list = ref([])
const loading = ref(false)
const page = ref(1)
const total = ref(0)
const filterStatus = ref('')
const filters = reactive({ q: '' })

const stats = reactive({ total: 0, pending: 0, approved: 0, rejected: 0, today: 0 })

const showDetail = ref(false)
const detailData = ref(null)

const showReview = ref(false)
const reviewData = ref(null)
const reviewForm = reactive({ action: 'approve', comment: '' })
const reviewing = ref(false)

const trendChartRef = ref(null)
const showPreview = ref(false)
const previewUrl = ref('')

function reasonLabel(val) {
    const map = { misunderstanding: '账号被误封', behavior_changed: '已改正违规行为', urgent_need: '账号内有重要数据', other: '其他原因' }
    return map[val] || val
}
function statusLabel(val) {
    const map = { pending: '待处理', reviewing: '审核中', approved: '已通过', rejected: '已驳回' }
    return map[val] || val
}
function statusTagType(val) {
    const map = { pending: 'warning', reviewing: 'info', approved: 'success', rejected: 'danger' }
    return map[val] || 'info'
}
function isImage(url) {
    return /\.(jpg|jpeg|png|gif|webp|svg)(\?.*)?$/i.test(url)
}

async function loadStats() {
    try {
        const res = await apiClient.get('/admin/appeals/stats')
        const d = res.data?.data || {}
        Object.assign(stats, d.stats || {})
        renderTrend(d.trend || [])
    } catch {}
}

function renderTrend(trend) {
    nextTick(() => {
        const el = trendChartRef.value
        if (!el || !trend.length) return
        // Simple bar chart using divs
        el.innerHTML = '<div class="trend-bars">' + trend.map(t => `
            <div class="trend-col" title="${t.date}">
                <div class="trend-bar-wrap">
                    <div class="trend-bar trend-bar-approved" style="height:${Math.max(t.approved * 30, 2)}px" title="通过 ${t.approved}"></div>
                    <div class="trend-bar trend-bar-rejected" style="height:${Math.max(t.rejected * 30, 2)}px" title="驳回 ${t.rejected}"></div>
                </div>
                <div class="trend-label">${t.date.slice(5)}</div>
            </div>
        `).join('') + '</div>'
    })
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: page.value }
        if (filterStatus.value) params.status = filterStatus.value
        if (filters.q) params.q = filters.q
        const res = await apiClient.get('/admin/appeals', { params })
        const d = res.data?.data || {}
        list.value = d.data || []
        total.value = d.total || 0
    } catch (e) {
        ElMessage.error('加载失败')
    } finally {
        loading.value = false
    }
}

function refresh() { loadList(); loadStats() }

function openDetail(row) {
    detailData.value = row
    showDetail.value = true
}

function openReview(row) {
    reviewData.value = row
    reviewForm.action = 'approve'
    reviewForm.comment = ''
    showReview.value = true
}

async function doReview() {
    if (!reviewForm.action) { ElMessage.warning('请选择审核结果'); return }
    reviewing.value = true
    try {
        await apiClient.post(`/admin/appeals/${reviewData.value.id}/review`, {
            action: reviewForm.action,
            comment: reviewForm.comment || undefined,
        })
        ElMessage.success(reviewForm.action === 'approve' ? '申诉已通过，账号已恢复' : '申诉已驳回')
        showReview.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '审核失败')
    } finally {
        reviewing.value = false
    }
}

onMounted(() => { loadList(); loadStats() })
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.text-gray-500 { color: #909399; }
.text-sm { font-size: 13px; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.flex { display: flex; }
.gap-2 { gap: 8px; }
.justify-center { justify-content: center; }

.stat-item { text-align: center; padding: 6px 0; cursor: pointer; }
.stat-value { font-size: 24px; font-weight: 700; color: #303133; }
.stat-label { font-size: 12px; color: #909399; margin-top: 2px; }
.text-warning { color: #e6a23c; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.card-active { border-color: #409eff; background: #ecf5ff; }

.user-info { display: flex; align-items: center; gap: 8px; }
.user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #409eff; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
.user-name { font-size: 13px; font-weight: 500; }
.user-email { font-size: 12px; color: #909399; }

.review-info p { margin: 4px 0; font-size: 14px; }
.review-approve { color: #67c23a; }
.review-reject { color: #f56c6c; }

.attach-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.attach-item { width: 100px; height: 100px; overflow: hidden; border-radius: 6px; border: 1px solid #e4e7ed; cursor: pointer; }
.attach-img { width: 100%; height: 100%; object-fit: cover; }
.attach-link { display: block; padding: 8px; font-size: 12px; text-align: center; word-break: break-all; }

.trend-bars { display: flex; align-items: flex-end; gap: 12px; height: 100%; padding: 10px 0; }
.trend-col { flex: 1; display: flex; flex-direction: column; align-items: center; }
.trend-bar-wrap { display: flex; gap: 3px; align-items: flex-end; height: 100%; min-height: 40px; }
.trend-bar { width: 12px; border-radius: 3px 3px 0 0; min-height: 2px; }
.trend-bar-approved { background: #67c23a; }
.trend-bar-rejected { background: #f56c6c; }
.trend-label { font-size: 11px; color: #909399; margin-top: 4px; white-space: nowrap; }
</style>
