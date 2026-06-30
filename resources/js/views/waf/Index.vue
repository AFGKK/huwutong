<template>
  <div class="waf-page">
    <!-- 顶部操作栏 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <h2 class="page-title">WAF 基础防护</h2>
        <p class="page-desc text-secondary">OWASP Top 10 规则引擎 · CC 攻击防护 · IP 黑白名单</p>
      </el-col>
      <el-col :span="12" class="text-right">
        <el-button type="primary" @click="refreshAll" :loading="loading">
          <el-icon class="mr-1"><Refresh /></el-icon>刷新
        </el-button>
      </el-col>
    </el-row>

    <!-- 状态概览卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">今日拦截</div>
          <div class="stat-value text-danger">{{ dashboard?.today?.blocked || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">今日检测</div>
          <div class="stat-value text-warning">{{ dashboard?.today?.detected || 0 }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">活跃规则</div>
          <div class="stat-value">{{ dashboard?.rules?.active || 0 }}<small class="text-secondary"> / {{ dashboard?.rules?.total || 0 }}</small></div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">黑名单 IP</div>
          <div class="stat-value">{{ dashboard?.ip_lists?.blacklist || 0 }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 模式状态 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">防护模式</div>
          <div>
            <el-tag :type="modeTagType(dashboard?.mode)" effect="dark" size="large">{{ modeLabel(dashboard?.mode) }}</el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">CC 防护</div>
          <div>
            <el-tag :type="dashboard?.cc_enabled ? 'success' : 'info'" size="large">
              {{ dashboard?.cc_enabled ? '已启用' : '已禁用' }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
      <el-col :span="8">
        <el-card shadow="never" class="stat-card">
          <div class="stat-label">规则引擎</div>
          <div>
            <el-tag :type="dashboard?.rules_enabled ? 'success' : 'info'" size="large">
              {{ dashboard?.rules_enabled ? '已启用' : '已禁用' }}
            </el-tag>
          </div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主面板：Tabs -->
    <el-card shadow="never">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 仪表盘 -->
        <el-tab-pane label="攻击概览" name="overview">
          <el-row :gutter="16">
            <el-col :span="12">
              <h4 class="mb-2">今日攻击分类</h4>
              <el-table :data="categoryChartData" border stripe size="small">
                <el-table-column prop="label" label="分类" />
                <el-table-column prop="total" label="次数" width="100" />
                <el-table-column label="占比" width="160">
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
              <h4 class="mb-2">严重级别分布</h4>
              <el-table :data="severityChartData" border stripe size="small">
                <el-table-column prop="label" label="级别" />
                <el-table-column prop="total" label="次数" width="100" />
                <el-table-column label="状态" width="120">
                  <template #default="{ row }">
                    <el-tag :type="row.type" size="small">{{ row.label }}</el-tag>
                  </template>
                </el-table-column>
              </el-table>
            </el-col>
          </el-row>

          <h4 class="mt-4 mb-2">TOP 攻击 IP</h4>
          <el-table :data="dashboard?.top_ips || []" border stripe size="small">
            <el-table-column prop="ip" label="IP 地址" min-width="160" />
            <el-table-column prop="total" label="攻击次数" width="100" />
            <el-table-column prop="max_severity" label="最高严重级别" width="120">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.max_severity)" size="small">{{ row.max_severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="120" fixed="right">
              <template #default="{ row }">
                <el-button text type="danger" size="small" @click="handleAddBlacklist(row.ip)">加入黑名单</el-button>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 2: 规则管理 -->
        <el-tab-pane label="规则管理" name="rules">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="showCreateRule = true">
              <el-icon class="mr-1"><Plus /></el-icon>创建规则
            </el-button>
            <el-button size="small" @click="handleSeedRules" :loading="seeding">
              <el-icon class="mr-1"><Download /></el-icon>导入默认规则
            </el-button>
            <el-select v-model="ruleFilter.category" placeholder="分类筛选" clearable size="small" class="ml-2" style="width:140px" @change="loadRules">
              <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
            </el-select>
          </div>
          <el-table :data="rules" border stripe size="small" :loading="ruleLoading">
            <el-table-column prop="name" label="规则名称" min-width="180" />
            <el-table-column prop="category" label="分类" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ categoryOptions[row.category] || row.category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="severity" label="严重级别" width="100">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="mode" label="模式" width="80">
              <template #default="{ row }">
                <el-tag :type="row.mode === 'block' ? 'danger' : 'warning'" size="small">{{ row.mode }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="hit_count" label="命中" width="70" />
            <el-table-column prop="is_active" label="状态" width="80">
              <template #default="{ row }">
                <el-switch :model-value="row.is_active" @change="handleToggleRule(row)" size="small" />
              </template>
            </el-table-column>
            <el-table-column prop="priority" label="优先级" width="70" />
            <el-table-column label="操作" width="140" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" @click="handleEditRule(row)">编辑</el-button>
                <el-popconfirm title="确认删除？" @confirm="handleDeleteRule(row)">
                  <template #reference>
                    <el-button text type="danger" size="small">删除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: IP 黑白名单 -->
        <el-tab-pane label="IP 黑白名单" name="ipList">
          <div class="mb-2">
            <el-button type="primary" size="small" @click="showAddIp = true">
              <el-icon class="mr-1"><Plus /></el-icon>添加 IP
            </el-button>
            <el-button size="small" @click="showBatchAddIp = true">
              <el-icon class="mr-1"><Upload /></el-icon>批量添加
            </el-button>
            <el-button size="small" @click="showCheckIp = true">
              <el-icon class="mr-1"><Search /></el-icon>IP 查询
            </el-button>
            <el-radio-group v-model="ipTypeFilter" size="small" class="ml-2" @change="loadIpList">
              <el-radio-button label="">全部</el-radio-button>
              <el-radio-button label="blacklist">黑名单</el-radio-button>
              <el-radio-button label="whitelist">白名单</el-radio-button>
            </el-radio-group>
          </div>
          <el-table :data="ipList" border stripe size="small" :loading="ipLoading">
            <el-table-column prop="ip" label="IP / CIDR" min-width="160" />
            <el-table-column prop="type" label="类型" width="100">
              <template #default="{ row }">
                <el-tag :type="row.type === 'blacklist' ? 'danger' : 'success'" size="small">
                  {{ row.type === 'blacklist' ? '黑名单' : '白名单' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="source" label="来源" width="100" />
            <el-table-column prop="reason" label="原因" min-width="200" />
            <el-table-column prop="hit_count" label="命中" width="60" />
            <el-table-column prop="expires_at" label="过期时间" width="180">
              <template #default="{ row }">{{ row.expires_at || '永久' }}</template>
            </el-table-column>
            <el-table-column label="操作" width="100" fixed="right">
              <template #default="{ row }">
                <el-popconfirm title="确认移除？" @confirm="handleDeleteIp(row)">
                  <template #reference>
                    <el-button text type="danger" size="small">移除</el-button>
                  </template>
                </el-popconfirm>
              </template>
            </el-table-column>
          </el-table>
        </el-tab-pane>

        <!-- Tab 4: 攻击日志 -->
        <el-tab-pane label="攻击日志" name="logs">
          <div class="mb-2">
            <el-input v-model="logFilter.ip" placeholder="IP 搜索" size="small" class="mr-2" style="width:160px" clearable @change="loadLogs" />
            <el-select v-model="logFilter.severity" placeholder="严重级别" size="small" class="mr-2" style="width:120px" clearable @change="loadLogs">
              <el-option v-for="s in ['critical','high','medium','low']" :key="s" :label="s" :value="s" />
            </el-select>
            <el-button size="small" @click="handlePruneLogs" class="ml-2">
              <el-icon class="mr-1"><Delete /></el-icon>清理过期日志
            </el-button>
          </div>
          <el-table :data="logs" border stripe size="small" :loading="logLoading" max-height="600">
            <el-table-column prop="created_at" label="时间" width="170">
              <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
            </el-table-column>
            <el-table-column prop="ip" label="IP" width="150" />
            <el-table-column prop="rule_category" label="分类" width="120">
              <template #default="{ row }">
                <el-tag size="small">{{ row.rule_category }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="severity" label="级别" width="80">
              <template #default="{ row }">
                <el-tag :type="severityTag(row.severity)" size="small">{{ row.severity }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="action_taken" label="动作" width="80">
              <template #default="{ row }">
                <el-tag :type="row.action_taken === 'block' ? 'danger' : 'warning'" size="small">{{ row.action_taken }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="uri" label="URI" min-width="250" show-overflow-tooltip />
            <el-table-column prop="method" label="方法" width="70" />
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
    <el-dialog v-model="showCreateRule" :title="editingRule ? '编辑规则' : '创建规则'" width="600px">
      <el-form :model="ruleForm" label-width="100px" size="small">
        <el-form-item label="规则名称" required>
          <el-input v-model="ruleForm.name" />
        </el-form-item>
        <el-form-item label="分类" required>
          <el-select v-model="ruleForm.category" style="width:100%">
            <el-option v-for="(label, key) in categoryOptions" :key="key" :label="label" :value="key" />
          </el-select>
        </el-form-item>
        <el-row :gutter="12">
          <el-col :span="8">
            <el-form-item label="严重级别">
              <el-select v-model="ruleForm.severity">
                <el-option v-for="s in ['low','medium','high','critical']" :key="s" :label="s" :value="s" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="模式">
              <el-select v-model="ruleForm.mode">
                <el-option v-for="m in ['block','detect','simulate']" :key="m" :label="m" :value="m" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="匹配类型">
              <el-select v-model="ruleForm.match_type">
                <el-option v-for="t in ['regex','exact','prefix','suffix','contains']" :key="t" :label="t" :value="t" />
              </el-select>
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="匹配模式" required>
          <el-input v-model="ruleForm.pattern" type="textarea" :rows="3" placeholder="正则表达式或精确字符串" />
        </el-form-item>
        <el-form-item label="检测目标">
          <el-select v-model="ruleForm.target">
            <el-option v-for="t in ['all','query','body','headers','cookies','uri']" :key="t" :label="t" :value="t" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-input-number v-model="ruleForm.priority" :min="1" :max="9999" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="ruleForm.description" type="textarea" :rows="2" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCreateRule = false">取消</el-button>
        <el-button type="primary" @click="handleSaveRule" :loading="savingRule">保存</el-button>
      </template>
    </el-dialog>

    <!-- 添加 IP 对话框 -->
    <el-dialog v-model="showAddIp" title="添加 IP" width="450px">
      <el-form :model="ipForm" label-width="100px" size="small">
        <el-form-item label="IP / CIDR" required>
          <el-input v-model="ipForm.ip" placeholder="192.168.1.1 或 10.0.0.0/8" />
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="ipForm.type" style="width:100%">
            <el-option label="黑名单" value="blacklist" />
            <el-option label="白名单" value="whitelist" />
            <el-option label="挑战" value="challenge" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="ipForm.reason" type="textarea" :rows="2" />
        </el-form-item>
        <el-form-item label="过期时间">
          <el-date-picker v-model="ipForm.expires_at" type="datetime" placeholder="留空=永久" style="width:100%" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showAddIp = false">取消</el-button>
        <el-button type="primary" @click="handleSaveIp" :loading="savingIp">添加</el-button>
      </template>
    </el-dialog>

    <!-- 批量添加 IP 对话框 -->
    <el-dialog v-model="showBatchAddIp" title="批量添加 IP" width="500px">
      <el-form :model="batchIpForm" size="small">
        <el-form-item label="类型" required>
          <el-select v-model="batchIpForm.type" style="width:100%">
            <el-option label="黑名单" value="blacklist" />
            <el-option label="白名单" value="whitelist" />
          </el-select>
        </el-form-item>
        <el-form-item label="原因">
          <el-input v-model="batchIpForm.reason" />
        </el-form-item>
        <el-form-item label="IP 列表" required>
          <el-input
            v-model="batchIpForm.ipsText"
            type="textarea"
            :rows="8"
            placeholder="每行一个 IP 或 CIDR&#10;192.168.1.1&#10;10.0.0.0/8&#10;172.16.0.0/12"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showBatchAddIp = false">取消</el-button>
        <el-button type="primary" @click="handleBatchSaveIp" :loading="savingBatchIp">批量添加</el-button>
      </template>
    </el-dialog>

    <!-- IP 查询对话框 -->
    <el-dialog v-model="showCheckIp" title="IP 查询" width="500px">
      <el-input v-model="checkIpValue" placeholder="输入 IP 地址" class="mb-2" clearable />
      <el-button type="primary" @click="handleCheckIp" :loading="checkingIp">查询</el-button>
      <div v-if="checkIpResult" class="mt-2">
        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="IP">{{ checkIpResult.ip }}</el-descriptions-item>
          <el-descriptions-item label="黑名单">
            <el-tag :type="checkIpResult.in_blacklist ? 'danger' : 'info'" size="small">
              {{ checkIpResult.in_blacklist ? '是' : '否' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="白名单">
            <el-tag :type="checkIpResult.in_whitelist ? 'success' : 'info'" size="small">
              {{ checkIpResult.in_whitelist ? '是' : '否' }}
            </el-tag>
          </el-descriptions-item>
          <el-descriptions-item label="最近攻击">{{ checkIpResult.recent_attacks }} 次</el-descriptions-item>
        </el-descriptions>
      </div>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, Download, Upload, Search, Delete } from '@element-plus/icons-vue';
import {
  getWafDashboard, getWafRules, createWafRule, updateWafRule,
  deleteWafRule, toggleWafRule, seedWafRules,
  getWafIpList, addWafIp, batchAddWafIp, deleteWafIp, checkWafIp,
  getWafLogs, pruneWafLogs, getWafTrend,
} from '../../api/waf';

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

const categoryOptions = {
  sqli: 'SQL 注入', xss: 'XSS 跨站脚本', path_traversal: '路径穿越',
  cmd_injection: '命令注入', file_inclusion: '文件包含', ssrf: 'SSRF', custom: '自定义',
};

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
  const labels = { sqli: 'SQL注入', xss: 'XSS', path_traversal: '路径穿越', cmd_injection: '命令注入', file_inclusion: '文件包含', ssrf: 'SSRF', cc: 'CC攻击', inspection: '请求校验', cc_behavior: '攻击行为', cc_scan: '扫描' };
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
  return mode === 'block' ? '拦截模式' : mode === 'detect' ? '检测模式' : '模拟模式';
}

function severityTag(s) {
  return s === 'critical' ? 'danger' : s === 'high' ? 'warning' : s === 'medium' ? 'info' : 'info';
}

function formatTime(t) {
  return t ? t.replace('T', ' ').substring(0, 19) : '-';
}

async function refreshAll() {
  loading.value = true;
  try {
    const res = await getWafDashboard();
    dashboard.value = res.data;
  } catch (e) {
    ElMessage.error('获取 WAF 状态失败');
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
    ElMessage.error('获取规则列表失败');
  } finally {
    ruleLoading.value = false;
  }
}

async function handleSeedRules() {
  try {
    await ElMessageBox.confirm('将导入 config/waf.php 中定义的所有默认规则，已有规则不会重复导入。', '确认导入');
    seeding.value = true;
    const res = await seedWafRules();
    ElMessage.success(res.message || '规则导入成功');
    await loadRules();
    await refreshAll();
  } catch (e) {
    if (e !== 'cancel') ElMessage.error('导入失败');
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
      ElMessage.success('规则已更新');
    } else {
      await createWafRule(ruleForm.value);
      ElMessage.success('规则已创建');
    }
    showCreateRule.value = false;
    editingRule.value = null;
    ruleForm.value = { name: '', category: 'custom', severity: 'high', mode: 'block', match_type: 'regex', pattern: '', target: 'all', priority: 100, description: '' };
    await loadRules();
    await refreshAll();
  } catch (e) {
    ElMessage.error('保存失败');
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
    ElMessage.error('操作失败');
  }
}

async function handleDeleteRule(rule) {
  try {
    await deleteWafRule(rule.id);
    ElMessage.success('规则已删除');
    await loadRules();
    await refreshAll();
  } catch (e) {
    ElMessage.error('删除失败');
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
    ElMessage.error('获取 IP 列表失败');
  } finally {
    ipLoading.value = false;
  }
}

async function handleSaveIp() {
  savingIp.value = true;
  try {
    await addWafIp(ipForm.value);
    ElMessage.success('IP 已添加');
    showAddIp.value = false;
    ipForm.value = { ip: '', type: 'blacklist', reason: '', expires_at: null };
    await loadIpList();
    await refreshAll();
  } catch (e) {
    ElMessage.error('添加失败');
  } finally {
    savingIp.value = false;
  }
}

async function handleBatchSaveIp() {
  if (!batchIpForm.value.ipsText.trim()) {
    ElMessage.warning('请输入 IP 列表');
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
    ElMessage.error('批量添加失败');
  } finally {
    savingBatchIp.value = false;
  }
}

async function handleDeleteIp(row) {
  try {
    await deleteWafIp(row.id);
    ElMessage.success('IP 已移除');
    await loadIpList();
    await refreshAll();
  } catch (e) {
    ElMessage.error('移除失败');
  }
}

async function handleCheckIp() {
  if (!checkIpValue.value) return;
  checkingIp.value = true;
  try {
    const res = await checkWafIp(checkIpValue.value);
    checkIpResult.value = res.data;
  } catch (e) {
    ElMessage.error('查询失败');
  } finally {
    checkingIp.value = false;
  }
}

async function handleAddBlacklist(ip) {
  try {
    await addWafIp({ ip, type: 'blacklist', reason: '从攻击列表添加' });
    ElMessage.success(`已将 ${ip} 加入黑名单`);
    await refreshAll();
  } catch (e) {
    ElMessage.error('添加黑名单失败');
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
    ElMessage.error('获取攻击日志失败');
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
    ElMessage.error('清理失败');
  }
}

// Tab 切换时懒加载
const tabLoadMap = { rules: loadRules, ipList: loadIpList, logs: loadLogs };

function onTabChange(tab) {
  if (tabLoadMap[tab]) tabLoadMap[tab]();
}

const watcher = computed(() => activeTab.value);
import { watch } from 'vue';
watch(watcher, onTabChange);

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
