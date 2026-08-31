<template>
    <div class="test-env-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('sandbox_center.title') }}</h2>
                <span class="header-subtitle">{{ t('sandbox_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="testMainTab" type="border-card" @tab-change="onTestMainTabChange">
            <!-- ===== Tab 1: 开发者沙箱 ===== -->
            <el-tab-pane :label="t('sandbox_center.tab_dev')" name="sandbox">
                <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
                    <!-- ===== Tab 1: 开发者沙箱 ===== -->
                    <el-tab-pane :label="t('sandbox_center.tab_dev')" name="dev">
                        <!-- 未创建沙箱 -->
                        <div v-if="!sandboxActive && !loadingStatus" class="welcome-section">
                            <el-result icon="success" :title="t('sandbox_page.title')" :sub-title="t('sandbox_page.welcome_subtitle')">
                                <template #extra>
                                    <el-button type="primary" size="large" :loading="creating" @click="handleCreate">
                                        <el-icon><CirclePlus /></el-icon> {{ t('sandbox_page.create_now') }}
                                    </el-button>
                                </template>
                            </el-result>

                            <el-row :gutter="24" class="features-row">
                                <el-col :span="8" v-for="feature in features" :key="feature.key">
                                    <el-card shadow="never" class="feature-card">
                                        <el-icon :size="32" :color="feature.color">
                                            <component :is="feature.icon" />
                                        </el-icon>
                                        <h4>{{ feature.title }}</h4>
                                        <p>{{ feature.desc }}</p>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </div>

                        <!-- 沙箱已激活 -->
                        <template v-if="sandboxActive">
                            <div class="tab-header">
                                <el-button type="danger" :loading="resetting" @click="handleReset"><el-icon><Refresh /></el-icon> {{ t('sandbox_page.reset_sandbox') }}</el-button>
                            </div>
                            <el-row :gutter="16" class="info-row">
                                <el-col :span="6" v-for="stat in sandboxStats" :key="stat.key">
                                    <el-card shadow="never" class="stat-card" :body-style="{ padding: '16px' }">
                                        <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                                        <div class="stat-label">{{ stat.label }}</div>
                                        <div v-if="stat.sub" class="stat-sub">{{ stat.sub }}</div>
                                    </el-card>
                                </el-col>
                            </el-row>

                            <el-card shadow="never" class="table-card">
                                <template #header>
                                    <div class="card-header">
                                        <span>{{ t('sandbox_page.license_card_title') }}</span>
                                        <el-tag type="success" effect="plain" size="small">
                                            {{ t('sandbox_page.rate_limit_tag', { limit: sandboxInfo?.rate_limit || t('sandbox_page.rate_limit_default') }) }}
                                        </el-tag>
                                    </div>
                                </template>
                                <el-table :data="sandboxLicenses" v-loading="loadingLicenses" stripe>
                                    <el-table-column :label="t('sandbox_page.columns.license_key')" min-width="220" prop="license_key">
                                        <template #default="{ row }">
                                            <code class="license-key">{{ row.license_key }}</code>
                                            <el-button text size="small" type="primary" @click="copyKey(row.license_key)"><el-icon><CopyDocument /></el-icon></el-button>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('sandbox_page.columns.product')" width="150" prop="product_name" />
                                    <el-table-column :label="t('sandbox_page.columns.type')" width="100" prop="type">
                                        <template #default="{ row }"><el-tag size="small" effect="plain" type="warning">{{ t('sandbox_page.type_sandbox') }}</el-tag></template>
                                    </el-table-column>
                                    <el-table-column :label="t('sandbox_page.columns.status')" width="100" prop="status">
                                        <template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ statusLabel(row.status) }}</el-tag></template>
                                    </el-table-column>
                                    <el-table-column :label="t('sandbox_page.columns.device_binding')" width="100" prop="device_count" align="center" />
                                    <el-table-column :label="t('sandbox_page.columns.expires_at')" width="170" prop="expires_at">
                                        <template #default="{ row }">{{ formatDate(row.expires_at) || t('sandbox_page.no_expiry') }}</template>
                                    </el-table-column>
                                </el-table>
                            </el-card>

                            <el-card shadow="never" class="quickstart-card">
                                <template #header><span>{{ t('sandbox_page.quickstart.title') }}</span></template>
                                <el-alert :title="t('sandbox_page.quickstart.alert')" type="info" show-icon :closable="false" />
                                <div class="quickstart-content">
                                    <el-descriptions :column="2" border size="small" class="mt-3">
                                        <el-descriptions-item :label="t('sandbox_page.quickstart.api_url')">
                                            <code>{{ apiBaseUrl }}</code>
                                            <el-button text size="small" type="primary" @click="copyText(apiBaseUrl)">{{ t('actions.copy') }}</el-button>
                                        </el-descriptions-item>
                                        <el-descriptions-item :label="t('sandbox_page.quickstart.rate_limit')">
                                            {{ sandboxInfo?.rate_limit || t('sandbox_page.quickstart.rate_limit_default') }}
                                        </el-descriptions-item>
                                        <el-descriptions-item :label="t('sandbox_page.quickstart.sample_license')">
                                            <code>{{ firstLicenseKey }}</code>
                                            <el-button text size="small" type="primary" @click="copyText(firstLicenseKey)">{{ t('actions.copy') }}</el-button>
                                        </el-descriptions-item>
                                        <el-descriptions-item :label="t('sandbox_page.quickstart.data_isolation')">
                                            {{ t('sandbox_page.quickstart.isolation_desc') }}
                                        </el-descriptions-item>
                                    </el-descriptions>
                                    <el-button type="primary" @click="$router.push('/wizard')" class="mt-3">
                                        <el-icon><MagicStick /></el-icon> {{ t('sandbox_page.quickstart.use_wizard') }}
                                    </el-button>
                                </div>
                            </el-card>
                        </template>
                    </el-tab-pane>

                    <!-- ===== Tab 2: 代码沙箱 ===== -->
                    <el-tab-pane :label="t('sandbox_center.tab_code')" name="code">
                        <div class="tab-header">
                            <el-button @click="loadLanguages" :loading="loadingLangs"><el-icon><Refresh /></el-icon> {{ t('code_sandbox_page.check_env') }}</el-button>
                            <el-button @click="clearOutput"><el-icon><Delete /></el-icon> {{ t('code_sandbox_page.clear_output') }}</el-button>
                        </div>
                        <el-row :gutter="16">
                            <el-col :span="14">
                                <el-card shadow="never">
                                    <template #header>
                                        <div class="sandbox-header">
                                            <div class="flex gap-2 items-center">
                                                <el-select v-model="language" style="width:130px" @change="onLanguageChange">
                                                    <el-option v-for="opt in languageOptions" :key="opt.value" :label="opt.label" :value="opt.value" :disabled="opt.disabled" />
                                                </el-select>
                                                <span class="lang-version" v-if="langVersions[language]">v{{ langVersions[language] }}</span>
                                            </div>
                                            <div class="flex gap-2">
                                                <el-button text size="small" @click="loadTemplate">{{ t('code_sandbox_page.sample_code') }}</el-button>
                                                <el-button type="primary" :loading="running" @click="runCode"><el-icon><CaretRight /></el-icon> {{ t('code_sandbox_page.run') }}</el-button>
                                            </div>
                                        </div>
                                    </template>
                                    <div class="editor-wrap">
                                        <textarea ref="editorRef" v-model="code" class="code-editor" spellcheck="false" :placeholder="codePlaceholder" @keydown="handleKeydown"></textarea>
                                    </div>
                                    <div class="editor-info">
                                        <span>{{ t('code_sandbox_page.char_count', { current: code.length, max: maxLength }) }}</span>
                                        <span v-if="code.length > maxLength" class="text-danger">{{ t('code_sandbox_page.over_limit') }}</span>
                                    </div>
                                </el-card>
                            </el-col>
                            <el-col :span="10">
                                <el-card shadow="never">
                                    <template #header>
                                        <div class="sandbox-header">
                                            <span>{{ t('code_sandbox_page.output_title') }}</span>
                                            <el-tag v-if="lastResult" :type="lastResult.success ? 'success' : 'danger'" size="small">{{ executionStatusLabel }}</el-tag>
                                        </div>
                                    </template>
                                    <div class="output-wrap">
                                        <div v-if="!lastResult" class="output-placeholder">
                                            <el-icon :size="40" color="#dcdfe6"><Upload /></el-icon>
                                            <p>{{ t('code_sandbox_page.empty_output_hint') }}</p>
                                        </div>
                                        <div v-else class="output-content">
                                            <div v-if="lastResult.output" class="output-section">
                                                <div class="output-label">{{ t('code_sandbox_page.stdout') }}</div>
                                                <pre class="output-text">{{ lastResult.output }}</pre>
                                            </div>
                                            <div v-if="lastResult.error" class="output-section">
                                                <div class="output-label output-error-label">{{ t('code_sandbox_page.error_output') }}</div>
                                                <pre class="output-text output-error">{{ lastResult.error }}</pre>
                                            </div>
                                            <div class="output-meta">
                                                <span>{{ t('code_sandbox_page.execution_time', { ms: lastResult.execution_time }) }}</span>
                                                <span v-if="lastResult.exit_code !== undefined">{{ t('code_sandbox_page.exit_code', { code: lastResult.exit_code }) }}</span>
                                                <span v-if="lastResult.rows !== undefined">{{ t('code_sandbox_page.rows', { rows: lastResult.rows }) }}</span>
                                                <span>{{ t('code_sandbox_page.code_length', { length: lastResult.code_length }) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </el-card>
                            </el-col>
                        </el-row>
                    </el-tab-pane>
                </el-tabs>
            </el-tab-pane>

            <!-- ===== Tab 2: Staging 环境 ===== -->
            <el-tab-pane :label="t('staging_page.title')" name="staging">
                <template v-if="sg_tabVisited">
                    <div class="tab-header">
                        <el-button v-if="sg_stagingActive" type="danger" :loading="sg_resetting" @click="sg_handleReset">
                            <el-icon><Refresh /></el-icon> {{ t('staging_page.buttons.reset_env') }}
                        </el-button>
                        <el-button v-else type="primary" :loading="sg_creating" @click="sg_handleCreate">
                            <el-icon><CirclePlus /></el-icon> {{ t('staging_page.buttons.request_staging') }}
                        </el-button>
                    </div>

                    <!-- 还没有 Staging 环境 -->
                    <div v-if="!sg_stagingActive && !sg_loadingStatus" class="welcome-section">
                        <el-result
                            icon="success"
                            :title="t('staging_page.welcome.title')"
                            :sub-title="t('staging_page.welcome.subtitle')"
                        >
                            <template #extra>
                                <el-button type="primary" size="large" :loading="sg_creating" @click="sg_handleCreate">
                                    <el-icon><CirclePlus /></el-icon> {{ t('staging_page.buttons.apply_now') }}
                                </el-button>
                            </template>
                        </el-result>

                        <el-row :gutter="24" class="features-row">
                            <el-col :span="6" v-for="f in sg_features" :key="f.title">
                                <el-card shadow="never" class="feature-card">
                                    <el-icon :size="28" :color="f.color"><component :is="f.icon" /></el-icon>
                                    <h4>{{ f.title }}</h4>
                                    <p>{{ f.desc }}</p>
                                </el-card>
                            </el-col>
                        </el-row>
                    </div>

                    <!-- Staging 环境详情 -->
                    <template v-if="sg_stagingActive">
                        <el-row :gutter="16" class="info-row">
                            <el-col :span="6" v-for="stat in sg_statsCards" :key="stat.label">
                                <el-card shadow="never" class="stat-card">
                                    <div class="stat-value" :style="{ color: stat.color }">{{ stat.value }}</div>
                                    <div class="stat-label">{{ stat.label }}</div>
                                    <div v-if="stat.sub" class="stat-sub">{{ stat.sub }}</div>
                                </el-card>
                            </el-col>
                        </el-row>

                        <!-- 环境信息 -->
                        <el-card shadow="never" class="detail-card">
                            <template #header>
                                <span>{{ t('staging_page.detail.env_details') }}</span>
                                <el-tag
                                    :type="sg_envInfo.status === 'active' ? 'success' : 'danger'"
                                    size="small"
                                    effect="plain"
                                    style="margin-left: 12px;"
                                >{{ sg_envInfo.status === 'active' ? t('licenses_page.st_active') : sg_envInfo.status }}</el-tag>
                            </template>
                            <el-descriptions :column="2" border size="small">
                                <el-descriptions-item :label="t('staging_page.detail.env_name')">
                                    <template #default>
                                        <el-input v-model="sg_editForm.name" size="small" class="inline-input" @blur="sg_handleUpdate" />
                                    </template>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.subdomain')">
                                    <code>{{ sg_envInfo.subdomain }}.staging.huwutong.com</code>
                                    <el-button text size="small" type="primary" @click="copyText(sg_envInfo.subdomain + '.staging.huwutong.com')">
                                        <el-icon><CopyDocument /></el-icon>
                                    </el-button>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.api_base_url')">
                                    <code>{{ sg_envInfo.api_base_url }}</code>
                                    <el-button text size="small" type="primary" @click="copyText(sg_envInfo.api_base_url)">
                                        <el-icon><CopyDocument /></el-icon>
                                    </el-button>
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.api_rate_limit')">
                                    <el-input-number
                                        v-model="sg_editForm.rate_limit"
                                        :min="30"
                                        :max="600"
                                        size="small"
                                        style="width: 120px;"
                                        @change="sg_handleUpdate"
                                    /> {{ t('staging_page.detail.per_min') }}
                                </el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.created_at')">{{ sg_envInfo.created_at }}</el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.expires_at')">{{ sg_envInfo.expires_at || t('licenses_page.permanent') }}</el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.last_reset')">{{ sg_envInfo.last_reset_at || t('staging_page.detail.not_reset_yet') }}</el-descriptions-item>
                                <el-descriptions-item :label="t('staging_page.detail.data_isolation')">
                                    <el-tag type="success" size="small">{{ t('staging_page.detail.isolated') }}</el-tag>
                                </el-descriptions-item>
                            </el-descriptions>
                        </el-card>

                        <!-- License 列表 -->
                        <el-card shadow="never" class="table-card">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('staging_page.table.license_list', { current: sg_envInfo.licenses_total || 0, limit: sg_envInfo.license_limit }) }}</span>
                                </div>
                            </template>
                            <el-table :data="sg_stagingLicenses" v-loading="sg_loadingLicenses" stripe>
                                <el-table-column :label="t('licenses_page.license_key')" min-width="240" prop="license_key">
                                    <template #default="{ row }">
                                        <code class="license-key">{{ row.license_key }}</code>
                                        <el-button text size="small" type="primary" @click="copyText(row.license_key)">
                                            <el-icon><CopyDocument /></el-icon>
                                        </el-button>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('licenses_page.product')" width="150" prop="product_name" />
                                <el-table-column :label="t('staging_page.stats.status')" width="90" prop="status">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                            {{ row.status === 'active' ? t('licenses_page.st_active') : row.status }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('staging_page.table.devices')" width="120" align="center">
                                    <template #default="{ row }">
                                        {{ row.device_count }} / {{ row.max_devices }}
                                    </template>
                                </el-table-column>
                                <el-table-column :label="t('staging_page.table.expires')" width="170" prop="expires_at">
                                    <template #default="{ row }">{{ formatDate(row.expires_at) || t('licenses_page.permanent') }}</template>
                                </el-table-column>
                            </el-table>
                        </el-card>

                        <!-- 快速集成 -->
                        <el-card shadow="never" class="quickstart-card">
                            <template #header><span>{{ t('staging_page.quickstart.title') }}</span></template>
                            <el-alert :title="t('staging_page.quickstart.alert')" type="info" show-icon :closable="false" />
                            <div class="quickstart-actions mt-3">
                                <el-descriptions :column="2" border size="small">
                                    <el-descriptions-item :label="t('staging_page.quickstart.api_address')">{{ sg_envInfo.api_base_url }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('staging_page.quickstart.rate_policy')">{{ sg_envInfo.rate_limit }}</el-descriptions-item>
                                    <el-descriptions-item :label="t('staging_page.quickstart.sample_license')">
                                        <code>{{ sg_firstKey }}</code>
                                        <el-button text size="small" type="primary" @click="copyText(sg_firstKey)">{{ t('actions.copy') }}</el-button>
                                    </el-descriptions-item>
                                    <el-descriptions-item :label="t('staging_page.quickstart.integration_wizard')">
                                        <el-button type="primary" size="small" @click="$router.push('/wizard')">
                                            {{ t('staging_page.buttons.use_wizard') }}
                                        </el-button>
                                    </el-descriptions-item>
                                </el-descriptions>
                            </div>
                        </el-card>
                    </template>
                </template>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { CirclePlus, Refresh, CopyDocument, Key, Connection, Monitor, WarnTriangleFilled, MagicStick, CaretRight, Delete, Upload, Switch } from '@element-plus/icons-vue';
import sandboxApi from '@/api/sandbox';
import stagingApi from '@/api/staging';
import apiClient from '@/utils/request';

const { t, locale } = useI18n();

// ================================================================
// Outer tabs
// ================================================================
const testMainTab = ref('sandbox');
const sg_tabVisited = ref(false);

function onTestMainTabChange(tab) {
    if (tab === 'staging' && !sg_tabVisited.value) {
        sg_tabVisited.value = true;
    }
}

// ================================================================
// Tab 1: 开发者沙箱
// ================================================================
const mainTab = ref('dev');
const creating = ref(false);
const resetting = ref(false);
const loadingStatus = ref(true);
const loadingLicenses = ref(false);
const sandboxActive = ref(false);
const sandboxInfo = reactive({});
const sandboxLicenses = ref([]);

const featureDefs = [
    { key: 'product', icon: Key, color: '#0f172a' },
    { key: 'licenses', icon: Connection, color: '#67C23A' },
    { key: 'rate_limit', icon: Monitor, color: '#E6A23C' },
    { key: 'isolation', icon: WarnTriangleFilled, color: '#F56C6C' },
    { key: 'free_tier', icon: Key, color: '#909399' },
    { key: 'one_click_reset', icon: Refresh, color: '#0f172a' },
];

const features = computed(() =>
    featureDefs.map(({ key, icon, color }) => ({
        key, title: t(`sandbox_page.features.${key}.title`), desc: t(`sandbox_page.features.${key}.desc`), icon, color,
    }))
);

const apiBaseUrl = window.location.origin || 'https://api.huwutong.com';

const sandboxStats = computed(() => [
    { key: 'total', label: t('sandbox_page.stats.total_licenses'), value: sandboxInfo.licenses_created || 0, color: '#0f172a' },
    { key: 'active', label: t('sandbox_page.stats.active_licenses'), value: sandboxInfo.licenses_active || 0, color: '#67C23A', sub: t('sandbox_page.stats.active_sub') },
    { key: 'devices', label: t('sandbox_page.stats.devices_bound'), value: sandboxInfo.devices_bound || 0, color: '#E6A23C', sub: t('sandbox_page.stats.devices_sub') },
    { key: 'remaining', label: t('sandbox_page.stats.remaining'), value: sandboxInfo.remaining_licenses || 0, color: '#F56C6C', sub: t('sandbox_page.stats.remaining_sub') },
]);

const firstLicenseKey = computed(() => sandboxLicenses.value[0]?.license_key || 'SANDBOX-1-XXXXXXXX');

function statusLabel(status) { return status === 'active' ? t('sandbox_page.status_active') : status; }
function formatDate(dateStr) {
    if (!dateStr) return null;
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(dateStr).toLocaleString(loc, { year: 'numeric', month: '2-digit', day: '2-digit' });
}

async function loadStatus() {
    loadingStatus.value = true;
    try {
        const { data: res } = await sandboxApi.status();
        if (res.success) {
            Object.assign(sandboxInfo, res.data);
            sandboxActive.value = res.data.is_sandbox;
            if (res.data.is_sandbox) await loadLicenses();
        } else { sandboxActive.value = false; }
    } catch { sandboxActive.value = false; }
    finally { loadingStatus.value = false; }
}
async function loadLicenses() {
    loadingLicenses.value = true;
    try { const { data: res } = await sandboxApi.licenses(); if (res.success) sandboxLicenses.value = res.data || []; }
    catch { sandboxLicenses.value = []; }
    finally { loadingLicenses.value = false; }
}
async function handleCreate() {
    creating.value = true;
    try {
        const { data: res } = await sandboxApi.create();
        if (res.success) {
            ElMessage.success(res.data?.message || t('sandbox_page.messages.create_success'));
            Object.assign(sandboxInfo, res.data?.sandbox_info || {});
            sandboxActive.value = true; await loadLicenses();
        }
    } catch { ElMessage.error(t('sandbox_page.messages.create_failed')); }
    finally { creating.value = false; }
}
async function handleReset() {
    try {
        await ElMessageBox.confirm(t('sandbox_page.confirm_reset'), t('sandbox_page.confirm_reset_title'), { confirmButtonText: t('sandbox_page.confirm_reset_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' });
        resetting.value = true;
        const { data: res } = await sandboxApi.reset();
        if (res.success) { ElMessage.success(res.message || t('sandbox_page.messages.reset_success')); await loadLicenses(); }
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('sandbox_page.messages.reset_failed')); }
    finally { resetting.value = false; }
}
function copyKey(key) { copyText(key); }
function copyText(text) {
    navigator.clipboard.writeText(text).then(() => { ElMessage.success(t('portal.copied_clipboard')); });
}

// ================================================================
// Tab 2: 代码沙箱
// ================================================================
const code = ref('');
const language = ref('php');
const running = ref(false);
const lastResult = ref(null);
const loadingLangs = ref(false);
const langVersions = ref({});
const maxLength = 5000;
const languageKeys = ['php', 'python', 'node', 'sql', 'bash'];

const languageOptions = computed(() =>
    languageKeys.map((value) => ({ value, label: t(`code_sandbox_page.languages.${value}`), disabled: value === 'bash' }))
);
const languageLabel = computed(() => t(`code_sandbox_page.languages.${language.value}`, language.value));
const codePlaceholder = computed(() => t('code_sandbox_page.code_placeholder', { lang: languageLabel.value }));
const executionStatusLabel = computed(() => {
    if (!lastResult.value) return '';
    return lastResult.value.success ? t('code_sandbox_page.execution_success') : t('code_sandbox_page.execution_failed');
});

function handleKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); runCode(); }
    if (e.key === 'Tab') { e.preventDefault(); const start = e.target.selectionStart; const end = e.target.selectionEnd; code.value = code.value.substring(0, start) + '    ' + code.value.substring(end); e.target.selectionStart = e.target.selectionEnd = start + 4; }
}
async function loadLanguages() {
    loadingLangs.value = true;
    try {
        const res = await apiClient.get('/code-sandbox/languages');
        const langs = res.data?.data?.languages || {};
        langVersions.value = {};
        Object.entries(langs).forEach(([k, v]) => { if (v.version) { const m = v.version.match(/(\d+\.\d+[\.\d]*)/); langVersions.value[k] = m ? m[1] : v.version; } });
        ElMessage.success(t('code_sandbox_page.messages.env_check_done'));
    } catch { ElMessage.error(t('code_sandbox_page.messages.env_check_failed')); }
    finally { loadingLangs.value = false; }
}
async function loadTemplate() {
    try { const res = await apiClient.get('/code-sandbox/templates'); const templates = res.data?.data?.templates || {}; const tmpl = templates[language.value]; if (tmpl) code.value = tmpl.code; }
    catch { ElMessage.error(t('messages.load_failed')); }
}
async function runCode() {
    if (!code.value.trim()) { ElMessage.warning(t('code_sandbox_page.messages.code_required')); return; }
    if (code.value.length > maxLength) { ElMessage.warning(t('code_sandbox_page.messages.code_too_long')); return; }
    running.value = true; lastResult.value = null;
    try { const res = await apiClient.post('/code-sandbox/execute', { code: code.value, language: language.value }); lastResult.value = res.data?.data; }
    catch (e) { ElMessage.error(e.response?.data?.message || t('code_sandbox_page.messages.execute_failed')); }
    finally { running.value = false; }
}
function clearOutput() { lastResult.value = null; }
function onLanguageChange() { lastResult.value = null; }

