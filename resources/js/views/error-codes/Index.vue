<template>
  <div class="error-code-reference">
    <!-- 页面头部 -->
    <div class="page-header">
      <h2>{{ t('error_codes_page.title') }}</h2>
      <p class="text-muted">{{ t('error_codes_page.subtitle') }}</p>
    </div>

    <!-- 统计概览 -->
    <el-row :gutter="16" class="stats-row">
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.total }}</div>
            <div class="stat-label">{{ t('error_codes_page.stat_total') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ Object.keys(stats.by_domain || {}).length }}</div>
            <div class="stat-label">{{ t('error_codes_page.stat_domains') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">{{ stats.retry_safe_count }}</div>
            <div class="stat-label">{{ t('error_codes_page.stat_retry_safe') }}</div>
          </div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover">
          <div class="stat-card">
            <div class="stat-value">
              {{ Object.keys(stats.by_http_status || {}).length }}
            </div>
            <div class="stat-label">{{ t('error_codes_page.stat_http_status_types') }}</div>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 搜索 & 筛选 -->
    <el-row :gutter="16" class="toolbar-row">
      <el-col :span="12">
        <el-input
          v-model="searchQuery"
          :placeholder="t('error_codes_page.search_ph')"
          clearable
          @input="onSearch"
        >
          <template #prefix>
            <el-icon><Search /></el-icon>
          </template>
        </el-input>
      </el-col>
      <el-col :span="4">
        <el-select v-model="activeDomain" :placeholder="t('error_codes_page.filter_domain_ph')" clearable filterable @change="filterData">
          <el-option
            v-for="domain in domains"
            :key="domain"
            :label="domainLabel(domain)"
            :value="domain"
          />
        </el-select>
      </el-col>
      <el-col :span="4">
        <el-select v-model="statusFilter" :placeholder="t('error_codes_page.filter_status_ph')" clearable @change="filterData">
          <el-option
            v-for="opt in statusFilterPresets"
            :key="opt.value"
            :label="opt.label"
            :value="opt.value"
          />
          <el-option v-for="s in Object.keys(stats.by_http_status || {}).sort()" :key="s" :label="s" :value="s" />
        </el-select>
      </el-col>
      <el-col :span="4" class="text-right">
        <el-switch
          v-model="showRetrySafe"
          :active-text="t('error_codes_page.switch_retry_only')"
          :inactive-text="t('error_codes_page.switch_all')"
          @change="filterData"
        />
      </el-col>
    </el-row>

    <!-- 错误码列表 -->
    <div v-loading="loading" class="error-code-list">
      <template v-if="Object.keys(filteredDomains).length > 0">
        <el-card v-for="(codes, domain) in filteredDomains" :key="domain" class="domain-card">
          <template #header>
            <div class="domain-header">
              <span class="domain-badge">{{ domainLabel(domain) }}</span>
              <el-tag size="small" type="info">{{ t('error_codes_page.codes_count', { n: codes.length }) }}</el-tag>
            </div>
          </template>

          <el-table :data="codes" stripe style="width: 100%" size="small">
            <el-table-column :label="t('error_codes_page.col_code')" width="260">
              <template #default="{ row }">
                <el-tag
                  :type="row.http_status >= 500 ? 'danger' : row.http_status >= 400 ? 'warning' : 'info'"
                  effect="plain"
                >
                  <code>{{ row.code }}</code>
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('error_codes_page.col_message')" min-width="300">
              <template #default="{ row }">
                <span>{{ row.message }}</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('error_codes_page.col_http_status')" width="100" align="center">
              <template #default="{ row }">
                <el-tag :type="statusType(row.http_status)" size="small" effect="dark">
                  {{ row.http_status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('error_codes_page.col_retry')" width="80" align="center">
              <template #default="{ row }">
                <el-tag v-if="row.retry_safe" type="success" size="small" effect="plain">{{ t('error_codes_page.yes') }}</el-tag>
                <el-tag v-else type="info" size="small" effect="plain">{{ t('error_codes_page.no') }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('error_codes_page.col_actions')" width="100" align="center">
              <template #default="{ row }">
                <el-button link type="primary" size="small" @click="showDetail(row)">{{ t('actions.view_details') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-card>
      </template>
      <el-empty v-else :description="t('error_codes_page.empty')" />
    </div>

    <!-- 详情对话框 -->
    <el-dialog v-model="detailVisible" :title="t('error_codes_page.detail_dialog_title')" width="600px">
      <template v-if="selectedCode">
        <el-descriptions :column="2" border>
          <el-descriptions-item :label="t('error_codes_page.col_code')" :span="2">
            <el-tag type="danger">{{ selectedCode.code }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('error_codes_page.detail_domain')">{{ domainLabel(selectedCode.domain) }}</el-descriptions-item>
          <el-descriptions-item :label="t('error_codes_page.detail_http_status')">
            <el-tag :type="statusType(selectedCode.http_status)" size="small">
              {{ selectedCode.http_status }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('error_codes_page.detail_message')" :span="2">
            {{ selectedCode.message }}
          </el-descriptions-item>
          <el-descriptions-item :label="t('error_codes_page.detail_retry_safe')">
            <el-tag v-if="selectedCode.retry_safe" type="success">{{ t('error_codes_page.yes') }}</el-tag>
            <el-tag v-else type="info">{{ t('error_codes_page.no') }}</el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('error_codes_page.detail_error_type')">
            <el-tag v-if="selectedCode.http_status >= 500" type="danger">{{ t('error_codes_page.error_type_server') }}</el-tag>
            <el-tag v-else type="warning">{{ t('error_codes_page.error_type_client') }}</el-tag>
          </el-descriptions-item>
        </el-descriptions>

        <div class="detail-sdk-note">
          <h4>{{ t('error_codes_page.sdk_guide_title') }}</h4>
          <p>
            {{ t('error_codes_page.sdk_guide_p1') }}
            <code>X-Error-Code</code>
            {{ t('error_codes_page.sdk_guide_p2') }}
            <code>error.code</code>
            {{ t('error_codes_page.sdk_guide_p3') }}
            {{ selectedCode.retry_safe ? t('error_codes_page.sdk_retry_hint') : t('error_codes_page.sdk_no_retry_hint') }}
          </p>
          <el-alert
            v-if="selectedCode.http_status >= 500"
            :title="t('error_codes_page.server_error_alert_title')"
            type="warning"
            :description="t('error_codes_page.server_error_alert_desc', { code: selectedCode.code })"
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
import { useI18n } from 'vue-i18n';
import { Search } from '@element-plus/icons-vue';
import errorCodesApi from '../../api/errorCodes';
import { ElMessage } from 'element-plus';

const { t } = useI18n();

const loading = ref(false);
const searchQuery = ref('');
const activeDomain = ref('');
const statusFilter = ref('');
const showRetrySafe = ref(false);
const detailVisible = ref(false);
const selectedCode = ref(null);

const rawData = ref({});
const searchResults = ref(null);

const DOMAIN_KEYS = [
  'AUTH', 'SDK', 'LICENSE', 'ACTIVATION', 'API_KEY', 'MFA', 'SSO',
  'RATE_LIMIT', 'SIGNATURE', 'IDEMPOTENT', 'BILLING', 'INVOICE', 'TAX',
  'DEVICE', 'DOMAIN', 'WEBHOOK', 'LLM', 'VALIDATION', 'PERMISSION', 'SYSTEM',
  'TENANT', 'FEATURE_FLAG', 'CUSTOMER', 'TAG', 'FILE', 'ACCOUNT',
  'API_VERSION', 'ERRCODE', 'UNKNOWN', 'TEST',
];

const statusFilterPresets = computed(() => [
  { label: t('error_codes_page.filter_4xx'), value: '4xx' },
  { label: t('error_codes_page.filter_5xx'), value: '5xx' },
]);

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

const domainLabel = (key) => {
  if (DOMAIN_KEYS.includes(key)) {
    return t(`error_codes_page.domains.${key}`);
  }
  return key;
};

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
    ElMessage.error(t('error_codes_page.messages.fetch_failed'));
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
  color: #0f172a;
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
  background: #0f172a;
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
