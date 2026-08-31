<template>
    <div class="license-cdn-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('license_files_page.title') }}</h2>
                <span class="header-subtitle">{{ t('license_files_page.subtitle') }}</span>
            </div>
        </div>

        <!-- 统计 -->
        <el-row :gutter="16" class="stats-row">
            <el-col :span="4" v-for="s in statCards" :key="s.label">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                    <div class="stat-label">{{ s.label }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作 -->
        <el-card shadow="never" class="action-card">
            <template #header>
                <div class="flex-between">
                    <span>{{ t('license_files_page.action_panel') }}</span>
                    <div>
                        <el-button type="primary" size="small" @click="showGenerateDialog = true">
                            <el-icon><Plus /></el-icon> {{ t('license_files_page.btn_generate') }}
                        </el-button>
                        <el-button size="small" @click="showBatchDialog = true">
                            <el-icon><Collection /></el-icon> {{ t('license_files_page.btn_batch') }}
                        </el-button>
                        <el-button size="small" @click="showKeyDialog = true">
                            <el-icon><Refresh /></el-icon> {{ t('license_files_page.btn_rotate_key') }}
                        </el-button>
                    </div>
                </div>
            </template>
            <el-alert :title="t('license_files_page.info_upload')" type="info" show-icon :closable="false" />
        </el-card>

        <!-- 分发列表 -->
        <el-card shadow="never" class="table-card">
            <template #header>
                <div class="flex-between">
                    <span>{{ t('license_files_page.list_title') }}</span>
                    <div class="filter-row">
                        <el-select v-model="filterStatus" :placeholder="t('licenses_page.status')" size="small" clearable style="width: 110px;" @change="loadList">
                            <el-option v-for="opt in statusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-input v-model="searchText" size="small" :placeholder="t('license_files_page.search_ph')" clearable style="width: 230px; margin-left: 8px;" @clear="loadList" @keyup.enter="loadList" />
                    </div>
                </div>
            </template>
            <el-table :data="fileList" v-loading="loadingList" stripe>
                <el-table-column :label="t('licenses_page.license_key')" min-width="200">
                    <template #default="{ row }">
                        <code class="mono">{{ row.license_key }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('licenses_page.col_product')" width="130" prop="product_name" />
                <el-table-column :label="t('license_files_page.col_filename')" width="200" prop="original_filename" />
                <el-table-column :label="t('license_files_page.col_size')" width="80" prop="file_size" align="right">
                    <template #default="{ row }">{{ formatSize(row.file_size) }}</template>
                </el-table-column>
                <el-table-column :label="t('license_files_page.col_key_version')" width="80" prop="key_version" align="center" />
                <el-table-column :label="t('license_files_page.col_algorithm')" width="90" prop="algorithm" />
                <el-table-column :label="t('licenses_page.col_status')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('license_files_page.col_downloads')" width="70" align="center" prop="download_count" />
                <el-table-column :label="t('licenses_page.col_actions')" width="210" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="copyText(row.cdn_url)">{{ t('license_files_page.copy_url') }}</el-button>
                        <el-button text size="small" v-if="row.status === 'active'" type="warning" @click="handleRedistribute(row)">{{ t('license_files_page.redistribute') }}</el-button>
                        <el-button text size="small" v-if="row.status === 'active'" type="danger" @click="handleRevoke(row)">{{ t('licenses_page.revoke') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap" v-if="pagination.total > pagination.per_page">
                <el-pagination
                    v-model:current-page="pagination.current_page"
                    :page-size="pagination.per_page"
                    :total="pagination.total"
                    layout="prev, pager, next"
                    small
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 分发日志 -->
        <el-card shadow="never" class="table-card">
            <template #header>
                <div class="flex-between">
                    <span>{{ t('license_files_page.logs_title') }}</span>
                    <el-button size="small" text @click="loadLogs">{{ t('license_files_page.refresh') }}</el-button>
                </div>
            </template>
            <el-table :data="logList" v-loading="loadingLogs" stripe size="small" max-height="300">
                <el-table-column :label="t('licenses_page.license_key')" min-width="180">
                    <template #default="{ row }"><code class="mono">{{ row.license_key }}</code></template>
                </el-table-column>
                <el-table-column :label="t('license_files_page.col_client_ip')" width="130" prop="client_ip" />
                <el-table-column :label="t('license_files_page.col_country')" width="80" prop="country" />
                <el-table-column :label="t('license_files_page.col_response')" width="70" prop="response_code" align="center" />
                <el-table-column :label="t('license_files_page.col_bytes')" width="80" prop="bytes_served" align="right">
                    <template #default="{ row }">{{ formatSize(row.bytes_served) }}</template>
                </el-table-column>
                <el-table-column :label="t('license_files_page.col_downloaded_at')" width="170" prop="downloaded_at" />
            </el-table>
        </el-card>

        <!-- 生成对话框 -->
        <el-dialog v-model="showGenerateDialog" :title="t('license_files_page.generate_dialog_title')" width="460px">
            <el-form label-position="top">
                <el-form-item :label="t('license_files_page.select_license')">
                    <el-select v-model="selectedLicenseId" filterable remote :placeholder="t('license_files_page.search_license_ph')" style="width: 100%;"
                        :remote-method="searchLicenses" :loading="searchingLicense">
                        <el-option v-for="l in licenseOptions" :key="l.id" :label="l.license_key" :value="l.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showGenerateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="generating" :disabled="!selectedLicenseId" @click="handleGenerate">{{ t('license_files_page.generate_and_distribute') }}</el-button>
            </template>
        </el-dialog>

        <!-- 批量分发对话框 -->
        <el-dialog v-model="showBatchDialog" :title="t('license_files_page.batch_dialog_title')" width="400px">
            <el-alert :title="t('license_files_page.batch_info')" type="info" show-icon :closable="false" class="mb-3" />
            <el-table :data="batchLicenseList" stripe size="small" @selection-change="onBatchSelection">
                <el-table-column type="selection" width="40" />
                <el-table-column :label="t('license_files_page.col_id')" width="60" prop="id" />
                <el-table-column :label="t('licenses_page.license_key')" prop="license_key" />
                <el-table-column :label="t('licenses_page.col_product')" width="100" prop="product_name" />
            </el-table>
            <template #footer>
                <el-button @click="showBatchDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="batchGenerating" :disabled="batchIds.length === 0" @click="handleBatchGenerate">
                    {{ t('license_files_page.distribute_selected', { n: batchIds.length }) }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 轮换公钥对话框 -->
        <el-dialog v-model="showKeyDialog" :title="t('license_files_page.rotate_dialog_title')" width="500px">
            <el-alert :title="t('license_files_page.rotate_warn')" type="warning" show-icon :closable="false" class="mb-3" />
            <el-form label-position="top">
                <el-form-item :label="t('license_files_page.new_public_key')">
                    <el-input v-model="newPublicKey" type="textarea" :rows="3" :placeholder="t('license_files_page.new_public_key_ph')" />
                </el-form-item>
                <el-form-item :label="t('license_files_page.algorithm')">
                    <el-radio-group v-model="keyAlgorithm">
                        <el-radio value="Ed25519">Ed25519</el-radio>
                        <el-radio value="RSA-2048">RSA-2048</el-radio>
                    </el-radio-group>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showKeyDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="warning" :loading="rotating" :disabled="!newPublicKey" @click="handleRotateKey">{{ t('license_files_page.confirm_rotate') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Collection, Refresh } from '@element-plus/icons-vue';
import licenseFileCdnApi from '@/api/license-file-cdn';
import licenseApi from '@/api/license';

const { t } = useI18n();

const loadingList = ref(false);
const loadingLogs = ref(false);
const generating = ref(false);
const batchGenerating = ref(false);
const rotating = ref(false);
const searchingLicense = ref(false);

const showGenerateDialog = ref(false);
const showBatchDialog = ref(false);
const showKeyDialog = ref(false);

const filterStatus = ref('');
const searchText = ref('');
const stats = reactive({ total_files: 0, active_files: 0, total_downloads: 0, recent_downloads_24h: 0 });
const fileList = ref([]);
const logList = ref([]);
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 });

const selectedLicenseId = ref(null);
const licenseOptions = ref([]);
const batchLicenseList = ref([]);
const batchIds = ref([]);
const newPublicKey = ref('');
const keyAlgorithm = ref('Ed25519');

const statCards = computed(() => [
    { label: t('license_files_page.stat_total_files'), value: stats.total_files, color: '#0f172a' },
    { label: t('license_files_page.stat_active_files'), value: stats.active_files, color: '#67C23A' },
    { label: t('license_files_page.stat_total_downloads'), value: stats.total_downloads, color: '#E6A23C' },
    { label: t('license_files_page.stat_downloads_24h'), value: stats.recent_downloads_24h, color: '#F56C6C' },
]);

const statusKeys = ['active', 'revoked', 'expired'];

const statusOptions = computed(() => statusKeys.map((value) => ({
    value,
    label: t(`licenses_page.st_${value}`),
})));

const statusLabels = computed(() => Object.fromEntries(
    statusKeys.map((key) => [key, t(`licenses_page.st_${key}`)]),
));

function statusLabel(status) {
    return statusLabels.value[status] || status;
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
    return size.toFixed(1) + ' ' + units[i];
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => ElMessage.success(t('license_files_page.messages.copied')));
}

async function loadStats() {
    try {
        const { data: res } = await licenseFileCdnApi.stats();
        if (res.success) Object.assign(stats, res.data);
    } catch { /* ignore */ }
}

async function loadList() {
    loadingList.value = true;
    try {
        const params = { page: pagination.current_page, per_page: pagination.per_page };
        if (filterStatus.value) params.status = filterStatus.value;
        if (searchText.value) params.search = searchText.value;
        const { data: res } = await licenseFileCdnApi.index(params);
        if (res.success) {
            fileList.value = res.data?.data || [];
            pagination.current_page = res.data?.current_page || 1;
            pagination.per_page = res.data?.per_page || 20;
            pagination.total = res.data?.total || 0;
        }
    } finally {
        loadingList.value = false;
    }
}

async function loadLogs() {
    loadingLogs.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.logs({ per_page: 50 });
        if (res.success) logList.value = res.data?.data || [];
    } finally {
        loadingLogs.value = false;
    }
}

async function searchLicenses(query) {
    if (!query) return;
    searchingLicense.value = true;
    try {
        const { data: res } = await licenseApi.index({ search: query, per_page: 20 });
        if (res.success) licenseOptions.value = res.data?.data || [];
    } finally {
        searchingLicense.value = false;
    }
}

async function loadBatchLicenses() {
    try {
        const { data: res } = await licenseApi.index({ per_page: 200 });
        if (res.success) batchLicenseList.value = res.data?.data || [];
    } catch { /* ignore */ }
}

function onBatchSelection(rows) {
    batchIds.value = rows.map(r => r.id);
}

async function handleGenerate() {
    if (!selectedLicenseId.value) return;
    generating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.generate(selectedLicenseId.value);
        if (res.success) {
            ElMessage.success(res.message || t('license_files_page.messages.generate_ok'));
            showGenerateDialog.value = false;
            await loadList();
            await loadStats();
        }
    } catch {
        ElMessage.error(t('license_files_page.messages.generate_fail'));
    } finally {
        generating.value = false;
    }
}

async function handleBatchGenerate() {
    if (batchIds.value.length === 0) return;
    batchGenerating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.batchGenerate(batchIds.value);
        if (res.success) {
            ElMessage.success(res.message || t('license_files_page.messages.batch_ok'));
            showBatchDialog.value = false;
            await loadList();
            await loadStats();
        }
    } catch {
        ElMessage.error(t('license_files_page.messages.batch_fail'));
    } finally {
        batchGenerating.value = false;
    }
}