function onMainTabChange(tab) {
    if (tab === 'code' && !langVersions.value[language.value]) { loadLanguages(); loadTemplate(); }
}

// ================================================================
// Tab: Staging 环境 (sg_ prefix)
// ================================================================
const sg_creating = ref(false);
const sg_resetting = ref(false);
const sg_loadingStatus = ref(true);
const sg_loadingLicenses = ref(false);
const sg_stagingActive = ref(false);
const sg_envInfo = reactive({});
const sg_stagingLicenses = ref([]);
const sg_editForm = reactive({ name: '', rate_limit: 120 });

const sg_featureDefs = [
    { key: 'subdomain', icon: Monitor, color: '#0f172a' },
    { key: 'test_licenses', icon: Key, color: '#67C23A' },
    { key: 'api_rate_limit', icon: Connection, color: '#E6A23C' },
    { key: 'one_click_reset', icon: Refresh, color: '#F56C6C' },
    { key: 'data_isolation', icon: Switch, color: '#909399' },
    { key: 'validity', icon: Key, color: '#0f172a' },
];

const sg_features = computed(() => sg_featureDefs.map((f) => ({
    icon: f.icon,
    color: f.color,
    title: t(`staging_page.features.${f.key}`),
    desc: t(`staging_page.features.${f.key}_desc`),
})));

