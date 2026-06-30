<template>
  <div>
    <div class="flex items-center justify-between mb-3">
      <span class="text-sm text-gray-500">共 {{ total }} 条记录</span>
      <div>
        <el-select v-model="filters.severity" placeholder="严重程度" clearable size="small" style="width:120px"
          @change="load" class="mr-2">
          <el-option label="轻微" value="minor" />
          <el-option label="严重" value="major" />
          <el-option label="紧急" value="critical" />
        </el-select>
        <el-select v-model="filters.status" placeholder="状态" clearable size="small" style="width:120px" @change="load"
          class="mr-2">
          <el-option label="待处理" value="open" />
          <el-option label="已确认" value="acknowledged" />
          <el-option label="已解决" value="resolved" />
          <el-option label="已升级" value="escalated" />
        </el-select>
        <el-button size="small" @click="load" type="primary">刷新</el-button>
      </div>
    </div>

    <el-table :data="breaches" stripe v-loading="loading">
      <el-table-column label="合约" prop="contract?.name" min-width="140" />
      <el-table-column label="类型" prop="breach_type" width="110">
        <template #default="{ row }">{{ typeLabels[row.breach_type] || row.breach_type }}</template>
      </el-table-column>
      <el-table-column label="严重程度" prop="severity" width="90">
        <template #default="{ row }">
          <el-tag :type="severityTag(row.severity)" size="small">{{ severityLabels[row.severity] }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="描述" prop="description" min-width="200" show-overflow-tooltip />
      <el-table-column label="预期/实际" width="130">
        <template #default="{ row }">
          {{ row.expected_value }} vs {{ row.actual_value }}
        </template>
      </el-table-column>
      <el-table-column label="状态" prop="status" width="90">
        <template #default="{ row }">
          <el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="时间" prop="created_at" width="160" />
      <el-table-column label="操作" width="140" fixed="right">
        <template #default="{ row }">
          <el-button v-if="row.status === 'open'" size="small" @click="acknowledge(row)">确认</el-button>
          <el-button v-if="['open', 'acknowledged'].includes(row.status)" size="small" type="success"
            @click="showResolve(row)">解决</el-button>
        </template>
      </el-table-column>
    </el-table>

    <!-- 分页 -->
    <div class="flex justify-center mt-3" v-if="pagination.total > pagination.per_page">
      <el-pagination background layout="prev, pager, next" v-model:current-page="pagination.page"
        :page-size="pagination.per_page" :total="pagination.total" @current-change="load" />
    </div>

    <!-- 解决对话框 -->
    <el-dialog v-model="resolveDialog.visible" title="解决违约" width="400px">
      <el-input v-model="resolveDialog.notes" type="textarea" :rows="3" placeholder="解决备注（可选）" />
      <template #footer>
        <el-button @click="resolveDialog.visible = false">取消</el-button>
        <el-button type="primary" :loading="resolving" @click="confirmResolve">确认解决</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getBreaches, acknowledgeBreach, resolveBreach } from '../../../api/sla'

const breaches = ref([])
const total = ref(0)
const loading = ref(false)
const resolving = ref(false)

const filters = reactive({ severity: '', status: '' })
const pagination = reactive({ page: 1, per_page: 50, total: 0 })
const resolveDialog = reactive({ visible: false, breach: null, notes: '' })

const typeLabels = { response_time: '响应时间', resolution_time: '解决时间', uptime: '正常运行', availability: '可用性' }
const severityLabels = { minor: '轻微', major: '严重', critical: '紧急' }
const statusLabels = { open: '待处理', acknowledged: '已确认', resolved: '已解决', escalated: '已升级' }

function severityTag(s) {
  return { minor: 'info', major: 'warning', critical: 'danger' }[s] || 'info'
}
function statusTag(s) {
  return { open: 'danger', acknowledged: 'warning', resolved: 'success', escalated: 'info' }[s] || 'info'
}

async function acknowledge(breach) {
  try {
    await acknowledgeBreach(breach.id)
    ElMessage.success('已确认')
    await load()
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

function showResolve(breach) {
  resolveDialog.breach = breach
  resolveDialog.notes = ''
  resolveDialog.visible = true
}

async function confirmResolve() {
  resolving.value = true
  try {
    await resolveBreach(resolveDialog.breach.id, resolveDialog.notes)
    ElMessage.success('已解决')
    resolveDialog.visible = false
    await load()
  } catch (e) {
    ElMessage.error('操作失败')
  } finally {
    resolving.value = false
  }
}

async function load() {
  loading.value = true
  try {
    const params = { page: pagination.page, per_page: pagination.per_page, ...filters }
    const { data } = await getBreaches(params)
    const list = Array.isArray(data) ? data : data?.data || []
    breaches.value = list
    pagination.total = data?.total || list.length
    total.value = pagination.total
  } catch (e) {
    ElMessage.error('加载违约记录失败')
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
