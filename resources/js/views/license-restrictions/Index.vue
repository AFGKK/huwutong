<template>
    <div class="license-restrictions-page">
        <div class="page-header">
            <h2>License 访问限制</h2>
            <p class="text-muted">IP 范围限制 (CIDR) + 地理围栏 (Geofencing) — 按 License 级别控制激活来源</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <el-tag :type="ipSettings.enabled ? 'success' : 'info'" size="large" effect="dark">
                            {{ ipSettings.enabled ? '已启用' : '未启用' }}
                        </el-tag>
                        <div class="card-title">IP 范围限制</div>
                        <div class="card-info">CIDR 白名单 / 黑名单控制</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <el-tag :type="geoSettings.enabled ? 'success' : 'info'" size="large" effect="dark">
                            {{ geoSettings.enabled ? '已启用' : '未启用' }}
                        </el-tag>
                        <div class="card-title">地理围栏</div>
                        <div class="card-info">按国家/地区限制激活</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" :body-style="{ padding: '16px' }">
                    <div class="stat-card">
                        <div class="status-number">{{ countryCount }}</div>
                        <div class="card-title">支持的国家</div>
                        <div class="card-info">ISO 3166-1 alpha-2</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-tabs v-model="activeTab">
            <!-- IP 范围限制 -->
            <el-tab-pane label="IP 范围限制" name="ip">
                <el-card shadow="never">
                    <template #header>
                        <span>按 License 配置 IP 白名单 / 黑名单</span>
                    </template>
                    <el-form :model="ipForm" label-width="120px">
                        <el-form-item label="License ID">
                            <el-input-number v-model="ipForm.licenseId" :min="1" style="width:200px" />
                            <el-button class="ml-2" @click="loadIpConfig" :disabled="!ipForm.licenseId">加载</el-button>
                        </el-form-item>
                        <el-form-item label="启用">
                            <el-switch v-model="ipConfig.is_active" />
                        </el-form-item>
                        <el-form-item label="拦截动作">
                            <el-radio-group v-model="ipConfig.action">
                                <el-radio label="block">拦截</el-radio>
                                <el-radio label="allow">允许</el-radio>
                                <el-radio label="audit">仅审计</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="CIDR 范围">
                            <el-input v-model="ipRangeInput" placeholder="如: 10.0.0.0/8, 192.168.0.0/16" class="mb-2" />
                            <el-button size="small" @click="addIpRange">添加</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(r, i) in ipConfig.ip_ranges" :key="i" closable @close="removeIpRange(i)" class="mr-1 mb-1">{{ r }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item label="IP 白名单">
                            <el-input v-model="ipWhitelistInput" placeholder="如: 1.2.3.4, 5.6.7.8" class="mb-2" />
                            <el-button size="small" @click="addIpWhitelist">添加</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(w, i) in ipConfig.ip_whitelist" :key="i" closable @close="removeIpWhitelist(i)" type="success" class="mr-1 mb-1">{{ w }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item label="IP 黑名单">
                            <el-input v-model="ipBlacklistInput" placeholder="如: 10.0.0.99" class="mb-2" />
                            <el-button size="small" @click="addIpBlacklist">添加</el-button>
                            <div class="tag-list mt-1">
                                <el-tag v-for="(b, i) in ipConfig.ip_blacklist" :key="i" closable @close="removeIpBlacklist(i)" type="danger" class="mr-1 mb-1">{{ b }}</el-tag>
                            </div>
                        </el-form-item>
                        <el-form-item label="备注">
                            <el-input v-model="ipConfig.description" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleSaveIp" :loading="ipSaving">保存配置</el-button>
                            <el-button @click="handleDeleteIp" type="danger" plain>删除配置</el-button>
                            <el-button @click="handleTestIp" :loading="ipTesting">测试当前 IP</el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="ipTestResult" :title="ipTestResult.allowed ? '✅ 允许' : '❌ 拦截'" :description="ipTestResult.reason || '-'" :type="ipTestResult.allowed ? 'success' : 'warning'" show-icon :closable="true" class="mt-2" />
                </el-card>
            </el-tab-pane>

            <!-- 地理围栏 -->
            <el-tab-pane label="地理围栏" name="geo">
                <el-card shadow="never">
                    <template #header>
                        <span>按国家/地区限制 License 激活</span>
                    </template>
                    <el-form :model="geoForm" label-width="140px">
                        <el-form-item label="License ID">
                            <el-input-number v-model="geoForm.licenseId" :min="1" style="width:200px" />
                            <el-button class="ml-2" @click="loadGeoConfig" :disabled="!geoForm.licenseId">加载</el-button>
                        </el-form-item>
                        <el-form-item label="启用">
                            <el-switch v-model="geoConfig.is_active" />
                        </el-form-item>
                        <el-form-item label="拦截动作">
                            <el-radio-group v-model="geoConfig.action">
                                <el-radio label="block">拦截</el-radio>
                                <el-radio label="allow">允许</el-radio>
                                <el-radio label="audit">仅审计</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="未知位置处理">
                            <el-radio-group v-model="geoConfig.unknown_location_action">
                                <el-radio label="allow">允许</el-radio>
                                <el-radio label="block">拦截</el-radio>
                                <el-radio label="audit">仅审计</el-radio>
                            </el-radio-group>
                        </el-form-item>
                        <el-form-item label="允许的国家">
                            <el-transfer
                                v-model="geoConfig.allowed_countries"
                                :data="countryOptions"
                                :titles="['全部国家', '允许的国家']"
                                filterable
                                filter-placeholder="搜索国家"
                            />
                        </el-form-item>
                        <el-form-item label="禁止的国家">
                            <el-transfer
                                v-model="geoConfig.blocked_countries"
                                :data="countryOptions"
                                :titles="['全部国家', '禁止的国家']"
                                filterable
                                filter-placeholder="搜索国家"
                            />
                        </el-form-item>
                        <el-form-item label="备注">
                            <el-input v-model="geoConfig.description" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleSaveGeo" :loading="geoSaving">保存配置</el-button>
                            <el-button @click="handleDeleteGeo" type="danger" plain>删除配置</el-button>
                            <el-button @click="handleTestGeo" :loading="geoTesting">测试当前 IP</el-button>
                        </el-form-item>
                    </el-form>
                    <el-alert v-if="geoTestResult" :title="geoTestResult.allowed ? '✅ 允许' : '❌ 拦截'" :description="(geoTestResult.reason || '') + (geoTestResult.country ? ' (国家: ' + (countries[geoTestResult.country] || geoTestResult.country) + ')' : '')" :type="geoTestResult.allowed ? 'success' : 'warning'" show-icon :closable="true" class="mt-2" />
                </el-card>
            </el-tab-pane>

            <!-- 限制日志 -->
            <el-tab-pane label="拦截日志" name="logs">
                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>限制拦截日志</span>
                            <div class="card-actions">
                                <el-select v-model="logFilter.type" size="small" style="width:140px" @change="loadLogs">
                                    <el-option label="全部类型" value="" />
                                    <el-option label="IP 范围" value="ip_range" />
                                    <el-option label="地理围栏" value="geo_fence" />
                                </el-select>
                                <el-select v-model="logFilter.result" size="small" style="width:140px" @change="loadLogs">
                                    <el-option label="全部结果" value="" />
                                    <el-option label="已拦截" value="blocked" />
                                    <el-option label="已允许" value="allowed" />
                                    <el-option label="已审计" value="audited" />
                                </el-select>
                                <el-button size="small" @click="loadLogs">刷新</el-button>
                            </div>
                        </div>
                    </template>
                    <el-table :data="logs" v-loading="logLoading" stripe>
                        <el-table-column prop="type" label="类型" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.type === 'ip_range' ? '' : 'warning'" size="small">{{ row.type === 'ip_range' ? 'IP' : '地理' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="restrictable_id" label="License ID" width="100" />
                        <el-table-column label="结果" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.result === 'blocked' ? 'danger' : (row.result === 'allowed' ? 'success' : 'info')" size="small">
                                    {{ { blocked: '拦截', allowed: '允许', audited: '审计' }[row.result] || row.result }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="ip_address" label="IP" width="140" />
                        <el-table-column prop="country" label="国家" width="80">
                            <template #default="{ row }">{{ countries[row.country] || row.country || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="reason" label="原因" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="created_at" label="时间" width="160" />
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import {
    getIpRestriction, saveIpRestriction, deleteIpRestriction,
    getGeoFence, saveGeoFence, deleteGeoFence,
    testIpCheck, testGeoCheck,
    getCountries, getRestrictionLogs,
} from '@/api/licenseRestrictions';

const activeTab = ref('ip');
const ipSettings = ref({ enabled: true });
const geoSettings = ref({ enabled: true });
const countries = ref({});
const countryOptions = ref([]);
const countryCount = computed(() => Object.keys(countries.value).length);

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
        if (res.data.success) ElMessage.success('IP 限制已保存');
    } catch { ElMessage.error('保存失败'); }
    finally { ipSaving.value = false; }
};

