<template>
  <div>
    <el-card shadow="never" class="mb-4">
      <el-row :gutter="12">
        <el-col :span="6">
          <el-select v-model="filters.source" placeholder="来源" clearable style="width:100%" size="small" @change="fetchData">
            <el-option label="全部" value="" />
            <el-option v-for="(lb, key) in sources" :key="key" :label="lb" :value="key" />
          </el-select>
        </el-col>
        <el-col :span="6">
          <el-select v-model="filters.channel_id" placeholder="渠道" clearable style="width:100%" size="small" @change="fetchData">
            <el-option label="全部" value="" />
            <el-option v-for="ch in channels" :key="ch.id" :label="ch.name" :value="ch.id" />
          </el-select>
        </el-col>
        <el-col :span="4">
          <el-select v-model="filters.converted" placeholder="转化" clearable style="width:100%" size="small" @change="fetchData">
            <el-option label="全部" value="" />
            <el-option label="已转化" value="yes" />
            <el-option label="未转化" value="no" />
          </el-select>
        </el-col>
        <el-col :span="8" class="text-right">
          <el-button size="small" @click="fetchData" :icon="Refresh">刷新</el-button>
        </el-col>
      </el-row>
    </el-card>

    <el-table :data="trackings" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="id" label="ID" width="60" />
      <el-table-column label="用户" width="150">
        <template #default="{ row }">{{ row.user?.name || row.user?.email || '—' }}</template>
      </el-table-column>
      <el-table-column label="邀请码" width="120">
        <template #default="{ row }">
          <span class="code-text">{{ row.invite_code || '—' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="渠道" width="120">
        <template #default="{ row }">{{ row.channel?.name || '—' }}</template>
      </el-table-column>
      <el-table-column label="来源" width="90">
        <template #default="{ row }">{{ sources[row.source] || row.source }}</template>
      </el-table-column>
      <el-table-column label="落地页" min-width="180">
        <template #default="{ row }">
          <span class="text-xs break-all">{{ row.landing_page || '—' }}</span>
        </template>
      </el-table-column>
      <el-table-column label="转化" width="80" align="center">
        <template #default="{ row }">
          <el-tag :type="row.converted ? 'success' : 'info'" size="small">
            {{ row.converted ? '是' : '否' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="IP" width="120">
        <template #default="{ row }">{{ row.ip_address || '—' }}</template>
      </el-table-column>
      <el-table-column label="注册时间" width="150">
        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchData(page)" @size-change="s => { perPage = s; fetchData() }" />
    </div>

    <el-empty v-if="!loading && !trackings.length" description="暂无注册记录" />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Refresh } from '@element-plus/icons-vue'
import { getRegistrationTrackings, getChannels } from '../../../api/invite-codes'

const trackings = ref([])
const channels = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)

const filters = reactive({ source: '', channel_id: '', converted: '' })

const sources = { invite: '邀请码', direct: '直接', social: '社交', oauth: 'OAuth', trial: '试用' }

async function fetchData(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filters.source) params.source = filters.source
    if (filters.channel_id) params.channel_id = filters.channel_id
    if (filters.converted) params.converted = filters.converted
    const { data } = await getRegistrationTrackings(params)
    trackings.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error('获取注册追踪失败')
  } finally {
    loading.value = false
  }
}

function formatDate(d) {
  if (!d) return '-'
  return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
}

onMounted(() => {
  fetchData()
  getChannels().then(r => { channels.value = r.data?.data || [] }).catch(() => {})
})
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.text-xs { font-size: 12px; }
.break-all { word-break: break-all; }
.code-text { font-family: 'Consolas', monospace; font-size: 12px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
</style>
