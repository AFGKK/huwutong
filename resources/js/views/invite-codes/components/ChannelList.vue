<template>
  <div>
    <el-row :gutter="12" class="mb-4" justify="space-between" align="middle">
      <el-col :span="12">
        <el-space>
          <el-select v-model="filterType" placeholder="类型" clearable style="width:130px" @change="fetchChannels">
            <el-option label="全部" value="" />
            <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
          </el-select>
          <el-input v-model="searchText" placeholder="搜索渠道名称..." clearable style="width:200px"
            @clear="fetchChannels" @keyup.enter="fetchChannels" />
        </el-space>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon> 新建渠道
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="channels" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" label="渠道名称" min-width="150">
        <template #default="{ row }">
          <span class="font-medium">{{ row.name }}</span>
          <el-tag v-if="row.is_public" size="small" type="success" class="ml-2">公开</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="slug" label="标识" width="120">
        <template #default="{ row }"><code>{{ row.slug }}</code></template>
      </el-table-column>
      <el-table-column label="类型" width="120">
        <template #default="{ row }">{{ typeLabel(row.type) }}</template>
      </el-table-column>
      <el-table-column label="状态" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
            {{ row.status === 'active' ? '活跃' : '停用' }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column label="邀请码数" width="90" align="center">
        <template #default="{ row }">{{ row.code_count || 0 }}</template>
      </el-table-column>
      <el-table-column label="注册数" width="80" align="center">
        <template #default="{ row }">{{ row.registration_count || 0 }}</template>
      </el-table-column>
      <el-table-column label="转化率" width="80" align="center">
        <template #default="{ row }">{{ row.conversion_rate ? (row.conversion_rate / 10).toFixed(1) + '%' : '—' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="250" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link type="primary" @click="viewDashboard(row)">看板</el-button>
            <el-button size="small" link @click="editChannel(row)">编辑</el-button>
            <el-popconfirm title="确定删除此渠道？" @confirm="deleteChannel(row)">
              <template #reference>
                <el-button size="small" type="danger" link>删除</el-button>
              </template>
            </el-popconfirm>
          </el-space>
        </template>
      </el-table-column>
    </el-table>

    <div class="mt-4 flex justify-end" v-if="total > perPage">
      <el-pagination v-model:page-size="perPage" :total="total" :page-sizes="[20, 50, 100]"
        layout="sizes, prev, pager, next"
        @current-change="page => fetchChannels(page)" @size-change="s => { perPage = s; fetchChannels() }" />
    </div>

    <!-- 渠道对话框 -->
    <ChannelDialog ref="dialogRef" @saved="fetchChannels" />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getChannels, deleteChannel as deleteChannelApi } from '../../../api/invite-codes'
import ChannelDialog from './ChannelDialog.vue'
import ChannelDashboard from './ChannelDashboard.vue'

const channels = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const filterType = ref('')
const searchText = ref('')
const dialogRef = ref(null)
const emit = defineEmits(['view-dashboard'])

const types = [
  { value: 'promotional', label: '推广' },
  { value: 'marketing', label: '营销' },
  { value: 'partner', label: '合作伙伴' },
  { value: 'event', label: '活动' },
  { value: 'social', label: '社交' },
  { value: 'internal', label: '内部' },
]

function typeLabel(t) {
  return types.find(x => x.value === t)?.label || t
}

async function fetchChannels(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filterType.value) params.type = filterType.value
    if (searchText.value) params.search = searchText.value
    const { data } = await getChannels(params)
    channels.value = data?.data || []
    total.value = data?.total || 0
  } catch (e) {
    ElMessage.error('获取渠道列表失败')
  } finally {
    loading.value = false
  }
}

function openCreate() { dialogRef.value?.open('create') }
function editChannel(row) { dialogRef.value?.open('edit', row) }

function viewDashboard(row) {
  emit('view-dashboard', row)
}

async function deleteChannel(row) {
  try {
    await deleteChannelApi(row.id)
    ElMessage.success('渠道已删除')
    fetchChannels()
  } catch (e) {
    ElMessage.error('删除失败')
  }
}

onMounted(() => fetchChannels())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.text-right { text-align: right; }
.font-medium { font-weight: 500; }
.ml-2 { margin-left: 8px; }
.flex { display: flex; }
.justify-end { justify-content: flex-end; }
code { background: #f5f7fa; padding: 1px 4px; border-radius: 3px; font-size: 12px; }
</style>