async function handleRevoke(row) {
    try {
        await ElMessageBox.confirm(
            t('license_files_page.revoke_confirm', { key: row.license_key }),
            t('license_files_page.revoke_title'),
        );
        const { data: res } = await licenseFileCdnApi.revoke(row.license_key, t('license_files_page.default_revoke_reason'));
        if (res.success) {
            ElMessage.success(t('license_files_page.messages.revoke_ok'));
            await loadList();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('license_files_page.messages.revoke_fail'));
    }
}

async function handleRedistribute(row) {
    try {
        await ElMessageBox.confirm(
            t('license_files_page.redistribute_confirm', { key: row.license_key }),
            t('license_files_page.redistribute_title'),
        );
        const { data: res } = await licenseFileCdnApi.redistribute(row.id);
        if (res.success) {
            ElMessage.success(t('license_files_page.messages.redistribute_ok'));
            await loadList();
        }
    } catch (e) {
        if (e !== 'cancel') ElMessage.error(t('license_files_page.messages.redistribute_fail'));
    }
}

async function handleRotateKey() {
    if (!newPublicKey.value) return;
    rotating.value = true;
    try {
        const { data: res } = await licenseFileCdnApi.rotateKey(newPublicKey.value, keyAlgorithm.value);
        if (res.success) {
            ElMessage.success(res.message || t('license_files_page.messages.rotate_ok'));
            showKeyDialog.value = false;
            newPublicKey.value = '';
        }
    } catch {
        ElMessage.error(t('license_files_page.messages.rotate_fail'));
    } finally {
        rotating.value = false;
    }
}

onMounted(() => {
    loadStats();
    loadList();
    loadLogs();
    loadBatchLicenses();
});
</script>

<style scoped>
.license-cdn-page { padding: 20px; }
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}
.stats-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 24px; font-weight: 700; }
.stat-label { font-size: 13px; color: var(--el-text-color-secondary); margin-top: 4px; }
.action-card,
.table-card { margin-bottom: 16px; }
.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.filter-row { display: flex; align-items: center; }
.mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; }
.mb-3 { margin-bottom: 12px; }
.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}
:deep(.el-card__body) { padding: 16px; }
</style>
