<template>
    <div>
        <!-- 页头 -->
        <div class="page-header">
            <div>
                <h2>{{ t('admin_appeals_page.title') }}</h2>
                <p class="text-gray-500 text-sm">{{ t('admin_appeals_page.desc') }}</p>
            </div>
            <el-button @click="refresh" :loading="loading">
                <el-icon><Refresh /></el-icon> {{ t('admin_appeals_page.refresh') }}
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="never" @click="filterStatus = ''; loadList()" style="cursor:pointer" :class="{ 'card-active': !filterStatus }">
                    <div class="stat-item"><div class="stat-value">{{ stats.total }}</div><div class="stat-label">{{ t('admin_appeals_page.stats.all') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'pending'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'pending' }">
                    <div class="stat-item"><div class="stat-value text-warning">{{ stats.pending }}</div><div class="stat-label">{{ t('admin_appeals_page.stats.pending') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'approved'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'approved' }">
                    <div class="stat-item"><div class="stat-value text-success">{{ stats.approved }}</div><div class="stat-label">{{ t('admin_appeals_page.stats.approved') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never" @click="filterStatus = 'rejected'; loadList()" style="cursor:pointer" :class="{ 'card-active': filterStatus === 'rejected' }">
                    <div class="stat-item"><div class="stat-value text-danger">{{ stats.rejected }}</div><div class="stat-label">{{ t('admin_appeals_page.stats.rejected') }}</div></div>
                </el-card>
            </el-col>
            <el-col :span="5">
                <el-card shadow="never">
                    <div class="stat-item"><div class="stat-value">{{ stats.today }}</div><div class="stat-label">{{ t('admin_appeals_page.stats.today') }}</div></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 趋势图 -->
        <el-card shadow="never" class="mb-4">
            <template #header><span>{{ t('admin_appeals_page.trend_title') }}</span></template>
            <div ref="trendChartRef" style="height:200px"></div>
        </el-card>

        <!-- 筛选 -->
        <el-card shadow="never" class="mb-4">
            <el-form :inline="true" :model="filters" size="small">
                <el-form-item :label="t('admin_appeals_page.filter.status')">
                    <el-select v-model="filterStatus" :placeholder="t('admin_appeals_page.filter.all')" clearable @change="loadList" style="width:130px">
                        <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('admin_appeals_page.filter.search')">
                    <el-input v-model="filters.q" :placeholder="t('admin_appeals_page.filter.search_ph')" clearable @clear="loadList" @keyup.enter="loadList" style="width:240px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">{{ t('actions.search') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 数据表格 -->
        <el-card shadow="never">
            <el-table :data="list" v-loading="loading" stripe style="width:100%">
                <el-table-column :label="t('admin_appeals_page.cols.user')" min-width="160">
                    <template #default="{ row }">
                        <div class="user-info">
                            <span class="user-avatar">{{ row.user?.name?.charAt(0) || '?' }}</span>
                            <div>
                                <div class="user-name">{{ row.user?.name || t('admin_appeals_page.user_fallback', { id: row.user_id }) }}</div>
                                <div class="user-email">{{ row.user?.email || '-' }}</div>
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="reason" :label="t('admin_appeals_page.cols.reason')" width="130">
                    <template #default="{ row }">{{ reasonLabel(row.reason) }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_appeals_page.cols.status')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('admin_appeals_page.cols.submitted_at')" width="170">
                    <template #default="{ row }">{{ row.appealed_at || row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_appeals_page.cols.reviewer')" width="120">
                    <template #default="{ row }">{{ row.reviewer?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_appeals_page.cols.reviewed_at')" width="170">
                    <template #default="{ row }">{{ row.reviewed_at || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('admin_appeals_page.cols.ops')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'pending' || row.status === 'reviewing'" text size="small" type="primary" @click="openReview(row)">{{ t('admin_appeals_page.review') }}</el-button>
                        <el-button text size="small" @click="openDetail(row)">{{ t('admin_appeals_page.detail') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-center">
                <el-pagination v-model:current-page="page" :page-size="20" :total="total" layout="prev,pager,next" @current-change="loadList" />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetail" :title="t('admin_appeals_page.detail_dialog.title')" width="600px">
            <template v-if="detailData">
                <el-descriptions :column="1" border size="small">
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.user')">{{ detailData.user?.name }} ({{ detailData.user?.email }})</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.status')"><el-tag :type="statusTagType(detailData.status)" size="small">{{ statusLabel(detailData.status) }}</el-tag></el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.reason')">{{ reasonLabel(detailData.reason) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.explanation')">{{ detailData.explanation || t('admin_appeals_page.none') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.contact_phone')">{{ detailData.contact_phone || t('admin_appeals_page.none') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.contact_email')">{{ detailData.contact_email || t('admin_appeals_page.none') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.submitted_at')">{{ detailData.appealed_at || detailData.created_at }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.reviewer')">{{ detailData.reviewer?.name || t('admin_appeals_page.not_reviewed') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.review_comment')">{{ detailData.review_comment || t('admin_appeals_page.none') }}</el-descriptions-item>
                    <el-descriptions-item :label="t('admin_appeals_page.detail_dialog.reviewed_at')">{{ detailData.reviewed_at || '-' }}</el-descriptions-item>
                </el-descriptions>
                <!-- 证明材料 -->
                <div v-if="detailData.attachments?.length" class="mt-4">
                    <h4>{{ t('admin_appeals_page.detail_dialog.attachments') }}</h4>
                    <div class="attach-grid">
                        <div v-for="(att, i) in detailData.attachments" :key="i" class="attach-item">
                            <img v-if="isImage(att)" :src="att" class="attach-img" @click="previewUrl = att; showPreview = true" />
                            <a v-else :href="att" target="_blank" class="attach-link">{{ t('admin_appeals_page.detail_dialog.attachment_n', { n: i + 1 }) }}</a>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <el-button @click="showDetail = false">{{ t('actions.close') }}</el-button>
                <el-button v-if="detailData?.status === 'pending' || detailData?.status === 'reviewing'" type="primary" @click="showDetail = false; openReview(detailData)">{{ t('admin_appeals_page.go_review') }}</el-button>
            </template>
        </el-dialog>

        <!-- 审核对话框 -->
        <el-dialog v-model="showReview" :title="t('admin_appeals_page.review_dialog.title')" width="500px">
            <div class="review-info">
                <p><strong>{{ t('admin_appeals_page.review_dialog.user_label') }}：</strong>{{ reviewData?.user?.name }} ({{ reviewData?.user?.email }})</p>
                <p><strong>{{ t('admin_appeals_page.review_dialog.reason_label') }}：</strong>{{ reasonLabel(reviewData?.reason) }}</p>
                <p><strong>{{ t('admin_appeals_page.review_dialog.explanation_label') }}：</strong>{{ reviewData?.explanation || t('admin_appeals_page.none') }}</p>
            </div>
            <el-divider />
            <el-form :model="reviewForm" label-position="top" size="small">
                <el-form-item :label="t('admin_appeals_page.review_dialog.result')">
                    <el-radio-group v-model="reviewForm.action">
                        <el-radio value="approve" class="review-approve">{{ t('admin_appeals_page.review_dialog.approve') }}</el-radio>
                        <el-radio value="reject" class="review-reject">{{ t('admin_appeals_page.review_dialog.reject') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('admin_appeals_page.review_dialog.comment')">
                    <el-input v-model="reviewForm.comment" type="textarea" :rows="4" maxlength="2000" :placeholder="t('admin_appeals_page.review_dialog.comment_ph')" show-word-limit />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showReview = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="reviewing" @click="doReview">{{ t('admin_appeals_page.review_dialog.submit') }}</el-button>
            </template>
        </el-dialog>

        <!-- 图片预览 -->
        <el-image-viewer v-if="showPreview" :url-list="[previewUrl]" @close="showPreview = false" />
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getAppeals, getAppealStats, reviewAppeal, startAppealReview } from '@/api/adminAppeals'

const { t } = useI18n()

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

const reasonLabels = computed(() => ({
    misunderstanding: t('appeal_page.reason_misunderstanding'),
    behavior_changed: t('appeal_page.reason_behavior'),
    urgent_need: t('appeal_page.reason_urgent'),
    other: t('appeal_page.reason_other'),
}))

const statusLabels = computed(() => ({
    pending: t('admin_appeals_page.status.pending'),
    reviewing: t('admin_appeals_page.status.reviewing'),
    approved: t('admin_appeals_page.status.approved'),
    rejected: t('admin_appeals_page.status.rejected'),
}))

const statusOptions = computed(() => [
    { label: t('admin_appeals_page.status.pending'), value: 'pending' },
    { label: t('admin_appeals_page.status.reviewing'), value: 'reviewing' },
    { label: t('admin_appeals_page.status.approved'), value: 'approved' },
    { label: t('admin_appeals_page.status.rejected'), value: 'rejected' },
])

function reasonLabel(val) {
    return reasonLabels.value[val] || val
}
function statusLabel(val) {
    return statusLabels.value[val] || val
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
        const res = await getAppealStats()
        const d = res.data?.data || {}
        Object.assign(stats, d.stats || {})
        renderTrend(d.trend || [])
    } catch {}
}

function renderTrend(trend) {
    nextTick(() => {
        const el = trendChartRef.value
        if (!el || !trend.length) return
        el.innerHTML = '<div class="trend-bars">' + trend.map(item => `
            <div class="trend-col" title="${item.date}">
                <div class="trend-bar-wrap">
                    <div class="trend-bar trend-bar-approved" style="height:${Math.max(item.approved * 30, 2)}px" title="${t('admin_appeals_page.trend.approved', { n: item.approved })}"></div>
                    <div class="trend-bar trend-bar-rejected" style="height:${Math.max(item.rejected * 30, 2)}px" title="${t('admin_appeals_page.trend.rejected', { n: item.rejected })}"></div>
                </div>
                <div class="trend-label">${item.date.slice(5)}</div>
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
        const res = await getAppeals(params)
        list.value = res.data?.data || []
        total.value = res.data?.meta?.total || 0
    } catch (e) {
        ElMessage.error(t('messages.load_failed'))
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
    if (row.status === 'pending') {
        startAppealReview(row.id).then(() => {
            row.status = 'reviewing'
        }).catch(() => {})
    }
}

async function doReview() {
    if (!reviewForm.action) { ElMessage.warning(t('admin_appeals_page.messages.select_result')); return }
    reviewing.value = true
    try {
        await reviewAppeal(reviewData.value.id, {
            action: reviewForm.action,
            comment: reviewForm.comment || undefined,
        })
        ElMessage.success(reviewForm.action === 'approve' ? t('admin_appeals_page.messages.approve_ok') : t('admin_appeals_page.messages.reject_ok'))
        showReview.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('admin_appeals_page.messages.review_fail'))
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
.card-active { border-color: #0f172a; background: #f1f5f9; }

.user-info { display: flex; align-items: center; gap: 8px; }
.user-avatar { width: 32px; height: 32px; border-radius: 50%; background: #0f172a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
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
