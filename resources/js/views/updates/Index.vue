<template>
    <div class="updates-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('updates_page.title') }}</h2>
                <span class="header-subtitle">{{ t('updates_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="loadProducts">
                    <el-icon><Refresh /></el-icon>
                    {{ t('updates_page.refresh') }}
                </el-button>
                <el-button type="primary" @click="showCreate = true" :disabled="!selectedProduct">
                    <el-icon><Upload /></el-icon>
                    {{ t('updates_page.upload_package') }}
                </el-button>
            </div>
        </div>

        <!-- 产品选择 -->
        <el-card shadow="never" class="mb-4">
            <div class="product-selector">
                <span class="selector-label">{{ t('updates_page.select_product') }}</span>
                <el-select
                    v-model="selectedProduct"
                    :placeholder="t('updates_page.select_product_ph')"
                    filterable
                    size="large"
                    style="width: 320px"
                    @change="onProductChange"
                >
                    <el-option
                        v-for="p in products"
                        :key="p.id"
                        :label="p.name"
                        :value="p.id"
                    />
                </el-select>
            </div>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4" v-if="selectedProduct">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('updates_page.stat_total') }}</div>
                        <div class="stat-value">{{ packages.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('updates_page.stat_published') }}</div>
                        <div class="stat-value text-success">{{ statusCount('published') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('updates_page.stat_draft') }}</div>
                        <div class="stat-value text-warning">{{ statusCount('draft') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">{{ t('updates_page.stat_deprecated') }}</div>
                        <div class="stat-value text-danger">{{ statusCount('deprecated') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 状态筛选 -->
        <div class="mb-4" v-if="selectedProduct">
            <el-radio-group v-model="statusFilter" @change="fetchPackages">
                <el-radio-button
                    v-for="opt in statusFilterOptions"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </el-radio-button>
            </el-radio-group>
        </div>

        <!-- 更新包列表 -->
        <el-card shadow="never" v-if="selectedProduct">
            <el-table :data="packages" v-loading="loading" stripe>
                <el-table-column :label="t('updates_page.col_version')" width="130">
                    <template #default="{ row }">
                        <span class="version-tag">v{{ row.version }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'full' ? 'primary' : row.type === 'incremental' ? 'success' : 'warning'" size="small" effect="plain">
                            {{ typeLabel(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_filename')" min-width="200">
                    <template #default="{ row }">
                        <div class="filename-cell">
                            <el-icon><Document /></el-icon>
                            <span>{{ row.file_name }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_file_size')" width="100">
                    <template #default="{ row }">
                        <code>{{ row.file_size_human || formatBytes(row.file_size) }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_prev_version')" width="100">
                    <template #default="{ row }">
                        {{ row.prev_version ? 'v' + row.prev_version : t('updates_page.em_dash') }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_creator')" width="120">
                    <template #default="{ row }">
                        {{ row.creator?.name || row.created_by || t('updates_page.em_dash') }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_created_at')" width="170">
                    <template #default="{ row }">
                        {{ formatTime(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('updates_page.col_actions')" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="showDetail(row)">{{ t('updates_page.detail') }}</el-button>
                        <el-button
                            v-if="row.status === 'draft'"
                            text
                            size="small"
                            type="success"
                            @click="handlePublish(row)"
                        >
                            {{ t('updates_page.publish') }}
                        </el-button>
                        <el-popconfirm
                            v-if="row.status === 'draft' || row.status === 'published'"
                            :title="t('updates_page.confirm_deprecate')"
                            :confirm-button-text="t('updates_page.deprecate')"
                            @confirm="handleDeprecate(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="warning">{{ t('updates_page.deprecate') }}</el-button>
                            </template>
                        </el-popconfirm>
                        <el-popconfirm
                            :title="t('messages.confirm_delete')"
                            :confirm-button-text="t('actions.delete')"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchPackages"
                    @size-change="fetchPackages"
                />
            </div>
        </el-card>

        <el-empty v-else-if="products.length > 0" :image-size="80" :description="t('updates_page.empty_select_product')" />
        <el-empty v-else :image-size="80" :description="t('updates_page.empty_no_products')" v-loading="loadingProducts" />

        <!-- 上传更新包 Dialog -->
        <el-dialog v-model="showCreate" :title="t('updates_page.upload_dialog_title')" width="520px" :close-on-click-modal="false" @close="resetUploadForm">
            <el-form :model="uploadForm" ref="uploadFormRef" :rules="uploadRules" label-width="120px">
                <el-form-item :label="t('updates_page.field_product')">
                    <el-tag>{{ selectedProductName }}</el-tag>
                </el-form-item>
                <el-form-item :label="t('updates_page.field_version')" prop="version">
                    <el-input v-model="uploadForm.version" :placeholder="t('updates_page.version_ph')" maxlength="32" />
                </el-form-item>
                <el-form-item :label="t('updates_page.field_type')" prop="type">
                    <el-select v-model="uploadForm.type" style="width: 200px">
                        <el-option
                            v-for="opt in uploadTypeOptions"
                            :key="opt.value"
                            :label="opt.label"
                            :value="opt.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('updates_page.field_prev_version')" prop="prev_version" v-if="uploadForm.type !== 'full'">
                    <el-input v-model="uploadForm.prev_version" :placeholder="t('updates_page.prev_version_ph')" maxlength="32" />
                    <span class="form-hint">{{ t('updates_page.prev_version_hint') }}</span>
                </el-form-item>
                <el-form-item :label="t('updates_page.field_file')" prop="file">
                    <el-upload
                        ref="uploadRef"
                        :auto-upload="false"
                        :show-file-list="true"
                        :limit="1"
                        :on-change="onFileChange"
                        :on-exceed="() => ElMessage.warning(t('updates_page.single_file_warning'))"
                    >
                        <el-button type="primary"><el-icon><Upload /></el-icon>{{ t('updates_page.select_file') }}</el-button>
                        <template #tip>
                            <div class="form-hint">{{ t('updates_page.file_hint') }}</div>
                        </template>
                    </el-upload>
                </el-form-item>
                <el-form-item :label="t('updates_page.field_release_notes')" prop="release_notes">
                    <el-input
                        v-model="uploadForm.release_notes"
                        type="textarea"
                        :rows="4"
                        :placeholder="t('updates_page.release_notes_ph')"
                    />
                </el-form-item>
                <el-form-item :label="t('updates_page.field_metadata')" prop="metadata">
                    <el-input
                        v-model="uploadForm.metadata"
                        type="textarea"
                        :rows="2"
                        :placeholder="t('updates_page.metadata_ph')"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleUpload" :loading="uploading">
                    <el-icon><Upload /></el-icon> {{ t('actions.upload') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog
            v-model="showDetailDialog"
            :title="t('updates_page.detail_dialog_title', { version: detailData?.version || '' })"
            width="600px"
        >
            <div v-if="detailData" v-loading="loadingDetail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('updates_page.col_version')">
                        <span class="version-tag">v{{ detailData.version }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_type')">
                        <el-tag :type="detailData.type === 'full' ? 'primary' : detailData.type === 'incremental' ? 'success' : 'warning'" size="small">
                            {{ typeLabel(detailData.type) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_status')">
                        <el-tag :type="statusType(detailData.status)" size="small" effect="dark">
                            {{ statusLabel(detailData.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_file_size')">
                        <code>{{ detailData.file_size_human || formatBytes(detailData.file_size) }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_filename')">{{ detailData.file_name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_prev_version')">
                        {{ detailData.prev_version ? 'v' + detailData.prev_version : t('updates_page.none') }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.field_file_hash')">
                        <code class="hash-text">{{ detailData.file_hash || t('updates_page.em_dash') }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.field_signature')">
                        <code class="hash-text">{{ detailData.signature ? detailData.signature.substring(0, 32) + '...' : t('updates_page.em_dash') }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.field_published_at')">
                        {{ formatTime(detailData.published_at) || t('updates_page.not_published') }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.field_deprecated_at')">
                        {{ formatTime(detailData.deprecated_at) || t('updates_page.em_dash') }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('updates_page.col_creator')">{{ detailData.creator?.name || t('updates_page.em_dash') }}</el-descriptions-item>
                </el-descriptions>

                <!-- 发布说明 -->
                <div class="detail-section">
                    <h4>{{ t('updates_page.section_release_notes') }}</h4>
                    <div class="release-notes-content" v-if="detailData.release_notes">
                        <pre>{{ typeof detailData.release_notes === 'object' ? JSON.stringify(detailData.release_notes, null, 2) : detailData.release_notes }}</pre>
                    </div>
                    <span v-else class="text-muted">{{ t('updates_page.none') }}</span>
                </div>

                <!-- 下载统计 -->
                <div class="detail-section" v-if="detailStats">
                    <h4>{{ t('updates_page.section_download_stats') }}</h4>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <div class="mini-stat">
                                <div class="mini-stat-value">{{ detailStats.total_downloads }}</div>
                                <div class="mini-stat-label">{{ t('updates_page.stat_total_downloads') }}</div>
                            </div>
                        </el-col>
                        <el-col :span="12">
                            <div class="mini-stat">
                                <div class="mini-stat-value">{{ detailStats.today_downloads }}</div>
                                <div class="mini-stat-label">{{ t('updates_page.stat_today_downloads') }}</div>
                            </div>
                        </el-col>
                    </el-row>
                    <div v-if="detailStats.top_subnets?.length" class="top-subnets">
                        <h5>{{ t('updates_page.top_subnets') }}</h5>
                        <div v-for="(s, i) in detailStats.top_subnets" :key="i" class="subnet-row">
                            <span class="subnet-rank">#{{ i + 1 }}</span>
                            <code>{{ s.subnet }}.*</code>
                            <span class="subnet-count">{{ t('updates_page.download_count', { count: s.count }) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Upload, Document } from '@element-plus/icons-vue';
import updatePackageApi from '@/api/updatePackage';
import productApi from '@/api/product';

const { t, locale } = useI18n();

const loading = ref(false);
const loadingProducts = ref(false);
const loadingDetail = ref(false);
const uploading = ref(false);
const showCreate = ref(false);
const showDetailDialog = ref(false);

const products = ref([]);
const selectedProduct = ref(null);
const packages = ref([]);
const page = ref(1);
const perPage = ref(15);
const total = ref(0);
const statusFilter = ref('');
const detailData = ref(null);
const detailStats = ref(null);
const uploadRef = ref(null);
const uploadFormRef = ref(null);
const selectedFile = ref(null);

const uploadForm = ref({
    version: '',
    type: 'full',
    prev_version: '',
    file: null,
    release_notes: '',
    metadata: '',
});

const statusFilterOptions = computed(() => [
    { value: '', label: t('updates_page.filter_all') },
    { value: 'draft', label: t('updates_page.status_draft') },
    { value: 'published', label: t('updates_page.status_published') },
    { value: 'deprecated', label: t('updates_page.status_deprecated') },
]);

const uploadTypeOptions = computed(() => [
    { value: 'full', label: t('updates_page.type_full') },
    { value: 'incremental', label: t('updates_page.type_incremental') },
    { value: 'hotfix', label: t('updates_page.type_hotfix') },
]);

const statusLabelMap = computed(() => ({
    draft: t('updates_page.status_draft'),
    published: t('updates_page.status_published'),
    deprecated: t('updates_page.status_deprecated'),
}));

const typeLabelMap = computed(() => ({
    full: t('updates_page.type_full'),
    incremental: t('updates_page.type_incremental'),
    hotfix: t('updates_page.type_hotfix'),
}));

const uploadRules = computed(() => ({
    version: [{ required: true, message: t('updates_page.validation_version_required'), trigger: 'blur' }],
    type: [{ required: true, message: t('updates_page.validation_type_required'), trigger: 'change' }],
}));

const selectedProductName = computed(() => {
    const p = products.value.find(p => p.id === selectedProduct.value);
    return p?.name || '';
});

function statusCount(status) {
    return packages.value.filter(p => p.status === status).length;
}

function statusType(status) {
    const map = { draft: 'info', published: 'success', deprecated: 'danger' };
    return map[status] || 'info';
}

function statusLabel(status) {
    return statusLabelMap.value[status] || status;
}

function typeLabel(type) {
    return typeLabelMap.value[type] || type;
}

function formatTime(time) {
    if (!time) return '';
    return new Date(time).toLocaleString(locale.value === 'zh_CN' ? 'zh-CN' : 'en-US');
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return t('updates_page.em_dash');
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) {
        size /= 1024;
        i++;
    }
    return size.toFixed(2) + ' ' + units[i];
}

function onFileChange(file) {
    selectedFile.value = file.raw;
}

function resetUploadForm() {
    uploadForm.value = { version: '', type: 'full', prev_version: '', release_notes: '', metadata: '' };
    selectedFile.value = null;
    uploadFormRef.value?.resetFields();
}

function onProductChange() {
    statusFilter.value = '';
    page.value = 1;
    fetchPackages();
}

async function loadProducts() {
    loadingProducts.value = true;
    try {
        const { data: res } = await productApi.list({ per_page: 100 });
        if (res.success) {
            products.value = res.data?.data || [];
        }
    } catch {
        ElMessage.error(t('updates_page.msg_load_products_failed'));
    } finally {
        loadingProducts.value = false;
    }
}

async function fetchPackages() {
    if (!selectedProduct.value) return;
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value };
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }
        const { data: res } = await updatePackageApi.list(selectedProduct.value, params);
        if (res.success) {
            packages.value = res.data?.data || [];
            total.value = res.data?.total || 0;
        }
    } catch {
        ElMessage.error(t('updates_page.msg_load_packages_failed'));
        packages.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleUpload() {
    const valid = await uploadFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    if (!selectedFile.value) {
        ElMessage.warning(t('updates_page.msg_select_file'));
        return;
    }

    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('version', uploadForm.value.version);
        formData.append('type', uploadForm.value.type);
        formData.append('file', selectedFile.value);
        if (uploadForm.value.prev_version) {
            formData.append('prev_version', uploadForm.value.prev_version);
        }
        if (uploadForm.value.release_notes) {
            formData.append('release_notes', uploadForm.value.release_notes);
        }
        if (uploadForm.value.metadata) {
            formData.append('metadata', uploadForm.value.metadata);
        }

        const { data: res } = await updatePackageApi.create(selectedProduct.value, formData);
        if (res.success) {
            ElMessage.success(t('updates_page.msg_upload_success'));
            showCreate.value = false;
            fetchPackages();
        } else {
            ElMessage.error(res.message || t('updates_page.msg_upload_failed'));
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('updates_page.msg_upload_failed'));
    } finally {
        uploading.value = false;
    }
}

async function handlePublish(row) {
    try {
        await ElMessageBox.confirm(
            t('updates_page.publish_confirm_msg', { version: row.version }),
            t('updates_page.publish_confirm_title'),
            {
                confirmButtonText: t('updates_page.confirm_publish'),
                cancelButtonText: t('actions.cancel'),
                type: 'success',
            },
        );
        const { data: res } = await updatePackageApi.publish(row.id);
        if (res.success) {
            ElMessage.success(t('updates_page.msg_published'));
            fetchPackages();
        }
    } catch {
        // cancelled
    }
}

async function handleDeprecate(row) {
    try {
        const { data: res } = await updatePackageApi.deprecate(row.id);
        if (res.success) {
            ElMessage.success(t('updates_page.msg_deprecated'));
            fetchPackages();
        }
    } catch {
        ElMessage.error(t('messages.failed'));
    }
}

async function handleDelete(row) {
    try {
        const { data: res } = await updatePackageApi.destroy(row.id);
        if (res.success) {
            ElMessage.success(t('updates_page.msg_deleted'));
            fetchPackages();
        }
    } catch {
        ElMessage.error(t('updates_page.msg_delete_failed'));
    }
}

async function showDetail(row) {
    showDetailDialog.value = true;
    loadingDetail.value = true;
    detailStats.value = null;

    try {
        const [{ data: detailRes }, { data: statsRes }] = await Promise.all([
            updatePackageApi.show(row.id),
            updatePackageApi.stats(row.id),
        ]);
        if (detailRes.success) {
            detailData.value = detailRes.data;
        }
        if (statsRes.success) {
            detailStats.value = statsRes.data;
        }
    } catch {
        ElMessage.error(t('updates_page.msg_load_detail_failed'));
    } finally {
        loadingDetail.value = false;
    }
}

onMounted(() => {
    loadProducts();
});
</script>

<style scoped>
.updates-page { padding: 20px; }

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

.mb-4 { margin-bottom: 16px; }

.stat-item {
    text-align: center;
    padding: 8px 0;
}
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-text-color-primary);
}
.text-success { color: var(--el-color-success); }
.text-danger { color: var(--el-color-danger); }
.text-warning { color: var(--el-color-warning); }
.text-muted { color: var(--el-text-color-placeholder); }

.product-selector {
    display: flex;
    align-items: center;
    gap: 12px;
}
.selector-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    white-space: nowrap;
}

.version-tag {
    font-family: 'SF Mono', Monaco, 'Cascadia Code', monospace;
    font-weight: 600;
    color: var(--el-color-primary);
}

.filename-cell {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filename-cell span {
    font-size: 13px;
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.form-hint {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    display: block;
    margin-top: 4px;
}

/* Detail */
.detail-section {
    margin-top: 20px;
}
.detail-section h4 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 12px 0;
}
.release-notes-content pre {
    background: #f5f7fa;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
}

.hash-text {
    font-size: 11px;
    word-break: break-all;
    user-select: all;
}

.mini-stat {
    text-align: center;
    padding: 16px;
    background: var(--el-color-info-light-9);
    border-radius: 8px;
}
.mini-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--el-color-primary);
}
.mini-stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.top-subnets {
    margin-top: 16px;
}
.top-subnets h5 {
    font-size: 13px;
    font-weight: 600;
    margin: 0 0 8px 0;
}
.subnet-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 6px 10px;
    border-bottom: 1px solid var(--el-border-color-light);
    font-size: 13px;
}
.subnet-rank {
    font-weight: 600;
    color: var(--el-text-color-placeholder);
    min-width: 24px;
}
.subnet-count {
    margin-left: auto;
    color: var(--el-text-color-secondary);
}

:deep(.el-card__body) { padding: 16px; }
</style>
