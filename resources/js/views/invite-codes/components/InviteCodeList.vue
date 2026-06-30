<template>
  <div>
    <el-row :gutter="12" class="mb-4">
      <el-col :span="6">
        <el-select v-model="filterStatus" placeholder="状态" clearable style="width:100%" @change="fetchCodes">
          <el-option label="全部" value="" />
          <el-option label="活跃" value="active" />
          <el-option label="已用完" value="exhausted" />
          <el-option label="已过期" value="expired" />
          <el-option label="已禁用" value="disabled" />
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-select v-model="filterChannel" placeholder="渠道" clearable style="width:100%" @change="fetchCodes">
          <el-option label="全部渠道" value="" />
          <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
        </el-select>
      </el-col>
      <el-col :span="6">
        <el-input v-model="searchCode" placeholder="搜索邀请码..." clearable @clear="fetchCodes" @keyup.enter="fetchCodes" />
      </el-col>
      <el-col :span="6" class="text-right">
        <el-button type="primary" @click="openGenerate">
          <el-icon><Plus /></el-icon> 生成邀请码
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="codes" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="code" label="邀请码" min-width="160">
        <template #default="{ row }">
          <span class="code-text">{{ row.code }}</span>
          <el-button text size="small" :icon="CopyDocument" @click="copyCode(row.code)" />
        </template>
      </el-table-column>
      <el-table-column label="渠道" width="130">
        <template #default="{ row }">
          <el-tag v-if="row.channel" size="small">{{ row.channel.name }}</el-tag>
          <span v-else class="text-gray-400 text-xs">通用</span>
        </template>
      </el-table-column>
      <el-table-column label="状态" width="90">
        <template #default="{ row }">
          <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column label="使用量" width="120">
        <template #default="{ row }">
          <el-progress :percentage="usagePercent(row)" :stroke-width="14" striped>
            {{ row.used_count }}/{{ row.max_uses || '∞' }}
          </el-progress>
        </template>
      </el-table-column>
      <el-table-column label="过期时间" width="140">
        <template #default="{ row }">
          {{ row.expires_at ? formatDate(row.expires_at) : '永不过期' }}
        </template>
      </el-table-column>
      <el-table-column label="最近使用" width="140">
        <template #default="{ row }">{{ row.last_used_at ? formatDate(row.last_used_at) : '—' }}</template>
      </el-table-column>
      <el-table-column label="备注" min-width="120" prop="remarks" />
      <el-table-column label="操作" width="100" fixed="right">
        <template #default="{ row }">
          <el-popconfirm title="禁用此邀请码？" @confirm="disableCode(row)">
            <template #reference>
              <el-button size="small" type="danger" link :disabled="row.status !== 'active'">禁用</el-button>
            </template>
          </el-popconfirm>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchCodes(page)" @size-change="s => { perPage = s; fetchCodes() }" />
    </div>

    <!-- 生成对话框 -->
    <el-dialog v-model="dialogVisible" title="生成邀请码" width="520px">
      <el-form :model="form" label-width="120px">
        <el-form-item label="生成数量">
          <el-input-number v-model="form.count" :min="1" :max="500" />
          <span class="form-help">1~500 个</span>
        </el-form-item>
        <el-form-item label="所属渠道">
          <el-select v-model="form.channel_id" clearable style="width:100%" placeholder="通用(无渠道)">
            <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="最大使用次数">
          <el-input-number v-model="form.max_uses" :min="1" :max="10000" />
          <span class="form-help">每个邀请码可用次数</span>
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker v-model="form.expires_at" type="datetime" placeholder="永不过期" clearable
            value-format="YYYY-MM-DD HH:mm:ss" style="width:100%" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remarks" type="textarea" :rows="2" maxlength="500" show-word-limit
            placeholder="选填：用于区分不同批次" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleGenerate" :loading="generating">生成</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus, CopyDocument } from '@element-plus/icons-vue'
import { getInviteCodes, generateInviteCodes, disableInviteCode, getChannels } from '../../../api/invite-codes'

const codes = ref([])
const channels = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const filterStatus = ref('')
const filterChannel = ref('')
const searchCode = ref('')
const dialogVisible = ref(false)
const generating = ref(false)
const form = ref({ count: 10, max_uses: 1, expires_at: null, remarks: '', channel_id: null })

async function fetchCodes(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filterStatus.value) params.status = filterStatus.value
    if (filterChannel.value) params.channel_id = filterChannel.value
    if (searchCode.value) params.search = searchCode.value
    const res = await getInviteCodes(params)
    codes.value = res.data?.data?.data || res.data?.data || []
    total.value = res.data?.data?.total || 0
  } catch (e) {
    ElMessage.error('获取邀请码列表失败')
  } finally {
    loading.value = false
  }
}

async function loadChannels() {
  try {
    const { data } = await getChannels()
    channels.value = data?.data || []
  } catch (e) { /* ignore */ }
}

function openGenerate() {
  form.value = { count: 10, max_uses: 1, expires_at: null, remarks: '', channel_id: null }
  dialogVisible.value = true
}

async function handleGenerate() {
  generating.value = true
  try {
    await generateInviteCodes(form.value.count, {
      max_uses: form.value.max_uses,
      expires_at: form.value.expires_at || undefined,
      remarks: form.value.remarks,
      channel_id: form.value.channel_id || undefined,
    })
    ElMessage.success(`成功生成 ${form.value.count} 个邀请码`)
    dialogVisible.value = false
    fetchCodes()
  } catch (e) {
    ElMessage.error(e.response?.data?.message || '生成失败')
  } finally {
    generating.value = false
  }
}

async function disableCode(row) {
  try {
    await disableInviteCode(row.id)
    ElMessage.success('邀请码已禁用')
    row.status = 'disabled'
  } catch (e) {
    ElMessage.error('操作失败')
  }
}

function copyCode(code) {
  navigator.clipboard.writeText(code).then(() => ElMessage.success('已复制'))
    .catch(() => ElMessage.warning('复制失败'))
}

function statusType(status) {
  return { active: 'success', exhausted: 'warning', expired: 'info', disabled: 'danger' }[status] || 'info'
}
function statusLabel(status) {
  return { active: '活跃', exhausted: '已用完', expired: '已过期', disabled: '已禁用' }[status] || status
}
function usagePercent(row) {
  if (!row.max_uses) return 0
  return Math.min(100, Math.round((row.used_count / row.max_uses) * 100))
}
function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => { fetchCodes(); loadChannels() })
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
.code-text { font-family: 'Consolas', monospace; font-size: 14px; font-weight: 600; letter-spacing: 1px; }
.form-help { color: #999; font-size: 12px; margin-left: 8px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
:deep(.el-progress__text) { font-size: 12px !important; }
</style>
