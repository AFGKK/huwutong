<template>
  <div class="customer-audit-logs">
    <!-- 页面头部 -->
    <div class="page-header">
      <div class="header-left">
        <h2>操作审计日志</h2>
        <span class="header-subtitle">查看团队内所有关键操作记录</span>
      </div>
      <div class="header-right">
        <el-button type="primary" @click="handleExport">
          <el-icon><Download /></el-icon> 导出 CSV
        </el-button>
        <el-button @click="refreshData">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <!-- 统计概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">总操作数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value today">{{ stats.today }}</div>
            <div class="stat-label">今日操作</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value week">{{ stats.this_week }}</div>
            <div class="stat-label">本周操作</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-box">
            <div class="stat-value month">{{ stats.this_month }}</div>
            <div class="stat-label">本月操作</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 筛选区 -->
    <el-card shadow="never" class="mb-4">
      <el-form :inline="true" :model="filters" size="small">
        <el-form-item label="操作类别">
          <el-select
            v-model="filters.action_prefix"
            placeholder="全部操作"
            clearable
            style="width: 160px"
            @change="handleFilterChange"
          >
            <el-option label="全部操作" value="" />
            <el-option
              v-for="(cat, key) in actionCategories"
              :key="key"
              :label="cat.label"
              :value="cat.prefix"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="具体操作">
          <el-select
            v-model="filters.action"
            placeholder="全部"
            clearable
            style="width: 180px"
            @change="handleFilterChange"
          >
            <el-option label="全部" value="" />
            <template v-for="(cat, key) in actionCategories" :key="key">
              <el-option
                v-for="(label, act) in cat.actions"
                :key="act"
                :label="label"
                :value="act"
              />
            </template>
          </el-select>
        </el-form-item>
        <el-form-item label="时间范围">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="至"
            start-placeholder="开始日期"
            end-placeholder="结束日期"
            value-format="YYYY-MM-DD"
            style="width: 260px"
            @change="handleDateChange"
          />
        </el-form-item>
        <el-form-item label="关键词">
          <el-input
            v-model="filters.search"
            placeholder="搜索操作描述..."
            clearable
            style="width: 200px"
            @keyup.enter="handleFilterChange"
            @clear="handleFilterChange"
          >
            <template #prefix>
              <el-icon><Search /></el-icon>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleFilterChange">查询</el-button>
          <el-button @click="resetFilters">重置</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 审计日志列表 -->
    <el-card shadow="never">
      <el-table
        :data="logs"
        v-loading="loading"
        stripe
        style="width: 100%"
        @sort-change="handleSortChange"
      >
        <el-table-column
          prop="created_at"
          label="时间"
          width="170"
          sortable="custom"
        >
          <template #default="{ row }">
            <span class="log-time">{{ formatTime(row.created_at) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="操作人" width="150">
          <template #default="{ row }">
            <div class="user-info" v-if="row.user">
              <el-tooltip :content="row.user.email" placement="top">
                <el-avatar :size="24" class="user-avatar">
                  {{ row.user.name?.charAt(0) || '?' }}
                </el-avatar>
              </el-tooltip>
              <span class="user-name">{{ row.user.name || row.user.email }}</span>
            </div>
            <span v-else class="text-muted">系统</span>
          </template>
        </el-table-column>
        <el-table-column prop="action" label="操作类型" width="140">
          <template #default="{ row }">
            <el-tag
              :type="getActionTagType(row.action)"
              size="small"
              effect="plain"
            >
              {{ getActionLabel(row.action) }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column prop="description" label="操作描述" min-width="250">
          <template #default="{ row }">
            <div class="log-description">{{ row.description }}</div>
          </template>
        </el-table-column>
        <el-table-column prop="ip_address" label="IP 地址" width="140">
          <template #default="{ row }">
            <code class="ip-address">{{ row.ip_address || '-' }}</code>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button
              link
              type="primary"
              size="small"
              @click="showDetail(row)"
            >
              详情
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrapper">
        <el-pagination
          v-model:current-page="currentPage"
          v-model:page-size="perPage"
          :page-sizes="[10, 20, 50, 100]"
          :total="total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>

    <!-- 详情抽屉 -->
    <el-drawer
      v-model="detailVisible"
      title="审计日志详情"
      size="500px"
    >
      <template v-if="detailLog">
        <div class="detail-section">
          <div class="detail-item">
            <label>操作时间</label>
            <span>{{ formatTime(detailLog.created_at) }}</span>
          </div>
          <div class="detail-item">
            <label>操作类型</label>
            <el-tag
              :type="getActionTagType(detailLog.action)"
              size="small"
            >
              {{ getActionLabel(detailLog.action) }}
            </el-tag>
          </div>
          <div class="detail-item">
            <label>操作描述</label>
            <span>{{ detailLog.description }}</span>
          </div>
          <div class="detail-item" v-if="detailLog.user">
            <label>操作人</label>
            <span>{{ detailLog.user.name || detailLog.user.email }}</span>
          </div>
          <div class="detail-item" v-if="detailLog.user">
            <label>邮箱</label>
            <span>{{ detailLog.user.email }}</span>
          </div>
          <div class="detail-item">
            <label>IP 地址</label>
            <code>{{ detailLog.ip_address || '-' }}</code>
          </div>
          <div class="detail-item">
            <label>User-Agent</label>
            <div class="ua-text">{{ detailLog.user_agent || '-' }}</div>
          </div>
          <div class="detail-item" v-if="detailLog.payload">
            <label>负载数据</label>
            <pre class="payload-json">{{ JSON.stringify(detailLog.payload, null, 2) }}</pre>
          </div>
        </div>
      </template>
    </el-drawer>
  </div>
</template>

<script>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Download, Refresh, Search } from '@element-plus/icons-vue';
import customerAuditLogApi from '../../api/customerAuditLog';

export default {
  name: 'CustomerAuditLogs',
  components: { Download, Refresh, Search },
  setup() {
    const loading = ref(false);
    const logs = ref([]);
    const total = ref(0);
    const currentPage = ref(1);
    const perPage = ref(20);
    const sortOrder = ref('-created_at');

    const stats = reactive({
      total: 0,
      today: 0,
      this_week: 0,
      this_month: 0,
    });

    const filters = reactive({
      action_prefix: '',
      action: '',
      user_id: '',
      search: '',
      sort: '-created_at',
    });

    const dateRange = ref(null);
    const actionCategories = ref({});

    const detailVisible = ref(false);
    const detailLog = ref(null);

    function getActionLabel(action) {
      for (const cat of Object.values(actionCategories.value)) {
        if (cat.actions && cat.actions[action]) {
          return cat.actions[action];
        }
      }
      // fallback: 从 action 推断
      const parts = action.split('.');
      const label = parts[parts.length - 1].replace(/_/g, ' ');
      return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function getActionTagType(action) {
      const prefix = action.split('.')[0];
      const typeMap = {
        license: 'primary',
        device: 'success',
        team: 'warning',
        member: 'warning',
        payment: 'danger',
        billing: 'danger',
        security: 'info',
        setting: '',
      };
      return typeMap[prefix] || '';
    }

    function formatTime(time) {
      if (!time) return '-';
      const d = new Date(time);
      const pad = (n) => String(n).padStart(2, '0');
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    }

    async function fetchLogs() {
      loading.value = true;
      try {
        const params = {
          per_page: perPage.value,
          page: currentPage.value,
          sort: sortOrder.value,
        };
        if (filters.action_prefix) params['filter[action_prefix]'] = filters.action_prefix;
        if (filters.action) params['filter[action]'] = filters.action;
        if (filters.search) params['search'] = filters.search;
        if (dateRange.value) {
          params['date_from'] = dateRange.value[0];
          params['date_to'] = dateRange.value[1];
        }

        const response = await customerAuditLogApi.list(params);
        const data = response.data;
        logs.value = data.data || [];
        total.value = data.meta?.total || data.total || 0;
      } catch (err) {
        console.error('Failed to fetch audit logs:', err);
        ElMessage.error('获取审计日志失败');
      } finally {
        loading.value = false;
      }
    }

    async function fetchStats() {
      try {
        const response = await customerAuditLogApi.stats();
        const data = response.data;
        Object.assign(stats, data);
      } catch (err) {
        console.error('Failed to fetch stats:', err);
      }
    }

    async function fetchActionCategories() {
      try {
        const response = await customerAuditLogApi.actionCategories();
        actionCategories.value = response.data || {};
      } catch (err) {
        console.error('Failed to fetch action categories:', err);
      }
    }

    function handleFilterChange() {
      currentPage.value = 1;
      fetchLogs();
    }

    function resetFilters() {
      filters.action_prefix = '';
      filters.action = '';
      filters.search = '';
      dateRange.value = null;
      currentPage.value = 1;
      fetchLogs();
    }

    function handleSortChange({ prop, order }) {
      if (!prop) return;
      sortOrder.value = order === 'descending' ? `-${prop}` : prop;
      filters.sort = sortOrder.value;
      fetchLogs();
    }

    function handlePageChange(page) {
      currentPage.value = page;
      fetchLogs();
    }

    function handleSizeChange(size) {
      perPage.value = size;
      currentPage.value = 1;
      fetchLogs();
    }

    function handleDateChange() {
      currentPage.value = 1;
      fetchLogs();
    }

    async function showDetail(row) {
      try {
        const response = await customerAuditLogApi.detail(row.id);
        detailLog.value = response.data;
        detailVisible.value = true;
      } catch (err) {
        ElMessage.error('获取日志详情失败');
      }
    }

    async function handleExport() {
      try {
        const params = {};
        if (filters.action_prefix) params['filter[action_prefix]'] = filters.action_prefix;
        if (filters.action) params['filter[action]'] = filters.action;
        if (dateRange.value) {
          params['date_from'] = dateRange.value[0];
          params['date_to'] = dateRange.value[1];
        }

        const blob = await customerAuditLogApi.exportCsv(params);
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `audit-logs-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        ElMessage.success('导出成功');
      } catch (err) {
        ElMessage.error('导出失败');
      }
    }

    async function refreshData() {
      await Promise.all([fetchLogs(), fetchStats()]);
    }

    onMounted(() => {
      fetchActionCategories();
      fetchLogs();
      fetchStats();
    });

    return {
      loading,
      logs,
      total,
      currentPage,
      perPage,
      filters,
      dateRange,
      actionCategories,
      stats,
      detailVisible,
      detailLog,
      getActionLabel,
      getActionTagType,
      formatTime,
      handleFilterChange,
      resetFilters,
      handleSortChange,
      handlePageChange,
      handleSizeChange,
      handleDateChange,
      showDetail,
      handleExport,
      refreshData,
    };
  },
};
</script>

<style scoped>
.customer-audit-logs {
  padding: 20px;
}

.mb-4 {
  margin-bottom: 16px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.header-left h2 {
  margin: 0;
  font-size: 20px;
  font-weight: 600;
}

.header-subtitle {
  font-size: 13px;
  color: #909399;
  margin-left: 12px;
}

.stat-box {
  text-align: center;
  padding: 8px 0;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #303133;
}

.stat-value.today {
  color: #409eff;
}

.stat-value.week {
  color: #67c23a;
}

.stat-value.month {
  color: #e6a23c;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.log-time {
  font-family: 'SF Mono', 'Fira Code', monospace;
  font-size: 13px;
  color: #606266;
}

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.user-avatar {
  flex-shrink: 0;
}

.user-name {
  font-size: 13px;
  color: #303133;
}

.text-muted {
  color: #c0c4cc;
  font-size: 13px;
}

.log-description {
  font-size: 13px;
  color: #303133;
  line-height: 1.5;
}

.ip-address {
  font-size: 12px;
  color: #606266;
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 3px;
}

.pagination-wrapper {
  margin-top: 16px;
  display: flex;
  justify-content: flex-end;
}

.detail-section {
  padding: 0 8px;
}

.detail-item {
  margin-bottom: 16px;
}

.detail-item label {
  display: block;
  font-size: 12px;
  color: #909399;
  margin-bottom: 4px;
  font-weight: 500;
}

.detail-item > span,
.detail-item > code {
  font-size: 14px;
  color: #303133;
}

.ua-text {
  font-size: 12px;
  color: #606266;
  word-break: break-all;
  line-height: 1.5;
}

.payload-json {
  font-size: 12px;
  background: #f5f7fa;
  padding: 12px;
  border-radius: 4px;
  overflow-x: auto;
  max-height: 300px;
  white-space: pre-wrap;
  word-break: break-all;
}
</style>
