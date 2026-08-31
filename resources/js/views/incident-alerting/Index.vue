<template>
    <div class="incident-alerting-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <el-tag :type="status.pagerduty?.enabled ? 'success' : 'danger'" size="large" effect="dark">
                            {{ status.pagerduty?.enabled ? t(`${P}.enabled`) : t(`${P}.disabled`) }}
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
                            {{ status.opsgenie?.enabled ? t(`${P}.enabled`) : t(`${P}.disabled`) }}
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
                        <div class="card-title">{{ t(`${P}.cards.severity_mapping`) }}</div>
                        <div class="card-info">{{ t(`${P}.cards.severity_hint`) }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="status-card">
                        <el-icon :color="status.event_sync?.enabled ? '#67c23a' : '#c0c4cc'" :size="28">
                            <Clock />
                        </el-icon>
                        <div class="card-title">{{ t(`${P}.cards.event_sync`) }}</div>
                        <div class="card-info">
                            {{ status.event_sync?.enabled
                                ? t(`${P}.cards.sync_every`, { n: status.event_sync?.interval_minutes })
                                : t(`${P}.disabled`) }}
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t(`${P}.tabs.test`)" name="test">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t(`${P}.test.title`) }}</span>
                    </template>
                    <el-form :model="testForm" label-width="120px">
                        <el-form-item :label="t(`${P}.test.channel`)">
                            <el-radio-group v-model="testForm.channel">
                                <el-radio label="pagerduty">PagerDuty</el-radio>
                                <el-radio label="opsgenie">OpsGenie</el-radio>
                                <el-radio label="both">{{ t(`${P}.test.both`) }}</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.test.severity`)">
                            <el-select v-model="testForm.severity" style="width:200px">
                                <el-option
                                    v-for="(map, key) in severityMapping"
                                    :key="key"
                                    :label="`${key} — ${map.description}`"
                                    :value="key"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleTestConnection" :loading="testing">
                                {{ testBtnLabel }}
                            </el-button>
                            <el-button @click="handleSendTestAlert" :loading="sendingTest">
                                {{ t(`${P}.test.send_test`) }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert
                        v-if="testResult"
                        :title="testResult.title"
                        :description="testResult.description"
                        :type="testResult.type"
                        show-icon
                        :closable="true"
                        @close="testResult = null"
                    />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.pagerduty`)" name="pagerduty">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.pd.open_events`) }}</span>
                            <el-button size="small" @click="loadPagerDutyEvents">{{ t('security_page.refresh') }}</el-button>
                        </div>
                    </template>
                    <el-table :data="pdEvents" v-loading="pdLoading" stripe>
                        <el-table-column prop="title" :label="t(`${P}.cols.title`)" min-width="200" />
                        <el-table-column prop="status" :label="t(`${P}.cols.status`)" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'triggered' ? 'danger' : 'warning'" size="small">
                                    {{ pdStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="urgency" :label="t(`${P}.cols.urgency`)" width="80" />
                        <el-table-column prop="service" :label="t(`${P}.cols.service`)" width="120" />
                        <el-table-column prop="created_at" :label="t(`${P}.cols.created_at`)" width="170" />
                    </el-table>
                    <el-empty v-if="!pdLoading && pdEvents.length === 0" :description="t(`${P}.pd.empty`)" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.opsgenie`)" name="opsgenie">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t(`${P}.og.open_alerts`) }}</span>
                            <el-button size="small" @click="loadOpsGenieAlerts">{{ t('security_page.refresh') }}</el-button>
                        </div>
                    </template>
                    <el-table :data="ogAlerts" v-loading="ogLoading" stripe>
                        <el-table-column prop="message" :label="t(`${P}.og.cols.message`)" min-width="200" />
                        <el-table-column prop="priority" :label="t(`${P}.og.cols.priority`)" width="100">
                            <template #default="{ row }">
                                <el-tag :type="priorityTag(row.priority)" size="small">{{ row.priority }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="status" :label="t(`${P}.cols.status`)" width="100" />
                        <el-table-column prop="created_at" :label="t(`${P}.cols.created_at`)" width="170" />
                        <el-table-column prop="tags" :label="t(`${P}.og.cols.tags`)" width="150">
                            <template #default="{ row }">
                                <el-tag v-for="tag in (row.tags || [])" :key="tag" size="small" class="mr-1">{{ tag }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!ogLoading && ogAlerts.length === 0" :description="t(`${P}.og.empty`)" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.config`)" name="config">
                <el-card shadow="never">
                    <template #header><span>{{ t(`${P}.config.env_title`) }}</span></template>
                    <el-table :data="envConfigs" stripe>
                        <el-table-column prop="key" :label="t(`${P}.config.cols.key`)" width="300" />
                        <el-table-column prop="channel" :label="t(`${P}.config.cols.channel`)" width="120" />
                        <el-table-column prop="description" :label="t(`${P}.config.cols.description`)" min-width="200" />
                        <el-table-column prop="required" :label="t(`${P}.config.cols.required`)" width="60">
                            <template #default="{ row }">
                                <el-tag v-if="row.required" size="small" type="danger">{{ t(`${P}.config.yes`) }}</el-tag>
                                <el-tag v-else size="small" type="info">{{ t(`${P}.config.no`) }}</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { Clock } from '@element-plus/icons-vue';
import {
    getIncidentAlertingStatus,
    testPagerDuty,
    testOpsGenie,
    sendTestAlert as apiSendTestAlert,
    getPagerDutyEvents,
    getOpsGenieAlerts,
} from '@/api/incidentAlerting';

const P = 'incident_alerting_page';
const { t } = useI18n();

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
    const map = {
        pagerduty: t(`${P}.test.btn_pd`),
        opsgenie: t(`${P}.test.btn_og`),
        both: t(`${P}.test.btn_both`),
    };
    return map[testForm.value.channel] || t(`${P}.test.btn_default`);
});

