<template>
  <div class="p-6">
    <!-- 头部 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">{{ t('ai_proactive_page.title') }}</h1>
        <p class="text-gray-500 text-sm mt-1">{{ t('ai_proactive_page.subtitle') }}</p>
      </div>
      <el-button type="primary" plain :disabled="!stats.unread" @click="handleMarkAllRead">
        {{ t('notifications_page.mark_all_read') }}
      </el-button>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">{{ t('ai_proactive_page.stats.total') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value text-warning">{{ stats.unread }}</div>
          <div class="stat-label">{{ t('ai_proactive_page.stats.unread') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.today }}</div>
          <div class="stat-label">{{ t('ai_proactive_page.stats.today') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value text-primary">{{ insightTypes.length }}</div>
          <div class="stat-label">{{ t('ai_proactive_page.stats.insight_types') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 类型分布 + 状态分布 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>{{ t('ai_proactive_page.sections.type_distribution') }}</span></template>
          <div v-if="Object.keys(stats.by_type || {}).length" class="flex flex-wrap gap-2">
            <el-tag v-for="(count, type) in stats.by_type" :key="type" :type="tagType(type)" class="text-sm">
              {{ typeLabel(type) }}: {{ count }}
            </el-tag>
          </div>
          <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>{{ t('ai_proactive_page.sections.status_distribution') }}</span></template>
          <div v-if="Object.keys(stats.by_status || {}).length" class="flex flex-wrap gap-2">
            <el-tag v-for="(count, st) in stats.by_status" :key="st" :type="statusTag(st)" class="text-sm">
              {{ statusLabel(st) }}: {{ count }}
            </el-tag>
          </div>
          <el-empty v-else :description="t('messages.no_data')" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 洞察列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>{{ t('ai_proactive_page.sections.records') }}</span>
          <div class="flex gap-2">
            <el-select v-model="filters.status" :placeholder="t('ai_proactive_page.filters.status_ph')" clearable size="small" style="width:120px" @change="fetchList">
              <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="filters.type" :placeholder="t('ai_proactive_page.filters.type_ph')" clearable size="small" style="width:120px" @change="fetchList">
              <el-option v-for="opt in insightTypeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="title" :label="t('ai_proactive_page.cols.title')" min-width="180">
          <template #default="{ row }">
            <div class="flex items-center gap-1">
              <el-tag v-if="row.status === 'pending' || row.status === 'sent'" type="danger" size="small" class="mr-1">{{ t('ai_proactive_page.badge_new') }}</el-tag>
              <span :class="{ 'font-bold': row.status === 'pending' || row.status === 'sent' }">{{ row.title }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="content" :label="t('ai_proactive_page.cols.content')" min-width="280" show-overflow-tooltip />
        <el-table-column prop="type" :label="t('ai_proactive_page.cols.type')" width="80">
          <template #default="{ row }">
            <el-tag :type="tagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" :label="t('ai_proactive_page.cols.status')" width="80">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column :label="t('ai_proactive_page.cols.time')" width="150">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column :label="t('ai_proactive_page.cols.actions')" width="140" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'pending' || row.status === 'sent'">
              <el-button link type="primary" size="small" @click="handleMarkRead(row)">{{ t('notifications_page.mark_read') }}</el-button>
              <el-button link type="info" size="small" @click="handleDismiss(row)">{{ t('ai_proactive_page.dismiss') }}</el-button>
            </template>
            <span v-else class="text-gray-400 text-xs">-</span>
          </template>
        </el-table-column>
      </el-table>

      <div class="flex justify-center mt-4" v-if="pagination.total > pagination.per_page">
        <el-pagination
          v-model:current-page="pagination.current_page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="prev, pager, next"
          @current-change="fetchList"
        />
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import proactiveApi from '@/api/proactive';

const { t, locale } = useI18n();

// ── 状态 ──
const loading = ref(false);
const list = ref([]);
const stats = ref({ total: 0, unread: 0, today: 0, by_type: {}, by_status: {}, recent: [] });
const insightTypes = ref([]);

const filters = reactive({ status: '', type: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const insightTypeMap = computed(() => {
  const map = {};
  (insightTypes.value || []).forEach(item => { map[item.type || item] = item; });
  return map;
});

const typeLabels = computed(() => ({
  follow_up: t('ai_proactive_page.types.follow_up'),
  reminder: t('ai_proactive_page.types.reminder'),
  suggestion: t('ai_proactive_page.types.suggestion'),
  insight: t('ai_proactive_page.types.insight'),
  alert: t('ai_proactive_page.types.alert'),
}));

const statusLabels = computed(() => ({
  pending: t('ai_proactive_page.status.pending'),
  sent: t('ai_proactive_page.status.sent'),
  read: t('ai_proactive_page.status.read'),
  dismissed: t('ai_proactive_page.status.dismissed'),
}));

const statusFilterOptions = computed(() => [
  { value: 'pending', label: statusLabels.value.pending },
  { value: 'sent', label: statusLabels.value.sent },
  { value: 'read', label: statusLabels.value.read },
  { value: 'dismissed', label: statusLabels.value.dismissed },
]);

const insightTypeFilterOptions = computed(() =>
  (insightTypes.value || []).map(item => {
    const type = item.type || item;
    return { value: type, label: typeLabel(type) };
  }),
);

// ── 工具函数 ──
const TYPE_TAG = { follow_up: 'primary', reminder: 'warning', suggestion: 'success', insight: 'info', alert: 'danger' };
const STATUS_TAG = { pending: 'danger', sent: 'warning', read: 'info', dismissed: '' };

function typeLabel(type) { return typeLabels.value[type] || insightTypeMap.value[type]?.label || type; }
function tagType(type) { return TYPE_TAG[type] || ''; }
function statusTag(status) { return STATUS_TAG[status] || ''; }
function statusLabel(status) { return statusLabels.value[status] || status; }
function formatTime(time) {
  if (!time) return '';
  const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
  return new Date(time).toLocaleString(loc);
}

// ── 数据加载 ──
async function fetchList() {
  loading.value = true;
  try {
    const params = { page: pagination.current_page, per_page: pagination.per_page, ...filters };
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k]; });
    const res = await proactiveApi.list(params);
    list.value = res.data?.data || [];
    pagination.current_page = res.data?.meta?.current_page || 1;
    pagination.per_page = res.data?.meta?.per_page || 20;
    pagination.total = res.data?.meta?.total || 0;
  } catch { ElMessage.error(t('messages.load_failed')); }
  finally { loading.value = false; }
}

async function fetchStats() {
  try {
    const [statsRes, typesRes] = await Promise.all([
      proactiveApi.stats(),
      proactiveApi.types(),
    ]);
    stats.value = statsRes.data?.data || stats.value;
    const typeData = typesRes.data?.data;
    insightTypes.value = typeData ? Object.entries(typeData).map(([k, v]) => ({ type: k, ...v })) : [];
  } catch { /* ignore */ }
}

// ── 操作 ──
async function handleMarkRead(row) {
  try {
    await proactiveApi.markRead(row.id);
    ElMessage.success(t('notifications_page.mark_read_ok'));
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error(t('messages.failed')); }
}

async function handleDismiss(row) {
  try {
    await proactiveApi.dismiss(row.id);
    ElMessage.success(t('ai_proactive_page.messages.dismissed_ok'));
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error(t('messages.failed')); }
}

async function handleMarkAllRead() {
  try {
    await proactiveApi.markAllRead();
    ElMessage.success(t('notifications_page.mark_all_read_ok'));
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error(t('messages.failed')); }
}

// ── 初始化 ──
onMounted(() => {
  fetchStats();
  fetchList();
});
</script>

<style scoped>
.stat-card { text-align: center; }
.stat-value { font-size: 32px; font-weight: 700; color: #0f172a; }
.stat-value.text-warning { color: #e6a23c; }
.stat-value.text-primary { color: #0f172a; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
