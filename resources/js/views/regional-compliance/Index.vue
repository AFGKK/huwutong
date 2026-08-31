<template>
    <div class="regional-compliance-page">
        <h2>{{ t(`${P}.title`) }}</h2>

        <div v-if="!initialized" style="margin-bottom:20px">
            <el-alert :title="t(`${P}.init_alert.title`)" type="warning" :closable="false" show-icon>
                <template #default>
                    <p>{{ t(`${P}.init_alert.desc`) }}</p>
                    <el-button type="primary" @click="handleInitialize" :loading="initializing" style="margin-top:8px">{{ t(`${P}.init_alert.button`) }}</el-button>
                </template>
            </el-alert>
        </div>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total_regions || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.total_regions`) }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.compliance_percentage || 0 }}%</div><div class="stat-label">{{ t(`${P}.stats.compliance_rate`) }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.met_requirements || 0 }}/{{ stats.total_requirements || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.requirements`) }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.active_restrictions || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.sales_restrictions`) }}</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.recent_logs?.length || 0 }}</div><div class="stat-label">{{ t(`${P}.stats.recent_logs`) }}</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane :label="t(`${P}.tabs.overview`)" name="overview">
                <div v-if="Object.keys(byRegion).length === 0" style="text-align:center;padding:40px;color:#909399">
                    {{ t(`${P}.overview.empty`) }}
                </div>
                <el-row :gutter="20" v-else>
                    <el-col :span="12" v-for="(region, key) in byRegion" :key="key" style="margin-bottom:16px">
                        <el-card shadow="hover">
                            <template #header>
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <span><strong>{{ region.config.region_name || key }}</strong> ({{ key }})</span>
                                    <el-tag :type="region.checks.met_count === region.checks.total_count ? 'success' : 'warning'" size="small">
                                        {{ region.checks.met_count }}/{{ region.checks.total_count }}
                                    </el-tag>
                                </div>
                            </template>
                            <div class="region-requirements">
                                <div v-for="(check, ck) in region.checks.details" :key="ck" class="requirement-item">
                                    <el-icon v-if="check.status === 'met'" color="#67c23a"><CircleCheck /></el-icon>
                                    <el-icon v-else color="#e6a23c"><Warning /></el-icon>
                                    <span>{{ check.label }}</span>
                                    <el-tag :type="check.status === 'met' ? 'success' : 'warning'" size="small" style="margin-left:auto">
                                        {{ checkStatusLabel(check.status) }}
                                    </el-tag>
                                </div>
                            </div>
                            <el-divider style="margin:8px 0" />
                            <div style="font-size:12px;color:#909399">
                                {{ t(`${P}.overview.tax_type`) }}: {{ region.config.tax_type || '-' }} | {{ t(`${P}.overview.tax_rate`) }}: {{ region.config.tax_rate }}%
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.configs`)" name="configs">
                <el-table :data="configs" v-loading="configsLoading" stripe>
                    <el-table-column prop="region_key" :label="t(`${P}.cols.region`)" width="120">
                        <template #default="{row}"><el-tag>{{ row.region_key }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="region_name" :label="t(`${P}.cols.name`)" width="120" />
                    <el-table-column :label="t(`${P}.cols.compliance`)" min-width="280">
                        <template #default="{row}">
                            <el-tag v-if="row.gdpr_enabled" size="small" style="margin:2px">{{ t(`${P}.requirements.gdpr`) }}</el-tag>
                            <el-tag v-if="row.pipl_enabled" size="small" type="warning" style="margin:2px">{{ t(`${P}.requirements.pipl`) }}</el-tag>
                            <el-tag v-if="row.vat_enabled" size="small" type="success" style="margin:2px">{{ t(`${P}.requirements.vat`) }}</el-tag>
                            <el-tag v-if="row.data_residency_enabled" size="small" type="info" style="margin:2px">{{ t(`${P}.requirements.data_residency`) }}</el-tag>
                            <el-tag v-if="row.cookie_consent_enabled" size="small" type="danger" style="margin:2px">{{ t(`${P}.requirements.cookie`) }}</el-tag>
                            <el-tag v-if="row.tax_reporting_enabled" size="small" style="margin:2px">{{ t(`${P}.requirements.tax_reporting`) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="tax_type" :label="t(`${P}.cols.tax_type`)" width="80" />
                    <el-table-column prop="tax_rate" :label="t(`${P}.cols.tax_rate`)" width="70"><template #default="{row}">{{ row.tax_rate }}%</template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.active`)" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_active?'success':'info'" size="small">{{ row.is_active ? yesLabel : noLabel }}</el-tag></template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.actions`)" width="100">
                        <template #default="{row}">
                            <el-button link type="primary" size="small" @click="openConfigEdit(row)">{{ t('actions.edit') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showConfigDialog" :title="t(`${P}.dialogs.edit_config`)" width="550px">
                    <el-form :model="editForm" label-width="130px" size="small">
                        <el-form-item :label="t(`${P}.forms.region_name`)"><el-input v-model="editForm.region_name" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.compliance`)">
                            <el-checkbox v-model="editForm.gdpr_enabled">{{ t(`${P}.requirements.gdpr`) }}</el-checkbox>
                            <el-checkbox v-model="editForm.pipl_enabled">{{ t(`${P}.requirements.pipl`) }}</el-checkbox>
                            <el-checkbox v-model="editForm.vat_enabled">{{ t(`${P}.requirements.vat_gst`) }}</el-checkbox>
                            <el-checkbox v-model="editForm.data_residency_enabled">{{ t(`${P}.requirements.data_residency`) }}</el-checkbox>
                            <el-checkbox v-model="editForm.cookie_consent_enabled">{{ t(`${P}.requirements.cookie_consent`) }}</el-checkbox>
                            <el-checkbox v-model="editForm.tax_reporting_enabled">{{ t(`${P}.requirements.tax_reporting`) }}</el-checkbox>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.forms.tax_type`)">
                            <el-select v-model="editForm.tax_type" style="width:100%">
                                <el-option v-for="opt in taxTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.forms.tax_rate`)"><el-input-number v-model="editForm.tax_rate" :min="0" :max="100" :precision="2" style="width:100%" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.reporting_frequency`)">
                            <el-select v-model="editForm.tax_reporting_frequency" style="width:100%">
                                <el-option v-for="opt in frequencyOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.forms.digital_service_tax`)"><el-switch v-model="editForm.digital_service_tax" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.currency`)"><el-input v-model="editForm.currency" maxlength="10" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.timezone`)"><el-input v-model="editForm.timezone" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.enabled`)"><el-switch v-model="editForm.is_active" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showConfigDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="handleConfigSave">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.restrictions`)" name="restrictions">
                <div class="toolbar">
                    <el-button type="primary" @click="showAddRestriction = true">{{ t(`${P}.buttons.add_restriction`) }}</el-button>
                    <el-select v-model="restrictionFilterRegion" :placeholder="t(`${P}.filters.region`)" clearable style="width:140px;margin-left:8px" @change="loadRestrictions">
                        <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                    </el-select>
                    <el-button @click="loadRestrictions" style="margin-left:8px">{{ t('compliance_page.buttons.refresh') }}</el-button>
                </div>
                <el-table :data="restrictions" v-loading="restLoading" stripe>
                    <el-table-column prop="region_key" :label="t(`${P}.cols.region`)" width="90"><template #default="{row}"><el-tag size="small">{{ row.region_key }}</el-tag></template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.target`)" width="150"><template #default="{row}">{{ row.restrictable_type }} #{{ row.restrictable_id }}</template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.allowed`)" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_allowed?'success':'danger'" size="small">{{ row.is_allowed ? yesLabel : noLabel }}</el-tag></template></el-table-column>
                    <el-table-column prop="reason" :label="t(`${P}.cols.reason`)" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="effective_at" :label="t(`${P}.cols.effective_at`)" width="170" />
                    <el-table-column prop="expires_at" :label="t(`${P}.cols.expires_at`)" width="170" />
                    <el-table-column :label="t(`${P}.cols.actions`)" width="80">
                        <template #default="{row}">
                            <el-button link type="danger" size="small" @click="handleRemoveRestriction(row.id)">{{ t(`${P}.buttons.remove`) }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showAddRestriction" :title="t(`${P}.dialogs.add_restriction`)" width="450px">
                    <el-form :model="restForm" label-width="100px" size="small">
                        <el-form-item :label="t(`${P}.forms.target_type`)">
                            <el-select v-model="restForm.restrictable_type" style="width:100%">
                                <el-option v-for="opt in targetTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.forms.target_id`)"><el-input-number v-model="restForm.restrictable_id" :min="1" style="width:100%" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.region`)">
                            <el-select v-model="restForm.region_key" style="width:100%">
                                <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t(`${P}.forms.allow_sales`)"><el-switch v-model="restForm.is_allowed" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.reason`)"><el-input v-model="restForm.reason" type="textarea" :rows="2" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.effective_at`)"><el-date-picker v-model="restForm.effective_at" type="datetime" style="width:100%" /></el-form-item>
                        <el-form-item :label="t(`${P}.forms.expires_at`)"><el-date-picker v-model="restForm.expires_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showAddRestriction = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="handleAddRestriction">{{ t(`${P}.buttons.add`) }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.report`)" name="report">
                <div class="toolbar">
                    <el-button type="primary" @click="handleGenerateSummary">{{ t(`${P}.buttons.generate_report`) }}</el-button>
                </div>
                <div v-if="complianceSummary.length > 0">
                    <el-card v-for="item in complianceSummary" :key="item.region" shadow="hover" style="margin-bottom:12px">
                        <template #header>
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <span><strong>{{ item.region_name }} ({{ item.region }})</strong></span>
                                <el-tag :type="item.compliance_score >= 80 ? 'success' : item.compliance_score >= 50 ? 'warning' : 'danger'" size="large">
                                    {{ item.compliance_score }}%
                                </el-tag>
                            </div>
                        </template>
                        <div v-for="(check, ck) in item.checks" :key="ck" style="display:flex;align-items:center;gap:8px;padding:4px 0">
                            <el-icon v-if="check.status === 'met'" color="#67c23a"><CircleCheck /></el-icon>
                            <el-icon v-else color="#e6a23c"><Warning /></el-icon>
                            <span>{{ check.label }}</span>
                            <el-tag :type="check.status === 'met' ? 'success' : 'warning'" size="small" style="margin-left:auto">
                                {{ checkStatusLabel(check.status) }}
                            </el-tag>
                        </div>
                    </el-card>
                </div>
                <div v-else style="text-align:center;padding:40px;color:#909399">
                    {{ t(`${P}.report.empty`) }}
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t(`${P}.tabs.logs`)" name="logs">
                <div class="toolbar">
                    <el-select v-model="logFilterRegion" :placeholder="t(`${P}.filters.region_ph`)" clearable style="width:140px;margin-right:8px" @change="loadLogs">
                        <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                    </el-select>
                    <el-select v-model="logFilterAction" :placeholder="t(`${P}.filters.action_type`)" clearable style="width:160px;margin-right:8px" @change="loadLogs">
                        <el-option v-for="opt in logActionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-button @click="loadLogs">{{ t('compliance_page.buttons.refresh') }}</el-button>
                </div>
                <el-table :data="logs" v-loading="logsLoading" stripe>
                    <el-table-column prop="occurred_at" :label="t(`${P}.cols.time`)" width="170" />
                    <el-table-column prop="region_key" :label="t(`${P}.cols.region`)" width="90"><template #default="{row}"><el-tag size="small">{{ row.region_key }}</el-tag></template></el-table-column>
                    <el-table-column prop="action_type" :label="t(`${P}.cols.action_type`)" width="140"><template #default="{row}"><el-tag size="small">{{ row.action_type }}</el-tag></template></el-table-column>
                    <el-table-column :label="t(`${P}.cols.status`)" width="80"><template #default="{row}"><el-tag :type="row.status==='success'?'success':'danger'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="description" :label="t(`${P}.cols.description`)" min-width="300" show-overflow-tooltip />
                    <el-table-column prop="performed_by" :label="t(`${P}.cols.performed_by`)" width="150" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { CircleCheck, Warning } from '@element-plus/icons-vue';
import {
    getRegionalComplianceDashboard, initializeRegionalCompliance,
    getRegionalComplianceConfigs, updateRegionalComplianceConfig,
    getRegionalRestrictions, addRegionalRestriction, removeRegionalRestriction,
    generateRegionalComplianceSummary, getRegionalComplianceLogs, getAvailableRegions,
} from '@/api/regionalCompliance';

const { t } = useI18n();
const P = 'regional_compliance_page';

const activeTab = ref('overview');
const initialized = ref(true);
const initializing = ref(false);
const stats = ref({});
const byRegion = ref({});
const configs = ref([]);
const configsLoading = ref(false);
const restrictions = ref([]);
const restLoading = ref(false);
const restrictionFilterRegion = ref('');
const logs = ref([]);
const logsLoading = ref(false);
const logFilterRegion = ref('');
const logFilterAction = ref('');
const showConfigDialog = ref(false);
const editForm = reactive({
    region_name: '', gdpr_enabled: false, pipl_enabled: false, vat_enabled: false,
    data_residency_enabled: false, cookie_consent_enabled: true, tax_reporting_enabled: true,
    tax_type: '', tax_rate: 0, tax_reporting_frequency: 'quarterly',
    digital_service_tax: false, currency: '', timezone: '', is_active: true,
});
const currentConfigKey = ref('');
const showAddRestriction = ref(false);
const restForm = reactive({
    restrictable_type: 'product', restrictable_id: 1, region_key: 'cn',
    is_allowed: false, reason: '', effective_at: null, expires_at: null,
});
const complianceSummary = ref([]);
const availableRegions = ref({});

const yesLabel = computed(() => t(`${P}.yes`));
const noLabel = computed(() => t(`${P}.no`));

const taxTypeOptions = computed(() => [
    { value: '', label: t(`${P}.tax_types.none`) },
    { value: 'vat', label: t(`${P}.tax_types.vat`) },
    { value: 'sales_tax', label: t(`${P}.tax_types.sales_tax`) },
    { value: 'gst', label: t(`${P}.tax_types.gst`) },
]);

const frequencyOptions = computed(() =>
    ['monthly', 'quarterly', 'yearly'].map((k) => ({ value: k, label: t(`${P}.frequencies.${k}`) }))
);

const targetTypeOptions = computed(() =>
    ['product', 'plan', 'sku'].map((k) => ({ value: k, label: t(`${P}.target_types.${k}`) }))
);

const logActionOptions = computed(() =>
    ['report_generated', 'tax_filed', 'config_updated', 'sales_blocked', 'sales_unblocked'].map((k) => ({
        value: k,
        label: t(`${P}.log_actions.${k}`),
    }))
);

function checkStatusLabel(status) {
    return status === 'met' ? t(`${P}.status.met`) : t(`${P}.status.unmet`);
}

async function loadDashboard() {
    try {
        const r = await getRegionalComplianceDashboard();
        stats.value = r.stats || {};
        byRegion.value = r.byRegion || {};
    } catch (e) {
        initialized.value = false;
        console.error(e);
    }
}
async function loadConfigs() {
    configsLoading.value = true;
    try { configs.value = await getRegionalComplianceConfigs() || []; }
    catch (e) { console.error(e); } finally { configsLoading.value = false; }
}
async function loadRestrictions() {
    restLoading.value = true;
    try {
        const r = await getRegionalRestrictions({ region_key: restrictionFilterRegion.value || undefined });
        restrictions.value = r.data || [];
    } catch (e) { console.error(e); } finally { restLoading.value = false; }
}
async function loadLogs() {
    logsLoading.value = true;
    try {
        const r = await getRegionalComplianceLogs({
            region_key: logFilterRegion.value || undefined,
            action_type: logFilterAction.value || undefined,
            per_page: 50,
        });
        logs.value = r.data || [];
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}
async function loadAvailableRegions() {
    try { availableRegions.value = await getAvailableRegions() || {}; }
    catch (e) { console.error(e); }
}

async function handleInitialize() {
    initializing.value = true;
    try {
        await initializeRegionalCompliance();
        ElMessage.success(t(`${P}.messages.init_ok`));
        initialized.value = true;
        loadDashboard();
        loadConfigs();
    } catch (e) { ElMessage.error(t(`${P}.messages.init_failed`)); } finally { initializing.value = false; }
}

function openConfigEdit(row) {
    Object.assign(editForm, {
        region_name: row.region_name,
        gdpr_enabled: row.gdpr_enabled,
        pipl_enabled: row.pipl_enabled,
        vat_enabled: row.vat_enabled,
        data_residency_enabled: row.data_residency_enabled,
        cookie_consent_enabled: row.cookie_consent_enabled,
        tax_reporting_enabled: row.tax_reporting_enabled,
        tax_type: row.tax_type || '',
        tax_rate: row.tax_rate || 0,
        tax_reporting_frequency: row.tax_reporting_frequency || 'quarterly',
        digital_service_tax: row.digital_service_tax || false,
        currency: row.currency || '',
        timezone: row.timezone || '',
        is_active: row.is_active,
    });
    currentConfigKey.value = row.region_key;
    showConfigDialog.value = true;
}

async function handleConfigSave() {
    try {
        await updateRegionalComplianceConfig(currentConfigKey.value, { ...editForm });
        ElMessage.success(t(`${P}.messages.config_updated`));
        showConfigDialog.value = false;
        loadConfigs();
        loadDashboard();
    } catch (e) { ElMessage.error(t(`${P}.messages.update_failed`)); }
}

async function handleAddRestriction() {
    try {
        await addRegionalRestriction({ ...restForm });
        ElMessage.success(t(`${P}.messages.restriction_added`));
        showAddRestriction.value = false;
        loadRestrictions();
        loadDashboard();
    } catch (e) { ElMessage.error(t(`${P}.messages.add_failed`)); }
}

async function handleRemoveRestriction(id) {
    try {
        await removeRegionalRestriction(id);
        ElMessage.success(t(`${P}.messages.restriction_removed`));
        loadRestrictions();
        loadDashboard();
    } catch (e) { ElMessage.error(t(`${P}.messages.remove_failed`)); }
}

async function handleGenerateSummary() {
    try {
        complianceSummary.value = await generateRegionalComplianceSummary() || [];
        ElMessage.success(t(`${P}.messages.report_generated`));
    } catch (e) { ElMessage.error(t(`${P}.messages.generate_failed`)); }
}

onMounted(() => {
    loadDashboard();
    loadConfigs();
    loadRestrictions();
    loadLogs();
    loadAvailableRegions();
});
</script>

<style scoped>
.regional-compliance-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
.region-requirements { display: flex; flex-direction: column; gap: 6px; }
.requirement-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
</style>
