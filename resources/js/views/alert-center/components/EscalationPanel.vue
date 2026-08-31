<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">{{ t('escalation_panel.total', { n: total }) }}</span>
      <el-button type="primary" size="small" @click="showCreate">{{ t('escalation_panel.create') }}</el-button>
    </div>
    <el-table :data="escalations" stripe v-loading="loading">
      <el-table-column :label="t('escalation_panel.cols.name')" prop="name" min-width="160" />
      <el-table-column :label="t('escalation_panel.cols.level')" prop="escalation_level" width="90">
        <template #default="{ row }">Lv.{{ row.escalation_level }}</template>
      </el-table-column>
      <el-table-column :label="t('escalation_panel.cols.after_minutes')" prop="after_minutes" width="100" />
      <el-table-column :label="t('escalation_panel.cols.notify_type')" prop="notify_type" width="100" />
      <el-table-column :label="t('escalation_panel.cols.rule')" prop="alert_rule_id" width="100">
        <template #default="{ row }">{{ row.alert_rule_id ? t('escalation_panel.specific_rule') : t('escalation_panel.global') }}</template>
      </el-table-column>
      <el-table-column :label="t('escalation_panel.cols.action')" width="100">
        <template #default="{ row }">{{ escalationActionLabel(row.escalate_action) }}</template>
      </el-table-column>
      <el-table-column :label="t('escalation_panel.cols.status')" width="70">
        <template #default="{ row }"><el-tag :type="row.is_enabled ? 'success' : 'danger'" size="small">{{ row.is_enabled ? t('escalation_panel.enabled') : t('escalation_panel.disabled') }}</el-tag></template>
      </el-table-column>
      <el-table-column :label="t('escalation_panel.cols.actions')" width="120" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">{{ t('actions.edit') }}</el-button>
          <el-popconfirm :title="t('escalation_panel.confirm_delete')" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>{{ t('actions.delete') }}</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <EscalationDialog v-model:visible="dialog.visible" :escalation="dialog.escalation" @saved="load" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { getEscalations, deleteEscalation } from '@/api/alerting'
import EscalationDialog from './EscalationDialog.vue'

const { t } = useI18n()
const escalations = ref([])
const total = ref(0)
const loading = ref(false)
const dialog = reactive({ visible: false, escalation: null })

function escalationActionLabel(a) {
  const key = { notify_admin: 'notify_admin', create_ticket: 'create_ticket', run_webhook: 'run_webhook' }[a]
  return key ? t(`escalation_panel.action_types.${key}`) : (a || '-')
}

function showCreate() { dialog.escalation = null; dialog.visible = true }
function edit(esc) { dialog.escalation = { ...esc }; dialog.visible = true }

async function remove(esc) {
  try {
    await deleteEscalation(esc.id)
    ElMessage.success(t('escalation_panel.messages.deleted'))
    await load()
  } catch (e) { ElMessage.error(t('escalation_panel.messages.delete_failed')) }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getEscalations()
    escalations.value = Array.isArray(data) ? data : data?.data || []
    total.value = escalations.value.length
  } catch (e) { ElMessage.error(t('escalation_panel.messages.load_failed')) } finally { loading.value = false }
}

onMounted(load)
</script>
