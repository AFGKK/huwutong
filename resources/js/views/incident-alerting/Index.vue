<template>
    <div class="incident-alerting-page">
        <div class="page-header">
            <h2>PagerDuty / OpsGenie 告警集成</h2>
            <p class="text-muted">将紧急告警自动推送至 PagerDuty / OpsGenie，值班工程师电话+推送通知 → 确认/升级/解决闭环</p>
        </div>

        <!-- 状态概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <el-tag :type="status.pagerduty?.enabled ? 'success' : 'danger'" size="large" effect="dark">
                            {{ status.pagerduty?.enabled ? '已启用' : '未启用' }}
                        </el-tag>
                        <div class="card-title">PagerDuty</div>
                        <div class="card-info">{{ status.pagerduty?.config?.api_endpoint || '-' }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <el-tag :type="status.opsgenie?.enabled ? 'success' : 'danger'" size="large" effect="dark">
                            {{ status.opsgenie?.enabled ? '已启用' : '未启用' }}
                        </el-tag>
                        <div class="card-title">OpsGenie</div>
                        <div class="card-info">{{ status.opsgenie?.config?.api_endpoint || '-' }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <div class="status-number">{{ Object.keys(severityMapping).length }}</div>
                        <div class="card-title">告警级别映射</div>
                        <div class="card-info">critical → P1 / P5</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <el-icon :color="status.event_sync?.enabled ? '#67c23a' : '#c0c4cc'" :size="28">
                            <Clock />
                        </el-icon>
                        <div class="card-title">事件同步</div>
                        <div class="card-info">{{ status.event_sync?.enabled ? '每' + status.event_sync?.interval_minutes + '分钟同步' : '未启用' }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- 连接测试 -->
            <el-tab-pane label="连接测试" name="test">
                <el-card shadow="never">
                    <template #header>
                        <span>测试连接</span>
                    </template>
                    <el-form :model="testForm" label-width="120px">
                        <el-form-item label="选择频道">
                            <el-radio-group v-model="testForm.channel">
                                <el-radio label="pagerduty">PagerDuty</el-radio>
                                <el-radio label="opsgenie">OpsGenie</el-radio>
                                <el-radio label="both">两者</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="告警级别">
                            <el-select v-model="testForm.severity" style="width:200px">
                                <el-option v-for="(map, key) in severityMapping" :key="key" :label="`${key} — ${map.description}`" :value="key" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleTestConnection" :loading="testing">
                                {{ testBtnLabel }}
                            </el-button>
                            <el-button @click="handleSendTestAlert" :loading="sendingTest">
                                发送测试告警
                            </el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="testResult" :title="testResult.title" :description="testResult.description" :type="testResult.type" show-icon :closable="true" @close="testResult = null" />
                </el-card>
            </el-tab-pane>

            <!-- PagerDuty 事件 -->
            <el-tab-pane label="PagerDuty 事件" name="pagerduty">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>未确认/未解决事件</span>
                            <el-button size="small" @click="loadPagerDutyEvents">刷新</el-button>
                        </div>
                    </template>
                    <el-table :data="pdEvents" v-loading="pdLoading" stripe>
                        <el-table-column prop="title" label="标题" min-width="200" />
                        <el-table-column prop="status" label="状态" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'triggered' ? 'danger' : 'warning'" size="small">
                                    {{ { triggered: '已触发', acknowledged: '已确认' }[row.status] || row.status }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="urgency" label="紧急度" width="80" />
                        <el-table-column prop="service" label="服务" width="120" />
                        <el-table-column prop="created_at" label="创建时间" width="170" />
                    </el-table>
                    <el-empty v-if="!pdLoading && pdEvents.length === 0" description="暂无 PagerDuty 事件" />
                </el-card>
            </el-tab-pane>

            <!-- OpsGenie 告警 -->
            <el-tab-pane label="OpsGenie 告警" name="opsgenie">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>未关闭告警</span>
                            <el-button size="small" @click="loadOpsGenieAlerts">刷新</el-button>
                        </div>
                    </template>
                    <el-table :data="ogAlerts" v-loading="ogLoading" stripe>
                        <el-table-column prop="message" label="消息" min-width="200" />
                        <el-table-column prop="priority" label="优先级" width="100">
                            <template #default="{ row }">
                                <el-tag :type="priorityTag(row.priority)" size="small">{{ row.priority }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="100" />
                        <el-table-column prop="created_at" label="创建时间" width="170" />
                        <el-table-column prop="tags" label="标签" width="150">
                            <template #default="{ row }">
                                <el-tag v-for="tag in (row.tags || [])" :key="tag" size="small" class="mr-1">{{ tag }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!ogLoading && ogAlerts.length === 0" description="暂无 OpsGenie 告警" />
                </el-card>
            </el-tab-pane>

            <!-- 配置参考 -->
            <el-tab-pane label="配置参考" name="config">
                <el-card shadow="never">
                    <template #header><span>环境变量配置</span></template>
                    <el-table :data="envConfigs" stripe>
                        <el-table-column prop="key" label="变量名" width="300" />
                        <el-table-column prop="channel" label="平台" width="120" />
                        <el-table-column prop="description" label="说明" min-width="200" />
                        <el-table-column prop="required" label="必填" width="60">
                            <template #default="{ row }">
                                <el-tag v-if="row.required" size="small" type="danger">是</el-tag>
                                <el-tag v-else size="small" type="info">否</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    getIncidentAlertingStatus,
    testPagerDuty,
    testOpsGenie,
    sendTestAlert as apiSendTestAlert,
    getPagerDutyEvents,
    getOpsGenieAlerts,
} from '@/api/incidentAlerting';

const activeTab = ref('test');
const status = ref({ pagerduty: { enabled: false, config: {} }, opsgenie: { enabled: false, config: {} }, severity_mapping: {}, event_sync: {} });
const severityMapping = ref({});
const testing = ref(false);
const sendingTest = ref(false);
const testResult = ref(null);
const pdEvents = ref([]);
const pdLoading = ref(false);
const ogAlerts = ref([]);
const ogLoading = ref(false);

const testForm = ref({
    channel: 'pagerduty',
    severity: 'warning',
});

const priorityTag = (p) => ({ P1: 'danger', P2: 'warning', P3: 'info', P4: '', P5: 'info' }[p] || 'info');

const testBtnLabel = computed(() => {
    const map = { pagerduty: '测试 PagerDuty 连接', opsgenie: '测试 OpsGenie 连接', both: '测试两者连接' };
    return map[testForm.value.channel] || '测试连接';
});

const envConfigs = [
    { key: 'PAGERDUTY_ENABLED', channel: 'PagerDuty', description: '启用 PagerDuty 集成', required: true },
    { key: 'PAGERDUTY_API_KEY', channel: 'PagerDuty', description: 'REST API Key（用于查询事件）', required: false },
    { key: 'PAGERDUTY_ROUTING_KEY', channel: 'PagerDuty', description: 'Events API v2 Routing Key（用于推送告警）', required: true },
    { key: 'PAGERDUTY_FROM_EMAIL', channel: 'PagerDuty', description: '请求来源邮箱', required: false },
    { key: 'PAGERDUTY_SERVICE_ID', channel: 'PagerDuty', description: '默认 Service ID', required: false },
    { key: 'PAGERDUTY_ESCALATION_POLICY_ID', channel: 'PagerDuty', description: '升级策略 ID', required: false },
    { key: 'OPSGENIE_ENABLED', channel: 'OpsGenie', description: '启用 OpsGenie 集成', required: true },
    { key: 'OPSGENIE_API_KEY', channel: 'OpsGenie', description: 'API Key', required: true },
    { key: 'OPSGENIE_TEAM_ID', channel: 'OpsGenie', description: '默认团队 ID', required: false },
    { key: 'OPSGENIE_SCHEDULE_ID', channel: 'OpsGenie', description: '值班排班 ID', required: false },
];

import { computed } from 'vue';

const loadStatus = async () => {
    try {
        const res = await getIncidentAlertingStatus();
        if (res.data.success) {
            status.value = res.data.data;
            severityMapping.value = res.data.data.severity_mapping || {};
        }
    } catch { /* ignore */ }
};

const handleTestConnection = async () => {
    testing.value = true;
    testResult.value = null;
    try {
        const channel = testForm.value.channel;
        let res;
        if (channel === 'pagerduty' || channel === 'both') {
            res = await testPagerDuty();
        }
        if (channel === 'opsgenie' || channel === 'both') {
            res = await testOpsGenie();
        }
        if (res?.data?.success) {
            testResult.value = { title: '测试成功', description: res.data.message || res.data.data?.message || '', type: 'success' };
        } else if (res?.data?.success === false) {
            testResult.value = { title: '测试失败', description: res.data.message || '连接异常', type: 'error' };
        }
    } catch (e) {
        testResult.value = { title: '测试失败', description: e.message || '请求异常', type: 'error' };
    } finally {
        testing.value = false;
    }
};

const handleSendTestAlert = async () => {
    sendingTest.value = true;
    testResult.value = null;
    try {
        const res = await apiSendTestAlert(testForm.value.channel, testForm.value.severity);
        if (res.data.success) {
            testResult.value = { title: '测试告警已发送', description: '请查看 PagerDuty/OpsGenie 控制台', type: 'success' };
        } else {
            testResult.value = { title: '发送失败', description: JSON.stringify(res.data.message || ''), type: 'error' };
        }
    } catch (e) {
        testResult.value = { title: '发送失败', description: e.message || '请求异常', type: 'error' };
    } finally {
        sendingTest.value = false;
    }
};

const loadPagerDutyEvents = async () => {
    pdLoading.value = true;
    try {
        const res = await getPagerDutyEvents();
        if (res.data.success) {
            pdEvents.value = res.data.data.incidents || [];
        } else {
            pdEvents.value = [];
        }
    } catch {
        pdEvents.value = [];
    } finally {
        pdLoading.value = false;
    }
};

const loadOpsGenieAlerts = async () => {
    ogLoading.value = true;
    try {
        const res = await getOpsGenieAlerts();
        if (res.data.success) {
            ogAlerts.value = res.data.data.alerts || [];
        } else {
            ogAlerts.value = [];
        }
    } catch {
        ogAlerts.value = [];
    } finally {
        ogLoading.value = false;
    }
};

onMounted(() => {
    loadStatus();
});
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.status-card { text-align: center; cursor: default; }
.card-title { font-weight: 600; font-size: 15px; margin-top: 8px; }
.card-info { font-size: 12px; color: #909399; margin-top: 4px; }
.status-number { font-size: 32px; font-weight: 700; color: #409eff; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.mr-1 { margin-right: 4px; }
</style>
