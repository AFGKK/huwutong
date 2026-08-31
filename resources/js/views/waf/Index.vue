<template>
  <div class="waf-page">
    <!-- 顶部操作栏 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">{{ t('waf_page.title') }}</h2>
        <p class="page-desc text-secondary">{{ t('waf_page.subtitle') }}</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>{{ t('waf_page.refresh') }}
        </el-button>
      </el-col>
    </el-row>

    <!-- 状态概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.stats.today_blocked') }}</div>
          <div class="stat-value text-danger">{{ dashboard?.today?.blocked || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.stats.today_detected') }}</div>
          <div class="stat-value text-warning">{{ dashboard?.today?.detected || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.stats.active_rules') }}</div>
          <div class="stat-value">{{ dashboard?.rules?.active || 0 }}<small class="text-secondary"> / {{ dashboard?.rules?.total || 0 }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.stats.blacklist_ips') }}</div>
          <div class="stat-value">{{ dashboard?.ip_lists?.blacklist || 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 模式状态 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.protection_mode') }}</div>
          <div>
            <el-tag :type="modeTagType(dashboard?.mode)" effect="dark" size="large">{{ modeLabel(dashboard?.mode) }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.cc_protection') }}</div>
          <div>
            <el-tag :type="dashboard?.cc_enabled ? 'success' : 'info'" size="large">
              {{ dashboard?.cc_enabled ? t('waf_page.status.enabled') : t('waf_page.status.disabled') }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">{{ t('waf_page.rules_engine') }}</div>
          <div>
            <el-tag :type="dashboard?.rules_enabled ? 'success' : 'info'" size="large">
              {{ dashboard?.rules_enabled ? t('waf_page.status.enabled') : t('waf_page.status.disabled') }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主面板：Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 仪表盘 -->
        <el-tab-pane :label="t('waf_page.tabs.overview')" name="overview">
          <el-row :gutter="16">
            <el-col :span="12">
              <h4 class="mb-2">{{ t('waf_page.overview.today_categories') }}</h4>
              <el-table :data="categoryChartData" border stripe size="small">
                <el-table-column prop="label" :label="t('waf_page.columns.category')" />
                <el-table-column prop="total" :label="t('waf_page.columns.count')" width="100" />
                <el-table-column :label="t('waf_page.columns.share')" width="160">
                  <template #default="{ row }">
                    <el-progress
                      :percentage="row.percent"
                      :stroke-width="12"
                      :status="row.total > 100 ? 'exception' : 'warning'"
                    />
                  </template>
                </el-table-column>
              </el-table>
            </el-col>
            <el-col :span="12">
              <h4 class="mb-2">{{ t('waf_page.overview.severity_distribution') }}</h4>
              <el-table :data="severityChartData" border stripe size="small">
                <el-table-column prop="label" :label="t('waf_page.columns.level')" />
                <el-table-column prop="total" :label="t('waf_page.columns.count')" width="100" />
                <el-table-column :label="t('waf_page.columns.status')" width="120">
                  <template #default="{ row }">
                    <el-tag :type="row.type" size="small">{{ row.label }}</el-tag>
                  </template>
                </el-table-column>
              </el-table>
            </el-col>
          </el-row>

          <h4 class="mt-4 mb-2">{{ t('waf_page.overview.top_attack_ips') }}</h4>
          <el-table :data="dashboard?.top_ips || []" border stripe size="small">
            <el-table-column prop="ip" :label="t('waf_page.columns.ip_address')" min-width="160" />
            <el-table-column prop="total" :label="t('waf_page.columns.attack_count')" width="100" />
            <el-table-column prop="max_severity" :label="t('waf_page.columns.max_severity')" width="120">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.max_severity)" size="small">{{ row.max_severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('waf_page.columns.actions')" width="120" fixed="right">
              <template #default="{ row }">
                <el-button text type="danger" size="small" @click="handleAddBlacklist(row.ip)">{{ t('waf_page.row_actions.add_blacklist') }}</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 规则管理 -->
        <el-tab-pane :label="t('waf_page.tabs.rules')" name="rules">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="showCreateRule = true">
              <el-icon class="mr-1"><Plus /></el-icon>{{ t('waf_page.row_actions.create_rule') }}
            </el-button>
            <el-button size="small" @click="handleSeedRules" :loading="seeding">
              <el-icon class="mr-1"><Download /></el-icon>{{ t('waf_page.row_actions.import_defaults') }}
            </el-button>
            <el-select v-model="ruleFilter.category" :placeholder="t('waf_page.filters.category')" clearable size="small" class="ml-2" style="width:140px" @change="loadRules">
              <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
            </el-select>
          </div>
          <el-table :data="rules" border stripe size="small" :loading="ruleLoading">
            <el-table-column prop="name" :label="t('waf_page.columns.rule_name')" min-width="180" />
            <el-table-column prop="category" :label="t('waf_page.columns.category')" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ categoryOptions[row.category] || row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="severity" :label="t('waf_page.columns.severity')" width="100">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="mode" :label="t('waf_page.columns.mode')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.mode === 'block' ? 'danger' : 'warning'" size="small">{{ row.mode }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="hit_count" :label="t('waf_page.columns.hits')" width="70" />
            <el-table-column prop="is_active" :label="t('waf_page.columns.status')" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="handleToggleRule(row)" size="small" />
              </template>
            </el-table-column>
            <el-table-column prop="priority" :label="t('waf_page.columns.priority')" width="70" />
            <el-table-column :label="t('waf_page.columns.actions')" width="140" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="handleEditRule(row)">{{ t('actions.edit') }}</el-button>
                <el-popconfirm :title="t('waf_page.confirm_delete')" @confirm="handleDeleteRule(row)">
                  <template #reference>
                    <el-button text type="danger" size="small">{{ t('actions.delete') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: IP 黑白名单 -->
        <el-tab-pane :label="t('waf_page.tabs.ip_list')" name="ipList">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="showAddIp = true">
              <el-icon class="mr-1"><Plus /></el-icon>{{ t('waf_page.row_actions.add_ip') }}
            </el-button>
            <el-button size="small" @click="showBatchAddIp = true">
              <el-icon class="mr-1"><Upload /></el-icon>{{ t('waf_page.row_actions.batch_add') }}
            </el-button>
            <el-button size="small" @click="showCheckIp = true">
              <el-icon class="mr-1"><Search /></el-icon>{{ t('waf_page.row_actions.ip_lookup') }}
            </el-button>
            <el-radio-group v-model="ipTypeFilter" size="small" class="ml-2" @change="loadIpList">
              <el-radio-button label="">{{ t('waf_page.filters.all') }}</el-radio-button>
              <el-radio-button label="blacklist">{{ t('waf_page.filters.blacklist') }}</el-radio-button>
              <el-radio-button label="whitelist">{{ t('waf_page.filters.whitelist') }}</el-radio-button>
            </el-radio-group>
          </div>
          <el-table :data="ipList" border stripe size="small" :loading="ipLoading">
            <el-table-column prop="ip" :label="t('waf_page.columns.ip_cidr')" min-width="160" />
            <el-table-column prop="type" :label="t('waf_page.columns.type')" width="100">
              <template #default="{ row }">
                <el-tag :type="row.type === 'blacklist' ? 'danger' : 'success'" size="small">
                  {{ ipTypeLabel(row.type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="source" :label="t('waf_page.columns.source')" width="100" />
            <el-table-column prop="reason" :label="t('waf_page.columns.reason')" min-width="200" />
            <el-table-column prop="hit_count" :label="t('waf_page.columns.hits')" width="60" />
            <el-table-column prop="expires_at" :label="t('waf_page.columns.expires_at')" width="180">
              <template #default="{ row }">{{ row.expires_at || t('waf_page.permanent') }}</template>
            </el-table-column>
            <el-table-column :label="t('waf_page.columns.actions')" width="100" fixed="right">
              <template #default="{ row }">
                <el-popconfirm :title="t('waf_page.confirm_remove')" @confirm="handleDeleteIp(row)">
                  <template #reference>
                    <el-button text type="danger" size="small">{{ t('waf_page.row_actions.remove') }}</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 攻击日志 -->
        <el-tab-pane :label="t('waf_page.tabs.logs')" name="logs">
          <div class="mb-2">
            <el-input v-model="logFilter.ip" :placeholder="t('waf_page.filters.ip_search')" size="small" class="mr-2" style="width:160px" clearable @change="loadLogs" />
            <el-select v-model="logFilter.severity" :placeholder="t('waf_page.filters.severity')" size="small" class="mr-2" style="width:120px" clearable @change="loadLogs">
              <el-option v-for="s in ['critical','high','medium','low']" :key="s" :label="s" :value="s" />
            </el-select>
            <el-button size="small" @click="handlePruneLogs" class="ml-2">
              <el-icon class="mr-1"><Delete /></el-icon>{{ t('waf_page.row_actions.prune_logs') }}
            </el-button>
          </div>
          <el-table :data="logs" border stripe size="small" :loading="logLoading" max-height="600">
            <el-table-column prop="created_at" :label="t('waf_page.columns.time')" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="ip" label="IP" width="150" />
            <el-table-column prop="rule_category" :label="t('waf_page.columns.category')" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ row.rule_category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="severity" :label="t('waf_page.columns.level')" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="action_taken" :label="t('waf_page.columns.action')" width="80">
              <template #default="{ row }">
                <el-tag :type="row.action_taken === 'block' ? 'danger' : 'warning'" size="small">{{ row.action_taken }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="uri" label="URI" min-width="250" show-overflow-tooltip />
            <el-table-column prop="method" :label="t('waf_page.columns.method')" width="70" />
            <el-table-column prop="user_agent" label="User-Agent" min-width="200" show-overflow-tooltip />
          </el-table>
          <div class="mt-2 text-center" v-if="logTotal > logPerPage">
            <el-pagination
              v-model:current-page="logPage"
              :page-size="logPerPage"
              :total="logTotal"
              layout="prev, pager, next"
              size="small"
              @current-change="loadLogs"
            />
          </div>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <!-- 创建/编辑规则对话框 -->
    <el-dialog v-model="showCreateRule" :title="editingRule ? t('waf_page.dialogs.edit_rule') : t('waf_page.dialogs.create_rule')" width="600px">
      <el-form :model="ruleForm" label-width="100px" size="small">
        <el-form-item :label="t('waf_page.form.rule_name')" required>
          <el-input v-model="ruleForm.name" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.category')" required>
          <el-select v-model="ruleForm.category" style="width:100%">
            <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item :label="t('waf_page.form.severity')">
              <el-select v-model="ruleForm.severity">
                <el-option v-for="s in ['low','medium','high','critical']" :key="s" :label="s" :value="s" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('waf_page.form.mode')">
              <el-select v-model="ruleForm.mode">
                <el-option v-for="m in ['block','detect','simulate']" :key="m" :label="m" :value="m" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('waf_page.form.match_type')">
              <el-select v-model="ruleForm.match_type">
                <el-option v-for="mt in ['regex','exact','prefix','suffix','contains']" :key="mt" :label="mt" :value="mt" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('waf_page.form.pattern')" required>
          <el-input v-model="ruleForm.pattern" type="textarea" :rows="3" :placeholder="t('waf_page.form.pattern_ph')" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.target')">
          <el-select v-model="ruleForm.target">
            <el-option v-for="tg in ['all','query','body','headers','cookies','uri']" :key="tg" :label="tg" :value="tg" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('waf_page.form.priority')">
          <el-input-number v-model="ruleForm.priority" :min="1" :max="9999" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.description')">
          <el-input v-model="ruleForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateRule = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="savingRule">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>

    <!-- 添加 IP 对话框 -->
    <el-dialog v-model="showAddIp" :title="t('waf_page.dialogs.add_ip')" width="450px">
      <el-form :model="ipForm" label-width="100px" size="small">
        <el-form-item :label="t('waf_page.form.ip_cidr')" required>
          <el-input v-model="ipForm.ip" :placeholder="t('waf_page.form.ip_cidr_ph')" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.type')" required>
          <el-select v-model="ipForm.type" style="width:100%">
            <el-option :label="t('waf_page.ip_types.blacklist')" value="blacklist" />
            <el-option :label="t('waf_page.ip_types.whitelist')" value="whitelist" />
            <el-option :label="t('waf_page.ip_types.challenge')" value="challenge" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('waf_page.form.reason')">
          <el-input v-model="ipForm.reason" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.expires_at')">
          <el-date-picker v-model="ipForm.expires_at" type="datetime" :placeholder="t('waf_page.form.expires_ph')" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddIp = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleSaveIp" :loading="savingIp">{{ t('waf_page.add') }}</el-button>
      </template>
    </el-dialog>

    <!-- 批量添加 IP 对话框 -->
    <el-dialog v-model="showBatchAddIp" :title="t('waf_page.dialogs.batch_add_ip')" width="500px">
      <el-form :model="batchIpForm" size="small">
        <el-form-item :label="t('waf_page.form.type')" required>
          <el-select v-model="batchIpForm.type" style="width:100%">
            <el-option :label="t('waf_page.ip_types.blacklist')" value="blacklist" />
            <el-option :label="t('waf_page.ip_types.whitelist')" value="whitelist" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('waf_page.form.reason')">
          <el-input v-model="batchIpForm.reason" />
        </el-form-item>
        <el-form-item :label="t('waf_page.form.ip_list')" required>
          <el-input
            v-model="batchIpForm.ipsText"
            type="textarea"
            :rows="8"
            :placeholder="t('waf_page.form.ip_list_ph')"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchAddIp = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" @click="handleBatchSaveIp" :loading="savingBatchIp">{{ t('waf_page.row_actions.batch_add') }}</el-button>
      </template>
    </el-dialog>

    <!-- IP 查询对话框 -->
    <el-dialog v-model="showCheckIp" :title="t('waf_page.dialogs.ip_lookup')" width="500px">
      <el-input v-model="checkIpValue" :placeholder="t('waf_page.form.check_ip_ph')" class="mb-2" clearable />
      <el-button type="primary" @click="handleCheckIp" :loading="checkingIp">{{ t('waf_page.lookup_btn') }}</el-button>
      <div v-if="checkIpResult" class="mt-2">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="IP">{{ checkIpResult.ip }}</el-descriptions-item>
          <el-descriptions-item :label="t('waf_page.lookup.blacklist')">
            <el-tag :type="checkIpResult.in_blacklist ? 'danger' : 'info'" size="small">
              {{ checkIpResult.in_blacklist ? t('waf_page.yes') : t('waf_page.no') }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('waf_page.lookup.whitelist')">
            <el-tag :type="checkIpResult.in_whitelist ? 'success' : 'info'" size="small">
              {{ checkIpResult.in_whitelist ? t('waf_page.yes') : t('waf_page.no') }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item :label="t('waf_page.lookup.recent_attacks')">{{ t('waf_page.recent_attacks', { count: checkIpResult.recent_attacks }) }}</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, Download, Upload, Search, Delete } from '@element-plus/icons-vue';
import {
  getWafDashboard, getWafRules, createWafRule, updateWafRule,
  deleteWafRule, toggleWafRule, seedWafRules,
  getWafIpList, addWafIp, batchAddWafIp, deleteWafIp, checkWafIp,
  getWafLogs, pruneWafLogs,
} from '../../api/waf';

const { t } = useI18n();

// ─── 状态 ───
const loading = ref(false);
const activeTab = ref('overview');
const dashboard = ref(null);

// 规则
const rules = ref([]);
const ruleLoading = ref(false);
const ruleFilter = ref({ category: '' });
const showCreateRule = ref(false);
const editingRule = ref(null);
const seeding = ref(false);
const savingRule = ref(false);

const categoryKeys = ['sqli', 'xss', 'path_traversal', 'cmd_injection', 'file_inclusion', 'ssrf', 'custom'];

const categoryOptions = computed(() =>
  Object.fromEntries(categoryKeys.map((key) => [key, t(`waf_page.categories.${key}`)])),
);

const chartCategoryKeys = [
  'sqli', 'xss', 'path_traversal', 'cmd_injection', 'file_inclusion', 'ssrf',
  'cc', 'inspection', 'cc_behavior', 'cc_scan',
];

const chartCategoryLabels = computed(() =>
  Object.fromEntries(chartCategoryKeys.map((key) => [key, t(`waf_page.chart_categories.${key}`)])),
);

const modeLabels = computed(() => ({
  block: t('waf_page.modes.block'),
  detect: t('waf_page.modes.detect'),
  simulate: t('waf_page.modes.simulate'),
}));

const ipTypeLabels = computed(() => ({
  blacklist: t('waf_page.ip_types.blacklist'),
  whitelist: t('waf_page.ip_types.whitelist'),
  challenge: t('waf_page.ip_types.challenge'),
}));

const ruleForm = ref({
  name: '', category: 'custom', severity: 'high', mode: 'block',
  match_type: 'regex', pattern: '', target: 'all', priority: 100, description: '',
});

// IP 列表
const ipList = ref([]);
const ipLoading = ref(false);
const ipTypeFilter = ref('');
const showAddIp = ref(false);
const showBatchAddIp = ref(false);
const showCheckIp = ref(false);
const savingIp = ref(false);
const savingBatchIp = ref(false);
const checkIpValue = ref('');
const checkIpResult = ref(null);
const checkingIp = ref(false);

const ipForm = ref({ ip: '', type: 'blacklist', reason: '', expires_at: null });
const batchIpForm = ref({ type: 'blacklist', reason: '', ipsText: '' });

// 日志
const logs = ref([]);
const logLoading = ref(false);
const logFilter = ref({ ip: '', severity: '' });
const logPage = ref(1);
const logPerPage = 50;
const logTotal = ref(0);

// ─── 计算属性 ───
const categoryChartData = computed(() => {
  const stats = dashboard.value?.category_stats || {};
  const total = dashboard.value?.today?.total || 1;
  const labels = chartCategoryLabels.value;
  return Object.entries(stats).map(([key, val]) => ({
    label: labels[key] || key,
    total: val.total || 0,
    percent: Math.round(((val.total || 0) / total) * 100),
  }));
});

const severityChartData = computed(() => {
  const stats = dashboard.value?.severity_stats || {};
  const types = { critical: 'danger', high: 'warning', medium: 'info', low: 'info' };
  return Object.entries(stats).map(([key, val]) => ({
    label: key, total: val.total || 0, type: types[key] || 'info',
  }));
});

// ─── 方法 ───
function modeTagType(mode) {
  return mode === 'block' ? 'danger' : mode === 'detect' ? 'warning' : 'info';
}

function modeLabel(mode) {
  return modeLabels.value[mode] || mode;
}

function ipTypeLabel(type) {
  return ipTypeLabels.value[type] || type;
}

function severityTag(s) {
  return s === 'critical' ? 'danger' : s === 'high' ? 'warning' : s === 'medium' ? 'info' : 'info';
}

function formatTime(time) {
  return time ? time.replace('T', ' ').substring(0, 19) : '-';
}

async function refreshAll() {
  loading.value = true;
  try {
    const res = await getWafDashboard();
    dashboard.value = res.data;
  } catch (e) {
    ElMessage.error(t('waf_page.messages.dashboard_failed'));
  } finally {
    loading.value = false;
  }
}

// ─── 规则 ───
async function loadRules() {
  ruleLoading.value = true;
  try {
    const res = await getWafRules(ruleFilter.value);
    rules.value = res.data || [];
  } catch (e) {
    ElMessage.error(t('waf_page.messages.rules_load_failed'));
  } finally {
    ruleLoading.value = false;
  }
}

async function handleSeedRules() {
  try {
    await ElMessageBox.confirm(
      t('waf_page.confirm_import'),
      t('waf_page.confirm_import_title'),
    );
    seeding.value = true;
    const res = await seedWafRules();
    ElMessage.success(res.message || t('waf_page.messages.seed_success'));
    await loadRules();
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error(t('waf_page.messages.seed_failed'));
  } finally {
    seeding.value = false;
  }
}

function handleEditRule(rule) {
  editingRule.value = rule;
  ruleForm.value = {
    name: rule.name, category: rule.category, severity: rule.severity,
    mode: rule.mode, match_type: rule.match_type, pattern: rule.pattern,
    target: rule.target, priority: rule.priority, description: rule.description || '',
  };
  showCreateRule.value = true;
}

async function handleSaveRule() {
  savingRule.value = true;
  try {
    if (editingRule.value) {
      await updateWafRule(editingRule.value.id, ruleForm.value);
      ElMessage.success(t('waf_page.messages.rule_updated'));
    } else {
      await createWafRule(ruleForm.value);
      ElMessage.success(t('waf_page.messages.rule_created'));
    }
    showCreateRule.value = false;
    editingRule.value = null;
    ruleForm.value = { name: '', category: 'custom', severity: 'high', mode: 'block', match_type: 'regex', pattern: '', target: 'all', priority: 100, description: '' };
    await loadRules();
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.save_failed'));
  } finally {
    savingRule.value = false;
  }
}

async function handleToggleRule(rule) {
  try {
    const res = await toggleWafRule(rule.id);
    ElMessage.success(res.message);
    await loadRules();
  } catch (e) {
    ElMessage.error(t('messages.failed'));
  }
}

async function handleDeleteRule(rule) {
  try {
    await deleteWafRule(rule.id);
    ElMessage.success(t('waf_page.messages.rule_deleted'));
    await loadRules();
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.delete_failed'));
  }
}

// ─── IP 列表 ───
async function loadIpList() {
  ipLoading.value = true;
  try {
    const params = {};
    if (ipTypeFilter.value) params.type = ipTypeFilter.value;
    const res = await getWafIpList(params);
    ipList.value = res.data || [];
  } catch (e) {
    ElMessage.error(t('waf_page.messages.ip_list_failed'));
  } finally {
    ipLoading.value = false;
  }
}

async function handleSaveIp() {
  savingIp.value = true;
  try {
    await addWafIp(ipForm.value);
    ElMessage.success(t('waf_page.messages.ip_added'));
    showAddIp.value = false;
    ipForm.value = { ip: '', type: 'blacklist', reason: '', expires_at: null };
    await loadIpList();
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.add_failed'));
  } finally {
    savingIp.value = false;
  }
}

async function handleBatchSaveIp() {
  if (!batchIpForm.value.ipsText.trim()) {
    ElMessage.warning(t('waf_page.messages.ip_list_required'));
    return;
  }
  savingBatchIp.value = true;
  try {
    const ips = batchIpForm.value.ipsText.split('\n')
      .map(l => l.trim())
      .filter(l => l)
      .map(ip => ({ ip }));
    const res = await batchAddWafIp({
      ips, type: batchIpForm.value.type, reason: batchIpForm.value.reason,
    });
    ElMessage.success(res.message);
    showBatchAddIp.value = false;
    batchIpForm.value = { type: 'blacklist', reason: '', ipsText: '' };
    await loadIpList();
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.batch_add_failed'));
  } finally {
    savingBatchIp.value = false;
  }
}

async function handleDeleteIp(row) {
  try {
    await deleteWafIp(row.id);
    ElMessage.success(t('waf_page.messages.ip_removed'));
    await loadIpList();
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.remove_failed'));
  }
}

async function handleCheckIp() {
  if (!checkIpValue.value) return;
  checkingIp.value = true;
  try {
    const res = await checkWafIp(checkIpValue.value);
    checkIpResult.value = res.data;
  } catch (e) {
    ElMessage.error(t('waf_page.messages.lookup_failed'));
  } finally {
    checkingIp.value = false;
  }
}

async function handleAddBlacklist(ip) {
  try {
    await addWafIp({ ip, type: 'blacklist', reason: t('waf_page.messages.blacklist_reason') });
    ElMessage.success(t('waf_page.messages.blacklist_added', { ip }));
    await refreshAll();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.blacklist_add_failed'));
  }
}

// ─── 日志 ───
async function loadLogs() {
  logLoading.value = true;
  try {
    const params = {
      ...logFilter.value,
      page: logPage.value,
      per_page: logPerPage,
    };
    if (!params.ip) delete params.ip;
    if (!params.severity) delete params.severity;
    const res = await getWafLogs(params);
    logs.value = res.data?.items || [];
    logTotal.value = res.data?.total || 0;
  } catch (e) {
    ElMessage.error(t('waf_page.messages.logs_load_failed'));
  } finally {
    logLoading.value = false;
  }
}

async function handlePruneLogs() {
  try {
    const res = await pruneWafLogs();
    ElMessage.success(res.message);
    await loadLogs();
  } catch (e) {
    ElMessage.error(t('waf_page.messages.prune_failed'));
  }
}

// Tab 切换时懒加载
const tabLoadMap = { rules: loadRules, ipList: loadIpList, logs: loadLogs };

function onTabChange(tab) {
  if (tabLoadMap[tab]) tabLoadMap[tab]();
}

watch(() => activeTab.value, onTabChange);

onMounted(() => {
  refreshAll();
});
</script>

<style scoped>
.waf-page { padding: 16px; }
.page-title { margin: 0; font-size: 20px; font-weight: 600; }
.page-desc { margin: 4px 0 0; font-size: 13px; }
.stat-card { border-radius: 8px; }
.stat-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; display: flex; align-items: center; gap: 6px; }
.stat-value small { font-size: 13px; font-weight: 400; }
.text-danger { color: #f56c6c; }
.text-warning { color: #e6a23c; }
.text-secondary { color: #909399; }
.text-right { text-align: right; }
.mb-2 { margin-bottom: 8px; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-4 { margin-top: 16px; }
.mr-1 { margin-right: 4px; }
.mr-2 { margin-right: 8px; }
.ml-2 { margin-left: 8px; }
.text-center { text-align: center; }
</style>
