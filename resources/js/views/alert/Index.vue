<template>
    <div class="alert-page">
        <div class="page-header">
            <h2>智能告警引擎</h2>
            <div class="header-actions">
                <el-button @click="evaluateRules" :loading="evaluating" type="warning">
                    <el-icon><Refresh /></el-icon> 执行规则检测
                </el-button>
                <el-button @click="refreshAll" :loading="loading" type="primary">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="4">
                <el-card shadow="hover" class="stat-card stat-danger">
                    <div class="stat-value">{{ dashboard.firing_count }}</div>
                    <div class="stat-label">触发中</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-card stat-warning">
                    <div class="stat-value">{{ dashboard.acknowledged_count }}</div>
                    <div class="stat-label">已确认</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-card stat-success">
                    <div class="stat-value">{{ dashboard.resolved_count }}</div>
                    <div class="stat-label">已解决</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.total_today }}</div>
                    <div class="stat-label">今日告警</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ dashboard.active_rules }}/{{ dashboard.total_rules }}</div>
                    <div class="stat-label">活跃规则</div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- ── 告警事件 ── -->
            <el-tab-pane label="告警事件" name="events">
                <el-card class="mb-4">
                    <el-form :model="eventFilters" inline>
                        <el-form-item label="状态">
                            <el-select v-model="eventFilters.status" placeholder="全部" clearable style="width: 140px">
                                <el-option label="触发中" value="firing" />
                                <el-option label="已确认" value="acknowledged" />
                                <el-option label="已解决" value="resolved" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="严重等级">
                            <el-select v-model="eventFilters.severity" placeholder="全部" clearable style="width: 140px">
                                <el-option label="严重" value="critical" />
                                <el-option label="警告" value="warning" />
                                <el-option label="信息" value="info" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="fetchEvents(1)">搜索</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
                <el-table :data="eventList" stripe @row-click="showEventDetail">
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column label="严重等级" width="90">
                        <template #default="{ row }">
                            <el-tag :type="severityTag(row.severity)" size="small" effect="dark">
                                {{ severityLabel(row.severity) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                    <el-table-column label="规则" width="160">
                        <template #default="{ row }">{{ row.rule?.name || '手动触发' }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="statusTag(row.status)" size="small">{{ statusLabelAlert(row.status) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="触发时间" width="170" sortable>
                        <template #default="{ row }">{{ formatDate(row.fired_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="180">
                        <template #default="{ row }">
                            <el-button v-if="row.status === 'firing'" size="small" @click.stop="acknowledgeEvent(row)">确认</el-button>
                            <el-button v-if="row.status !== 'resolved'" size="small" type="success" @click.stop="resolveEvent(row)">解决</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrapper">
                    <el-pagination :current-page="eventPage" :total="eventTotal" :page-size="20" layout="total, prev, pager, next" @current-change="fetchEvents" />
                </div>
            </el-tab-pane>

            <!-- ── 告警规则 ── -->
            <el-tab-pane label="告警规则" name="rules">
                <div class="mb-4">
                    <el-button type="primary" @click="openRuleDialog()"><el-icon><Plus /></el-icon> 新建规则</el-button>
                </div>
                <el-table :data="ruleList" stripe>
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column prop="name" label="名称" min-width="180" />
                    <el-table-column prop="slug" label="标识" width="120"><template #default="{ row }"><code>{{ row.slug }}</code></template></el-table-column>
                    <el-table-column label="指标类型" width="140">
                        <template #default="{ row }">{{ metricTypeLabel(row.metric_type) }}</template>
                    </el-table-column>
                    <el-table-column label="条件" width="150">
                        <template #default="{ row }">{{ row.condition_operator }} {{ row.threshold }}{{ row.metric_type === 'apm_slow' ? 'ms' : '' }}</template>
                    </el-table-column>
                    <el-table-column label="严重等级" width="90">
                        <template #default="{ row }"><el-tag :type="severityTag(row.severity)" size="small">{{ severityLabel(row.severity) }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="冷却" width="80">{{ row.cooldown_minutes }}m</el-table-column>
                    <el-table-column label="每日限额" width="80">{{ row.max_alert_per_day }}</el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }"><el-switch v-model="row.is_active" @change="toggleRule(row)" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="140">
                        <template #default="{ row }">
                            <el-button size="small" @click="openRuleDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除？" @confirm="deleteRule(row)">
                                <template #reference><el-button size="small" type="danger">删除</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- ── 告警集成 ── -->
            <el-tab-pane label="告警集成" name="integrations">
                <div class="mb-4">
                    <el-button type="primary" @click="openIntegrationDialog()"><el-icon><Plus /></el-icon> 新建集成</el-button>
                </div>
                <el-table :data="integrationList" stripe>
                    <el-table-column type="index" label="#" width="50" />
                    <el-table-column prop="name" label="名称" min-width="180" />
                    <el-table-column label="类型" width="120">
                        <template #default="{ row }">
                            <el-tag>{{ integrationTypeLabel(row.type) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="webhook_url" label="Webhook URL" min-width="300" show-overflow-tooltip />
                    <el-table-column label="严重等级过滤" width="140">
                        <template #default="{ row }">{{ row.severity_filter === 'all' ? '全部' : severityLabel(row.severity_filter) }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="80">
                        <template #default="{ row }"><el-switch v-model="row.is_active" @change="toggleIntegration(row)" /></template>
                    </el-table-column>
                    <el-table-column label="操作" width="200">
                        <template #default="{ row }">
                            <el-button size="small" @click="testIntegration(row)">测试</el-button>
                            <el-button size="small" @click="openIntegrationDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除？" @confirm="deleteIntegration(row)">
                                <template #reference><el-button size="small" type="danger">删除</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>
        </el-tabs>

        <!-- 告警详情对话框 -->
        <el-dialog v-model="detailVisible" title="告警详情" width="60%" top="5vh">
            <div v-if="detailData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item label="标题">{{ detailData.title }}</el-descriptions-item>
                    <el-descriptions-item label="严重等级">
                        <el-tag :type="severityTag(detailData.severity)">{{ severityLabel(detailData.severity) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="规则">{{ detailData.rule?.name || '手动触发' }}</el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusTag(detailData.status)">{{ statusLabelAlert(detailData.status) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="触发时间">{{ formatDate(detailData.fired_at) }}</el-descriptions-item>
                    <el-descriptions-item label="事件类型">{{ detailData.event_type }}</el-descriptions-item>
                    <el-descriptions-item label="确认人">{{ detailData.acknowledged_by?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="确认时间">{{ formatDate(detailData.acknowledged_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="解决人">{{ detailData.resolved_by?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="解决时间">{{ formatDate(detailData.resolved_at) || '-' }}</el-descriptions-item>
                </el-descriptions>
                <el-card><pre class="message-pre">{{ detailData.message }}</pre></el-card>
                <div v-if="detailData.context" class="mt-2">
                    <el-collapse><el-collapse-item title="上下文数据"><pre>{{ JSON.stringify(detailData.context, null, 2) }}</pre></el-collapse-item></el-collapse>
                </div>
            </div>
            <template #footer>
                <el-button v-if="detailData?.status === 'firing'" @click="acknowledgeEvent(detailData)">确认告警</el-button>
                <el-button v-if="detailData?.status !== 'resolved'" type="success" @click="resolveEvent(detailData)">解决告警</el-button>
            </template>
        </el-dialog>

        <!-- 规则编辑对话框 -->
        <el-dialog v-model="ruleDialogVisible" :title="editingRule ? '编辑告警规则' : '新建告警规则'" width="70%" top="5vh">
            <el-form :model="ruleForm" label-width="140px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="名称" required>
                            <el-input v-model="ruleForm.name" placeholder="例如：License 过期提醒" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="标识" required>
                            <el-input v-model="ruleForm.slug" placeholder="例如：license-expiry" :disabled="!!editingRule" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="描述">
                    <el-input v-model="ruleForm.description" type="textarea" :rows="2" />
                </el-form-item>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="指标类型" required>
                            <el-select v-model="ruleForm.metric_type" style="width: 100%">
                                <el-option v-for="(label, key) in metaData.metric_types" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="运算符" required>
                            <el-select v-model="ruleForm.condition_operator" style="width: 100%">
                                <el-option v-for="(label, key) in metaData.operator_options" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="阈值" required>
                            <el-input-number v-model="ruleForm.threshold" :min="0" :step="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="8">
                        <el-form-item label="持续分钟">
                            <el-input-number v-model="ruleForm.duration_minutes" :min="0" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="严重等级">
                            <el-select v-model="ruleForm.severity" style="width: 100%">
                                <el-option v-for="(label, key) in metaData.severity_options" :key="key" :label="label" :value="key" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="冷却(分钟)">
                            <el-input-number v-model="ruleForm.cooldown_minutes" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="每日限额">
                            <el-input-number v-model="ruleForm.max_alert_per_day" :min="1" :max="100" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="通知渠道">
                            <el-checkbox-group v-model="ruleForm.channels">
                                <el-checkbox label="database">站内信</el-checkbox>
                                <el-checkbox label="slack">Slack</el-checkbox>
                                <el-checkbox label="dingtalk">钉钉</el-checkbox>
                                <el-checkbox label="webhook">Webhook</el-checkbox>
                            </el-checkbox-group>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="Slack Webhook">
                    <el-input v-model="ruleForm.slack_webhook" placeholder="https://hooks.slack.com/services/..." />
                </el-form-item>
                <el-form-item label="钉钉 Webhook">
                    <el-input v-model="ruleForm.dingtalk_webhook" placeholder="https://oapi.dingtalk.com/robot/send?access_token=..." />
                </el-form-item>
                <el-form-item label="自定义 Webhook">
                    <div v-for="(url, idx) in ruleForm.webhook_urls" :key="idx" class="flex mb-1">
                        <el-input v-model="ruleForm.webhook_urls[idx]" placeholder="https://..." style="margin-right: 8px" />
                        <el-button type="danger" :icon="Delete" circle size="small" @click="ruleForm.webhook_urls.splice(idx, 1)" />
                    </div>
                    <el-button size="small" @click="ruleForm.webhook_urls.push('')">+ 添加 URL</el-button>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="ruleDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="saveRule" :loading="savingRule">保存</el-button>
            </template>
        </el-dialog>

        <!-- 集成编辑对话框 -->
        <el-dialog v-model="integrationDialogVisible" :title="editingIntegration ? '编辑集成' : '新建集成'" width="55%" top="5vh">
            <el-form :model="integrationForm" label-width="120px">
                <el-form-item label="名称" required>
                    <el-input v-model="integrationForm.name" placeholder="例如：运维 Slack" />
                </el-form-item>
                <el-form-item label="类型" required>
                    <el-select v-model="integrationForm.type" style="width: 100%">
                        <el-option label="Slack" value="slack" />
                        <el-option label="钉钉" value="dingtalk" />
                        <el-option label="Webhook" value="webhook" />
                        <el-option label="邮件组" value="email_group" />
                    </el-select>
                </el-form-item>
                <el-form-item label="Webhook URL" required>
                    <el-input v-model="integrationForm.webhook_url" placeholder="https://..." />
                </el-form-item>
                <el-form-item label="严重等级过滤">
                    <el-select v-model="integrationForm.severity_filter" style="width: 100%">
                        <el-option label="全部" value="all" />
                        <el-option label="严重及以上" value="critical" />
                        <el-option label="警告及以上" value="warning" />
                        <el-option label="仅信息" value="info" />
                    </el-select>
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="integrationForm.description" type="textarea" :rows="2" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="integrationDialogVisible = false">取消</el-button>
                <el-button type="primary" @click="saveIntegration" :loading="savingIntegration">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Delete } from '@element-plus/icons-vue';
import alertApi from '../../api/alert';

const loading = ref(false);
const evaluating = ref(false);
const activeTab = ref('events');

const dashboard = reactive({
    firing_count: 0, acknowledged_count: 0, resolved_count: 0,
    total_today: 0, active_rules: 0, total_rules: 0,
    by_severity: {}, by_rule: [], trend: [],
});

const metaData = reactive({
    metric_types: {}, severity_options: {}, operator_options: {}, templates: {},
});

// Events
const eventList = ref([]);
const eventPage = ref(1);
const eventTotal = ref(0);
const eventFilters = reactive({ status: '', severity: '' });

// Detail
const detailVisible = ref(false);
const detailData = ref(null);

// Rules
const ruleList = ref([]);
const ruleDialogVisible = ref(false);
const editingRule = ref(null);
const savingRule = ref(false);
const baseRuleForm = () => ({
    name: '', slug: '', description: '',
    metric_type: 'license_expiry', condition_operator: 'gt', threshold: 0,
    duration_minutes: 0, severity: 'warning',
    channels: ['database'], cooldown_minutes: 60, max_alert_per_day: 10,
    slack_webhook: '', dingtalk_webhook: '', webhook_urls: [],
});
const ruleForm = reactive(baseRuleForm());

// Integrations
const integrationList = ref([]);
const integrationDialogVisible = ref(false);
const editingIntegration = ref(null);
const savingIntegration = ref(false);
const baseIntegrationForm = () => ({
    name: '', type: 'slack', webhook_url: '',
    severity_filter: 'all', description: '',
});
const integrationForm = reactive(baseIntegrationForm());

// ── Helpers ──
function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleString('zh-CN', { hour12: false });
}

function severityTag(s) {
    return s === 'critical' ? 'danger' : s === 'warning' ? 'warning' : 'info';
}
function severityLabel(s) {
    return s === 'critical' ? '严重' : s === 'warning' ? '警告' : '信息';
}
function statusTag(s) {
    return s === 'firing' ? 'danger' : s === 'acknowledged' ? 'warning' : 'success';
}
function statusLabelAlert(s) {
    return s === 'firing' ? '触发中' : s === 'acknowledged' ? '已确认' : '已解决';
}
function metricTypeLabel(t) {
    return metaData.metric_types[t] || t;
}
function integrationTypeLabel(t) {
    return { slack: 'Slack', dingtalk: '钉钉', webhook: 'Webhook', email_group: '邮件组' }[t] || t;
}

// ── Data ──
async function refreshAll() {
    loading.value = true;
    try {
        await Promise.all([fetchDashboard(), fetchMeta(), fetchRules(), fetchEvents(), fetchIntegrations()]);
        ElMessage.success('数据已刷新');
    } catch (e) {
        ElMessage.error('加载失败');
    } finally {
        loading.value = false;
    }
}

async function fetchDashboard() {
    try { const { data } = await alertApi.dashboard(); Object.assign(dashboard, data); } catch (e) {}
}
async function fetchMeta() {
    try { const { data } = await alertApi.meta(); Object.assign(metaData, data); } catch (e) {}
}
async function fetchRules() {
    try { const { data } = await alertApi.rules(); ruleList.value = data || []; } catch (e) {}
}
async function fetchEvents(page = 1) {
    try {
        const params = { page, per_page: 20 };
        if (eventFilters.status) params.status = eventFilters.status;
        if (eventFilters.severity) params.severity = eventFilters.severity;
        const { data } = await alertApi.events(params);
        eventList.value = data.data || [];
        eventPage.value = data.current_page || page;
        eventTotal.value = data.total || 0;
    } catch (e) {}
}
async function fetchIntegrations() {
    try { const { data } = await alertApi.integrations(); integrationList.value = data || []; } catch (e) {}
}

async function showEventDetail(row) {
    detailVisible.value = true;
    detailData.value = null;
    try { const { data } = await alertApi.showEvent(row.id); detailData.value = data; } catch (e) { ElMessage.error('获取详情失败'); }
}

async function acknowledgeEvent(row) {
    try { await alertApi.acknowledgeEvent(row.id); ElMessage.success('已确认'); fetchEvents(eventPage.value); fetchDashboard(); } catch (e) { ElMessage.error('操作失败'); }
}
async function resolveEvent(row) {
    try { await alertApi.resolveEvent(row.id); ElMessage.success('已解决'); fetchEvents(eventPage.value); fetchDashboard(); if (detailData.value) detailData.value.status = 'resolved'; } catch (e) { ElMessage.error('操作失败'); }
}

async function evaluateRules() {
    evaluating.value = true;
    try { const { data } = await alertApi.evaluate(); ElMessage.success(`检测完成：${data.fired} 条触发`); await refreshAll(); } catch (e) { ElMessage.error('检测失败'); } finally { evaluating.value = false; }
}

// ── Rules CRUD ──
function openRuleDialog(rule = null) {
    editingRule.value = rule;
    ruleDialogVisible.value = true;
    if (rule) {
        Object.assign(ruleForm, {
            name: rule.name, slug: rule.slug, description: rule.description || '',
            metric_type: rule.metric_type, condition_operator: rule.condition_operator, threshold: rule.threshold,
            duration_minutes: rule.duration_minutes ?? 0, severity: rule.severity || 'warning',
            channels: rule.channels || ['database'], cooldown_minutes: rule.cooldown_minutes ?? 60,
            max_alert_per_day: rule.max_alert_per_day ?? 10,
            slack_webhook: rule.slack_webhook || '', dingtalk_webhook: rule.dingtalk_webhook || '',
            webhook_urls: rule.webhook_urls || [],
        });
    } else {
        Object.assign(ruleForm, baseRuleForm());
    }
}

async function saveRule() {
    savingRule.value = true;
    try {
        const payload = { ...ruleForm };
        if (editingRule.value) {
            await alertApi.updateRule(editingRule.value.id, payload);
            ElMessage.success('规则已更新');
        } else {
            await alertApi.storeRule(payload);
            ElMessage.success('规则已创建');
        }
        ruleDialogVisible.value = false;
        await fetchRules();
    } catch (e) { ElMessage.error('保存失败'); } finally { savingRule.value = false; }
}

async function deleteRule(rule) {
    try { await alertApi.destroyRule(rule.id); ElMessage.success('已删除'); await fetchRules(); } catch (e) { ElMessage.error('删除失败'); }
}

async function toggleRule(rule) {
    try { await alertApi.updateRule(rule.id, { is_active: rule.is_active }); } catch (e) { rule.is_active = !rule.is_active; }
}

// ── Integrations CRUD ──
function openIntegrationDialog(integration = null) {
    editingIntegration.value = integration;
    integrationDialogVisible.value = true;
    Object.assign(integrationForm, integration ? {
        name: integration.name, type: integration.type, webhook_url: integration.webhook_url,
        severity_filter: integration.severity_filter || 'all', description: integration.description || '',
    } : baseIntegrationForm());
}

async function saveIntegration() {
    savingIntegration.value = true;
    try {
        if (editingIntegration.value) {
            await alertApi.updateIntegration(editingIntegration.value.id, integrationForm);
            ElMessage.success('集成已更新');
        } else {
            await alertApi.storeIntegration(integrationForm);
            ElMessage.success('集成已创建');
        }
        integrationDialogVisible.value = false;
        await fetchIntegrations();
    } catch (e) { ElMessage.error('保存失败'); } finally { savingIntegration.value = false; }
}

async function deleteIntegration(integration) {
    try { await alertApi.destroyIntegration(integration.id); ElMessage.success('已删除'); await fetchIntegrations(); } catch (e) { ElMessage.error('删除失败'); }
}

async function toggleIntegration(integration) {
    try { await alertApi.updateIntegration(integration.id, { is_active: integration.is_active }); } catch (e) { integration.is_active = !integration.is_active; }
}

async function testIntegration(integration) {
    try {
        await alertApi.testIntegration(integration.id);
        ElMessage.success('测试成功');
    } catch (e) {
        ElMessage.error('测试失败');
    }
}

onMounted(() => refreshAll());
</script>

<style scoped>
.alert-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; font-size: 1.5rem; font-weight: 600; }
.header-actions { display: flex; gap: 8px; }
.stat-card { text-align: center; cursor: default; }
.stat-value { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 0.8rem; color: #909399; margin-top: 4px; }
.stat-success .stat-value { color: #67c23a; }
.stat-danger .stat-value { color: #f56c6c; }
.stat-warning .stat-value { color: #e6a23c; }
.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }
.message-pre { white-space: pre-wrap; word-break: break-word; font-family: inherit; margin: 0; line-height: 1.6; }
.flex { display: flex; }
.mb-1 { margin-bottom: 4px; }
</style>