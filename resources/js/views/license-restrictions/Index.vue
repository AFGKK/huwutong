<template>
    <div class="license-restrictions-page">
        <div class="page-header">
            <h2>{{ t('license_restrictions_page.title') }}</h2>
            <p class="text-muted">{{ t('license_restrictions_page.subtitle') }}</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <el-tag :type="ipSettings.enabled ? 'success' : 'info'" size="large" effect="dark">
                            {{ ipSettings.enabled ? t('license_restrictions_page.stats.enabled') : t('license_restrictions_page.stats.not_enabled') }}
                        </el-tag>
                        <div class="card-title">{{ t('license_restrictions_page.stats.ip_title') }}</div>
                        <div class="card-info">{{ t('license_restrictions_page.stats.ip_info') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <el-tag :type="geoSettings.enabled ? 'success' : 'info'" size="large" effect="dark">
                            {{ geoSettings.enabled ? t('license_restrictions_page.stats.enabled') : t('license_restrictions_page.stats.not_enabled') }}
                        </el-tag>
                        <div class="card-title">{{ t('license_restrictions_page.stats.geo_title') }}</div>
                        <div class="card-info">{{ t('license_restrictions_page.stats.geo_info') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="status-number">{{ countryCount }}</div>
                        <div class="card-title">{{ t('license_restrictions_page.stats.countries_title') }}</div>
                        <div class="card-info">{{ t('license_restrictions_page.stats.countries_info') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('license_restrictions_page.tabs.ip')" name="ip">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('license_restrictions_page.ip.header') }}</span>
                    </template>
                    <el-form :model="ipForm" label-width="120px">
                        <el-form-item :label="t('time_restriction_page.cols.license_id')">
                            <el-input-number v-model="ipForm.licenseId" :min="1" style="width:200px" />
                            <el-button class="ml-2" @click="loadIpConfig" :disabled="!ipForm.licenseId">{{ t('license_restrictions_page.buttons.load') }}</el-button>
                        </el-form-item>
                        <el-form-item :label="t('actions.enable')">
                            <el-switch v-model="ipConfig.is_active" />
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.form.action')">
                            <el-radio-group v-model="ipConfig.action">
                                <el-radio v-for="opt in actionOptions" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.ip.cidr_ranges')">
                            <el-input v-model="ipRangeInput" :placeholder="t('license_restrictions_page.ip.cidr_ph')" class="mb-2" />
                            <el-button size="small" @click="addIpRange">{{ t('license_restrictions_page.buttons.add') }}</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(r, i) in ipConfig.ip_ranges" :key="i" closable @close="removeIpRange(i)" class="mr-1 mb-1">{{ r }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.ip.whitelist')">
                            <el-input v-model="ipWhitelistInput" :placeholder="t('license_restrictions_page.ip.whitelist_ph')" class="mb-2" />
                            <el-button size="small" @click="addIpWhitelist">{{ t('license_restrictions_page.buttons.add') }}</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(w, i) in ipConfig.ip_whitelist" :key="i" closable @close="removeIpWhitelist(i)" type="success" class="mr-1 mb-1">{{ w }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.ip.blacklist')">
                            <el-input v-model="ipBlacklistInput" :placeholder="t('license_restrictions_page.ip.blacklist_ph')" class="mb-2" />
                            <el-button size="small" @click="addIpBlacklist">{{ t('license_restrictions_page.buttons.add') }}</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(b, i) in ipConfig.ip_blacklist" :key="i" closable @close="removeIpBlacklist(i)" type="danger" class="mr-1 mb-1">{{ b }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item :label="t('time_restriction_page.cols.description')">
                            <el-input v-model="ipConfig.description" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleSaveIp" :loading="ipSaving">{{ t('license_restrictions_page.buttons.save_config') }}</el-button>
                            <el-button @click="handleDeleteIp" type="danger" plain>{{ t('license_restrictions_page.buttons.delete_config') }}</el-button>
                            <el-button @click="handleTestIp" :loading="ipTesting">{{ t('license_restrictions_page.buttons.test_ip') }}</el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="ipTestResult" :title="testResultTitle(ipTestResult.allowed)" :description="ipTestResult.reason || '-'" :type="ipTestResult.allowed ? 'success' : 'warning'" show-icon :closable="true" class="mt-2" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('license_restrictions_page.tabs.geo')" name="geo">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('license_restrictions_page.geo.header') }}</span>
                    </template>
                    <el-form :model="geoForm" label-width="140px">
                        <el-form-item :label="t('time_restriction_page.cols.license_id')">
                            <el-input-number v-model="geoForm.licenseId" :min="1" style="width:200px" />
                            <el-button class="ml-2" @click="loadGeoConfig" :disabled="!geoForm.licenseId">{{ t('license_restrictions_page.buttons.load') }}</el-button>
                        </el-form-item>
                        <el-form-item :label="t('actions.enable')">
                            <el-switch v-model="geoConfig.is_active" />
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.form.action')">
                            <el-radio-group v-model="geoConfig.action">
                                <el-radio v-for="opt in actionOptions" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.geo.unknown_location')">
                            <el-radio-group v-model="geoConfig.unknown_location_action">
                                <el-radio v-for="opt in actionOptions" :key="opt.value" :label="opt.value">{{ opt.label }}</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.geo.allowed_countries')">
                            <el-transfer
                                v-model="geoConfig.allowed_countries"
                                :data="countryOptions"
                                :titles="[t('license_restrictions_page.transfer.all_countries'), t('license_restrictions_page.transfer.allowed_countries')]"
                                filterable
                                :filter-placeholder="t('license_restrictions_page.transfer.search_country')"
                            />
                        </el-form-item>
                        <el-form-item :label="t('license_restrictions_page.geo.blocked_countries')">
                            <el-transfer
                                v-model="geoConfig.blocked_countries"
                                :data="countryOptions"
                                :titles="[t('license_restrictions_page.transfer.all_countries'), t('license_restrictions_page.transfer.blocked_countries')]"
                                filterable
                                :filter-placeholder="t('license_restrictions_page.transfer.search_country')"
                            />
                        </el-form-item>
                        <el-form-item :label="t('time_restriction_page.cols.description')">
                            <el-input v-model="geoConfig.description" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleSaveGeo" :loading="geoSaving">{{ t('license_restrictions_page.buttons.save_config') }}</el-button>
                            <el-button @click="handleDeleteGeo" type="danger" plain>{{ t('license_restrictions_page.buttons.delete_config') }}</el-button>
                            <el-button @click="handleTestGeo" :loading="geoTesting">{{ t('license_restrictions_page.buttons.test_ip') }}</el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="geoTestResult" :title="testResultTitle(geoTestResult.allowed)" :description="geoTestDescription" :type="geoTestResult.allowed ? 'success' : 'warning'" show-icon :closable="true" class="mt-2" />
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('license_restrictions_page.tabs.logs')" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('license_restrictions_page.logs.header') }}</span>
                            <div class="card-actions">
                                <el-select v-model="logFilter.type" size="small" style="width:140px" @change="loadLogs">
                                    <el-option v-for="opt in logTypeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                                <el-select v-model="logFilter.result" size="small" style="width:140px" @change="loadLogs">
                                    <el-option v-for="opt in logResultFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                                <el-button size="small" @click="loadLogs">{{ t('time_restriction_page.refresh') }}</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="logs" v-loading="logLoading" stripe>
                        <el-table-column prop="type" :label="t('license_restrictions_page.cols.type')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.type === 'ip_range' ? '' : 'warning'" size="small">{{ row.type === 'ip_range' ? t('license_restrictions_page.type_tag.ip') : t('license_restrictions_page.type_tag.geo') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="restrictable_id" :label="t('time_restriction_page.cols.license_id')" width="100" />
                        <el-table-column :label="t('time_restriction_page.cols.result')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'blocked' ? 'danger' : (row.result === 'allowed' ? 'success' : 'info')" size="small">
                                    {{ resultLabel(row.result) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip_address" :label="t('license_restrictions_page.cols.ip')" width="140" />
                        <el-table-column prop="country" :label="t('license_restrictions_page.cols.country')" width="80">
                            <template #default="{ row }">{{ countries[row.country] || row.country || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="reason" :label="t('time_restriction_page.cols.reason')" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="created_at" :label="t('time_restriction_page.cols.time')" width="160" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import {
    getIpRestriction, saveIpRestriction, deleteIpRestriction,
    getGeoFence, saveGeoFence, deleteGeoFence,
    testIpCheck, testGeoCheck,
    getCountries, getRestrictionLogs,
} from '@/api/licenseRestrictions';

const { t } = useI18n();

const activeTab = ref('ip');
const ipSettings = ref({ enabled: true });
const geoSettings = ref({ enabled: true });
const countries = ref({});
const countryOptions = ref([]);
const countryCount = computed(() => Object.keys(countries.value).length);

const actionOptions = computed(() => [
    { value: 'block', label: t('license_restrictions_page.action.block') },
    { value: 'allow', label: t('license_restrictions_page.action.allow') },
    { value: 'audit', label: t('license_restrictions_page.action.audit') },
]);

const logTypeFilterOptions = computed(() => [
    { label: t('license_restrictions_page.filter.all_types'), value: '' },
    { label: t('license_restrictions_page.filter.ip_range'), value: 'ip_range' },
    { label: t('license_restrictions_page.filter.geo_fence'), value: 'geo_fence' },
]);

const logResultFilterOptions = computed(() => [
    { label: t('license_restrictions_page.filter.all_results'), value: '' },
    { label: t('license_restrictions_page.filter.blocked'), value: 'blocked' },
    { label: t('license_restrictions_page.filter.allowed'), value: 'allowed' },
    { label: t('license_restrictions_page.filter.audited'), value: 'audited' },
]);

function testResultTitle(allowed) {
    return allowed ? t('license_restrictions_page.test.allowed') : t('license_restrictions_page.test.blocked');
}

function resultLabel(result) {
    const map = {
        blocked: t('license_restrictions_page.result.blocked'),
        allowed: t('license_restrictions_page.result.allowed'),
        audited: t('license_restrictions_page.result.audited'),
    };
    return map[result] || result;
}

const geoTestDescription = computed(() => {
    if (!geoTestResult.value) return '';
    let desc = geoTestResult.value.reason || '';
    if (geoTestResult.value.country) {
        const name = countries.value[geoTestResult.value.country] || geoTestResult.value.country;
        const suffix = t('license_restrictions_page.test.country_prefix', { name });
        desc = desc ? `${desc} (${suffix})` : suffix;
    }
    return desc;
});

const ipForm = ref({ licenseId: 1 });
const ipConfig = ref({ is_active: true, action: 'block', ip_ranges: [], ip_whitelist: [], ip_blacklist: [], description: '' });
const ipRangeInput = ref('');
const ipWhitelistInput = ref('');
const ipBlacklistInput = ref('');
const ipSaving = ref(false);
const ipTesting = ref(false);
const ipTestResult = ref(null);

const geoForm = ref({ licenseId: 1 });
const geoConfig = ref({ is_active: true, action: 'block', allowed_countries: [], blocked_countries: [], unknown_location_action: 'allow', description: '' });
const geoSaving = ref(false);
const geoTesting = ref(false);
const geoTestResult = ref(null);

const logs = ref([]);
const logLoading = ref(false);
const logFilter = ref({ type: '', result: '' });

const loadIpConfig = async () => {
    if (!ipForm.value.licenseId) return;
    try {
        const res = await getIpRestriction(ipForm.value.licenseId);
        if (res.data.success) {
            ipConfig.value = res.data.data.config || { is_active: true, action: 'block', ip_ranges: [], ip_whitelist: [], ip_blacklist: [], description: '' };
            ipSettings.value = res.data.data.settings || {};
        }
    } catch { /* ignore */ }
};

const addIpRange = () => {
    if (!ipRangeInput.value) return;
    const ranges = ipRangeInput.value.split(/[,，\s]+/).filter(Boolean);
    ranges.forEach(r => { if (!ipConfig.value.ip_ranges.includes(r)) ipConfig.value.ip_ranges.push(r); });
    ipRangeInput.value = '';
};

const removeIpRange = (i) => ipConfig.value.ip_ranges.splice(i, 1);
const addIpWhitelist = () => {
    if (!ipWhitelistInput.value) return;
    const items = ipWhitelistInput.value.split(/[,，\s]+/).filter(Boolean);
    items.forEach(w => { if (!ipConfig.value.ip_whitelist.includes(w)) ipConfig.value.ip_whitelist.push(w); });
    ipWhitelistInput.value = '';
};
const removeIpWhitelist = (i) => ipConfig.value.ip_whitelist.splice(i, 1);
const addIpBlacklist = () => {
    if (!ipBlacklistInput.value) return;
    const items = ipBlacklistInput.value.split(/[,，\s]+/).filter(Boolean);
    items.forEach(b => { if (!ipConfig.value.ip_blacklist.includes(b)) ipConfig.value.ip_blacklist.push(b); });
    ipBlacklistInput.value = '';
};
const removeIpBlacklist = (i) => ipConfig.value.ip_blacklist.splice(i, 1);

const handleSaveIp = async () => {
    ipSaving.value = true;
    try {
        const res = await saveIpRestriction(ipForm.value.licenseId, ipConfig.value);
        if (res.data.success) ElMessage.success(t('license_restrictions_page.messages.ip_saved'));
    } catch { ElMessage.error(t('license_restrictions_page.messages.save_failed')); }
    finally { ipSaving.value = false; }
};

const handleDeleteIp = async () => {
    try {
        const res = await deleteIpRestriction(ipForm.value.licenseId);
        if (res.data.success) {
            ElMessage.success(t('license_restrictions_page.messages.deleted'));
            ipConfig.value = { is_active: true, action: 'block', ip_ranges: [], ip_whitelist: [], ip_blacklist: [], description: '' };
        }
    } catch { ElMessage.error(t('license_restrictions_page.messages.delete_failed')); }
};

const handleTestIp = async () => {
    ipTesting.value = true;
    ipTestResult.value = null;
    try {
        const res = await testIpCheck(ipForm.value.licenseId, '8.8.8.8');
        ipTestResult.value = res.data.data;
    } catch { ElMessage.error(t('license_restrictions_page.messages.test_failed')); }
    finally { ipTesting.value = false; }
};

const loadGeoConfig = async () => {
    if (!geoForm.value.licenseId) return;
    try {
        const res = await getGeoFence(geoForm.value.licenseId);
        if (res.data.success) {
            geoConfig.value = res.data.data.config || { is_active: true, action: 'block', allowed_countries: [], blocked_countries: [], unknown_location_action: 'allow', description: '' };
            geoSettings.value = res.data.data.settings || {};
            countries.value = res.data.data.countries || {};
            countryOptions.value = Object.entries(countries.value).map(([k, v]) => ({ key: k, label: `${k} - ${v}` }));
        }
    } catch { /* ignore */ }
};

const handleSaveGeo = async () => {
    geoSaving.value = true;
    try {
        const res = await saveGeoFence(geoForm.value.licenseId, geoConfig.value);
        if (res.data.success) ElMessage.success(t('license_restrictions_page.messages.geo_saved'));
    } catch { ElMessage.error(t('license_restrictions_page.messages.save_failed')); }
    finally { geoSaving.value = false; }
};

const handleDeleteGeo = async () => {
    try {
        const res = await deleteGeoFence(geoForm.value.licenseId);
        if (res.data.success) {
            ElMessage.success(t('license_restrictions_page.messages.deleted'));
            geoConfig.value = { is_active: true, action: 'block', allowed_countries: [], blocked_countries: [], unknown_location_action: 'allow', description: '' };
        }
    } catch { ElMessage.error(t('license_restrictions_page.messages.delete_failed')); }
};

const handleTestGeo = async () => {
    geoTesting.value = true;
    geoTestResult.value = null;
    try {
        const res = await testGeoCheck(geoForm.value.licenseId, '8.8.8.8');
        geoTestResult.value = res.data.data;
    } catch { ElMessage.error(t('license_restrictions_page.messages.test_failed')); }
    finally { geoTesting.value = false; }
};

const loadLogs = async () => {
    logLoading.value = true;
    try {
        const res = await getRestrictionLogs({ type: logFilter.value.type || undefined, result: logFilter.value.result || undefined });
        if (res.data.success) logs.value = res.data.data.data || [];
    } catch { logs.value = []; }
    finally { logLoading.value = false; }
};

onMounted(async () => {
    try {
        const res = await getCountries();
        if (res.data.success) {
            countries.value = res.data.data.countries || {};
            countryOptions.value = Object.entries(countries.value).map(([k, v]) => ({ key: k, label: `${k} - ${v}` }));
        }
    } catch { /* ignore */ }
    loadLogs();
});
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.text-muted { color: #909399; font-size: 13px; margin: 4px 0 0 0; }
.mb-4 { margin-bottom: 16px; }
.mt-2 { margin-top: 8px; }
.mt-1 { margin-top: 4px; }
.mb-1 { margin-bottom: 4px; }
.mb-2 { margin-bottom: 8px; }
.ml-2 { margin-left: 8px; }
.mr-1 { margin-right: 4px; }
.stat-card { text-align: center; cursor: default; }
.card-title { font-weight: 600; font-size: 15px; margin-top: 8px; }
.card-info { font-size: 12px; color: #909399; margin-top: 4px; }
.status-number { font-size: 32px; font-weight: 700; color: #0f172a; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.card-actions { display: flex; gap: 8px; }
.tag-list { display: flex; flex-wrap: wrap; }
</style>
