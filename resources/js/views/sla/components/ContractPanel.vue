<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('contract_panel.total', { n: total }) }}</span>
      <el-button type="primary" @click="showCreate">{{ t('contract_panel.create') }}</el-button>
    </div>

    <el-table :data="contracts" stripe v-loading="loading" @row-click="selectContract" style="cursor: pointer;">
      <el-table-column :label="t('contract_panel.cols.name')" prop="name" min-width="160" />
      <el-table-column :label="t('contract_panel.cols.level')" prop="level" width="90">
        <template #default="{ row }">
          <el-tag :type="tagType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('contract_panel.cols.customer')" prop="customer?.name" width="140" />
      <el-table-column :label="t('contract_panel.cols.effective')" prop="effective_date" width="120" />
      <el-table-column :label="t('contract_panel.cols.expiry')" prop="expiry_date" width="120">
        <template #default="{ row }">{{ row.expiry_date || t('contract_panel.permanent') }}</template>
      </el-table-column>
      <el-table-column :label="t('contract_panel.cols.status')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('contract_panel.active') : t('contract_panel.inactive') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('contract_panel.cols.actions')" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click.stop="edit(row)">{{ t('actions.edit') }}</el-button>
          <el-button size="small" @click.stop="showMetrics(row)">{{ t('contract_panel.metrics') }}</el-button>
          <el-popconfirm :title="t('contract_panel.confirm_delete')" @confirm.stop="remove(row)">
            <template #reference>
              <el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <MetricListDialog v-model:visible="metricDialog.visible" :contract="metricDialog.contract"
      @saved="emit('refresh')" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getContracts, deleteContract } from '../../../api/sla'
import MetricListDialog from './MetricListDialog.vue'

const { t } = useI18n()
const emit = defineEmits(['select'])

const contracts = ref([])
const total = ref(0)
const loading = ref(false)
const metricDialog = ref({ visible: false, contract: null })

defineExpose({ refresh: loadContracts })

function tagType(level) {
  return { standard: '', premium: 'success', enterprise: 'warning', custom: 'info' }[level] || ''
}
function levelLabel(level) {
  const key = { standard: 'standard', premium: 'premium', enterprise: 'enterprise', custom: 'custom' }[level]
  return key ? t(`contract_panel.levels.${key}`) : level
}

function selectContract(row) {
  emit('select', row)
}

function showCreate() {
  emit('select', null)
}

function edit(contract) {
  emit('select', { ...contract })
}

function showMetrics(contract) {
  metricDialog.value.contract = contract
  metricDialog.value.visible = true
}

async function remove(contract) {
  try {
    await deleteContract(contract.id)
    ElMessage.success(t('contract_panel.messages.deleted'))
    await loadContracts()
  } catch (e) {
    ElMessage.error(t('contract_panel.messages.delete_failed'))
  }
}

async function loadContracts() {
  loading.value = true
  try {
    const { data } = await getContracts()
    contracts.value = Array.isArray(data) ? data : data?.data || []
    total.value = contracts.value.length
  } catch (e) {
    ElMessage.error(t('contract_panel.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

onMounted(loadContracts)
</script>
