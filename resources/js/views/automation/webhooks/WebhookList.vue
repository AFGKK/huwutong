<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row justify="space-between" align="middle">
        <el-col :span="12"><span class="text-lg font-medium">Webhook 端点</span></el-col>
        <el-col :span="12" class="text-right">
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon> 新建 Webhook
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-table :data="webhooks" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="name" label="名称" min-width="140" />
        <el-table-column prop="url" label="URL" min-width="280">
          <template #default="{ row }">
            <span class="text-xs break-all">{{ row.url }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="method" label="方法" width="80" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.method || 'POST' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="调用统计" width="160">
          <template #default="{ row }">
            <span class="text-green-500">✓{{ row.success_count }}</span>
            <span class="text-red-500 ml-2">✗{{ row.failure_count }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="is_active" label="状态" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '禁用' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="240" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" link @click="testWebhook(row)" :loading="testing === row.id">测试</el-button>
              <el-button size="small" link type="primary" @click="editWebhook(row)">编辑</el-button>
              <el-popconfirm title="确定删除此 Webhook？" @confirm="deleteWebhook(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>删除</el-button>
                </template>
              </el-popconfirm>
            </el-space>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <WebhookDialog ref="dialogRef" @saved="fetchWebhooks" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import WebhookDialog from './WebhookDialog.vue'

const loading = ref(false)
const testing = ref(null)
const webhooks = ref([])
const dialogRef = ref(null)

async function fetchWebhooks() {
  loading.value = true
  try {
    const { data } = await api.getWebhooks()
    webhooks.value = data ?? []
  } catch (e) {
    ElMessage.error('获取 Webhook 列表失败')
  } finally {
    loading.value = false
  }
}

async function testWebhook(row) {
  testing.value = row.id
  try {
    const { data } = await api.testWebhook(row.id)
    if (data.success) {
      ElMessage.success(`测试成功 (HTTP ${data.status})`)
    } else {
      ElMessage.warning(`响应异常 (HTTP ${data.status})`)
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.error || '测试失败')
  } finally {
    testing.value = null
  }
}

function openCreateDialog() { dialogRef.value?.open('create') }
function editWebhook(row) { dialogRef.value?.open('edit', row) }

async function deleteWebhook(row) {
  try {
    await api.deleteWebhook(row.id)
    ElMessage.success('Webhook 已删除')
    fetchWebhooks()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

onMounted(() => fetchWebhooks())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.text-right { text-align: right; }
.text-lg { font-size: 16px; }
.font-medium { font-weight: 500; }
.text-xs { font-size: 12px; }
.break-all { word-break: break-all; }
.text-green-500 { color: #67c23a; }
.text-red-500 { color: #f56c6c; }
</style>
