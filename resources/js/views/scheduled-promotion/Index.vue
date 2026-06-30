<template>
  <div class="scheduled-promotion-page">
    <div class="page-header">
      <h2>📅 定时上下架 & 促销管理</h2>
      <div class="header-actions">
        <el-button type="primary" @click="showFormDialog(null)">新建活动</el-button>
        <el-button @click="loadAll" :loading="loading"><el-icon><Refresh /></el-icon> 刷新</el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">总计</div><div class="stat-value">{{ stats.total }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">进行中</div><div class="stat-value text-success">{{ stats.active }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">待开始</div><div class="stat-value text-warning">{{ stats.scheduled }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">已结束</div><div class="stat-value text-info">{{ stats.expired }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">草稿</div><div class="stat-value">{{ stats.draft }}</div>
        </el-card>
      </el-col>
      <el-col :xs="12" :sm="4">
        <el-card shadow="hover" size="small">
          <div class="stat-label">总预算</div><div class="stat-value">¥{{ formatNum(stats.total_budget) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 活动日历 -->
    <el-card shadow="hover" class="mb-6">
      <template #header>
        <div class="card-header">
          <span>📆 活动日历</span>
          <div class="calendar-nav">
            <el-button size="small" @click="changeMonth(-1)">‹ 上月</el-button>
            <span class="current-month">{{ currentMonth }}</span>
            <el-button size="small" @click="changeMonth(1)">下月 ›</el-button>
          </div>
        </div>
      </template>
      <div v-if="calendarEvents.length" class="calendar-list">
        <div v-for="evt in calendarEvents" :key="evt.id" class="calendar-event" :style="{ borderLeftColor: evt.color || '#409eff' }">
          <div class="event-date">{{ formatDate(evt.start_at) }}</div>
          <div class="event-info">
            <div class="event-title">{{ evt.title }}</div>
            <div class="event-meta">
              <el-tag :type="evt.status === 'active' ? 'success' : evt.status === 'scheduled' ? 'warning' : 'info'" size="small">
                {{ evt.status === 'active' ? '进行中' : evt.status === 'scheduled' ? '待开始' : evt.status }}
              </el-tag>
              <span v-if="evt.end_at" class="event-end">至 {{ formatDate(evt.end_at) }}</span>
            </div>
          </div>
        </div>
      </div>
      <el-empty v-else description="本月暂无活动" :image-size="60" />
    </el-card>

    <!-- 活动列表 -->
    <el-card shadow="hover">
      <template #header>
        <div class="card-header">
          <span>📋 活动列表</span>
          <el-form :inline="true" size="small">
            <el-form-item>
              <el-select v-model="filters.status" placeholder="全部状态" clearable style="width:120px" @change="loadList">
                <el-option label="进行中" value="active" />
                <el-option label="待开始" value="scheduled" />
                <el-option label="草稿" value="draft" />
                <el-option label="已暂停" value="paused" />
                <el-option label="已结束" value="expired" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-select v-model="filters.type" placeholder="全部类型" clearable style="width:130px" @change="loadList">
                <el-option v-for="(label, key) in typeMap" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-input v-model="filters.search" placeholder="搜索活动" clearable style="width:160px" @input="onSearch" />
            </el-form-item>
          </el-form>
        </div>
      </template>
      <el-table :data="promotions" v-loading="loadingList" stripe style="width:100%">
        <el-table-column prop="name" label="活动名称" min-width="160" />
        <el-table-column label="类型" width="100">
          <template #default="{ row }">{{ typeMap[row.type] || row.type }}</template>
        </el-table-column>
        <el-table-column label="折扣" width="100">
          <template #default="{ row }">
            {{ row.discount_type === 'percentage' ? row.discount_value + '%' : '¥' + row.discount_value }}
          </template>
        </el-table-column>
        <el-table-column label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="开始时间" width="150">
          <template #default="{ row }">{{ row.starts_at }}</template>
        </el-table-column>
        <el-table-column label="结束时间" width="150">
          <template #default="{ row }">{{ row.ends_at || '—' }}</template>
        </el-table-column>
        <el-table-column label="使用量" width="80" align="center">
          <template #default="{ row }">{{ row.usage_count }}/{{ row.usage_limit || '∞' }}</template>
        </el-table-column>
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button size="small" @click="showFormDialog(row)">编辑</el-button>
            <el-button v-if="row.status === 'draft' || row.status === 'scheduled'" size="small" type="primary" @click="publish(row)">发布</el-button>
            <el-button v-if="row.status === 'active'" size="small" type="warning" @click="pause(row)">暂停</el-button>
            <el-button v-if="row.status === 'draft'" size="small" type="danger" @click="remove(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
      <el-pagination
        v-if="pagination.total > pagination.per_page"
        background layout="prev,pager,next,total"
        :total="pagination.total" :page-size="pagination.per_page"
        :current-page="pagination.current_page"
        @current-change="onPageChange"
        style="margin-top:16px;justify-content:center"
      />
    </el-card>

    <!-- 新建/编辑对话框 -->
    <el-dialog v-model="showForm" :title="editing ? '编辑活动' : '新建促销活动'" width="700px">
      <el-form :model="form" label-width="120px" size="small">
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="活动名称" required>
              <el-input v-model="form.name" placeholder="如：618限时秒杀" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="活动类型" required>
              <el-select v-model="form.type" style="width:100%">
                <el-option v-for="(label, key) in typeMap" :key="key" :label="label" :value="key" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="描述">
          <el-input v-model="form.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="折扣类型">
              <el-select v-model="form.discount_type" style="width:100%">
                <el-option label="百分比" value="percentage" />
                <el-option label="固定金额" value="fixed_amount" />
                <el-option label="免费" value="free" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="折扣值">
              <el-input v-model="form.discount_value" type="number" min="0" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="最大折扣">
              <el-input v-model="form.max_discount" type="number" min="0" placeholder="不限" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="12">
            <el-form-item label="开始时间" required>
              <el-date-picker v-model="form.starts_at" type="datetime" placeholder="选择开始时间" style="width:100%" />
            </el-form-item>
          </el-col>
          <el-col :span="12">
            <el-form-item label="结束时间">
              <el-date-picker v-model="form.ends_at" type="datetime" placeholder="可选" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="使用限制">
              <el-input v-model="form.usage_limit" type="number" min="0" placeholder="不限" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="每客户限">
              <el-input v-model="form.usage_limit_per_customer" type="number" min="0" placeholder="不限" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="预算">
              <el-input v-model="form.budget" type="number" min="0" placeholder="不限" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-divider />
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="仅首单">
              <el-switch v-model="form.is_first_order_only" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="会员专享">
              <el-switch v-model="form.is_member_only" />
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item v-if="form.is_member_only" label="等级要求">
              <el-select v-model="form.member_tier" style="width:100%">
                <el-option label="白银" value="silver" />
                <el-option label="黄金" value="gold" />
                <el-option label="铂金" value="platinum" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="自动恢复原价">
          <el-switch v-model="form.auto_recover" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showForm = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveForm">{{ editing ? '保存' : '创建' }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getScheduledPromotions, getScheduledPromotionDetail,
  createScheduledPromotion, updateScheduledPromotion,
  publishScheduledPromotion, pauseScheduledPromotion, deleteScheduledPromotion,
  getPromotionStats, getPromotionCalendar,
} from '@/api/scheduledPromotion'

const loading = ref(false)
const loadingList = ref(false)
const saving = ref(false)
const showForm = ref(false)
const editing = ref(null)
const promotions = ref([])
const calendarEvents = ref([])
const currentMonth = ref(new Date().toISOString().slice(0, 7))
const stats = reactive({ total: 0, active: 0, scheduled: 0, expired: 0, draft: 0, total_budget: 0 })
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const filters = reactive({ status: '', type: '', search: '' })

const typeMap = {
  flash_sale: '限时秒杀', bulk_discount: '批量折扣', bundle: '捆绑销售',
  x_for_y: '买X送Y', free_gift: '赠送礼品', tiered: '阶梯优惠',
}

const form = reactive({
  name: '', type: 'flash_sale', description: '',
  discount_type: 'percentage', discount_value: 0, max_discount: null,
  starts_at: '', ends_at: null,
  usage_limit: null, usage_limit_per_customer: null, budget: null,
  is_first_order_only: false, is_member_only: false, member_tier: null,
  auto_recover: true,
})

let searchTimer = null

function formatNum(v) { return v ? Number(v).toLocaleString() : '0.00' }
function formatDate(d) { return d ? new Date(d).toLocaleDateString('zh-CN') : '—' }

function statusType(s) {
  return { active: 'success', scheduled: 'warning', draft: 'info', paused: 'warning', expired: 'info', cancelled: 'danger' }[s] || 'info'
}
function statusLabel(s) {
  return { active: '进行中', scheduled: '待开始', draft: '草稿', paused: '已暂停', expired: '已结束', cancelled: '已取消' }[s] || s
}

async function loadStats() {
  try { const r = await getPromotionStats(); Object.assign(stats, r.data || {}) } catch {}
}

async function loadCalendar() {
  try { const r = await getPromotionCalendar(currentMonth.value); calendarEvents.value = r.data || [] } catch {}
}

async function loadList(page = 1) {
  loadingList.value = true
  pagination.current_page = page
  try {
    const params = { ...filters, page, per_page: pagination.per_page }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const r = await getScheduledPromotions(params)
    const data = r.data?.data || r.data || []
    promotions.value = Array.isArray(data) ? data : []
    Object.assign(pagination, r.data || r.meta || {})
  } catch { promotions.value = [] }
  finally { loadingList.value = false }
}

function loadAll() { loadStats(); loadCalendar(); loadList() }
function onSearch() { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadList(), 300) }
function onPageChange(p) { loadList(p) }

function changeMonth(dir) {
  const [y, m] = currentMonth.value.split('-').map(Number)
  const d = new Date(y, m - 1 + dir, 1)
  currentMonth.value = d.toISOString().slice(0, 7)
  loadCalendar()
}

function showFormDialog(row) {
  if (row) {
    editing.value = row.id
    Object.assign(form, {
      name: row.name, type: row.type, description: row.description || '',
      discount_type: row.discount_type, discount_value: row.discount_value, max_discount: row.max_discount,
      starts_at: row.starts_at, ends_at: row.ends_at,
      usage_limit: row.usage_limit, usage_limit_per_customer: row.usage_limit_per_customer, budget: row.budget,
      is_first_order_only: row.is_first_order_only, is_member_only: row.is_member_only,
      member_tier: row.member_tier, auto_recover: row.auto_recover !== false,
    })
  } else {
    editing.value = null
    Object.assign(form, {
      name: '', type: 'flash_sale', description: '', discount_type: 'percentage',
      discount_value: 0, max_discount: null, starts_at: '', ends_at: null,
      usage_limit: null, usage_limit_per_customer: null, budget: null,
      is_first_order_only: false, is_member_only: false, member_tier: null, auto_recover: true,
    })
  }
  showForm.value = true
}

async function saveForm() {
  saving.value = true
  try {
    if (editing.value) {
      await updateScheduledPromotion(editing.value, form)
      ElMessage.success('活动已更新')
    } else {
      await createScheduledPromotion(form)
      ElMessage.success('活动已创建')
    }
    showForm.value = false
    loadAll()
  } catch { /* ignore */ }
  finally { saving.value = false }
}

async function publish(row) {
  try { await publishScheduledPromotion(row.id); ElMessage.success('已发布'); loadAll() } catch {}
}

async function pause(row) {
  try { await pauseScheduledPromotion(row.id); ElMessage.success('已暂停'); loadAll() } catch {}
}

async function remove(row) {
  try {
    await ElMessageBox.confirm(`确定删除活动「${row.name}」？`)
    await deleteScheduledPromotion(row.id)
    ElMessage.success('已删除'); loadAll()
  } catch {}
}

onMounted(() => { loadAll() })
</script>

<style scoped>
.scheduled-promotion-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.page-header h2 { margin: 0; font-size: 20px; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.mb-6 { margin-bottom: 24px; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 2px; }
.stat-value { font-size: 20px; font-weight: 700; color: #303133; }
.text-success { color: #67C23A; }
.text-warning { color: #E6A23C; }
.text-info { color: #909399; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.calendar-nav { display: flex; align-items: center; gap: 8px; }
.current-month { font-size: 14px; font-weight: 600; min-width: 80px; text-align: center; }
.calendar-list { display: flex; flex-direction: column; gap: 8px; }
.calendar-event { display: flex; gap: 12px; padding: 10px 12px; border-left: 4px solid; background: #fafafa; border-radius: 4px; }
.event-date { font-size: 13px; color: #606266; min-width: 80px; padding-top: 2px; }
.event-title { font-weight: 600; font-size: 14px; }
.event-meta { display: flex; gap: 8px; align-items: center; margin-top: 4px; }
.event-end { font-size: 12px; color: #909399; }
</style>
