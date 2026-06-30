<template>
  <div>
    <el-row :gutter="12" class="mb-4 justify-end">
      <el-col :span="24" class="text-right">
        <el-button type="primary" size="small" @click="openCreate">
          <el-icon><Plus /></el-icon> 新建策略
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="policies" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" label="策略名称" min-width="150" />
      <el-table-column label="类型" width="100">
        <template #default="{ row }">
          <el-tag size="small" :type="typeTag(row.type)">{{ typeLabel(row.type) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="归档(天)" width="110" align="center">
        <template #default="{ row }">{{ row.archive_after_days }}天后</template>
      </el-table-column>
      <el-table-column label="清理(天)" width="110" align="center">
        <template #default="{ row }">{{ row.delete_after_days }}天后</template>
      </el-table-column>
      <el-table-column label="存储" width="100" align="center">
        <template #default="{ row }">{{ row.archive_disk || 'local' }}</template>
      </el-table-column>
      <el-table-column label="压缩" width="70" align="center">
        <template #default="{ row }">
          <el-tag :type="row.compress_archive ? 'success' : 'info'" size="small">
            {{ row.compress_archive ? '是' : '否' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="执行次数" width="80" align="center" prop="execution_count" />
      <el-table-column label="状态" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
            {{ row.is_active ? '启用' : '停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="操作" width="200" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link @click="editPolicy(row)">编辑</el-button>
            <el-popconfirm title="删除此策略？" @confirm="deletePolicy(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
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
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getArchivePolicies, deleteArchivePolicy } from '../../../api/auditExport'
import ArchivePolicyDialog from './ArchivePolicyDialog.vue'

const policies = ref([])
const loading = ref(false)
const dialogRef = ref(null)

function typeTag(t) { return { audit: '', security: 'danger', error: 'warning', system: 'info' }[t] || '' }
function typeLabel(t) { return { audit: '审计', security: '安全', error: '错误', system: '系统' }[t] || t }

async function fetchPolicies() {
  loading.value = true
  try {
    const { data } = await getArchivePolicies()
    policies.value = data || []
  } catch (e) {
    ElMessage.error('获取归档策略失败')
  } finally {
    loading.value = false
  }
}

function openCreate() { dialogRef.value?.open('create') }
function editPolicy(row) { dialogRef.value?.open('edit', row) }

async function deletePolicy(row) {
  try {
    await deleteArchivePolicy(row.id)
    ElMessage.success('策略已删除')
    fetchPolicies()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

onMounted(() => fetchPolicies())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-right { text-align: right; }
</style>
