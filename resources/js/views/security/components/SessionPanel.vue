<template>
  <div>
    <div class="mb-4">
      <el-space>
        <el-checkbox v-model="showAll" @change="fetchSessions">显示全部会话</el-checkbox>
        <el-button size="small" type="danger" plain @click="terminateAll">终止全部会话</el-button>
      </el-space>
    </div>

    <el-table :data="sessions" v-loading="loading" size="small" max-height="420">
      <el-table-column label="用户" min-width="140">
        <template #default="{ row }">{{ row.user?.name || row.user?.email || '—' }}</template>
      </el-table-column>
      <el-table-column prop="ip_address" label="IP" width="140">
        <template #default="{ row }"><code>{{ row.ip_address }}</code></template>
      </el-table-column>
      <el-table-column prop="device_type" label="设备" width="80">
        <template #default="{ row }">
          <el-tag size="small" effect="plain">{{ row.device_type }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="browser" label="浏览器" width="100" show-overflow-tooltip />
      <el-table-column prop="os" label="系统" width="80" show-overflow-tooltip />
      <el-table-column label="当前" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_current" type="success" size="small">当前</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="MFA" width="60" align="center">
        <template #default="{ row }">
          <el-tag v-if="row.is_mfa_verified" type="success" size="small">✓</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="最后活动" width="160">
        <template #default="{ row }">{{ row.last_activity_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column label="过期" width="160">
        <template #default="{ row }">{{ row.expires_at?.slice(0, 16) || '—' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-popconfirm title="终止此会话？" @confirm="terminate(row)">
            <template #reference>
              <el-button size="small" type="danger" link :disabled="row.is_current && !showAll">终止</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getSessions, terminateSession, terminateAllSessions } from '../../../api/securityCenter'

const loading = ref(false)
const sessions = ref([])
const showAll = ref(false)

async function fetchSessions() {
  loading.value = true
  try {
    const { data } = await getSessions({ all: showAll.value ? 'true' : undefined })
    sessions.value = data || []
  } catch (e) { ElMessage.error('获取会话列表失败') }
  finally { loading.value = false }
}

async function terminate(row) {
  try {
    await terminateSession(row.id)
    ElMessage.success('会话已终止')
    fetchSessions()
  } catch (e) { ElMessage.error('操作失败') }
}

function terminateAll() {
  ElMessageBox.confirm('确定终止全部活跃会话？所有用户需要重新登录。', '确认', {
    confirmButtonText: '终止全部', cancelButtonText: '取消', type: 'warning',
  }).then(async () => {
    try {
      await terminateAllSessions()
      ElMessage.success('已终止全部会话')
      fetchSessions()
    } catch (e) { ElMessage.error('操作失败') }
  }).catch(() => {})
}

onMounted(fetchSessions)
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
code { background: #f5f7fa; padding: 2px 4px; border-radius: 3px; font-family: monospace; font-size: 12px; }
</style>
