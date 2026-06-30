<template>
    <div class="review-management-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_reviews }}</div>
                    <div class="stat-label">评论总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card" style="border-top: 3px solid #e6a23c;">
                    <div class="stat-value" style="color: #e6a23c;">{{ stats.pending }}</div>
                    <div class="stat-label">待审核</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card" style="border-top: 3px solid #67c23a;">
                    <div class="stat-value" style="color: #67c23a;">{{ stats.approved }}</div>
                    <div class="stat-label">已通过</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.avg_rating }}</div>
                    <div class="stat-label">平均评分</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选 -->
        <el-card class="filter-card">
            <el-form :inline="true" size="small">
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width: 120px">
                        <el-option label="待审核" value="pending" />
                        <el-option label="已通过" value="approved" />
                        <el-option label="已驳回" value="rejected" />
                    </el-select>
                </el-form-item>
                <el-form-item label="评分">
                    <el-select v-model="filters.rating" clearable placeholder="全部" style="width: 120px">
                        <el-option v-for="i in 5" :key="i" :label="`${i}星`" :value="i" />
                    </el-select>
                </el-form-item>
                <el-form-item label="搜索">
                    <el-input v-model="filters.search" placeholder="评论内容/用户名" clearable style="width: 200px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadReviews">搜索</el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 评论列表 -->
        <el-card>
            <el-table :data="reviews" v-loading="loading" stripe border>
                <el-table-column type="index" label="#" width="50" />
                <el-table-column label="商品" min-width="150">
                    <template #default="{ row }">
                        <div class="ellipsis">{{ row.product?.name }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="用户" width="140">
                    <template #default="{ row }">
                        <div class="flex items-center gap-1">
                            <span>{{ row.is_anonymous ? '匿名用户' : (row.user?.name || '用户') }}</span>
                            <el-tag v-if="row.is_verified_purchase" size="small" type="success" style="font-size:10px;height:18px;line-height:16px;padding:0 4px;">已购买</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="评分" width="120">
                    <template #default="{ row }">
                        <el-rate :model-value="row.rating" disabled show-score text-color="#ff9900" score-template="{value}分" />
                    </template>
                </el-table-column>
                <el-table-column label="评论" min-width="200">
                    <template #default="{ row }">
                        <div class="flex items-start gap-2">
                            <el-image v-if="row.images?.length" :src="row.images[0]" style="width:40px;height:40px;border-radius:4px;flex-shrink:0;" fit="cover" />
                            <div class="review-content">{{ row.content || '-' }}</div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="标签" width="140">
                    <template #default="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <el-tag v-for="tag in (row.tags || [])" :key="tag" size="small" style="font-size:10px;height:18px;line-height:16px;padding:0 4px;">{{ tag }}</el-tag>
                            <span v-if="!row.tags?.length" class="text-gray-300 text-xs">-</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="280" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="success" @click="approveReview(row)">通过</el-button>
                        <el-button v-if="row.status === 'pending'" size="small" type="danger" @click="showRejectDialog(row)">驳回</el-button>
                        <el-popconfirm title="确认删除此评论？" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger" plain>删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadReviews"
                />
            </div>
        </el-card>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailVisible" title="评论详情" width="650px">
            <template v-if="detail">
                <div class="detail-section flex items-start gap-4">
                    <div class="flex-shrink-0">
                        <el-avatar v-if="detail.user?.avatar_url" :src="detail.user.avatar_url" :size="48" />
                        <el-avatar v-else :size="48">{{ (detail.is_anonymous ? '匿' : (detail.user?.name?.charAt(0) || '?')) }}</el-avatar>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <strong>{{ detail.is_anonymous ? '匿名用户' : detail.user?.name }}</strong>
                            <el-tag v-if="detail.is_anonymous" size="small" type="info">匿名</el-tag>
                            <el-tag v-if="detail.is_verified_purchase" size="small" type="success">已购买</el-tag>
                            <el-tag :type="statusType(detail.status)" size="small">{{ statusLabel(detail.status) }}</el-tag>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-sm text-gray-400">
                            <el-rate :model-value="detail.rating" disabled show-score score-template="{value}" style="display:inline-flex" />
                            <span>ID: {{ detail.id }}</span>
                            <span>{{ detail.created_at }}</span>
                        </div>
                    </div>
                </div>
                <el-divider />
                <div class="detail-section">
                    <div><strong>商品：</strong>
                        <a :href="'/products/' + (detail.product?.slug || '')" target="_blank" class="text-primary-500 hover:underline">{{ detail.product?.name }}</a>
                    </div>
                    <div><strong>用户邮箱：</strong>{{ detail.is_anonymous ? '（匿名）' : (detail.user?.email || '-') }}</div>
                </div>
                <el-divider />
                <div class="detail-section">
                    <div><strong>评论内容：</strong></div>
                    <p class="text-gray-700 bg-gray-50 rounded-lg p-4 mt-1 leading-relaxed">{{ detail.content }}</p>
                </div>
                <div v-if="detail.tags?.length" class="detail-section">
                    <div><strong>评价标签：</strong></div>
                    <div class="flex flex-wrap gap-1 mt-1">
                        <el-tag v-for="tag in detail.tags" :key="tag" size="small" style="border-radius:12px;">{{ tag }}</el-tag>
                    </div>
                </div>
                <div v-if="detail.images?.length" class="detail-section">
                    <div><strong>晒单图片：</strong></div>
                    <div class="flex gap-2 mt-1 flex-wrap">
                        <el-image v-for="(img, idx) in detail.images" :key="idx" :src="img" :preview-src-list="detail.images" style="width:80px;height:80px;border-radius:8px;" fit="cover" />
                    </div>
                </div>

                <!-- 商家回复 -->
                <el-divider />
                <div class="detail-section">
                    <div><strong>商家回复：</strong></div>
                    <div v-if="detail.admin_reply" class="reply-box bg-gray-50 rounded-lg p-3 mt-1">
                        <p class="text-gray-700">{{ detail.admin_reply }}</p>
                        <small class="text-gray-400">{{ detail.replied_by_name || '' }} · {{ detail.reply_at || '' }}</small>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <el-input v-model="replyText" type="textarea" :rows="2" placeholder="输入商家回复..." style="flex:1;" />
                        <el-button type="primary" @click="submitReply(detail.id)" :disabled="!replyText.trim()" style="align-self:flex-end;">发送回复</el-button>
                    </div>
                </div>

                <div v-if="detail.reject_reason" class="detail-section">
                    <el-divider />
                    <div><strong>驳回原因：</strong><span class="text-red-500">{{ detail.reject_reason }}</span></div>
                </div>

                <!-- 操作按钮 -->
                <el-divider />
                <div class="flex items-center gap-2 justify-end">
                    <el-button v-if="detail.status === 'pending'" type="success" @click="approveReview(detail)">
                        <el-icon><Check /></el-icon> 通过
                    </el-button>
                    <el-button v-if="detail.status === 'pending'" type="danger" @click="showRejectDialog(detail)">
                        <el-icon><Close /></el-icon> 驳回
                    </el-button>
                    <el-button @click="detailVisible = false">关闭</el-button>
                </div>
            </template>
        </el-dialog>

        <!-- 驳回对话框 -->
        <el-dialog v-model="rejectVisible" title="驳回评论" width="400px">
            <el-form>
                <el-form-item label="驳回原因">
                    <el-input v-model="rejectReason" type="textarea" :rows="3" placeholder="请输入驳回原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="rejectVisible = false">取消</el-button>
                <el-button type="danger" @click="confirmReject">确定驳回</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Close } from '@element-plus/icons-vue'
