<template>
  <div>
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6" v-for="stat in stats" :key="stat.label">
        <el-card shadow="hover" class="stat-card" @click="stat.click ? stat.click() : null">
          <div class="stat-label">{{ stat.label }}</div>
          <div class="stat-value" :class="stat.color">{{ stat.value }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12" justify="space-between" align="middle">
        <el-col :span="16">
          <el-space wrap>
            <el-input v-model="filters.search" :placeholder="t('rule_list_page.search_ph')" clearable style="width:240px"
              @clear="fetchRules" @keyup.enter="fetchRules" />
            <el-select v-model="filters.status" :placeholder="t('rule_list_page.cols.status')" clearable style="width:120px" @change="fetchRules">
              <el-option :label="t('rule_list_page.all')" value="" />
              <el-option :label="t('rule_list_page.statuses.active')" value="active" />
              <el-option :label="t('rule_list_page.statuses.paused')" value="paused" />
              <el-option :label="t('rule_list_page.statuses.draft')" value="draft" />
              <el-option :label="t('rule_list_page.statuses.archived')" value="archived" />
            </el-select>
            <el-select v-model="filters.category" :placeholder="t('rule_list_page.cols.category')" clearable style="width:130px" @change="fetchRules">
              <el-option :label="t('rule_list_page.all')" value="" />
              <el-option v-for="c in ruleCategories" :key="c.value" :label="c.label" :value="c.value" />
            </el-select>
            <el-select v-model="filters.trigger_type" :placeholder="t('rule_list_page.cols.trigger')" clearable style="width:130px" @change="fetchRules">
              <el-option :label="t('rule_list_page.all')" value="" />
              <el-option :label="t('rule_list_page.triggers.event')" value="event" />
              <el-option :label="t('rule_list_page.triggers.schedule')" value="schedule" />
              <el-option label="Webhook" value="webhook" />
              <el-option :label="t('rule_list_page.triggers.condition')" value="condition" />
            </el-select>
          </el-space>
        </el-col>
        <el-col :span="8" class="text-right">
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon> {{ t('rule_list_page.new_rule') }}
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-table :data="rules" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="name" :label="t('rule_list_page.cols.name')" min-width="180">
          <template #default="{ row }">
            <div class="rule-name">
              <el-tag size="small" :type="categoryTagType(row.category)" class="mr-2">{{ categoryLabel(row.category) }}</el-tag>
              <span class="font-medium">{{ row.name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="trigger_type" :label="t('rule_list_page.cols.trigger')" width="100">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ triggerLabel(row.trigger_type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('rule_list_page.cols.action_count')" width="80" align="center">
          <template #default="{ row }">{{ row.actions?.length ?? 0 }}</template>
        </el-table-column>
        <el-table-column :label="t('rule_list_page.cols.exec_count')" width="160">
          <template #default="{ row }">
            <el-progress :percentage="execPercent(row)" :stroke-width="12" striped>
              <span class="text-xs">{{ row.success_count }}/{{ row.execution_count }}</span>
            </el-progress>
          </template>
        </el-table-column>
        <el-table-column prop="priority" :label="t('rule_list_page.cols.priority')" width="80" align="center" />
        <el-table-column :label="t('rule_list_page.cols.status')" width="100" align="center">
          <template #default="{ row }">
            <el-switch :model-value="row.status === 'active'" :loading="toggling === row.id"
              @click="toggleRule(row)" :active-text="t('actions.enable')" :inactive-text="t('rule_list_page.statuses.paused')" />
          </template>
        </el-table-column>
        <el-table-column :label="t('rule_list_page.cols.actions')" width="280" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" type="primary" link @click="viewRule(row)">{{ t('actions.view_details') }}</el-button>
              <el-button size="small" link @click="editRule(row)">{{ t('actions.edit') }}</el-button>
              <el-button size="small" link @click="manualExecute(row)" :disabled="row.status !== 'active'">{{ t('rule_list_page.execute') }}</el-button>
              <el-popconfirm :title="t('rule_list_page.delete_confirm')" @confirm="deleteRule(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
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

    <RuleDialog ref="ruleDialogRef" :categories="ruleCategories" :triggerTypes="triggerTypes"
      :availableActions="availableActions" :webhooks="webhookList" @saved="fetchRules" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import RuleDialog from './RuleDialog.vue'

const { t } = useI18n()
const emit = defineEmits(['view-rule'])

const loading = ref(false)
const toggling = ref(null)
const rules = ref([])
const total = ref(0)
const perPage = ref(20)
const ruleCategories = computed(() => [
  { value: 'license', label: 'License' },
  { value: 'billing', label: t('rule_list_page.categories.billing') },
  { value: 'customer', label: t('rule_list_page.categories.customer') },
  { value: 'security', label: t('rule_list_page.categories.security') },
  { value: 'system', label: t('rule_list_page.categories.system') },
  { value: 'custom', label: t('rule_list_page.categories.custom') },
])
const availableActions = ref([])
const webhookList = ref([])
const triggerTypes = ref([])

const filters = reactive({ search: '', status: '', category: '', trigger_type: '' })

const stats = computed(() => [
  { label: t('rule_list_page.stats.total'), value: rules.value.length, color: 'text-blue-500' },
  { label: t('rule_list_page.stats.active'), value: rules.value.filter(r => r.status === 'active').length, color: 'text-green-500' },
  { label: t('rule_list_page.stats.recent'), value: '—', color: 'text-purple-500' },
  { label: t('rule_list_page.stats.failed'), value: rules.value.filter(r => r.failure_count > 0).length, color: 'text-red-500' },
])

const ruleDialogRef = ref(null)
function categoryTagType(cat) {
  return { license: 'success', billing: 'warning', customer: 'primary', security: 'danger', system: 'info', custom: '' }[cat] || ''
}
function categoryLabel(cat) { return ruleCategories.value.find(c => c.value === cat)?.label || cat }
function triggerLabel(type) {
  const key = { event: 'event', schedule: 'schedule', webhook: 'webhook', condition: 'condition' }[type]
  return key ? t(`rule_list_page.triggers.${key}`) : type
}
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
    ElMessage.error(t('rule_list_page.messages.load_failed'))
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
    ElMessage.success(row.status === 'active' ? t('rule_list_page.messages.enabled') : t('rule_list_page.messages.paused'))
  } catch (e) {
    ElMessage.error(t('messages.failed'))
  } finally {
    toggling.value = null
  }
}

async function deleteRule(row) {
  try {
    await api.deleteRule(row.id)
    ElMessage.success(t('rule_list_page.messages.deleted'))
    fetchRules()
  } catch (e) {
    ElMessage.error(t('rule_list_page.messages.delete_failed'))
  }
}

function manualExecute(row) {
  ElMessageBox.prompt(t('rule_list_page.execute_prompt'), t('rule_list_page.execute_title'), {
    inputType: 'textarea',
    inputPlaceholder: '{}',
  }).then(async ({ value }) => {
    try {
      const context = value ? JSON.parse(value) : {}
      await api.executeRule(row.id, context)
      ElMessage.success(t('rule_list_page.messages.executed'))
    } catch (e) {
      ElMessage.error(e.response?.data?.message || t('rule_list_page.messages.execute_failed'))
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
