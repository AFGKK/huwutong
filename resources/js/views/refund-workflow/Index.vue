<template>
  <div class="refund-workflow-page">
    <h2>退款售后管理</h2>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value">{{ stats.total ?? 0 }}</div>
          <div class="stat-label">总计</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value text-warning">{{ stats.pending ?? 0 }}</div>
          <div class="stat-label">待处理</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value text-primary">{{ stats.completed ?? 0 }}</div>
          <div class="stat-label">已完成</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value text-danger">{{ stats.rejected ?? 0 }}</div>
          <div class="stat-label">已拒绝</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value">¥{{ (stats.total_amount ?? 0).toFixed(2) }}</div>
          <div class="stat-label">退款总额</div>
        </el-card>
      </el-col>
      <el-col :span="4">
        <el-card shadow="never">
          <div class="stat-value">{{ stats.avg_refund_time_hours ?? 0 }}h</div>
          <div class="stat-label">平均处理时长</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 过滤器 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item label="状态">
          <el-select v-model="filters.status" clearable placeholder="全部">
            <el-option label="待处理" value="pending" />
            <el-option label="已通过" value="approved" />
            <el-option label="已完成" value="completed" />
            <el-option label="已拒绝" value="rejected" />
          </el-select>
        </el-form-item>
        <el-form-item label="搜索">
          <el-input v-model="filters.search" placeholder="订单号/客户名" clearable @keyup.enter="fetchRefunds" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="fetchRefunds">搜索</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 退款列表 -->
    <el-card shadow="never">
      <el-table :data="refunds" v-loading="loading" stripe>
        <el-table-column prop="refund_no" label="退款单号" width="160" />
        <el-table-column prop="order.order_no" label="订单号" width="160" />
        <el-table-column label="金额" width="120" align="center">
          <template #default="{ row }">¥{{ (row.amount ?? 0).toFixed(2) }}</template>
        </el-table-column>
        <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
        <el-table-column prop="status" label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ row.status }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="created_at" label="时间" width="160" />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" type="primary" size="small" @click="openReview(row, 'approve')">
              通过
            </el-button>
            <el-button v-if="row.status === 'pending'" type="danger" size="small" @click="openReview(row, 'reject')">
              拒绝
            </el-button>
            <span v-else class="text-gray text-sm">{{ row.status }}</span>
          </template>
        </el-table-column>
      </el-table>
      <div class="mt-3 flex justify-end">
        <el-pagination
          v-model:current-page="page"
          :page-size="perPage"
          :total="total"
          layout="prev, pager, next"
          small
          @current-change="fetchRefunds"
        />
      </div>
    </el-card>

    <!-- 审核对话框 -->
    <el-dialog v-model="reviewVisible" :title="reviewAction === 'approve' ? '通过退款' : '拒绝退款'" width="500px">
      <el-form ref="reviewFormRef" :model="reviewData" :rules="reviewRules" label-width="100px">
        <el-form-item label="退款单号">{{ reviewTarget?.refund_no }}</el-form-item>
        <el-form-item label="金额">¥{{ (reviewTarget?.amount ?? 0).toFixed(2) }}</el-form-item>
        <el-form-item label="原因">{{ reviewTarget?.reason }}</el-form-item>
        <el-form-item v-if="reviewAction === 'reject'" label="拒绝原因" prop="reason">
          <el-input v-model="reviewData.reason" type="textarea" :rows="3" placeholder="请输入拒绝原因" maxlength="500" />
        </el-form-item>
        <el-form-item label="备注" prop="notes">
          <el-input v-model="reviewData.notes" type="textarea" :rows="2" placeholder="可选" maxlength="1000" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="reviewVisible = false">取消</el-button>
        <el-button type="primary" :loading="reviewSubmitting" @click="submitReview">
          {{ reviewAction === 'approve' ? '确认通过' : '确认拒绝' }}
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import api from '@/api/refundWorkflow'

const stats = ref({})
const refunds = ref([])
const loading = ref(false)
const filters = ref({ status: '', search: '' })
const page = ref(1)
const perPage = ref(20)
const total = ref(0)

// Review dialog
const reviewVisible = ref(false)
const reviewTarget = ref(null)
const reviewAction = ref('approve')
const reviewData = ref({ reason: '', notes: '' })
const reviewRules = { reason: [{ required: true, message: '拒绝时必须填写原因', trigger: 'blur' }] }
const reviewSubmitting = ref(false)
const reviewFormRef = ref(null)

function statusTag(s) {
  const map = { pending: 'warning', approved: 'primary', completed: 'success', rejected: 'danger' }
  return map[s] ?? 'info'
}

async function fetchStats() {
  try {
    const res = await api.stats()
    stats.value = res.data?.data ?? {}
  } catch (e) { console.error(e) }
}

async function fetchRefunds() {
  loading.value = true
  try {
    const params = { ...filters.value, page: page.value, per_page: perPage.value }
    const res = await api.list(params)
    refunds.value = res.data?.data?.data ?? res.data?.data ?? []
    total.value = res.data?.data?.total ?? 0
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

function openReview(row, action) {
  reviewTarget.value = row
  reviewAction.value = action
  reviewData.value = { reason: '', notes: '' }
  reviewVisible.value = true
}

async function submitReview() {
  if (reviewAction.value === 'reject') {
    const valid = await reviewFormRef.value.validate().catch(() => false)
    if (!valid) return
  }
  reviewSubmitting.value = true
  try {
    await api.review(reviewTarget.value.id, {
      action: reviewAction.value,
      reason: reviewData.value.reason,
      notes: reviewData.value.notes,
    })
    ElMessage.success(reviewAction.value === 'approve' ? '退款已批准' : '退款已拒绝')
    reviewVisible.value = false
    await fetchRefunds()
    await fetchStats()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '操作失败')
  } finally {
    reviewSubmitting.value = false
  }
}

onMounted(() => {
  fetchStats()
  fetchRefunds()
})
</script>

<style scoped>
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-warning { color: #e6a23c; }
.text-primary { color: #409eff; }
.text-danger { color: #f56c6c; }
.text-gray { color: #909399; }
</style>