const envConfigs = computed(() => [
    { key: 'PAGERDUTY_ENABLED', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_ENABLED`), required: true },
    { key: 'PAGERDUTY_API_KEY', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_API_KEY`), required: false },
    { key: 'PAGERDUTY_ROUTING_KEY', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_ROUTING_KEY`), required: true },
    { key: 'PAGERDUTY_FROM_EMAIL', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_FROM_EMAIL`), required: false },
    { key: 'PAGERDUTY_SERVICE_ID', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_SERVICE_ID`), required: false },
    { key: 'PAGERDUTY_ESCALATION_POLICY_ID', channel: 'PagerDuty', description: t(`${P}.config.desc.PAGERDUTY_ESCALATION_POLICY_ID`), required: false },
    { key: 'OPSGENIE_ENABLED', channel: 'OpsGenie', description: t(`${P}.config.desc.OPSGENIE_ENABLED`), required: true },
    { key: 'OPSGENIE_API_KEY', channel: 'OpsGenie', description: t(`${P}.config.desc.OPSGENIE_API_KEY`), required: true },
    { key: 'OPSGENIE_TEAM_ID', channel: 'OpsGenie', description: t(`${P}.config.desc.OPSGENIE_TEAM_ID`), required: false },
    { key: 'OPSGENIE_SCHEDULE_ID', channel: 'OpsGenie', description: t(`${P}.config.desc.OPSGENIE_SCHEDULE_ID`), required: false },
]);

function pdStatusLabel(status) {
    return ({
        triggered: t(`${P}.pd.status_triggered`),
        acknowledged: t(`${P}.pd.status_acknowledged`),
    })[status] || status;
}

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
            testResult.value = { title: t(`${P}.messages.test_ok`), description: res.data.message || res.data.data?.message || '', type: 'success' };
        } else if (res?.data?.success === false) {
            testResult.value = { title: t(`${P}.messages.test_fail`), description: res.data.message || t(`${P}.messages.connection_issue`), type: 'error' };
        }
    } catch (e) {
        testResult.value = { title: t(`${P}.messages.test_fail`), description: e.message || t(`${P}.messages.request_error`), type: 'error' };
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
            testResult.value = { title: t(`${P}.messages.alert_sent`), description: t(`${P}.messages.alert_sent_hint`), type: 'success' };
        } else {
            testResult.value = { title: t(`${P}.messages.send_fail`), description: JSON.stringify(res.data.message || ''), type: 'error' };
        }
    } catch (e) {
        testResult.value = { title: t(`${P}.messages.send_fail`), description: e.message || t(`${P}.messages.request_error`), type: 'error' };
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
.status-number { font-size: 32px; font-weight: 700; color: #0f172a; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.mr-1 { margin-right: 4px; }
</style>
