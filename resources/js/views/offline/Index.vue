<template>
    <div class="offline-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('offline_center.title') }}</h2>
                <span class="header-subtitle">{{ t('offline_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
            <!-- ===== Tab 1: 离线 License ===== -->
            <el-tab-pane :label="t('offline_center.tab_license')" name="license">
                <el-row :gutter="16">
                    <el-col :span="8">
                        <!-- 签名密钥 -->
                        <el-card shadow="never" class="mb-4">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('offline_page.sections.signing_key') }}</span>
                                    <el-button size="small" type="warning" @click="handleInitKeys" :loading="initKeying">
                                        <el-icon><Refresh /></el-icon> {{ t('offline_page.btn_rotate_key') }}
                                    </el-button>
                                </div>
                            </template>
                            <div v-loading="loadingKey">
                                <div v-if="publicKeyInfo">
                                    <el-descriptions :column="1" border size="small">
                                        <el-descriptions-item :label="keyDescLabels.key_version">{{ publicKeyInfo.key_version }}</el-descriptions-item>
                                        <el-descriptions-item :label="keyDescLabels.algorithm">{{ publicKeyInfo.algorithm }}</el-descriptions-item>
                                        <el-descriptions-item :label="keyDescLabels.created_at">{{ formatTime(publicKeyInfo.created_at) }}</el-descriptions-item>
                                        <el-descriptions-item :label="keyDescLabels.expires_at">{{ formatTime(publicKeyInfo.expires_at) }}</el-descriptions-item>
                                        <el-descriptions-item :label="keyDescLabels.public_key">
                                            <code class="key-text">{{ publicKeyInfo.public_key?.substring(0, 48) }}...</code>
                                            <el-button text size="small" @click="copyOfflineText(publicKeyInfo.public_key)"><el-icon><CopyDocument /></el-icon></el-button>
                                        </el-descriptions-item>
                                    </el-descriptions>
                                </div>
                                <el-empty v-else-if="!loadingKey" :image-size="50" :description="t('offline_page.empty_signing_key')" />
                            </div>
                        </el-card>

                        <!-- 吊销/恢复 License -->
                        <el-card shadow="never">
                            <template #header><span>{{ t('offline_page.sections.revoke_restore') }}</span></template>
                            <el-form :model="revokeForm" label-width="80px" size="small">
                                <el-form-item :label="t('licenses_page.license_key')">
                                    <el-input v-model="revokeForm.license_key" :placeholder="t('offline_page.license_key_ph')" />
                                </el-form-item>
                                <el-form-item :label="t('offline_page.label_revoke_reason')" v-if="showRevokeInput">
                                    <el-input v-model="revokeForm.reason" :placeholder="t('offline_page.revoke_reason_ph')" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="danger" size="small" @click="handleRevoke" :loading="revoking" :disabled="!revokeForm.license_key">
                                        <el-icon><Close /></el-icon> {{ t('licenses_page.revoke') }}
                                    </el-button>
                                    <el-button type="success" size="small" @click="handleRestore" :loading="restoring" :disabled="!revokeForm.license_key" class="ml-2">
                                        <el-icon><CircleCheck /></el-icon> {{ t('licenses_page.restore') }}
                                    </el-button>
                                </el-form-item>
                            </el-form>
                        </el-card>
                    </el-col>

                    <el-col :span="16">
                        <!-- 生成离线 License -->
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>{{ t('offline_page.sections.generate') }}</span>
                                    <el-button size="small" type="primary" @click="showGenerateDialog = true">
                                        <el-icon><Plus /></el-icon> {{ t('offline_page.btn_batch_generate') }}
                                    </el-button>
                                </div>
                            </template>
                            <el-alert :title="t('offline_page.info_alert_title')" type="info" :closable="false" show-icon class="mb-4" :description="t('offline_page.info_alert_desc')" />
                            <el-form :model="generateForm" label-width="120px" :inline="true">
                                <el-form-item :label="t('offline_page.label_license_id')">
                                    <el-input-number v-model="generateForm.license_id" :min="1" style="width: 200px" :placeholder="t('offline_page.license_id_ph')" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="handleGenerate" :loading="generating">{{ t('offline_page.btn_generate_file') }}</el-button>
                                </el-form-item>
                            </el-form>
                            <div v-if="generatedFile" class="generated-result">
                                <el-alert type="success" :closable="true" show-icon @close="generatedFile = null">
                                    <template #title><span>{{ t('offline_page.generate_success_title') }}</span></template>
                                    <div class="file-detail">
                                        <div class="file-detail-row"><span class="file-label">{{ fileDetailLabels.license_key }}:</span><code>{{ generatedFile.license_key }}</code></div>
                                        <div class="file-detail-row"><span class="file-label">{{ fileDetailLabels.algorithm }}:</span><span>{{ generatedFile.algorithm }}</span></div>
                                        <div class="file-detail-row" v-if="generatedFile.expires_at"><span class="file-label">{{ fileDetailLabels.expires_at }}:</span><span>{{ formatTime(generatedFile.expires_at) }}</span></div>
                                        <div class="file-detail-row"><span class="file-label">{{ fileDetailLabels.file_content }}:</span>
                                            <el-button text size="small" type="primary" @click="copyOfflineText(generatedFile.license_file)"><el-icon><CopyDocument /></el-icon> {{ t('offline_page.btn_copy_file') }}</el-button>
                                        </div>
                                    </div>
                                    <pre class="file-preview">{{ generatedFile.license_file?.substring(0, 200) }}...</pre>
                                </el-alert>
                            </div>
                        </el-card>

                        <!-- 最近记录 -->
                        <el-card shadow="never" class="mt-4">
                            <template #header><span>{{ t('offline_page.sections.recent') }}</span></template>
                            <el-empty :image-size="50" :description="t('offline_page.empty_recent')" />
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 批量生成 Dialog -->
                <el-dialog v-model="showGenerateDialog" :title="t('offline_page.batch_dialog_title')" width="520px">
                    <el-form :model="batchForm" label-width="100px">
                        <el-form-item :label="t('offline_page.label_license_ids')">
                            <el-input v-model="batchForm.license_ids" type="textarea" :rows="4" :placeholder="t('offline_page.batch_license_ids_ph')" />
                        </el-form-item>
                        <el-form-item><el-button type="primary" @click="handleBatchGenerate" :loading="batchGenerating">{{ t('offline_page.btn_batch') }}</el-button></el-form-item>
                    </el-form>
                    <div v-if="batchResults.length" class="batch-results">
                        <el-divider /><h4>{{ t('offline_page.batch_results_title') }}</h4>
                        <div v-for="(r, i) in batchResults" :key="i" class="batch-item">
                            <span class="batch-index">#{{ i + 1 }}</span><code>{{ r.license_key }}</code>
                            <el-tag v-if="!r.error" type="success" size="small">{{ t('offline_page.tag_success') }}</el-tag>
                            <el-tag v-else type="danger" size="small">{{ r.error }}</el-tag>
                            <el-button v-if="r.license_file" text size="small" @click="copyOfflineText(r.license_file)">{{ t('actions.copy') }}</el-button>
                        </div>
                    </div>
                </el-dialog>
            </el-tab-pane>

            <!-- ===== Tab 2: 气隙部署 ===== -->
            <el-tab-pane :label="t('offline_center.tab_airgap')" name="airgap">
                <el-alert v-if="agStatus.air_gapped_mode" :title="t('air_gapped_page.alerts.air_gapped_active')" type="warning" show-icon :closable="false" class="mb-4" />
                <el-alert v-else :title="t('air_gapped_page.alerts.network_detected')" type="info" show-icon :closable="false" class="mb-4" />

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ agStatus.license_count }}</div><div class="stat-label">{{ t('air_gapped_page.stats.licenses_imported') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ agStatus.update_count }}</div><div class="stat-label">{{ t('air_gapped_page.stats.offline_updates') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="stat-card stat-success"><div class="stat-value">{{ agStatus.last_import || '-' }}</div><div class="stat-label">{{ t('air_gapped_page.stats.last_license_import') }}</div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value">{{ formatBytes(agStatus.disk_usage || 0) }}</div><div class="stat-label">{{ t('air_gapped_page.stats.offline_storage') }}</div></el-card></el-col>
                </el-row>

                <el-card shadow="hover">
                    <el-tabs v-model="agSubTab">
                        <el-tab-pane :label="t('air_gapped_page.tabs.license')" name="license">
                            <div class="tab-toolbar">
                                <el-button type="primary" size="small" @click="scanUsbDrives" :loading="scanning"><el-icon><Search /></el-icon> {{ t('air_gapped_page.btn_scan_usb') }}</el-button>
                                <el-upload :before-upload="handleLicenseUpload" :show-file-list="false" accept=".lic,.license,.key,.pem,.bin">
                                    <el-button size="small" type="success"><el-icon><Upload /></el-icon> {{ t('air_gapped_page.btn_upload_license') }}</el-button>
                                </el-upload>
                            </div>
                            <el-card v-if="usbCandidates.length" shadow="never" class="mb-3">
                                <template #header><span>{{ t('air_gapped_page.usb_found_title') }}</span></template>
                                <el-table :data="usbCandidates" stripe size="small">
                                    <el-table-column prop="name" :label="t('air_gapped_page.cols.filename')" min-width="200" />
                                    <el-table-column prop="path" :label="t('air_gapped_page.cols.path')" min-width="300"><template #default="{ row }"><span class="mono">{{ row.path }}</span></template></el-table-column>
                                    <el-table-column prop="size" :label="t('air_gapped_page.cols.size')" width="100"><template #default="{ row }">{{ formatBytes(row.size) }}</template></el-table-column>
                                    <el-table-column prop="modified" :label="t('air_gapped_page.cols.modified')" width="160" />
                                    <el-table-column :label="t('air_gapped_page.cols.actions')" width="120" fixed="right"><template #default="{ row }"><el-button size="small" type="primary" @click="importLicenseFile(row.path)">{{ t('actions.import') }}</el-button></template></el-table-column>
                                </el-table>
                            </el-card>
                            <h4 class="mb-2">{{ t('air_gapped_page.sections.imported_licenses') }}</h4>
                            <el-table :data="agLicenses" stripe v-loading="agLicensesLoading" :empty-text="t('air_gapped_page.empty.no_licenses')">
                                <el-table-column prop="name" :label="t('air_gapped_page.cols.filename')" min-width="250"><template #default="{ row }"><el-icon><Document /></el-icon><span class="ml-1 mono">{{ row.name }}</span></template></el-table-column>
                                <el-table-column prop="size" :label="t('air_gapped_page.cols.size')" width="120"><template #default="{ row }">{{ formatBytes(row.size) }}</template></el-table-column>
                                <el-table-column prop="modified" :label="t('air_gapped_page.cols.import_time')" width="180" />
                            </el-table>
                        </el-tab-pane>
                        <el-tab-pane :label="t('air_gapped_page.tabs.updates')" name="updates">
                            <div class="tab-toolbar">
                                <el-upload :before-upload="handleUpdateUpload" :show-file-list="false" accept=".tar,.gz,.tgz">
                                    <el-button size="small" type="primary"><el-icon><Upload /></el-icon> {{ t('air_gapped_page.btn_upload_update') }}</el-button>
                                </el-upload>
                            </div>
                            <el-table :data="agUpdates" stripe v-loading="agUpdatesLoading" :empty-text="t('air_gapped_page.empty.no_updates')">
                                <el-table-column prop="name" :label="t('air_gapped_page.cols.package_name')" min-width="300"><template #default="{ row }"><el-icon><Connection /></el-icon><span class="ml-1 mono">{{ row.name }}</span></template></el-table-column>
                                <el-table-column prop="size" :label="t('air_gapped_page.cols.size')" width="120"><template #default="{ row }">{{ formatBytes(row.size) }}</template></el-table-column>
                                <el-table-column prop="modified" :label="t('air_gapped_page.cols.upload_time')" width="180" />
                                <el-table-column :label="t('air_gapped_page.cols.actions')" width="120" fixed="right"><template #default="{ row }"><el-button size="small" type="success" @click="applyUpdatePackage(row.name)">{{ t('air_gapped_page.btn_apply_update') }}</el-button></template></el-table-column>
                            </el-table>
                        </el-tab-pane>
                        <el-tab-pane :label="t('air_gapped_page.tabs.docker')" name="docker">
                            <div class="tab-toolbar"><el-button size="small" @click="fetchDockerInfo" :loading="dockerLoading"><el-icon><Refresh /></el-icon> {{ t('air_gapped_page.btn_refresh_docker') }}</el-button></div>
                            <el-descriptions :column="1" border class="mb-4" v-if="dockerInfo.version">
                                <el-descriptions-item :label="t('air_gapped_page.docker.version')">{{ dockerInfo.version }}</el-descriptions-item>
                                <el-descriptions-item :label="t('air_gapped_page.docker.compose')">{{ dockerInfo.compose_version || t('air_gapped_page.docker.not_installed') }}</el-descriptions-item>
                            </el-descriptions>
                            <h4 class="mb-2">{{ t('air_gapped_page.sections.running_containers') }}</h4>
                            <el-table :data="dockerContainers" stripe size="small" :empty-text="t('air_gapped_page.empty.no_containers')">
                                <el-table-column :label="t('air_gapped_page.cols.container_name')" min-width="200"><template #default="{ row }"><span class="mono">{{ row.split('\t')[0] }}</span></template></el-table-column>
                                <el-table-column :label="t('air_gapped_page.cols.status')" min-width="200"><template #default="{ row }"><span class="mono">{{ row.split('\t')[1] }}</span></template></el-table-column>
                                <el-table-column :label="t('air_gapped_page.cols.ports')" min-width="200"><template #default="{ row }"><span class="mono">{{ row.split('\t')[2] }}</span></template></el-table-column>
                            </el-table>
                            <h4 class="mb-2 mt-3">{{ t('air_gapped_page.sections.local_images') }}</h4>
                            <el-table :data="dockerImages" stripe size="small" :empty-text="t('air_gapped_page.empty.no_images')">
                                <el-table-column :label="t('air_gapped_page.cols.image_name')" min-width="300"><template #default="{ row }"><span class="mono">{{ row.split('\t')[0] }}</span></template></el-table-column>
                                <el-table-column :label="t('air_gapped_page.cols.size')" min-width="100"><template #default="{ row }">{{ row.split('\t')[1] }}</template></el-table-column>
                            </el-table>
                        </el-tab-pane>
                        <el-tab-pane :label="t('air_gapped_page.tabs.health')" name="health">
                            <div class="tab-toolbar"><el-button size="small" @click="runHealthCheck" :loading="healthLoading"><el-icon><Monitor /></el-icon> {{ t('air_gapped_page.btn_run_health_check') }}</el-button></div>
                            <el-descriptions :column="1" border v-if="agHealthData.timestamp">
                                <el-descriptions-item :label="t('air_gapped_page.health.php_version')">{{ agHealthData.php_version }}</el-descriptions-item>
                                <el-descriptions-item :label="t('air_gapped_page.health.air_gapped_mode')"><el-tag :type="agHealthData.air_gapped_mode ? 'warning' : 'info'" size="small">{{ agHealthData.air_gapped_mode ? agStatusLabels.yes : agStatusLabels.no }}</el-tag></el-descriptions-item>
                                <el-descriptions-item :label="t('air_gapped_page.health.storage_writable')"><el-tag :type="agHealthData.storage ? 'success' : 'danger'" size="small">{{ agHealthData.storage ? agStatusLabels.writable : agStatusLabels.not_writable }}</el-tag></el-descriptions-item>
                                <el-descriptions-item :label="t('air_gapped_page.health.check_time')">{{ agHealthData.timestamp }}</el-descriptions-item>
                            </el-descriptions>
                            <h4 class="mb-2 mt-3">{{ t('air_gapped_page.sections.php_extensions') }}</h4>
                            <el-table :data="extensionList" stripe size="small" v-if="agHealthData.extensions">
                                <el-table-column prop="name" :label="t('air_gapped_page.cols.extension_name')" width="200" />
                                <el-table-column :label="t('air_gapped_page.cols.status')" width="100"><template #default="{ row }"><el-tag :type="row.loaded ? 'success' : 'danger'" size="small">{{ row.loaded ? agStatusLabels.loaded : agStatusLabels.missing }}</el-tag></template></el-table-column>
                            </el-table>
                        </el-tab-pane>
                    </el-tabs>
                </el-card>

                <el-dialog v-model="showImportDialog" :title="t('air_gapped_page.dialog.import_title')" width="500px">
                    <el-form :model="agImportForm" label-width="120px">
                        <el-form-item :label="t('air_gapped_page.dialog.file_path')"><el-input v-model="agImportForm.file_path" :placeholder="t('air_gapped_page.dialog.file_path_ph')"><template #prepend><el-icon><Folder /></el-icon></template></el-input></el-form-item>
                        <el-form-item :label="t('air_gapped_page.dialog.signature_validation')"><el-switch v-model="agImportForm.validate" :active-text="t('air_gapped_page.dialog.validate_signature')" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showImportDialog = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="confirmImport" :loading="importing">{{ t('actions.import') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Close, CircleCheck, CopyDocument, Search, Upload, Folder, Document, Connection, Monitor } from '@element-plus/icons-vue';
import offlineApi from '@/api/offline';
import airGappedApi from '@/api/air-gapped';

const { t, locale } = useI18n();
const P = 'offline_page';
const mainTab = ref('license');
const agLoaded = ref(false);

function formatTime(time) { if (!time) return '—'; return new Date(time).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US'); }
function copyOfflineText(text) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => ElMessage.success(t('license_files_page.messages.copied'))).catch(() => {
        const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
        ElMessage.success(t('license_files_page.messages.copied'));
    });
}

// ================================================================
// Tab 1: 离线 License
// ================================================================
const loadingKey = ref(false); const generating = ref(false); const batchGenerating = ref(false);
const initKeying = ref(false); const revoking = ref(false); const restoring = ref(false);
const showGenerateDialog = ref(false); const showRevokeInput = ref(false);
const publicKeyInfo = ref(null); const generatedFile = ref(null); const batchResults = ref([]);
const revokeForm = reactive({ license_key: '', reason: '' });
const generateForm = reactive({ license_id: null });
const batchForm = reactive({ license_ids: '' });

const keyDescLabels = computed(() => ({
    key_version: t('license_files_page.col_key_version'), algorithm: t('license_files_page.algorithm'),
    created_at: t('licenses_page.col_created_at'), expires_at: t('licenses_page.col_expires_at'), public_key: t(`${P}.label_public_key`),
}));
const fileDetailLabels = computed(() => ({
    license_key: t('licenses_page.license_key'), algorithm: t(`${P}.label_signature_algorithm`),
    expires_at: t('licenses_page.col_expires_at'), file_content: t(`${P}.label_file_content`),
}));

async function loadPublicKey() {
    loadingKey.value = true;
    try { const { data: res } = await offlineApi.publicKey(); if (res.success) publicKeyInfo.value = res.data; }
    catch { publicKeyInfo.value = null; } finally { loadingKey.value = false; }
}
async function handleGenerate() {
    if (!generateForm.license_id) { ElMessage.warning(t(`${P}.messages.license_id_required`)); return; }
    generating.value = true;
    try { const { data: res } = await offlineApi.generate(generateForm.license_id); if (res.success) { generatedFile.value = res.data; ElMessage.success(t(`${P}.messages.generate_ok`)); } }
    catch (e) { ElMessage.error(e.response?.data?.error?.message || t('license_files_page.messages.generate_fail')); }
    finally { generating.value = false; }
}
async function handleBatchGenerate() {
    const ids = batchForm.license_ids.split('\n').map(s => s.trim()).filter(s => s && !isNaN(Number(s))).map(Number);
    if (ids.length === 0) { ElMessage.warning(t(`${P}.messages.batch_ids_required`)); return; }
    batchGenerating.value = true; batchResults.value = [];
    try { const { data: res } = await offlineApi.generateBatch(ids); if (res.success) { batchResults.value = Array.isArray(res.data) ? res.data : [res.data]; ElMessage.success(t(`${P}.messages.batch_ok`, { n: batchResults.value.length })); } }
    catch (e) { ElMessage.error(e.response?.data?.error?.message || t(`${P}.messages.batch_fail`)); }
    finally { batchGenerating.value = false; }
}
async function handleInitKeys() {
    try {
        await ElMessageBox.confirm(t(`${P}.rotate_confirm`), t(`${P}.rotate_confirm_title`), { confirmButtonText: t(`${P}.rotate_confirm_btn`), cancelButtonText: t('actions.cancel'), type: 'warning' });
        initKeying.value = true;
        const { data: res } = await offlineApi.initKeys();
        if (res.success) { ElMessage.success(t(`${P}.messages.rotate_ok`)); loadPublicKey(); }
    } catch {} finally { initKeying.value = false; }
}
async function handleRevoke() {
    if (!revokeForm.license_key) return;
    revoking.value = true;
    try { const reason = revokeForm.reason || t(`${P}.default_revoke_reason`); const { data: res } = await offlineApi.revoke(revokeForm.license_key, reason); if (res.success) { ElMessage.success(t(`${P}.messages.revoke_ok`)); revokeForm.license_key = ''; revokeForm.reason = ''; } }
    catch (e) { ElMessage.error(e.response?.data?.error?.message || t('license_files_page.messages.revoke_fail')); }
    finally { revoking.value = false; }
}
async function handleRestore() {
    if (!revokeForm.license_key) return;
    restoring.value = true;
    try { const { data: res } = await offlineApi.restore(revokeForm.license_key); if (res.success) { ElMessage.success(t(`${P}.messages.restore_ok`)); revokeForm.license_key = ''; } }
    catch (e) { ElMessage.error(e.response?.data?.error?.message || t(`${P}.messages.restore_fail`)); }
    finally { restoring.value = false; }
}

// ================================================================
// Tab 2: 气隙部署
// ================================================================
const agSubTab = ref('license');
const agLoading = ref(false); const scanning = ref(false); const importing = ref(false);
const agLicensesLoading = ref(false); const agUpdatesLoading = ref(false);
const dockerLoading = ref(false); const healthLoading = ref(false);
const agStatus = ref({ air_gapped_mode: false, detected: false, license_count: 0, update_count: 0, last_import: null, last_update: null, disk_usage: 0, php_extensions: {}, storage_writable: false });
const usbCandidates = ref([]); const agLicenses = ref([]); const agUpdates = ref([]);
const dockerInfo = ref({}); const agHealthData = ref({});
const showImportDialog = ref(false);
const agImportForm = reactive({ file_path: '', validate: true });

const dockerContainers = computed(() => dockerInfo.value.containers || []);
const dockerImages = computed(() => dockerInfo.value.images || []);
const extensionList = computed(() => { if (!agHealthData.value.extensions) return []; return Object.entries(agHealthData.value.extensions).map(([name, loaded]) => ({ name, loaded })); });
const agStatusLabels = computed(() => ({
    yes: t('air_gapped_page.status.yes'), no: t('air_gapped_page.status.no'),
    writable: t('air_gapped_page.status.writable'), not_writable: t('air_gapped_page.status.not_writable'),
    loaded: t('air_gapped_page.status.loaded'), missing: t('air_gapped_page.status.missing'),
}));

function formatBytes(bytes) { if (!bytes || bytes === 0) return '0 B'; const u = ['B','KB','MB','GB','TB']; const i = Math.floor(Math.log(bytes)/Math.log(1024)); return (bytes/Math.pow(1024,i)).toFixed(1)+' '+u[i]; }

async function agRefreshAll() {
    agLoading.value = true;
    try { await Promise.all([agFetchStatus(), agFetchLicenses(), agFetchUpdates()]); } finally { agLoading.value = false; }
}
async function agFetchStatus() { try { const { data } = await airGappedApi.getStatus(); if (data.success) Object.assign(agStatus.value, data.data); } catch {} }
async function agFetchLicenses() { agLicensesLoading.value = true; try { const { data } = await airGappedApi.listLicenses(); if (data.success) agLicenses.value = data.data; } finally { agLicensesLoading.value = false; } }
async function agFetchUpdates() { agUpdatesLoading.value = true; try { const { data } = await airGappedApi.listUpdates(); if (data.success) agUpdates.value = data.data; } finally { agUpdatesLoading.value = false; } }
async function scanUsbDrives() {
    scanning.value = true;
    try { const { data } = await airGappedApi.scanUsb(); if (data.success) { usbCandidates.value = data.data.candidates; ElMessage.success(t('air_gapped_page.messages.usb_scan_found', { count: data.data.count })); if (data.data.count === 0) ElMessage.info(t('air_gapped_page.messages.usb_scan_none')); } }
    catch { ElMessage.error(t('air_gapped_page.messages.usb_scan_failed')); } finally { scanning.value = false; }
}
async function importLicenseFile(filePath) {
    importing.value = true;
    try { const { data } = await airGappedApi.importLicense(filePath, true); if (data.success) { ElMessage.success(data.message); await agFetchLicenses(); await agFetchStatus(); } else ElMessage.error(data.message); }
    catch { ElMessage.error(t('air_gapped_page.messages.license_import_failed')); } finally { importing.value = false; }
}
function handleLicenseUpload(file) { airGappedApi.uploadLicense(file).then(({ data }) => { if (data.success) { ElMessage.success(data.message); agFetchLicenses(); } else ElMessage.error(data.message); }).catch(() => ElMessage.error(t('air_gapped_page.messages.license_upload_failed'))); return false; }
function handleUpdateUpload(file) { airGappedApi.uploadUpdate(file).then(({ data }) => { if (data.success) { ElMessage.success(t('air_gapped_page.messages.update_upload_ok')); agFetchUpdates(); } else ElMessage.error(data.message); }).catch(() => ElMessage.error(t('air_gapped_page.messages.update_upload_failed'))); return false; }
async function applyUpdatePackage(name) {
    try {
        await ElMessageBox.confirm(t('air_gapped_page.messages.apply_confirm', { name }), t('air_gapped_page.messages.apply_confirm_title'), { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' });
        const { data } = await airGappedApi.applyUpdate(name); if (data.success) { ElMessage.success(data.message); await agFetchUpdates(); await agFetchStatus(); } else ElMessage.error(data.message);
    } catch {}
}
async function fetchDockerInfo() { dockerLoading.value = true; try { const { data } = await airGappedApi.getDockerInfo(); if (data.success) dockerInfo.value = data.data; } catch { ElMessage.warning(t('air_gapped_page.messages.docker_unavailable')); } finally { dockerLoading.value = false; } }
async function runHealthCheck() { healthLoading.value = true; try { const { data } = await airGappedApi.healthCheck(); if (data.success) agHealthData.value = data.data; } catch { ElMessage.error(t('air_gapped_page.messages.health_check_failed')); } finally { healthLoading.value = false; } }
function confirmImport() { if (!agImportForm.file_path) { ElMessage.warning(t('air_gapped_page.messages.file_path_required')); return; } importLicenseFile(agImportForm.file_path); showImportDialog.value = false; }

// ===== Lazy loading =====
function onMainTabChange(tab) {
    if (tab === 'airgap' && !agLoaded.value) { agRefreshAll(); agLoaded.value = true; }
}

onMounted(() => { loadPublicKey(); });
</script>

<style scoped>
.offline-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }
.mb-4 { margin-bottom: 16px; } .mb-3 { margin-bottom: 12px; } .mb-2 { margin-bottom: 8px; } .mt-4 { margin-top: 16px; } .mt-3 { margin-top: 12px; } .ml-2 { margin-left: 8px; } .ml-1 { margin-left: 4px; }
.card-header { display: flex; align-items: center; justify-content: space-between; font-weight: 600; }
.key-text { font-size: 11px; word-break: break-all; user-select: all; }
.mono { font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace; font-size: 0.9em; }

/* --- License Tab --- */
.generated-result { margin-top: 16px; }
.file-detail { margin-top: 8px; display: flex; flex-direction: column; gap: 6px; }
.file-detail-row { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.file-label { font-weight: 600; color: var(--el-text-color-secondary); min-width: 100px; }
.file-preview { margin-top: 8px; background: #f5f7fa; padding: 10px; border-radius: 4px; font-size: 11px; word-break: break-all; max-height: 100px; overflow: hidden; }
.batch-results h4 { font-size: 14px; font-weight: 600; margin: 0 0 12px; }
.batch-item { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--el-border-color-light); font-size: 13px; }
.batch-index { font-weight: 600; color: var(--el-text-color-placeholder); min-width: 24px; }

/* --- Airgap Tab --- */
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card.stat-success .stat-value { font-size: 1em; font-weight: 600; color: #67c23a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.tab-toolbar { display: flex; gap: 8px; margin-bottom: 16px; }

:deep(.el-card__body) { padding: 16px; }
</style>
