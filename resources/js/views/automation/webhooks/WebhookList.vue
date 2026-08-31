<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row justify="space-between" align="middle">
        <el-col :span="12"><span class="text-lg font-medium">{{ t('webhook_list.title') }}</span></el-col>
        <el-col :span="12" class="text-right">
          <el-button type="primary" @click="openCreateDialog">
            <el-icon><Plus /></el-icon> {{ t('webhook_list.create') }}
          </el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-card shadow="never">
      <el-table :data="webhooks" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="name" :label="t('webhook_list.cols.name')" min-width="140" />
        <el-table-column prop="url" label="URL" min-width="280">
          <template #default="{ row }">
            <span class="text-xs break-all">{{ row.url }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="method" :label="t('webhook_list.cols.method')" width="80" align="center">
          <template #default="{ row }">
            <el-tag size="small" effect="plain">{{ row.method || 'POST' }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('webhook_list.cols.stats')" width="160">
          <template #default="{ row }">
            <span class="text-green-500">✓{{ row.success_count }}</span>
            <span class="text-red-500 ml-2">✗{{ row.failure_count }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="is_active" :label="t('webhook_list.cols.status')" width="80" align="center">
          <template #default="{ row }">
            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('webhook_list.active') : t('webhook_list.inactive') }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('webhook_list.cols.actions')" width="240" fixed="right">
          <template #default="{ row }">
            <el-space>
              <el-button size="small" link @click="testWebhook(row)" :loading="testing === row.id">{{ t('webhook_list.test') }}</el-button>
              <el-button size="small" link type="primary" @click="editWebhook(row)">{{ t('actions.edit') }}</el-button>
              <el-popconfirm :title="t('webhook_list.confirm_delete')" @confirm="deleteWebhook(row)">
                <template #reference>
                  <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
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
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import api from '../../../api/automation'
import WebhookDialog from './WebhookDialog.vue'

const { t } = useI18n()
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
    ElMessage.error(t('webhook_list.messages.load_failed'))
  } finally {
    loading.value = false
  }
}

async function testWebhook(row) {
  testing.value = row.id
  try {
    const { data } = await api.testWebhook(row.id)
    if (data.success) {
      ElMessage.success(t('webhook_list.messages.test_ok', { status: data.status }))
    } else {
      ElMessage.warning(t('webhook_list.messages.test_warn', { status: data.status }))
    }
  } catch (e) {
    ElMessage.error(e.response?.data?.error || t('webhook_list.messages.test_failed'))
  } finally {
    testing.value = null
  }
}

function openCreateDialog() { dialogRef.value?.open('create') }
function editWebhook(row) { dialogRef.value?.open('edit', row) }

async function deleteWebhook(row) {
  try {
    await api.deleteWebhook(row.id)
    ElMessage.success(t('webhook_list.messages.deleted'))
    fetchWebhooks()
  } catch (e) {
    ElMessage.error(t('webhook_list.messages.delete_failed'))
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
