<template>
    <div class="smtp-fallback-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('smtp_fallback_page.title') }}</h2>
                <span class="header-subtitle">{{ t('smtp_fallback_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="handleTest" :loading="testing">{{ t('smtp_fallback_page.test_chain') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </div>
        </div>

        <el-row :gutter="16">
            <el-col :span="8">
                <el-card shadow="never">
                    <template #header><span>{{ t('smtp_fallback_page.current_status') }}</span></template>
                    <div class="status-list">
                        <div class="status-item">
                            <span class="label">{{ t('smtp_fallback_page.primary_smtp') }}</span>
                            <el-tag :type="status.primary_healthy ? 'success' : 'danger'" size="small">
                                {{ healthLabel(status.primary_healthy) }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">{{ t('smtp_fallback_page.backup_smtp') }}</span>
                            <el-tag :type="status.backup_healthy ? 'success' : 'warning'" size="small">
                                {{ healthLabel(status.backup_healthy) }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">{{ t('smtp_fallback_page.currently_using') }}</span>
                            <el-tag :type="status.currently_using === 'primary' ? 'success' : 'warning'" size="small">
                                {{ currentlyUsingLabel(status.currently_using) }}
                            </el-tag>
                        </div>
                        <div class="status-item">
                            <span class="label">{{ t('smtp_fallback_page.last_fallback') }}</span>
                            <span class="value">{{ status.last_fallback_at || t('smtp_fallback_page.empty_value') }}</span>
                        </div>
                        <div class="status-item">
                            <span class="label">{{ t('smtp_fallback_page.last_recovery') }}</span>
                            <span class="value">{{ status.last_recovery_at || t('smtp_fallback_page.empty_value') }}</span>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="16">
                <el-card shadow="never">
                    <template #header><span>{{ t('smtp_fallback_page.strategy_config') }}</span></template>
                    <el-form :model="form" label-width="200px" label-position="left">
                        <el-form-item :label="t('smtp_fallback_page.failure_threshold')">
                            <el-input-number v-model="form.failure_threshold" :min="1" :max="20" />
                            <span class="ml-2 text-muted">{{ t('smtp_fallback_page.failure_threshold_hint') }}</span>
                        </el-form-item>
                        <el-form-item :label="t('smtp_fallback_page.recovery_interval')">
                            <el-input-number v-model="form.recovery_interval" :min="5" :max="1440" :step="5" />
                            <span class="ml-2 text-muted">{{ t('smtp_fallback_page.recovery_interval_hint') }}</span>
                        </el-form-item>
                        <el-form-item :label="t('smtp_fallback_page.auto_recover')">
                            <el-switch v-model="form.auto_recover" />
                            <span class="ml-2 text-muted">{{ t('smtp_fallback_page.auto_recover_hint') }}</span>
                        </el-form-item>
                        <el-divider content-position="left">{{ t('smtp_fallback_page.notification_section') }}</el-divider>
                        <el-form-item :label="t('smtp_fallback_page.notify_on_fallback')">
                            <el-switch v-model="form.notify_on_fallback" />
                        </el-form-item>
                        <el-form-item :label="t('smtp_fallback_page.notify_on_recovery')">
                            <el-switch v-model="form.notify_on_recovery" />
                        </el-form-item>
                        <el-form-item :label="t('smtp_fallback_page.notify_emails')">
                            <el-select v-model="form.notify_emails" multiple filterable allow-create default-first-option style="width:100%">
                                <el-option v-for="email in form.notify_emails" :key="email" :value="email" />
                            </el-select>
                            <span class="ml-2 text-muted">{{ t('smtp_fallback_page.notify_emails_hint') }}</span>
                        </el-form-item>
                    </el-form>
                </el-card>

                <!-- 测试结果 -->
                <el-card v-if="testResults" shadow="never" style="margin-top:16px;">
                    <template #header>
                        <span>{{ t('smtp_fallback_page.test_results') }}</span>
                        <el-tag :type="testResults.success ? 'success' : 'danger'" size="small">
                            {{ testResults.success ? t('smtp_fallback_page.test_passed') : t('smtp_fallback_page.test_failed') }}
                        </el-tag>
                    </template>
                    <p>{{ testResults.message }}</p>
                    <el-table :data="testResults.results" stripe size="small">
                        <el-table-column prop="host" :label="t('smtp_fallback_page.columns.host')" />
                        <el-table-column prop="provider" :label="t('smtp_fallback_page.columns.provider')" />
                        <el-table-column :label="t('smtp_fallback_page.columns.role')" width="70">
                            <template #default="{ row }">{{ roleLabel(row.is_primary) }}</template>
                        </el-table-column>
                        <el-table-column :label="t('smtp_fallback_page.columns.status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'success' ? 'success' : 'danger'" size="small">
                                    {{ rowStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="latency_ms" :label="t('smtp_fallback_page.columns.latency')" width="90" />
                        <el-table-column prop="error" :label="t('smtp_fallback_page.columns.error')" show-overflow-tooltip />
                    </el-table>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import api from '@/api/smtpFallback';

const { t } = useI18n();

const form = ref({
    failure_threshold: 3,
    recovery_interval: 30,
    notify_on_fallback: true,
    notify_on_recovery: true,
    notify_emails: [],
    auto_recover: true,
});
const status = ref({});
const testResults = ref(null);
const saving = ref(false);
const testing = ref(false);

const healthLabels = computed(() => ({
    true: t('smtp_fallback_page.health.healthy'),
    false: t('smtp_fallback_page.health.unhealthy'),
}));

const currentlyUsingLabels = computed(() => ({
    primary: t('smtp_fallback_page.primary_smtp'),
    backup: t('smtp_fallback_page.backup_smtp'),
    none: t('smtp_fallback_page.none_available'),
}));

const roleLabels = computed(() => ({
    primary: t('smtp_fallback_page.role.primary'),
    backup: t('smtp_fallback_page.role.backup'),
}));

const rowStatusLabels = computed(() => ({
    success: t('smtp_fallback_page.health.healthy'),
    failed: t('smtp_fallback_page.test_failed'),
}));

function healthLabel(healthy) {
    return healthLabels.value[healthy ? 'true' : 'false'];
}

function currentlyUsingLabel(value) {
    return currentlyUsingLabels.value[value] || currentlyUsingLabels.value.none;
}

function roleLabel(isPrimary) {
    return roleLabels.value[isPrimary ? 'primary' : 'backup'];
}

function rowStatusLabel(status) {
    return rowStatusLabels.value[status === 'success' ? 'success' : 'failed'];
}

function unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadConfig() {
    try {
        const res = await api.getConfig();
        const data = unwrap(res) || {};
        form.value.failure_threshold = data.failure_threshold ?? 3;
        form.value.recovery_interval = data.recovery_interval ?? 30;
        form.value.notify_on_fallback = data.notify_on_fallback ?? true;
        form.value.notify_on_recovery = data.notify_on_recovery ?? true;
        form.value.notify_emails = data.notify_emails || [];
        form.value.auto_recover = data.auto_recover ?? true;
        status.value = data.current_status || {};
    } catch (e) {
        ElMessage.error(t('messages.load_failed'));
    }
}

async function handleSave() {
    saving.value = true;
    try {
        await api.updateConfig(form.value);
        await loadConfig();
        ElMessage.success(t('smtp_fallback_page.messages.saved'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('smtp_fallback_page.messages.save_failed'));
    } finally {
        saving.value = false;
    }
}

async function handleTest() {
    testing.value = true;
    testResults.value = null;
    try {
        const res = await api.test();
        testResults.value = unwrap(res);
    } catch (e) {
        ElMessage.error(t('smtp_fallback_page.messages.test_failed'));
    } finally {
        testing.value = false;
    }
}

onMounted(() => {
    loadConfig();
});
</script>

<style scoped>
.smtp-fallback-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; display: inline; }
.header-subtitle { font-size: 13px; color: #999; margin-left: 8px; }
.status-list { display: flex; flex-direction: column; gap: 12px; }
.status-item { display: flex; justify-content: space-between; align-items: center; }
.status-item .label { color: #666; font-size: 13px; }
.status-item .value { color: #333; font-size: 13px; }
.text-muted { color: #999; font-size: 12px; }
.ml-2 { margin-left: 8px; }
</style>
