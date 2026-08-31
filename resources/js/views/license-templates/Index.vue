<template>
  <div class="license-templates">
    <h2 class="mb-4">{{ t('license_templates_page.title') }}</h2>

    <el-row :gutter="20" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value">{{ templates.length }}</div><div class="stat-label">{{ t('license_templates_page.stats.templates') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-success">{{ activeCount }}</div><div class="stat-label">{{ t('license_templates_page.stats.active') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-primary">{{ batchTasks.length }}</div><div class="stat-label">{{ t('license_templates_page.stats.batch_tasks') }}</div></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card"><div class="stat-value text-success">{{ totalGenerated }}</div><div class="stat-label">{{ t('license_templates_page.stats.generated') }}</div></div>
        </el-card>
      </el-col>
    </el-row>

    <el-tabs v-model="activeTab" type="border-card">
      <el-tab-pane :label="t('license_templates_page.tabs.templates')" name="templates">
        <div class="mb-3 flex items-center justify-between">
          <span class="text-sm text-gray-500">{{ t('license_templates_page.templates_hint') }}</span>
          <el-button type="primary" size="small" @click="showCreateTemplate">{{ t('license_templates_page.new_template') }}</el-button>
        </div>
        <el-table :data="templates" stripe v-loading="loading" @row-click="editTemplate" style="cursor:pointer">
          <el-table-column :label="t('license_templates_page.cols.name')" prop="name" min-width="160" />
          <el-table-column :label="t('license_templates_page.cols.product')" prop="product?.name" width="120" />
          <el-table-column :label="t('license_templates_page.cols.type')" prop="type" width="90">
            <template #default="{ row }"><el-tag size="small">{{ typeLabel(row.type) }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.seats')" prop="seats" width="70" />
          <el-table-column :label="t('license_templates_page.cols.devices')" prop="max_devices" width="70" />
          <el-table-column :label="t('license_templates_page.cols.expiry')" prop="expiry_days" width="90">
            <template #default="{ row }">{{ row.expiry_days ? t('license_templates_page.days_n', { n: row.expiry_days }) : t('license_templates_page.permanent') }}</template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.status')" width="70">
            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag></template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.actions')" width="200" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click.stop="editTemplate(row)">{{ t('actions.edit') }}</el-button>
              <el-button size="small" type="success" @click.stop="showBatchGenerate(row)">{{ t('license_templates_page.batch_generate') }}</el-button>
              <el-popconfirm :title="t('license_templates_page.confirm_delete')" @confirm.stop="removeTemplate(row)">
                <template #reference><el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <el-tab-pane :label="t('license_templates_page.tabs.batch')" name="batch">
        <div class="mb-3 text-sm text-gray-500">{{ t('license_templates_page.batch_hint') }}</div>
        <el-table :data="batchTasks" stripe v-loading="batchLoading">
          <el-table-column :label="t('license_templates_page.cols.task_name')" prop="name" min-width="160" />
          <el-table-column :label="t('license_templates_page.cols.template')" prop="template?.name" width="140" />
          <el-table-column :label="t('license_templates_page.cols.total')" prop="total_count" width="70" />
          <el-table-column :label="t('license_templates_page.cols.success')" prop="success_count" width="70">
            <template #default="{ row }"><span class="text-success">{{ row.success_count }}</span></template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.failed')" prop="failed_count" width="70">
            <template #default="{ row }"><span class="text-danger">{{ row.failed_count }}</span></template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.status')" prop="status" width="100">
            <template #default="{ row }">
              <el-tag :type="batchStatusTag(row.status)" size="small">{{ batchStatusLabel(row.status) }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column :label="t('license_templates_page.cols.operator')" prop="user?.name" width="100" />
          <el-table-column :label="t('license_templates_page.cols.completed_at')" prop="completed_at" width="160" />
          <el-table-column :label="t('license_templates_page.cols.actions')" width="120" fixed="right">
            <template #default="{ row }">
              <el-button size="small" @click.stop="showBatchDetail(row)">{{ t('actions.view_details') }}</el-button>
              <el-popconfirm :title="t('license_templates_page.confirm_delete')" @confirm.stop="removeBatchTask(row)">
                <template #reference><el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
              </el-popconfirm>
            </template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <TemplateDialog v-model:visible="templateDialog.visible" :template-data="templateDialog.data" @saved="loadTemplates" />
    <BatchGenerateDialog v-model:visible="batchDialog.visible" :template="batchDialog.template" @saved="loadBatchTasks" />
    <BatchDetailDialog v-model:visible="detailDialog.visible" :task-id="detailDialog.taskId" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getLicenseTemplates, deleteLicenseTemplate, getBatchTasks, deleteBatchTask } from '../../api/licenseTemplate'
import TemplateDialog from './components/TemplateDialog.vue'
import BatchGenerateDialog from './components/BatchGenerateDialog.vue'
import BatchDetailDialog from './components/BatchDetailDialog.vue'

const { t } = useI18n()

const activeTab = ref('templates')
const templates = ref([])
const batchTasks = ref([])
const loading = ref(false)
const batchLoading = ref(false)

const templateDialog = ref({ visible: false, data: null })
const batchDialog = ref({ visible: false, template: null })
const detailDialog = ref({ visible: false, taskId: null })

const activeCount = computed(() => templates.value.filter(tpl => tpl.is_active).length)
const totalGenerated = computed(() => batchTasks.value.reduce((s, task) => s + task.success_count, 0))

function typeLabel(type) {
  const key = { trial: 'trial', standard: 'standard', enterprise: 'enterprise', development: 'development' }[type]
  return key ? t(`license_templates_page.types.${key}`) : type
}
function batchStatusTag(s) { return { pending: 'info', processing: 'warning', completed: 'success', failed: 'danger', partial: 'warning' }[s] || 'info' }
function batchStatusLabel(s) {
  const key = { pending: 'pending', processing: 'processing', completed: 'completed', failed: 'failed', partial: 'partial' }[s]
  return key ? t(`license_templates_page.batch_statuses.${key}`) : s
}

function showCreateTemplate() { templateDialog.value.data = null; templateDialog.value.visible = true }
function editTemplate(row) { templateDialog.value.data = { ...row }; templateDialog.value.visible = true }
function showBatchGenerate(template) { batchDialog.value.template = template; batchDialog.value.visible = true }
function showBatchDetail(task) { detailDialog.value.taskId = task.id; detailDialog.value.visible = true }

async function removeTemplate(row) {
  try {
    await deleteLicenseTemplate(row.id)
    ElMessage.success(t('license_templates_page.messages.deleted'))
    await loadTemplates()
  } catch (e) { ElMessage.error(t('license_templates_page.messages.delete_failed')) }
}

async function removeBatchTask(row) {
  try {
    await deleteBatchTask(row.id)
    ElMessage.success(t('license_templates_page.messages.deleted'))
    await loadBatchTasks()
  } catch (e) { ElMessage.error(t('license_templates_page.messages.delete_failed')) }
}

async function loadTemplates() {
  loading.value = true
  try {
    const { data } = await getLicenseTemplates()
    templates.value = Array.isArray(data) ? data : data?.data || []
  } catch (e) { ElMessage.error(t('license_templates_page.messages.load_failed')) } finally { loading.value = false }
}

async function loadBatchTasks() {
  batchLoading.value = true
  try {
    const { data } = await getBatchTasks()
    batchTasks.value = Array.isArray(data) ? data : data?.data || []
  } catch (e) { console.error(e) } finally { batchLoading.value = false }
}

onMounted(() => {
  loadTemplates()
  loadBatchTasks()
})
</script>

<style scoped>
.stat-card { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.text-success { color: #67c23a !important; }
.text-danger { color: #f56c6c !important; }
.text-primary { color: #0f172a !important; }
</style>