const sg_statsCards = computed(() => [
    {
        label: t('staging_page.stats.licenses_total'),
        value: sg_envInfo.licenses_total || 0,
        color: '#0f172a',
        sub: t('staging_page.stats.limit_n', { n: sg_envInfo.license_limit }),
    },
    {
        label: t('staging_page.stats.active_licenses'),
        value: sg_envInfo.licenses_active || 0,
        color: '#67C23A',
        sub: t('staging_page.stats.usable'),
    },
    {
        label: t('staging_page.stats.devices_bound'),
        value: sg_envInfo.devices_bound || 0,
        color: '#E6A23C',
        sub: t('staging_page.stats.devices_per_license'),
    },
    {
        label: t('staging_page.stats.status'),
        value: sg_envInfo.status === 'active' ? t('staging_page.stats.running') : t('staging_page.stats.paused'),
        color: sg_envInfo.status === 'active' ? '#67C23A' : '#F56C6C',
        sub: sg_envInfo.api_base_url || '',
    },
]);

const sg_firstKey = computed(() => sg_stagingLicenses.value[0]?.license_key || 'STAGING-1-XXXXXXXX');

async function sg_loadStatus() {
    sg_loadingStatus.value = true;
    try {
        const { data: res } = await stagingApi.index();
        if (res.success && res.data) {
            Object.assign(sg_envInfo, res.data);
            sg_editForm.name = res.data.name || '';
            sg_editForm.rate_limit = res.data.rate_limit ? parseInt(res.data.rate_limit) : 120;
            sg_stagingActive.value = true;
            await sg_loadLicenses();
        } else {
            sg_stagingActive.value = false;
        }
    } catch {
        sg_stagingActive.value = false;
    } finally {
        sg_loadingStatus.value = false;
    }
}

