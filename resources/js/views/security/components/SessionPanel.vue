<template>
  <div>
    <div class="mb-4">
      <el-space>
        <el-checkbox v-model="showAll" @change="fetchSessions">{{ t('session_panel.show_all') }}</el-checkbox>
        <el-button size="small" type="danger" plain @click="terminateAll">{{ t('session_panel.terminate_all') }}</el-button>
      </el-space>
    </div>

    <el-table :data="sessions" v-loading="loading" size="small" max-height="420">
      <el-table-column :label="t('session_panel.cols.user')" min-width="140">
        <template #default="{ row }">{{ row.user?.name || row.user?.email || '—' }}</template>
      </el-table-column>
      <el-table-column prop="ip_address" :label="t('session_panel.cols.ip')" width="140">
        <template #default="{ row }"><code>{{ row.ip_address }}</code></template>
      </el-table-column>
      <el-table-column prop="device_type" :label="t('session_panel.cols.device')" width="80">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ row.device_type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="browser" :label="t('session_panel.cols.browser')" width="100" show-overflow-tooltip />
      <el-table-column prop="os" :label="t('session_panel.cols.os')" width="80" show-overflow-tooltip />
      <el-table-column :label="t('session_panel.cols.current')" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_current" type="success" size="small">{{ t('session_panel.current') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="MFA" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_mfa_verified" type="success" size="small">✓</el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('session_panel.cols.last_activity')" width="160">
        <template #default="{ row }">{{ row.last_activity_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('session_panel.cols.expires')" width="160">
        <template #default="{ row }">{{ row.expires_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('session_panel.cols.actions')" width="100" fixed="right">
        <template #default="{ row }">
          <el-popconfirm :title="t('session_panel.confirm_terminate')" @confirm="terminate(row)">
            <template #reference>
              <el-button size="small" type="danger" link :disabled="row.is_current && !showAll">{{ t('session_panel.terminate') }}</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getSessions, terminateSession, terminateAllSessions } from '../../../api/securityCenter'

const { t } = useI18n()
const loading = ref(false)
const sessions = ref([])
const showAll = ref(false)

async function fetchSessions() {
  loading.value = true
  try {
    const { data } = await getSessions({ all: showAll.value ? 'true' : undefined })
    sessions.value = data || []
  } catch (e) { ElMessage.error(t('session_panel.messages.load_failed')) }
  finally { loading.value = false }
}

async function terminate(row) {
  try {
    await terminateSession(row.id)
    ElMessage.success(t('session_panel.messages.terminated'))
    fetchSessions()
  } catch (e) { ElMessage.error(t('session_panel.messages.failed')) }
}

function terminateAll() {
  ElMessageBox.confirm(t('session_panel.confirm_all_body'), t('session_panel.confirm_all_title'), {
    confirmButtonText: t('session_panel.terminate_all'),
    cancelButtonText: t('actions.cancel'),
    type: 'warning',
  }).then(async () => {
    try {
      await terminateAllSessions()
      ElMessage.success(t('session_panel.messages.all_terminated'))
      fetchSessions()
    } catch (e) { ElMessage.error(t('session_panel.messages.failed')) }
  }).catch(() => {})
}

onMounted(fetchSessions)
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
code { background: #f5f7fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; font-size: 12px; }
</style>
