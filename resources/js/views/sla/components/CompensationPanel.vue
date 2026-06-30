<template>
  <div class="compensation-panel">
    <!-- 操作栏 -->
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg font-medium">SLA 违约补偿</h3>
      <el-button type="primary" :loading="generating" @click="autoGenerate" v-if="hasOpenBreaches">
        <el-icon><Plus /></el-icon> 为未处理违约生成补偿
      </el-button>
    </div>

    <!-- 补偿统计 -->
    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ compStats.total_count }}</div>
            <div class="stat-label">补偿总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-warning">{{ compStats.pending_count }}</div>
            <div class="stat-label">待审批</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value text-success">{{ compStats.total_amount }}</div>
            <div class="stat-label">总补偿金额</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">¥{{ compStats.total_amount }}</div>
            <div class="stat-label">已发放总额</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 月度趋势 -->
    <el-card shadow="hover" class="mb-4" v-if="compStats.monthly_trend?.length">
      <template #header>月度补偿趋势</template>
      <el-table :data="compStats.monthly_trend" stripe size="small">
        <el-table-column label="月份" prop="month" width="120" />
        <el-table-column label="补偿次数" prop="cnt" width="120">
          <template #default="{ row }">
            <el-tag>{{ row.cnt }} 次</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="补偿总额" prop="total" min-width="120">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.total }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 类型分布 -->
    <el-card shadow="hover" class="mb-4" v-if="compStats.by_type?.length">
      <template #header>补偿类型分布</template>
      <el-table :data="compStats.by_type" stripe size="small">
        <el-table-column label="补偿类型" prop="compensation_type" width="140">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.compensation_type)">{{ typeLabel(row.compensation_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="次数" prop="cnt" width="100" />
        <el-table-column label="总额" prop="total" min-width="120">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.total }}</span>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <!-- 筛选栏 -->
    <el-card shadow="hover" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item label="状态">
          <el-select v-model="filters.status" placeholder="全部状态" clearable style="width:140px">
            <el-option v-for="s in allStatuses" :key="s.value" :label="s.label" :value="s.value" />
          </el-select>
        </el-form-item>
        <el-form-item label="严重度">
          <el-select v-model="filters.severity" placeholder="全部" clearable style="width:120px">
            <el-option label="轻微" value="minor" />
            <el-option label="主要" value="major" />
            <el-option label="严重" value="critical" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型">
          <el-select v-model="filters.compensation_type" placeholder="全部类型" clearable style="width:140px">
            <el-option label="信用额度" value="credit" />
            <el-option label="折扣" value="discount" />
            <el-option label="服务延长" value="extension" />
            <el-option label="退款" value="refund" />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="loadData">筛选</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 补偿列表 -->
    <el-card shadow="hover">
      <el-table :data="compensations" stripe size="small" v-loading="loading">
        <el-table-column label="合约" prop="contract.name" min-width="140" />
        <el-table-column label="客户" prop="customer?.name" width="120">
          <template #default="{ row }">{{ row.customer?.name || '-' }}</template>
        </el-table-column>
        <el-table-column label="严重度" width="90">
          <template #default="{ row }">
            <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            <el-tag :type="typeTag(row.compensation_type)" size="small">{{ typeLabel(row.compensation_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="金额" prop="amount" width="100">
          <template #default="{ row }">
            <span class="text-success">¥{{ row.amount }}</span>
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="原因" prop="reason" min-width="200" show-overflow-tooltip />
        <el-table-column label="创建时间" prop="created_at" width="160" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button v-if="row.status === 'pending'" type="primary" link size="small" @click="approve(row)">
              审批
            </el-button>
            <el-button v-if="row.status === 'approved'" type="success" link size="small" @click="issue(row)">
              发放
            </el-button>
            <el-button v-if="row.status === 'pending'" type="danger" link size="small" @click="showRejectDialog(row)">
              拒绝
            </el-button>
            <el-tag v-if="row.status === 'issued'" type="success" size="small">已发放</el-tag>
            <el-tag v-if="row.status === 'rejected'" type="danger" size="small">已拒绝</el-tag>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="flex justify-center mt-4" v-if="pagination.total > pagination.per_page">
        <el-pagination
          background
          layout="prev, pager, next"
          :total="pagination.total"
          :page-size="pagination.per_page"
          :current-page="pagination.current_page"
          @current-change="onPageChange"
        />
      </div>
    </el-card>

    <!-- 拒绝对话框 -->
    <el-dialog v-model="rejectDialog.visible" title="拒绝补偿" width="420">
      <el-form :model="rejectDialog">
        <el-form-item label="拒绝原因">
          <el-input v-model="rejectDialog.reason" type="textarea" :rows="3" placeholder="请输入拒绝原因" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="rejectDialog.visible = false">取消</el-button>
        <el-button type="danger" :loading="rejecting" @click="doReject">确认拒绝</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import {
  getCompensations, getCompensationStats,
  autoGenerateCompensations, approveCompensation,
  issueCompensation, rejectCompensation,
} from '../../../api/sla'

const loading = ref(false)
const generating = ref(false)
const rejecting = ref(false)
const compensations = ref([])
const compStats = ref({ total_count: 0, pending_count: 0, total_amount: 0, by_type: [], monthly_trend: [] })
const hasOpenBreaches = ref(false)
const pagination = reactive({ total: 0, per_page: 20, current_page: 1 })

const filters = reactive({
  status: '', severity: '', compensation_type: '',
})

const rejectDialog = reactive({
  visible: false,
  compensationId: null,
  reason: '',
})

const allStatuses = [
  { value: 'pending', label: '待审批' },
  { value: 'approved', label: '已审批' },
  { value: 'issued', label: '已发放' },
  { value: 'rejected', label: '已拒绝' },
]

function severityTag(s) {
  const map = { minor: 'info', major: 'warning', critical: 'danger' }
  return map[s] || 'info'
}

function severityLabel(s) {
  const map = { minor: '轻微', major: '主要', critical: '严重' }
  return map[s] || s
}

function typeTag(t) {
  const map = { credit: 'success', discount: 'warning', extension: 'primary', refund: 'danger' }
  return map[t] || ''
}

function typeLabel(t) {
  const map = { credit: '信用额度', discount: '折扣', extension: '服务延长', refund: '退款' }
  return map[t] || t
}

function statusTag(s) {
  const map = { pending: 'warning', approved: 'primary', issued: 'success', rejected: 'danger' }
  return map[s] || ''
}

function statusLabel(s) {
  const map = { pending: '待审批', approved: '已审批', issued: '已发放', rejected: '已拒绝' }
  return map[s] || s
}

function resetFilters() {
  filters.status = ''
  filters.severity = ''
  filters.compensation_type = ''
  loadData()
}

function onPageChange(page) {
  pagination.current_page = page
  loadData()
}

async function loadData() {
  loading.value = true
  try {
    const params = { ...filters, page: pagination.current_page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const [compRes, statsRes] = await Promise.all([
      getCompensations(params),
      getCompensationStats(),
    ])
    compensations.value = compRes.data.data || []
    pagination.total = compRes.data.total || 0
    pagination.current_page = compRes.data.current_page || 1
    compStats.value = statsRes.data || compStats.value
    hasOpenBreaches.value = statsRes.data?.pending_count > 0
  } catch (e) {
    console.error('Failed to load compensations', e)
    ElMessage.error('加载补偿数据失败')
  } finally {
    loading.value = false
  }
}

async function autoGenerate() {
  generating.value = true
  try {
    const { data } = await autoGenerateCompensations()
    ElMessage.success(`已生成 ${data.generated} 条补偿记录`)
    loadData()
  } catch (e) {
    ElMessage.error('生成补偿失败')
  } finally {
    generating.value = false
  }
}

async function approve(comp) {
  try {
    await approveCompensation(comp.id)
    ElMessage.success('已审批')
    loadData()
  } catch (e) {
    ElMessage.error('审批失败')
  }
}

async function issue(comp) {
  try {
    await issueCompensation(comp.id)
    ElMessage.success('已标记为发放')
    loadData()
  } catch (e) {
    ElMessage.error('发放失败')
  }
}

function showRejectDialog(comp) {
  rejectDialog.compensationId = comp.id
  rejectDialog.reason = ''
  rejectDialog.visible = true
}

async function doReject() {
  rejecting.value = true
  try {
    await rejectCompensation(rejectDialog.compensationId, rejectDialog.reason)
    ElMessage.success('已拒绝')
    rejectDialog.visible = false
    loadData()
  } catch (e) {
    ElMessage.error('拒绝失败')
  } finally {
    rejecting.value = false
  }
}

onMounted(loadData)
</script>

<style scoped>
.compensation-panel { min-height: 300px; }
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.flex { display: flex; }
.justify-between { justify-content: space-between; }
.items-center { align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
</style>
