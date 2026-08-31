<template>
  <div>
    <el-row :gutter="12" class="mb-4" justify="space-between" align="middle">
      <el-col :span="12">
        <el-space>
          <el-select v-model="filterType" :placeholder="t('invite_channel_list.type')" clearable style="width:130px" @change="fetchChannels">
            <el-option :label="t('invite_channel_list.all')" value="" />
            <el-option v-for="item in types" :key="item.value" :label="item.label" :value="item.value" />
          </el-select>
          <el-input v-model="searchText" :placeholder="t('invite_channel_list.search_placeholder')" clearable style="width:200px"
            @clear="fetchChannels" @keyup.enter="fetchChannels" />
        </el-space>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="openCreate">
          <el-icon><Plus /></el-icon> {{ t('invite_channel_list.create') }}
        </el-button>
      </el-col>
    </el-row>

    <el-table :data="channels" v-loading="loading" stripe style="width:100%">
      <el-table-column prop="name" :label="t('invite_channel_list.cols.name')" min-width="150">
        <template #default="{ row }">
          <span class="font-medium">{{ row.name }}</span>
          <el-tag v-if="row.is_public" size="small" type="success" class="ml-2">{{ t('invite_channel_list.public') }}</el-tag>
        </template>
      </el-table-column>
      <el-table-column prop="slug" :label="t('invite_channel_list.cols.slug')" width="120">
        <template #default="{ row }"><code>{{ row.slug }}</code></template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.type')" width="120">
        <template #default="{ row }">{{ typeLabel(row.type) }}</template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.status')" width="80">
        <template #default="{ row }">
          <el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">
            {{ row.status === 'active' ? t('invite_channel_list.active') : t('invite_channel_list.inactive') }}
          </el-tag>
        </template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.codes')" width="90" align="center">
        <template #default="{ row }">{{ row.code_count || 0 }}</template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.registrations')" width="80" align="center">
        <template #default="{ row }">{{ row.registration_count || 0 }}</template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.conversion')" width="80" align="center">
        <template #default="{ row }">{{ row.conversion_rate ? (row.conversion_rate / 10).toFixed(1) + '%' : '—' }}</template>
      </el-table-column>
      <el-table-column :label="t('invite_channel_list.cols.actions')" width="250" fixed="right">
        <template #default="{ row }">
          <el-space>
            <el-button size="small" link type="primary" @click="viewDashboard(row)">{{ t('invite_channel_list.dashboard') }}</el-button>
            <el-button size="small" link @click="editChannel(row)">{{ t('actions.edit') }}</el-button>
            <el-popconfirm :title="t('invite_channel_list.confirm_delete')" @confirm="deleteChannel(row)">
              <template #reference>
                <el-button size="small" type="danger" link>{{ t('actions.delete') }}</el-button>
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

    <ChannelDialog ref="dialogRef" @saved="fetchChannels" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import { getChannels, deleteChannel as deleteChannelApi } from '../../../api/invite-codes'
import ChannelDialog from './ChannelDialog.vue'

const { t } = useI18n()
const channels = ref([])
const loading = ref(false)
const total = ref(0)
const perPage = ref(20)
const filterType = ref('')
const searchText = ref('')
const dialogRef = ref(null)
const emit = defineEmits(['view-dashboard'])

const types = computed(() => [
  { value: 'promotional', label: t('invite_channel_list.types.promotional') },
  { value: 'marketing', label: t('invite_channel_list.types.marketing') },
  { value: 'partner', label: t('invite_channel_list.types.partner') },
  { value: 'event', label: t('invite_channel_list.types.event') },
  { value: 'social', label: t('invite_channel_list.types.social') },
  { value: 'internal', label: t('invite_channel_list.types.internal') },
])

function typeLabel(type) {
  return types.value.find(x => x.value === type)?.label || type
}

async function fetchChannels(page = 1) {
  loading.value = true
  try {
    const params = { page, per_page: perPage.value }
    if (filterType.value) params.type = filterType.value
    if (searchText.value) params.search = searchText.value
    const { data } = await getChannels(params)
    channels.value = data?.data?.data || []
    total.value = data?.data?.total || 0
  } catch (e) {
    ElMessage.error(t('invite_channel_list.messages.load_failed'))
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
    ElMessage.success(t('invite_channel_list.messages.deleted'))
    fetchChannels()
  } catch (e) {
    ElMessage.error(t('invite_channel_list.messages.delete_failed'))
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
