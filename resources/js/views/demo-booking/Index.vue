<template>
    <div class="demo-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('demo_center.title') }}</h2>
                <span class="header-subtitle">{{ t('demo_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
            <!-- ===== Tab 1: Demo 预约 ===== -->
            <el-tab-pane :label="t('demo_center.tab_booking')" name="booking">
                <el-row :gutter="16">
                    <el-col :span="16">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('demo_booking_page.list_title') }}</span>
                                    <div class="card-actions">
                                        <el-select v-model="filterStatus" size="small" style="width:140px" @change="loadList">
                                            <el-option :label="t('demo_booking_page.filter_all')" value="" />
                                            <el-option v-for="(label, key) in statusLabels" :key="key" :label="label" :value="key" />
                                        </el-select>
                                    </div>
                                </div>
                            </template>
                            <el-table :data="list" v-loading="loading" stripe>
                                <el-table-column prop="company_name" :label="t('demo_booking_page.cols.company')" width="150" />
                                <el-table-column prop="contact_name" :label="t('demo_booking_page.cols.contact')" width="100" />
                                <el-table-column prop="email" :label="t('demo_booking_page.cols.email')" width="180" />
                                <el-table-column prop="phone" :label="t('demo_booking_page.cols.phone')" width="120" />
                                <el-table-column prop="status" :label="t('demo_booking_page.cols.status')" width="100">
                                    <template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabels[row.status] || row.status }}</el-tag></template>
                                </el-table-column>
                                <el-table-column prop="created_at" :label="t('demo_booking_page.cols.submitted_at')" width="160" />
                                <el-table-column :label="t('demo_booking_page.cols.actions')" width="140" fixed="right">
                                    <template #default="{ row }">
                                        <el-select v-model="row.status" size="small" @change="(val) => handleUpdateStatus(row.id, val)">
                                            <el-option v-for="(label, key) in statusLabels" :key="key" :label="label" :value="key" />
                                        </el-select>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <el-empty v-if="!loading && list.length === 0" :description="t('demo_booking_page.empty')" />
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never" class="mb-4">
                            <template #header><span>{{ t('demo_booking_page.stats_title') }}</span></template>
                            <div class="stats-grid">
                                <div class="stat-item" v-for="(val, key) in stats" :key="key">
                                    <div class="stat-value">{{ val }}</div>
                                    <div class="stat-label">{{ statusLabels[key] || key }}</div>
                                </div>
                            </div>
                        </el-card>
                        <el-card shadow="never">
                            <template #header><span>{{ t('demo_booking_page.calendly_title') }}</span></template>
                            <p class="text-muted">{{ t('demo_booking_page.calendly_desc') }}</p>
                            <el-tag :type="calendly.enabled ? 'success' : 'danger'" size="small">{{ calendly.enabled ? t('demo_booking_page.enabled') : t('demo_booking_page.disabled') }}</el-tag>
                            <div v-if="calendly.link" class="mt-2">
                                <el-input v-model="calendly.link" readonly size="small">
                                    <template #append><el-button @click="copyBookingText(calendly.link)" size="small">{{ t('actions.copy') }}</el-button></template>
                                </el-input>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ===== Tab 2: 产品演示管理 ===== -->
            <el-tab-pane :label="t('demo_center.tab_admin')" name="admin">
                <div class="tab-header-right">
                    <el-tag v-if="config.enabled" type="success" effect="dark" size="small">{{ t('demo_admin_page.enabled') }}</el-tag>
                    <el-tag v-else type="info" size="small">{{ t('demo_admin_page.disabled') }}</el-tag>
                    <el-button @click="refreshAll" :loading="adminLoading" size="small"><el-icon><Refresh /></el-icon> {{ t('demo_admin_page.refresh') }}</el-button>
                </div>

                <el-alert :title="t('demo_admin_page.alert')" type="success" show-icon :closable="false" class="mb-4" />

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ analytics.total }}</div><div class="stat-label">{{ t('demo_admin_page.stats.total') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value text-green">{{ analytics.active }}</div><div class="stat-label">{{ t('demo_admin_page.stats.active') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card"><div class="stat-value text-blue">{{ analytics.completed }}</div><div class="stat-label">{{ t('demo_admin_page.stats.completed') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card"><div class="stat-value text-yellow">{{ analytics.registrations }}</div><div class="stat-label">{{ t('demo_admin_page.stats.registrations') }}</div></el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" class="stat-card"><div class="stat-value text-red">{{ analytics.conversion_rate }}%</div><div class="stat-label">{{ t('demo_admin_page.stats.conversion') }}</div></el-card></el-col>
                </el-row>

                <el-card shadow="hover">
                    <el-tabs v-model="adminSubTab">
                        <el-tab-pane :label="t('demo_admin_page.tabs.dashboard')" name="dashboard">
                            <el-row :gutter="16">
                                <el-col :span="12">
                                    <el-card shadow="never">
                                        <template #header><span>{{ t('demo_admin_page.daily_trend') }}</span></template>
                                        <div class="trend-chart" v-if="analytics.daily_trend?.length">
                                            <div class="trend-row" v-for="d in analytics.daily_trend" :key="d.date">
                                                <span class="trend-date">{{ d.date.slice(5) }}</span>
                                                <div class="trend-bar-container">
                                                    <div class="trend-bar starts" :style="{ width: barWidth(d.starts, 'starts') + '%' }">{{ d.starts }}</div>
                                                    <div class="trend-bar completions" :style="{ width: barWidth(d.completions, 'completions') + '%', left: barWidth(d.starts, 'starts') + '%' }">{{ d.completions }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div v-else class="text-gray text-center p-4">{{ t('messages.no_data') }}</div>
                                    </el-card>
                                </el-col>
                                <el-col :span="12">
                                    <el-card shadow="never">
                                        <template #header><span>{{ t('demo_admin_page.browsers') }}</span></template>
                                        <div v-if="analytics.browsers?.length">
                                            <div v-for="b in analytics.browsers" :key="b.browser" class="browser-row">
                                                <span class="browser-name">{{ b.browser || 'Unknown' }}</span>
                                                <el-progress :percentage="browserPct(b.count)" :stroke-width="20" />
                                                <span class="browser-count">{{ b.count }}</span>
                                            </div>
                                        </div>
                                        <div v-else class="text-gray text-center p-4">{{ t('messages.no_data') }}</div>
                                    </el-card>
                                    <el-card shadow="never" class="mt-3">
                                        <template #header><span>{{ t('demo_admin_page.key_metrics') }}</span></template>
                                        <el-descriptions :column="2" border size="small">
                                            <el-descriptions-item :label="t('demo_admin_page.metrics.today')">{{ analytics.today }}</el-descriptions-item>
                                            <el-descriptions-item :label="t('demo_admin_page.metrics.week')">{{ analytics.this_week }}</el-descriptions-item>
                                            <el-descriptions-item :label="t('demo_admin_page.metrics.month')">{{ analytics.this_month }}</el-descriptions-item>
                                            <el-descriptions-item :label="t('demo_admin_page.metrics.avg_steps')">{{ analytics.avg_steps_completed }}</el-descriptions-item>
                                        </el-descriptions>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </el-tab-pane>
                        <el-tab-pane :label="t('demo_admin_page.tabs.config')" name="config">
                            <el-form :model="configForm" label-width="180px" @submit.prevent="saveConfig">
                                <el-form-item :label="t('demo_admin_page.form.enabled')"><el-switch v-model="configForm.enabled" /></el-form-item>
                                <el-form-item :label="t('demo_admin_page.form.duration')"><el-input-number v-model="configForm.session_duration_minutes" :min="5" :max="120" :step="5" /></el-form-item>
                                <el-form-item :label="t('demo_admin_page.form.extend')"><el-input-number v-model="configForm.extend_minutes" :min="5" :max="60" :step="5" /></el-form-item>
                                <el-form-item :label="t('demo_admin_page.form.cta_title')"><el-input v-model="configForm.cta_title" maxlength="100" /></el-form-item>
                                <el-form-item :label="t('demo_admin_page.form.cta_desc')"><el-input v-model="configForm.cta_description" type="textarea" :rows="2" maxlength="500" /></el-form-item>
                                <el-form-item><el-button type="primary" native-type="submit" :loading="saving">{{ t('demo_admin_page.save_config') }}</el-button></el-form-item>
                            </el-form>
                        </el-tab-pane>
                        <el-tab-pane :label="t('demo_admin_page.tabs.embed')" name="embed">
                            <el-alert :title="t('demo_admin_page.embed_alert')" type="info" show-icon :closable="false" class="mb-3" />
                            <el-card shadow="never">
                                <pre class="embed-code">{{ embedCode || t('demo_admin_page.loading') }}</pre>
                                <el-button size="small" type="primary" class="mt-2" @click="copyEmbedCode">{{ t('demo_admin_page.copy_embed') }}</el-button>
                            </el-card>
                            <el-card shadow="never" class="mt-3">
                                <template #header><span>{{ t('demo_admin_page.embed_options') }}</span></template>
                                <el-descriptions :column="1" border size="small">
                                    <el-descriptions-item :label="t('demo_admin_page.embed_js')">{{ embedJsUrl }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('demo_admin_page.demo_url')">https://demo.huwutong.com</el-descriptions-item>
                                    <el-descriptions-item :label="t('demo_admin_page.modes')">floating / inline / modal</el-descriptions-item>
                                </el-descriptions>
                                <pre class="config-example mt-2">{{ embedConfigExample }}</pre>
                            </el-card>
                        </el-tab-pane>
                        <el-tab-pane :label="t('demo_admin_page.tabs.sessions')" name="sessions">
                            <div class="section-toolbar">
                                <el-select v-model="sessionFilter.status" :placeholder="t('demo_admin_page.filter_status')" clearable style="width:150px" @change="loadSessions">
                                    <el-option :label="t('demo_admin_page.status.all')" value="" />
                                    <el-option :label="t('demo_admin_page.status.active')" value="active" />
                                    <el-option :label="t('demo_admin_page.status.completed')" value="completed" />
                                    <el-option :label="t('demo_admin_page.status.expired')" value="expired" />
                                </el-select>
                            </div>
                            <el-table :data="sessions" stripe v-loading="sessionsLoading">
                                <el-table-column prop="id" :label="t('demo_admin_page.cols.id')" width="60" />
                                <el-table-column :label="t('demo_admin_page.cols.status')" width="80"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'completed' ? 'primary' : 'info'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                                <el-table-column prop="ip_address" :label="t('demo_admin_page.cols.ip')" width="140" />
                                <el-table-column prop="step" :label="t('demo_admin_page.cols.step')" width="60" />
                                <el-table-column prop="created_at" :label="t('demo_admin_page.cols.started')" width="170" />
                                <el-table-column prop="expires_at" :label="t('demo_admin_page.cols.expires')" width="170" />
                                <el-table-column prop="last_activity_at" :label="t('demo_admin_page.cols.last_activity')" width="170" />
                            </el-table>
                            <div class="pagination-wrap" v-if="sessions.length">
                                <el-pagination background layout="prev,pager,next" :total="sessionsTotal" :current="sessionsPage" @current-change="loadSessions" />
                            </div>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';
import { getDemoBookingList, updateDemoBookingStatus, getDemoBookingStats, getCalendlyLink } from '@/api/demoBooking';
import demoAdminApi from '@/api/demoAdmin';

const { t } = useI18n();
const mainTab = ref('booking');

// ================================================================
// Tab 1: Demo 预约
// ================================================================
const list = ref([]);
const loading = ref(false);
const stats = ref({});
const calendly = ref({ enabled: false, link: '' });
const filterStatus = ref('');

const statusLabels = computed(() => ({
    pending: t('demo_booking_page.status.pending'),
    contacted: t('demo_booking_page.status.contacted'),
    scheduled: t('demo_booking_page.status.scheduled'),
    completed: t('demo_booking_page.status.completed'),
    converted: t('demo_booking_page.status.converted'),
    lost: t('demo_booking_page.status.lost'),
}));

const statusTag = (s) => ({ pending: 'info', contacted: 'warning', scheduled: 'primary', completed: 'success', converted: 'success', lost: 'danger' }[s] || 'info');

async function loadList() {
    loading.value = true;
    try { const res = await getDemoBookingList({ status: filterStatus.value || undefined }); if (res.data.success) list.value = res.data.data.data || []; }
    catch { list.value = []; }
    finally { loading.value = false; }
}
async function loadStats() { try { const res = await getDemoBookingStats(); if (res.data.success) stats.value = res.data.data; } catch {} }
async function loadCalendly() { try { const res = await getCalendlyLink(); if (res.data.success) calendly.value = res.data.data; } catch {} }
async function handleUpdateStatus(id, status) {
    try { const res = await updateDemoBookingStatus(id, status); if (res.data.success) ElMessage.success(t('demo_booking_page.messages.status_updated')); }
    catch { ElMessage.error(t('demo_booking_page.messages.update_fail')); }
}
async function copyBookingText(text) {
    try { await navigator.clipboard.writeText(text); ElMessage.success(t('demo_booking_page.messages.copied')); } catch {}
}

// ================================================================
// Tab 2: 产品演示管理
// ================================================================
const adminLoading = ref(false);
const adminSubTab = ref('dashboard');
const saving = ref(false);
const sessionsLoading = ref(false);
const sessions = ref([]);
const sessionsTotal = ref(0);
const sessionsPage = ref(1);
const embedCode = ref('');
const embedJsUrl = ref('');

const analytics = reactive({ total: 0, active: 0, completed: 0, expired: 0, today: 0, this_week: 0, this_month: 0, avg_steps_completed: 0, conversion_rate: 0, registrations: 0, daily_trend: [], browsers: [] });
const config = reactive({ enabled: true, session_duration_minutes: 30, extend_minutes: 15, cta_title: '', cta_description: '' });
const configForm = reactive({ enabled: true, session_duration_minutes: 30, extend_minutes: 15, cta_title: '', cta_description: '' });
const sessionFilter = reactive({ status: '' });

const embedConfigExample = computed(() => `// ${t('demo_admin_page.embed_example.optional')}
window.HWT_DEMO_CONFIG = {
  mode: 'floating',      // floating | inline | modal
  position: 'bottom-right',
  buttonText: '${t('demo_admin_page.embed_example.button')}',
  themeColor: '#0f172a',
  autoOpen: false,
  autoOpenDelay: 3000,
  onRegister: function(data) {
    // ${t('demo_admin_page.embed_example.callback')}
  },
};`);

function barWidth(val, type) {
    const max = type === 'starts' ? Math.max(...analytics.daily_trend.map(d => d.starts), 1) : Math.max(...analytics.daily_trend.map(d => d.completions), 1);
    return (val / max) * 100;
}
function browserPct(count) {
    const total = analytics.browsers.reduce((s, b) => s + b.count, 0);
    return total > 0 ? Math.round((count / total) * 100) : 0;
}

async function refreshAll() {
    adminLoading.value = true;
    try {
        const [anaRes, cfgRes, embedRes] = await Promise.all([demoAdminApi.getAnalytics(), demoAdminApi.getConfig(), demoAdminApi.getEmbedCode()]);
        if (anaRes?.data) Object.assign(analytics, anaRes.data);
        if (cfgRes?.data) { Object.assign(config, cfgRes.data); Object.assign(configForm, cfgRes.data); }
        if (embedRes?.data) { embedCode.value = embedRes.data.embed_code; embedJsUrl.value = embedRes.data.embed_js_url; }
    } catch { ElMessage.error(t('messages.load_failed')); }
    finally { adminLoading.value = false; }
}
async function saveConfig() {
    saving.value = true;
    try { const { data } = await demoAdminApi.updateConfig(configForm); if (data?.data) Object.assign(config, data.data); ElMessage.success(t('demo_admin_page.messages.saved')); }
    catch { ElMessage.error(t('demo_admin_page.messages.save_failed')); }
    finally { saving.value = false; }
}
async function loadSessions(page) {
    if (page) sessionsPage.value = page;
    sessionsLoading.value = true;
    try {
        const { data } = await demoAdminApi.getSessions({ page: sessionsPage.value, per_page: 15, status: sessionFilter.status || undefined });
        if (data?.data) { sessions.value = data.data.data || data.data || []; sessionsTotal.value = data.data.total || 0; }
    } finally { sessionsLoading.value = false; }
}
async function copyEmbedCode() {
    try { await navigator.clipboard.writeText(embedCode.value); ElMessage.success(t('demo_admin_page.messages.copied')); }
    catch { ElMessage.warning(t('demo_admin_page.messages.copy_failed')); }
}

// ===== Lazy loading =====
function onMainTabChange(tab) {
    if (tab === 'admin' && !analytics.total) refreshAll();
}

onMounted(() => { loadList(); loadStats(); loadCalendly(); });
</script>

<style scoped>
.demo-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.tab-header-right { display: flex; justify-content: flex-end; gap: 8px; align-items: center; margin-bottom: 12px; }

/* --- Booking Tab --- */
.mb-4 { margin-bottom: 16px; } .mt-2 { margin-top: 8px; } .mt-3 { margin-top: 12px; } .p-4 { padding: 16px; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-actions { display: flex; gap: 8px; }
.stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.stat-item { text-align: center; padding: 8px; }
.stat-item .stat-value { font-size: 24px; font-weight: 700; color: #0f172a; }
.stat-item .stat-label { font-size: 12px; color: #909399; margin-top: 4px; }

/* --- Admin Tab --- */
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.text-green { color: #67c23a; } .text-blue { color: #0f172a; } .text-yellow { color: #e6a23c; } .text-red { color: #f56c6c; }
.text-gray { color: #909399; } .text-center { text-align: center; }
.section-toolbar { margin-bottom: 12px; }
.trend-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 13px; }
.trend-date { width: 50px; flex-shrink: 0; }
.trend-bar-container { flex: 1; height: 24px; background: #f0f0f0; border-radius: 4px; position: relative; overflow: hidden; }
.trend-bar { position: absolute; height: 100%; border-radius: 4px; display: flex; align-items: center; padding-left: 6px; font-size: 11px; color: #fff; transition: width 0.3s; }
.trend-bar.starts { background: #0f172a; z-index: 1; }
.trend-bar.completions { background: #67c23a; z-index: 2; }
.browser-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; font-size: 13px; }
.browser-name { width: 100px; flex-shrink: 0; }
.browser-count { width: 40px; text-align: right; color: #909399; }
.embed-code { background: #1d1e1f; color: #e6e6e6; padding: 16px; border-radius: 6px; font-size: 13px; line-height: 1.6; overflow-x: auto; white-space: pre-wrap; max-height: 300px; }
.config-example { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; line-height: 1.5; color: #606266; }
.pagination-wrap { margin-top: 16px; text-align: center; }

:deep(.el-card__body) { padding: 16px; }
</style>
