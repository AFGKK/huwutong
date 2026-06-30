<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 个合约</span>
      <el-button type="primary" @click="showCreate">新建合约</el-button>
    </div>

    <el-table :data="contracts" stripe v-loading="loading" @row-click="selectContract" style="cursor: pointer;">
      <el-table-column label="名称" prop="name" min-width="160" />
      <el-table-column label="级别" prop="level" width="90">
        <template #default="{ row }">
          <el-tag :type="tagType(row.level)" size="small">{{ levelLabel(row.level) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="客户" prop="customer?.name" width="140" />
      <el-table-column label="发布日期" prop="effective_date" width="120" />
      <el-table-column label="到期日" prop="expiry_date" width="120">
        <template #default="{ row }">{{ row.expiry_date || '永久' }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="180" fixed="right">
        <template #default="{ row }">
          <el-button size="small" @click.stop="edit(row)">编辑</el-button>
          <el-button size="small" @click.stop="showMetrics(row)">指标</el-button>
          <el-popconfirm title="确认删除此 SLA 合约？" @confirm.stop="remove(row)">
            <template #reference>
              <el-button size="small" type="danger" @click.stop>删除</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <!-- 指标对话框 -->
    <MetricListDialog v-model:visible="metricDialog.visible" :contract="metricDialog.contract"
      @saved="emit('refresh')" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getContracts, deleteContract } from '../../../api/sla'
import MetricListDialog from './MetricListDialog.vue'

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
  return { standard: '标准', premium: '高级', enterprise: '企业', custom: '自定义' }[level] || level
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
    ElMessage.success('已删除')
    await loadContracts()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

async function loadContracts() {
  loading.value = true
  try {
    const { data } = await getContracts()
    contracts.value = Array.isArray(data) ? data : data?.data || []
    total.value = contracts.value.length
  } catch (e) {
    ElMessage.error('加载 SLA 合约失败')
  } finally {
    loading.value = false
  }
}

onMounted(loadContracts)
</script>