import {
    getAdminReviewList, getAdminReviewDetail,
    moderateReview, replyToReview, deleteReview, getReviewStats,
} from '@/api/productReview'

const loading = ref(false)
const reviews = ref([])
const total = ref(0)
const currentPage = ref(1)
const perPage = ref(20)
const stats = ref({ total_reviews: 0, pending: 0, approved: 0, avg_rating: 0 })

const filters = ref({
    status: '',
    rating: '',
    search: '',
})

const detailVisible = ref(false)
const detail = ref(null)

const rejectVisible = ref(false)
const rejectReviewId = ref(null)
const rejectReason = ref('')

const replyText = ref('')

function statusType(status) {
    return { approved: 'success', pending: 'warning', rejected: 'danger' }[status] || 'info'
}

function statusLabel(status) {
    return { approved: '已通过', pending: '待审核', rejected: '已驳回' }[status] || status
}

async function loadStats() {
    try {
        const res = await getReviewStats()
        stats.value = res.data || res
    } catch { /* ignore */ }
}

async function loadReviews() {
    loading.value = true
    try {
        const params = { ...filters.value, page: currentPage.value, per_page: perPage.value }

        // Clean empty filters
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })

        const res = await getAdminReviewList(params)

        if (res.data?.data?.data) {
            reviews.value = res.data.data.data
            total.value = res.data.data.total
            currentPage.value = res.data.data.current_page
        } else if (res.data?.data) {
            // Paginated response directly in data
            reviews.value = Array.isArray(res.data.data) ? res.data.data : (res.data.data.data || [])
            total.value = res.data.data.total || 0
            currentPage.value = res.data.data.current_page || 1
        } else if (res.data) {
            reviews.value = Array.isArray(res.data) ? res.data : res.data.data || []
            total.value = res.data.total || reviews.value.length
        }
    } catch (e) {
        ElMessage.error('加载评论列表失败')
    } finally {
        loading.value = false
    }
}

