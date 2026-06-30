<template>
  <div class="p-6">
    <!-- 头部 -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">🤖 AI 主动洞察</h1>
        <p class="text-gray-500 text-sm mt-1">AI 主动扫描未回复对话，推送智能跟进建议</p>
      </div>
      <el-button type="primary" plain :disabled="!stats.unread" @click="handleMarkAllRead">
        全部标记已读
      </el-button>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.total }}</div>
          <div class="stat-label">总洞察数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value text-warning">{{ stats.unread }}</div>
          <div class="stat-label">未读</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value">{{ stats.today }}</div>
          <div class="stat-label">今日新增</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-value text-primary">{{ insightTypes.length }}</div>
          <div class="stat-label">洞察类型</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 类型分布 + 状态分布 -->
    <el-row :gutter="16" class="mb-6">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>📊 类型分布</span></template>
          <div v-if="Object.keys(stats.by_type || {}).length" class="flex flex-wrap gap-2">
            <el-tag v-for="(count, type) in stats.by_type" :key="type" :type="tagType(type)" class="text-sm">
              {{ typeLabel(type) }}: {{ count }}
            </el-tag>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span>📈 状态分布</span></template>
          <div v-if="Object.keys(stats.by_status || {}).length" class="flex flex-wrap gap-2">
            <el-tag v-for="(count, st) in stats.by_status" :key="st" :type="statusTag(st)" class="text-sm">
              {{ statusLabel(st) }}: {{ count }}
            </el-tag>
          </div>
          <el-empty v-else description="暂无数据" :image-size="60" />
        </el-card>
      </el-col>
    </el-row>

    <!-- 洞察列表 -->
    <el-card shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>📋 洞察记录</span>
          <div class="flex gap-2">
            <el-select v-model="filters.status" placeholder="状态筛选" clearable size="small" style="width:120px" @change="fetchList">
              <el-option label="待处理" value="pending" />
              <el-option label="已推送" value="sent" />
              <el-option label="已读" value="read" />
              <el-option label="已忽略" value="dismissed" />
            </el-select>
            <el-select v-model="filters.type" placeholder="类型筛选" clearable size="small" style="width:120px" @change="fetchList">
              <el-option v-for="(info, t) in insightTypeMap" :key="t" :label="info.label" :value="t" />
            </el-select>
          </div>
        </div>
      </template>

      <el-table :data="list" v-loading="loading" stripe style="width:100%">
        <el-table-column prop="title" label="标题" min-width="180">
          <template #default="{ row }">
            <div class="flex items-center gap-1">
              <el-tag v-if="row.status === 'pending' || row.status === 'sent'" type="danger" size="small" class="mr-1">NEW</el-tag>
              <span :class="{ 'font-bold': row.status === 'pending' || row.status === 'sent' }">{{ row.title }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column prop="content" label="内容" min-width="280" show-overflow-tooltip />
        <el-table-column prop="type" label="类型" width="80">
          <template #default="{ row }">
            <el-tag :type="tagType(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="时间" width="150">
          <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
        </el-table-column>
        <el-table-column label="操作" width="140" fixed="right">
          <template #default="{ row }">
            <template v-if="row.status === 'pending' || row.status === 'sent'">
              <el-button link type="primary" size="small" @click="handleMarkRead(row)">标为已读</el-button>
              <el-button link type="info" size="small" @click="handleDismiss(row)">忽略</el-button>
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
import { ElMessage } from 'element-plus';
import proactiveApi from '@/api/proactive';

// ── 状态 ──
const loading = ref(false);
const list = ref([]);
const stats = ref({ total: 0, unread: 0, today: 0, by_type: {}, by_status: {}, recent: [] });
const insightTypes = ref([]);

const filters = reactive({ status: '', type: '' });
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const insightTypeMap = computed(() => {
  const map = {};
  (insightTypes.value || []).forEach(t => { map[t.type || t] = t; });
  return map;
});

// ── 工具函数 ──
const TYPE_TAG = { follow_up: 'primary', reminder: 'warning', suggestion: 'success', insight: 'info', alert: 'danger' };
const STATUS_TAG = { pending: 'danger', sent: 'warning', read: 'info', dismissed: '' };

function typeLabel(t) { return insightTypeMap.value[t]?.label || t; }
function tagType(t) { return TYPE_TAG[t] || ''; }
function statusTag(s) { return STATUS_TAG[s] || ''; }
function statusLabel(s) { return { pending: '待处理', sent: '已推送', read: '已读', dismissed: '已忽略' }[s] || s; }
function formatTime(t) { return t ? new Date(t).toLocaleString('zh-CN') : ''; }

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
  } catch { ElMessage.error('加载失败'); }
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
    ElMessage.success('已标记为已读');
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error('操作失败'); }
}

async function handleDismiss(row) {
  try {
    await proactiveApi.dismiss(row.id);
    ElMessage.success('已忽略');
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error('操作失败'); }
}

async function handleMarkAllRead() {
  try {
    await proactiveApi.markAllRead();
    ElMessage.success('全部标记为已读');
    await fetchStats();
    await fetchList();
  } catch { ElMessage.error('操作失败'); }
}

// ── 初始化 ──
onMounted(() => {
  fetchStats();
  fetchList();
});
</script>

<style scoped>
.stat-card { text-align: center; }
.stat-value { font-size: 32px; font-weight: 700; color: #409eff; }
.stat-value.text-warning { color: #e6a23c; }
.stat-value.text-primary { color: #409eff; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
