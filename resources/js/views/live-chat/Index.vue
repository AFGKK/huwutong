<template>
  <div class="live-chat-admin">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><ChatDotSquare /></el-icon>
        在线客服管理
      </h2>
      <el-button type="primary" @click="refreshAll" :loading="loading">
        <el-icon><Refresh /></el-icon> 刷新
      </el-button>
    </div>

    <!-- 指标 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-success">{{ stats.active }}</div>
          <div class="stat-label">活跃会话</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-warning">{{ stats.waiting }}</div>
          <div class="stat-label">等待中</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value stat-danger">{{ stats.pending_handoffs }}</div>
          <div class="stat-label">待转人工</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ stats.avg_rating ? stats.avg_rating.toFixed(1) + ' ⭐' : '—' }}</div>
          <div class="stat-label">平均评分</div>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="8">
        <el-card shadow="hover">
          <template #header><span>待处理转人工</span></template>
          <div v-if="pendingHandoffs.length">
            <div v-for="h in pendingHandoffs" :key="h.id" class="handoff-item">
              <div class="handoff-info">
                <span class="handoff-reason">{{ h.reason || '未指定' }}</span>
                <span class="handoff-time">{{ formatTime(h.handoff_at) }}</span>
              </div>
              <el-button size="small" type="primary" @click="acceptHandoff(h)">接单</el-button>
            </div>
          </div>
          <el-empty v-else description="无待处理" :image-size="50" />
        </el-card>
      </el-col>
      <el-col :span="16">
        <el-card shadow="hover">
          <template #header>
            <span>会话列表</span>
          </template>
          <div class="tab-toolbar">
            <el-select v-model="convFilter.status" placeholder="状态" clearable style="width:130px" @change="loadConversations">
              <el-option label="全部" value="" />
              <el-option label="活跃" value="active" />
              <el-option label="等待转人工" value="handoff" />
              <el-option label="已关闭" value="closed" />
            </el-select>
          </div>
          <el-table :data="conversations" stripe v-loading="convLoading" size="small">
            <el-table-column label="会话ID" width="180">
              <template #default="{ row }"><span class="font-mono text-sm">{{ row.session_id }}</span></template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'active' ? 'success' : row.status === 'handoff' ? 'warning' : 'info'" size="small">
                  {{ { active: '活跃', handoff: '转人工', closed: '已关闭' }[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="来源" prop="source" width="80" />
            <el-table-column label="客服" width="100">
              <template #default="{ row }">{{ row.assigned_to ? '#' + row.assigned_to : '—' }}</template>
            </el-table-column>
            <el-table-column label="消息数" width="80" align="center">
              <template #default="{ row }">{{ row.messages?.[0] ? '有' : '0' }}</template>
            </el-table-column>
            <el-table-column label="评分" width="60">
              <template #default="{ row }">{{ row.rating ? row.rating + '⭐' : '—' }}</template>
            </el-table-column>
            <el-table-column label="创建时间" width="160">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
          </el-table>
          <div class="pagination-wrap" v-if="convPagination.total > convPagination.per_page">
            <el-pagination
              v-model:current-page="convPagination.current_page"
              :page-size="convPagination.per_page"
              :total="convPagination.total"
              layout="prev, pager, next"
              @current-change="loadConversations"
            />
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { ChatDotSquare, Refresh } from '@element-plus/icons-vue';
import liveChatApi from '@/api/liveChat';

const loading = ref(false);
const convLoading = ref(false);

const stats = ref({ active: 0, waiting: 0, closed_today: 0, avg_rating: null, pending_handoffs: 0 });
const pendingHandoffs = ref([]);
const conversations = ref([]);
const convFilter = reactive({ status: '' });
const convPagination = reactive({ current_page: 1, per_page: 20, total: 0 });

onMounted(() => { refreshAll(); });

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, handoffRes] = await Promise.all([
      liveChatApi.getDashboard(),
      liveChatApi.getPendingHandoffs(),
    ]);
    stats.value = dashRes.data?.data || {};
    pendingHandoffs.value = handoffRes.data?.data || [];
  } finally { loading.value = false; }
  loadConversations();
}

async function loadConversations() {
  convLoading.value = true;
  try {
    const res = await liveChatApi.listConversations({ ...convFilter, page: convPagination.current_page });
    const pageData = res.data?.data || {};
    conversations.value = pageData.data || [];
    Object.assign(convPagination, {
      current_page: pageData.current_page || 1,
      per_page: pageData.per_page || 20,
      total: pageData.total || 0,
    });
  } finally { convLoading.value = false; }
}

async function acceptHandoff(h) {
  try {
    await liveChatApi.acceptHandoff(h.id);
    ElMessage.success('已接单');
    refreshAll();
  } catch { ElMessage.error('接单失败'); }
}

function formatTime(t) {
  if (!t) return '—';
  return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
</script>

<style scoped>
.live-chat-admin { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 20px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 28px; font-weight: 700; color: #303133; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.stat-success { color: #67C23A; }
.stat-danger { color: #F56C6C; }
.stat-warning { color: #E6A23C; }
.tab-toolbar { display: flex; align-items: center; margin-bottom: 12px; }
.pagination-wrap { display: flex; justify-content: flex-end; margin-top: 12px; }
.handoff-item { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.handoff-info { display: flex; flex-direction: column; gap: 2px; }
.handoff-reason { font-size: 13px; }
.handoff-time { font-size: 11px; color: #909399; }
.font-mono { font-family: 'SFMono-Regular', Consolas, monospace; }
</style>