function resetFilters() {
    filters.value = { status: '', rating: '', search: '' }
    currentPage.value = 1
    loadReviews()
}

async function showDetail(row) {
    try {
        const res = await getAdminReviewDetail(row.id)
        detail.value = res.data?.data || res
        detail.value.replied_by_name = detail.value.replier?.name || ''
        detailVisible.value = true
    } catch {
        ElMessage.error('加载详情失败')
    }
}

async function approveReview(row) {
    try {
        await moderateReview(row.id, { status: 'approved' })
        ElMessage.success('评论已通过')
        loadReviews()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

function showRejectDialog(row) {
    rejectReviewId.value = row.id
    rejectReason.value = ''
    rejectVisible.value = true
}

async function confirmReject() {
    try {
        await moderateReview(rejectReviewId.value, {
            status: 'rejected',
            reject_reason: rejectReason.value,
        })
        ElMessage.success('评论已驳回')
        rejectVisible.value = false
        loadReviews()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function handleDelete(row) {
    try {
        await deleteReview(row.id)
        ElMessage.success('评论已删除')
        loadReviews()
        loadStats()
    } catch {
        ElMessage.error('删除失败')
    }
}

async function submitReply(id) {
    if (!replyText.value.trim()) return
    try {
        await replyToReview(id, { reply: replyText.value.trim() })
        ElMessage.success('回复已发送')
        replyText.value = ''
        // Reload detail
        const res = await getAdminReviewDetail(id)
        detail.value = res.data?.data || res
        detail.value.replied_by_name = detail.value.replier?.name || ''
    } catch {
        ElMessage.error('回复失败')
    }
}

onMounted(() => {
    loadStats()
    loadReviews()
})
</script>

<style scoped>
.review-management-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.filter-card { margin-bottom: 16px; }
.review-content { max-height: 50px; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.pagination-wrapper { margin-top: 16px; text-align: right; }
.detail-section { margin-bottom: 12px; }
.reply-box { background: #f5f7fa; padding: 12px; border-radius: 6px; margin-top: 8px; }
.text-muted { color: #909399; }
.ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