const handleDeleteIp = async () => {
    try {
        const res = await deleteIpRestriction(ipForm.value.licenseId);
        if (res.data.success) { ElMessage.success('已删除'); ipConfig.value = { is_active: true, action: 'block', ip_ranges: [], ip_whitelist: [], ip_blacklist: [], description: '' }; }
    } catch { ElMessage.error('删除失败'); }
};

const handleTestIp = async () => {
    ipTesting.value = true;
    ipTestResult.value = null;
    try {
        const res = await testIpCheck(ipForm.value.licenseId, '8.8.8.8');
        ipTestResult.value = res.data.data;
    } catch { ElMessage.error('测试失败'); }
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
        if (res.data.success) ElMessage.success('地理围栏已保存');
    } catch { ElMessage.error('保存失败'); }
    finally { geoSaving.value = false; }
};

const handleDeleteGeo = async () => {
    try {
        const res = await deleteGeoFence(geoForm.value.licenseId);
        if (res.data.success) { ElMessage.success('已删除'); geoConfig.value = { is_active: true, action: 'block', allowed_countries: [], blocked_countries: [], unknown_location_action: 'allow', description: '' }; }
    } catch { ElMessage.error('删除失败'); }
};

const handleTestGeo = async () => {
    geoTesting.value = true;
    geoTestResult.value = null;
    try {
        const res = await testGeoCheck(geoForm.value.licenseId, '8.8.8.8');
        geoTestResult.value = res.data.data;
    } catch { ElMessage.error('测试失败'); }
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
.status-number { font-size: 32px; font-weight: 700; color: #409eff; }
.card-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; }
.card-actions { display: flex; gap: 8px; }
.tag-list { display: flex; flex-wrap: wrap; }
</style>
