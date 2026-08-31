<template>
  <div>
    <el-row :gutter="12" class="mb-4 justify-end">
      <el-col :span="24" class="text-right">
        <el-button type="primary" size="small" @click="openCreate">
          <el-icon><Plus /></el-icon> {{ t('archive_policy_list.create') }}
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="policies" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" :label="t('archive_policy_list.cols.name')" min-width="150" />
      <el-table-column :label="t('archive_policy_list.cols.type')" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="typeTag(row.type)">{{ typeLabel(row.type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.archive_days')" width="110" align="center">
        <template #default="{ row }">{{ t('archive_policy_list.days_later', { n: row.archive_after_days }) }}</template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.delete_days')" width="110" align="center">
        <template #default="{ row }">{{ t('archive_policy_list.days_later', { n: row.delete_after_days }) }}</template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.disk')" width="100" align="center">
        <template #default="{ row }">{{ row.archive_disk || 'local' }}</template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.compress')" width="70" align="center">
        <template #default="{ row }">
          <el-tag :type="row.compress_archive ? 'success' : 'info'" size="small">
            {{ row.compress_archive ? t('archive_policy_list.yes') : t('archive_policy_list.no') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.executions')" width="80" align="center" prop="execution_count" />
      <el-table-column :label="t('archive_policy_list.cols.status')" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
            {{ row.is_active ? t('archive_policy_list.active') : t('archive_policy_list.inactive') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('archive_policy_list.cols.actions')" width="200" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editPolicy(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('archive_policy_list.confirm_delete')" @confirm="deletePolicy(row)">
              <template #reference>
                <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <ArchivePolicyDialog ref="dialogRef" @saved="fetchPolicies" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getArchivePolicies, deleteArchivePolicy } from '../../../api/auditExport'
import ArchivePolicyDialog from './ArchivePolicyDialog.vue'

const { t } = useI18n()
const policies = ref([])
const loading = ref(false)
const dialogRef = ref(null)

function typeTag(type) { return { audit: '', security: 'danger', error: 'warning', system: 'info' }[type] || '' }
function typeLabel(type) {
  const key = { audit: 'audit', security: 'security', error: 'error', system: 'system' }[type]
  return key ? t(`archive_policy_list.types.${key}`) : type
}

async function fetchPolicies() {
  loading.value = true
  try {
    const { data } = await getArchivePolicies()
    policies.value = data || []
  } catch (e) {
    ElMessage.error(t('archive_policy_list.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

function openCreate() { dialogRef.value?.open('create') }
function editPolicy(row) { dialogRef.value?.open('edit', row) }

async function deletePolicy(row) {
  try {
    await deleteArchivePolicy(row.id)
    ElMessage.success(t('archive_policy_list.messages.deleted'))
    fetchPolicies()
  } catch (e) {
    ElMessage.error(t('archive_policy_list.messages.delete_failed'))
  }
}

onMounted(() => fetchPolicies())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-right { text-align: right; }
</style>
