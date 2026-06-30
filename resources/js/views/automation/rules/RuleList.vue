<template>
  <div>
    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6" v-for="stat in stats" :key="stat.label">
        <el-card shadow="hover" class="stat-card" @click="stat.click ? stat.click() : null">
          <div class="stat-label">{{ stat.label }}</div>
          <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 过滤 & 操作栏 -->
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12" justify="space-between" align="middle">
        <el-col :span="16">
          <el-space wrap>
            <el-input v-model="filters.search" placeholder="搜索规则名称..." clearable style="width:240px"
              @clear="fetchRules" @keyup.enter="fetchRules" />
            <el-select v-model="filters.status" placeholder="状态" clearable style="width:120px" @change="fetchRules">
              <el-option label="全部" value="" />
              <el-option label="启用" value="active" />
              <el-option label="暂停" value="paused" />
              <el-option label="草稿" value="draft" />
              <el-option label="归档" value="archived" />
            </el-select>
            <el-select v-model="filters.category" placeholder="分类" clearable style="width:130px" @change="fetchRules">
              <el-option label="全部" value="" />
              <el-option v-for="c in ruleCategories" :key="c.value" :label="c.label" :value="c.value" />
            </el-select>
            <el-select v-model="filters.trigger_type" placeholder="触发器" clearable style="width:130px" @change="fetchRules">
              <el-option label="全部" value="" />
              <el-option label="事件" value="event" />
              <el-option label="定时" value="schedule" />
              <el-option label="Webhook" value="webhook" />
              <el-option label="条件" value="condition" />
            </el-select>
          </el-space>
        </el-col>
        <el-col :span="8" class="text-right">
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon> 新建规则
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 规则表格 -->
    <el-card shadow="never">
      <el-table :data="rules" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="name" label="规则名称" min-width="180">
          <template #default="{ row }">
            <div class="rule-name">
              <el-tag size="small" :type="categoryTagType(row.category)" class="mr-2">{{ categoryLabel(row.category) }}</el-tag>
              <span class="font-medium">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="trigger_type" label="触发器" width="100">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ triggerLabel(row.trigger_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="动作数" width="80" align="center">
          <template #default="{ row }">{{ row.actions?.length ?? 0 }}</template>
        </el-table-column>
        <el-table-column label="执行计数" width="160">
          <template #default="{ row }">
            <el-progress :percentage="execPercent(row)" :stroke-width="12" striped>
              <span class="text-xs">{{ row.success_count }}/{{ row.execution_count }}</span>
            </el-progress>
          </template>
        </el-table-column>
        <el-table-column prop="priority" label="优先级" width="80" align="center" />
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-switch :model-value="row.status === 'active'" :loading="toggling === row.id"
              @click="toggleRule(row)" active-text="启用" inactive-text="暂停" />
          </template>
        </el-table-column>
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" type="primary" link @click="viewRule(row)">详情</el-button>
              <el-button size="small" link @click="editRule(row)">编辑</el-button>
              <el-button size="small" link @click="manualExecute(row)" :disabled="row.status !== 'active'">执行</el-button>
              <el-popconfirm title="确定删除此规则？" @confirm="deleteRule(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>删除</el-button>
                </template>
              </el-popconfirm>
            </el-space>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 flex justify-end" v-if="total > perPage">
        <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
          layout="sizes, prev, pager, next" @current-change="page => fetchRules(page)" @size-change="s => { perPage = s; fetchRules() }" />
      </div>
    </el-card>

    <!-- 规则对话框 (创建/编辑) -->
    <RuleDialog ref="ruleDialogRef" :categories="ruleCategories" :triggerTypes="triggerTypes"
      :availableActions="availableActions" :webhooks="webhookList" @saved="fetchRules" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import RuleDialog from './RuleDialog.vue'

const emit = defineEmits(['view-rule'])

const loading = ref(false)
const toggling = ref(null)
const rules = ref([])
const total = ref(0)
const perPage = ref(20)
const ruleCategories = [
  { value: 'license', label: 'License' },
  { value: 'billing', label: '账单' },
  { value: 'customer', label: '客户' },
  { value: 'security', label: '安全' },
  { value: 'system', label: '系统' },
  { value: 'custom', label: '自定义' },
]
const availableActions = ref([])
const webhookList = ref([])
const triggerTypes = ref([])

const filters = reactive({ search: '', status: '', category: '', trigger_type: '' })

const stats = computed(() => [
  { label: '规则总数', value: rules.value.length, color: 'text-blue-500' },
  { label: '已启用', value: rules.value.filter(r => r.status === 'active').length, color: 'text-green-500' },
  { label: '最近执行', value: '—', color: 'text-purple-500' },
  { label: '失败规则', value: rules.value.filter(r => r.failure_count > 0).length, color: 'text-red-500' },
])

const ruleDialogRef = ref(null)
function categoryTagType(cat) {
  return { license: 'success', billing: 'warning', customer: 'primary', security: 'danger', system: 'info', custom: '' }[cat] || ''
}
function categoryLabel(cat) { return ruleCategories.find(c => c.value === cat)?.label || cat }
function triggerLabel(t) { return { event: '事件', schedule: '定时', webhook: 'Webhook', condition: '条件' }[t] || t }
function execPercent(row) {
  if (!row.execution_count) return 0
  return Math.round((row.success_count / row.execution_count) * 100)
}

async function fetchRules(page = 1) {
  loading.value = true
  try {
    const { data } = await api.getRules({ ...filters, page, per_page: perPage.value })
    if (data.data) {
      rules.value = data.data
      total.value = data.total ?? 0
    } else {
      rules.value = data ?? []
      total.value = 0
    }
  } catch (e) {
    ElMessage.error('获取规则列表失败')
  } finally {
    loading.value = false
  }
}

async function loadReferenceData() {
  try {
    const [actRes, trigRes, webRes] = await Promise.all([
      api.availableActions(),
      api.triggers(),
      api.getWebhooks(),
    ])
    availableActions.value = actRes.data ?? actRes ?? []
    triggerTypes.value = trigRes.data ?? trigRes ?? []
    webhookList.value = webRes.data ?? webRes ?? []
  } catch (e) { /* ignore */ }
}

function openCreateDialog() {
  ruleDialogRef.value?.open('create')
}

function editRule(row) {
  ruleDialogRef.value?.open('edit', row)
}

function viewRule(row) {
  emit('view-rule', row)
}

async function toggleRule(row) {
  toggling.value = row.id
  try {
    await api.toggleRule(row.id)
    row.status = row.status === 'active' ? 'paused' : 'active'
    ElMessage.success(row.status === 'active' ? '规则已启用' : '规则已暂停')
  } catch (e) {
    ElMessage.error('操作失败')
  } finally {
    toggling.value = null
  }
}

async function deleteRule(row) {
  try {
    await api.deleteRule(row.id)
    ElMessage.success('规则已删除')
    fetchRules()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

function manualExecute(row) {
  ElMessageBox.prompt('输入上下文数据(JSON，可选)', '手动执行规则', {
    inputType: 'textarea',
    inputPlaceholder: '{}',
  }).then(async ({ value }) => {
    try {
      const context = value ? JSON.parse(value) : {}
      await api.executeRule(row.id, context)
      ElMessage.success('规则已触发执行')
    } catch (e) {
      ElMessage.error(e.response?.data?.message || '执行失败')
    }
  }).catch(() => {})
}

onMounted(() => {
  fetchRules()
  loadReferenceData()
})
</script>

<style scoped>
.stat-card { cursor: pointer; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 8px; }
.stat-value { font-size: 28px; font-weight: 700; }
.rule-name { display: flex; align-items: center; }
.font-medium { font-weight: 500; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-xs { font-size: 12px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
:deep(.el-progress__text) { font-size: 12px !important; }
</style>
