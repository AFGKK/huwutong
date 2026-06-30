<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 个升级策略</span>
      <el-button type="primary" size="small" @click="showCreate">新建策略</el-button>
    </div>
    <el-table :data="escalations" stripe v-loading="loading">
      <el-table-column label="名称" prop="name" min-width="160" />
      <el-table-column label="升级级别" prop="escalation_level" width="90">
        <template #default="{ row }">Lv.{{ row.escalation_level }}</template>
      </el-table-column>
      <el-table-column label="延迟(分钟)" prop="after_minutes" width="100" />
      <el-table-column label="通知方式" prop="notify_type" width="100" />
      <el-table-column label="关联规则" prop="alert_rule_id" width="100">
        <template #default="{ row }">{{ row.alert_rule_id ? '指定规则' : '全局' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="100">
        <template #default="{ row }">{{ escalationActionLabel(row.escalate_action) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="70">
        <template #default="{ row }"><el-tag :type="row.is_enabled ? 'success' : 'danger'" size="small">{{ row.is_enabled ? '启用' : '停用' }}</el-tag></template>
      </el-table-column>
      <el-table-column label="操作" width="120" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click="edit(row)">编辑</el-button>
          <el-popconfirm title="确认删除？" @confirm="remove(row)">
            <template #reference><el-button size="small" type="danger" @click.stop>删除</el-button></template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <EscalationDialog v-model:visible="dialog.visible" :escalation="dialog.escalation" @saved="load" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getEscalations, deleteEscalation } from '../../../api/alerting'
import EscalationDialog from './EscalationDialog.vue'

const escalations = ref([])
const total = ref(0)
const loading = ref(false)
const dialog = reactive({ visible: false, escalation: null })

function escalationActionLabel(a) { return { notify_admin: '通知管理员', create_ticket: '创建工单', run_webhook: '调用Webhook' }[a] || a || '-' }

function showCreate() { dialog.escalation = null; dialog.visible = true }
function edit(esc) { dialog.escalation = { ...esc }; dialog.visible = true }

async function remove(esc) {
  try {
    await deleteEscalation(esc.id)
    ElMessage.success('已删除')
    await load()
  } catch (e) { ElMessage.error('删除失败') }
}

async function load() {
  loading.value = true
  try {
    const { data } = await getEscalations()
    escalations.value = Array.isArray(data) ? data : data?.data || []
    total.value = escalations.value.length
  } catch (e) { ElMessage.error('加载升级策略失败') } finally { loading.value = false }
}

onMounted(load)
</script>