async function sg_loadLicenses() {
    if (!sg_envInfo.id) return;
    sg_loadingLicenses.value = true;
    try {
        const { data: res } = await stagingApi.licenses(sg_envInfo.id);
        if (res.success) sg_stagingLicenses.value = res.data || [];
    } catch {
        sg_stagingLicenses.value = [];
    } finally {
        sg_loadingLicenses.value = false;
    }
}

async function sg_handleCreate() {
    sg_creating.value = true;
    try {
        const { data: res } = await stagingApi.create();
        if (res.success) {
            ElMessage.success(res.message || t('staging_page.messages.create_ok'));
            Object.assign(sg_envInfo, res.data);
            sg_editForm.name = res.data.name || '';
            sg_editForm.rate_limit = res.data.rate_limit ? parseInt(res.data.rate_limit) : 120;
            sg_stagingActive.value = true;
            await sg_loadLicenses();
        }
    } catch {
        ElMessage.error(t('staging_page.messages.create_failed'));
    } finally {
        sg_creating.value = false;
    }
}

async function sg_handleReset() {
    if (!sg_envInfo.id) return;
    try {
        await ElMessageBox.confirm(
            t('staging_page.prompts.reset_confirm'),
            t('staging_page.prompts.reset_title'),
            { confirmButtonText: t('staging_page.prompts.confirm_reset'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        sg_resetting.value = true;
        const { data: res } = await stagingApi.reset(sg_envInfo.id);
        if (res.success) {
            ElMessage.success(res.message || t('staging_page.messages.reset_ok'));
            Object.assign(sg_envInfo, res.data);
            await sg_loadLicenses();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('staging_page.messages.reset_failed'));
    } finally {
        sg_resetting.value = false;
    }
}

async function sg_handleUpdate() {
    if (!sg_envInfo.id) return;
    try {
        const { data: res } = await stagingApi.update(sg_envInfo.id, {
            name: sg_editForm.name,
            rate_limit: sg_editForm.rate_limit,
        });
        if (res.success) {
            Object.assign(sg_envInfo, res.data);
        }
    } catch {
        ElMessage.error(t('licenses_page.update_fail'));
    }
}

// ===== Lifecycle =====
onMounted(() => { loadStatus(); });

// When switching to staging tab, trigger lazy load
watch(testMainTab, (val) => {
    if (val === 'staging') {
        sg_tabVisited.value = true;
        sg_loadStatus();
    }
});
</script>

<style scoped>
.test-env-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.tab-header { display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 12px; }

/* --- 开发者沙箱 --- */
.welcome-section { margin-bottom: 32px; }
.features-row { margin-top: 32px; }
.feature-card { text-align: center; padding: 24px 16px; margin-bottom: 16px; }
.feature-card h4 { margin: 12px 0 6px; font-size: 15px; }
.feature-card p { margin: 0; font-size: 13px; color: var(--el-text-color-secondary); }
.info-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 26px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.stat-sub { font-size: 11px; color: var(--el-text-color-secondary); margin-top: 2px; }
.table-card { margin-bottom: 16px; }
.card-header { display: flex; align-items: center; justify-content: space-between; }
.license-key { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 13px; font-weight: 600; letter-spacing: 0.5px; }
.quickstart-card { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; }

/* --- 代码沙箱 --- */
.flex { display: flex; } .gap-2 { gap: 8px; } .items-center { align-items: center; } .text-danger { color: #f56c6c; }
.sandbox-header { display: flex; justify-content: space-between; align-items: center; }
.lang-version { font-size: 12px; color: #909399; font-family: monospace; }
.editor-wrap { position: relative; }
.code-editor {
    width: 100%; height: 480px;
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 14px; line-height: 1.6; padding: 16px;
    background: #1e1e1e; color: #d4d4d4;
    border: 1px solid #333; border-radius: 6px;
    resize: vertical; tab-size: 4; outline: none; white-space: pre; overflow: auto;
}
.code-editor::placeholder { color: #666; }
.code-editor:focus { border-color: #0f172a; }
.editor-info { display: flex; gap: 12px; font-size: 12px; color: #909399; margin-top: 6px; justify-content: flex-end; }
.output-wrap { min-height: 480px; max-height: 520px; overflow-y: auto; }
.output-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 400px; color: #909399; gap: 12px; }
.output-placeholder p { font-size: 14px; margin: 0; }
.output-content { padding: 0; }
.output-section { margin-bottom: 12px; }
.output-label { font-size: 12px; font-weight: 600; color: #67c23a; margin-bottom: 4px; }
.output-error-label { color: #f56c6c; }
.output-text { background: #f5f7fa; border-radius: 4px; padding: 12px; font-family: 'Consolas', 'Courier New', monospace; font-size: 13px; line-height: 1.5; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow: auto; margin: 0; }
.output-error { background: #fef0f0; color: #f56c6c; }
.output-meta { display: flex; gap: 16px; font-size: 11px; color: #909399; padding-top: 8px; border-top: 1px solid #eee; flex-wrap: wrap; }

/* --- Staging 环境 --- */
.detail-card { margin-bottom: 16px; }
.inline-input { width: 200px; }

:deep(.el-card__body) { padding: 16px; }
</style>
