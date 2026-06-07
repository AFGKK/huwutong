<template>
  <div class="error-code-reference">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2>错误码参考手册</h2>
      <p class="text-muted">互物通标准化 SDK 错误码，按域分组，便于集成和调试</p>
    </div>

    <!-- 统计概览 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">错误码总数</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ Object.keys(stats.by_domain || {}).length }}</div>
            <div class="stat-label">域数量</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.retry_safe_count }}</div>
            <div class="stat-label">可安全重试</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">
              {{ Object.keys(stats.by_http_status || {}).length }}
            </div>
            <div class="stat-label">HTTP 状态码种类</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索 & 语言切换 -->
    <el-row :gutter="16" class="toolbar-row">
      <el-col :span="12">
        <el-input
          v-model="searchQuery"
          placeholder="搜索错误码或消息..."
          clearable
          @input="onSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
      </el-col>
      <el-col :span="4">
        <el-select v-model="activeDomain" placeholder="按域筛选" clearable filterable @change="filterData">
          <el-option
            v-for="domain in domains"
            :key="domain"
            :label="domainLabel(domain)"
            :value="domain"
          />
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-select v-model="statusFilter" placeholder="按状态码" clearable @change="filterData">
          <el-option label="4xx 客户端错误" value="4xx" />
          <el-option label="5xx 服务器错误" value="5xx" />
          <el-option v-for="s in Object.keys(stats.by_http_status || {}).sort()" :key="s" :label="s" :value="s" />
        </el-select>
      </el-col>
      <el-col :span="4" class="text-right">
        <el-switch
          v-model="showRetrySafe"
          active-text="仅可重试"
          inactive-text="全部"
          @change="filterData"
        />
      </el-col>
    </el-row>

    <!-- 错误码列表 -->
    <div v-loading="loading" class="error-code-list">
      <template v-if="filteredDomains.length > 0">
        <el-card v-for="(codes, domain) in filteredDomains" :key="domain" class="domain-card">
          <template #header>
            <div class="domain-header">
              <span class="domain-badge">{{ domainLabel(domain) }}</span>
              <el-tag size="small" type="info">{{ codes.length }} 个错误码</el-tag>
            </div>
          </template>

          <el-table :data="codes" stripe style="width: 100%" size="small">
            <el-table-column label="错误码" width="260">
              <template #default="{ row }">
                <el-tag
                  :type="row.http_status >= 500 ? 'danger' : row.http_status >= 400 ? 'warning' : 'info'"
                  effect="plain"
                >
                  <code>{{ row.code }}</code>
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="消息描述" min-width="300">
              <template #default="{ row }">
                <span>{{ row.message }}</span>
              </template>
            </el-table-column>
            <el-table-column label="HTTP 状态" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="statusType(row.http_status)" size="small" effect="dark">
                  {{ row.http_status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="可重试" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.retry_safe" type="success" size="small" effect="plain">是</el-tag>
                <el-tag v-else type="info" size="small" effect="plain">否</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="80" align="center">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="showDetail(row)">详情</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </template>
      <el-empty v-else description="未找到匹配的错误码" />
    </div>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailVisible" title="错误码详情" width="600px">
      <template v-if="selectedCode">
        <el-descriptions :column="2" border>
          <el-descriptions-item label="错误码" :span="2">
            <el-tag type="danger">{{ selectedCode.code }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="所属域">{{ domainLabel(selectedCode.domain) }}</el-descriptions-item>
          <el-descriptions-item label="HTTP 状态码">
            <el-tag :type="statusType(selectedCode.http_status)" size="small">
              {{ selectedCode.http_status }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="消息描述" :span="2">
            {{ selectedCode.message }}
          </el-descriptions-item>
          <el-descriptions-item label="可安全重试">
            <el-tag v-if="selectedCode.retry_safe" type="success">是</el-tag>
            <el-tag v-else type="info">否</el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="错误类型">
            <el-tag v-if="selectedCode.http_status >= 500" type="danger">服务器错误</el-tag>
            <el-tag v-else type="warning">客户端错误</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <div class="detail-sdk-note">
          <h4>SDK 集成指南</h4>
          <p>
            错误码由 <code>X-Error-Code</code> 响应头或响应体中的 <code>error.code</code> 字段返回。
            {{ selectedCode.retry_safe ? '此错误可安全重试，建议使用指数退避策略。' : '此错误不可重试，请检查请求参数后重试。' }}
          </p>
          <el-alert
            v-if="selectedCode.http_status >= 500"
            title="服务器错误"
            type="warning"
            :description="'错误码 ' + selectedCode.code + ' 表示服务器端问题，请联系互物通技术支持。'"
            show-icon
            :closable="false"
          />
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Search } from '@element-plus/icons-vue';
import errorCodesApi from '../../api/errorCodes';
import { ElMessage } from 'element-plus';

const loading = ref(false);
const searchQuery = ref('');
const activeDomain = ref('');
const statusFilter = ref('');
const showRetrySafe = ref(false);
const detailVisible = ref(false);
const selectedCode = ref(null);

const rawData = ref({});
const searchResults = ref(null);

const domainLabels = {
  AUTH: '认证与授权',
  SDK: 'SDK 通用',
  LICENSE: 'License',
  ACTIVATION: '激活/离线',
  API_KEY: 'API 密钥',
  MFA: '多因素认证',
  SSO: '单点登录',
  RATE_LIMIT: '频率限制',
  SIGNATURE: '签名验证',
  IDEMPOTENT: '幂等请求',
  BILLING: '计费与订阅',
  INVOICE: '发票',
  TAX: '税务',
  DEVICE: '设备',
  DOMAIN: '自定义域名',
  WEBHOOK: 'Webhook',
  LLM: 'AI/LLM',
  VALIDATION: '验证',
  PERMISSION: '权限',
  SYSTEM: '系统内部',
  TENANT: '租户',
  FEATURE_FLAG: 'Feature Flag',
  CUSTOMER: '客户',
  TAG: '标签',
  FILE: '文件',
  ACCOUNT: '账户操作',
  API_VERSION: 'API 版本',
  ERRCODE: '错误码系统',
  UNKNOWN: '未知',
  TEST: '测试',
};

const domains = computed(() => Object.keys(rawData.value));

const filteredDomains = computed(() => {
  let data = rawData.value;

  // 搜索模式
  if (searchResults.value !== null) {
    data = searchResults.value;
  }

  const result = {};
  for (const [domain, codes] of Object.entries(data)) {
    let filtered = codes;

    if (activeDomain.value && domain !== activeDomain.value) {
      continue;
    }

    if (statusFilter.value === '4xx') {
      filtered = filtered.filter((c) => c.http_status >= 400 && c.http_status < 500);
    } else if (statusFilter.value === '5xx') {
      filtered = filtered.filter((c) => c.http_status >= 500);
    } else if (statusFilter.value) {
      filtered = filtered.filter((c) => String(c.http_status) === statusFilter.value);
    }

    if (showRetrySafe.value) {
      filtered = filtered.filter((c) => c.retry_safe);
    }

    if (filtered.length > 0) {
      result[domain] = filtered;
    }
  }
  return result;
});

const stats = ref({
  total: 0,
  by_domain: {},
  by_http_status: {},
  retry_safe_count: 0,
});

const statusType = (status) => {
  if (status >= 500) return 'danger';
  if (status >= 400) return 'warning';
  if (status >= 300) return 'info';
  return 'success';
};

const domainLabel = (key) => domainLabels[key] || key;

const fetchData = async () => {
  loading.value = true;
  try {
    const [codesRes, statsRes] = await Promise.all([
      errorCodesApi.getByDomain(),
      errorCodesApi.getStats(),
    ]);
    rawData.value = codesRes.data?.data || {};
    stats.value = statsRes.data?.data || stats.value;
  } catch (e) {
    ElMessage.error('获取错误码列表失败');
  } finally {
    loading.value = false;
  }
};

const onSearch = async (query) => {
  if (!query) {
    searchResults.value = null;
    return;
  }
  try {
    const res = await errorCodesApi.search(query);
    const results = res.data?.data || [];

    // 按域重新分组搜索结果
    const grouped = {};
    for (const item of results) {
      const domain = item.domain || 'UNKNOWN';
      if (!grouped[domain]) grouped[domain] = [];
      grouped[domain].push(item);
    }
    searchResults.value = grouped;
  } catch {
    searchResults.value = null;
  }
};

const filterData = () => {
  // 计算属性会自动处理
};

const showDetail = (code) => {
  selectedCode.value = code;
  detailVisible.value = true;
};

onMounted(fetchData);
</script>

<style scoped>
.error-code-reference {
  padding: 20px;
}

.page-header {
  margin-bottom: 24px;
}

.page-header h2 {
  margin: 0 0 8px;
  font-size: 24px;
}

.text-muted {
  color: #909399;
  font-size: 14px;
  margin: 0;
}

.stats-row {
  margin-bottom: 16px;
}

.stat-card {
  text-align: center;
  padding: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #409eff;
}

.stat-label {
  font-size: 13px;
  color: #909399;
  margin-top: 4px;
}

.toolbar-row {
  margin-bottom: 16px;
}

.text-right {
  display: flex;
  align-items: center;
  justify-content: flex-end;
}

.domain-card {
  margin-bottom: 16px;
}

.domain-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.domain-badge {
  font-size: 16px;
  font-weight: 600;
}

.domain-badge::before {
  content: '';
  display: inline-block;
  width: 4px;
  height: 16px;
  background: #409eff;
  border-radius: 2px;
  margin-right: 8px;
  vertical-align: middle;
}

.detail-sdk-note {
  margin-top: 20px;
}

.detail-sdk-note h4 {
  margin: 0 0 8px;
  font-size: 15px;
}

.detail-sdk-note code {
  background: #f5f7fa;
  padding: 2px 6px;
  border-radius: 3px;
  font-size: 13px;
}
</style>
