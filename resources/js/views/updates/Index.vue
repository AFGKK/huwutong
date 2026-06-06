<template>
    <div class="updates-page">
        <div class="page-header">
            <div class="header-left">
                <h2>自动更新包管理</h2>
                <span class="header-subtitle">管理产品的更新版本分发，上传、发布、废弃更新包</span>
            </div>
            <div class="header-right">
                <el-button @click="loadProducts">
                    <el-icon><Refresh /></el-icon>
                    刷新
                </el-button>
                <el-button type="primary" @click="showCreate = true" :disabled="!selectedProduct">
                    <el-icon><Upload /></el-icon>
                    上传更新包
                </el-button>
            </div>
        </div>

        <!-- 产品选择 -->
        <el-card shadow="never" class="mb-4">
            <div class="product-selector">
                <span class="selector-label">选择产品：</span>
                <el-select
                    v-model="selectedProduct"
                    placeholder="请选择产品"
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
                        <div class="stat-label">总更新包数</div>
                        <div class="stat-value">{{ packages.length }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已发布</div>
                        <div class="stat-value text-success">{{ statusCount('published') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">草稿</div>
                        <div class="stat-value text-warning">{{ statusCount('draft') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">已废弃</div>
                        <div class="stat-value text-danger">{{ statusCount('deprecated') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 状态筛选 -->
        <div class="mb-4" v-if="selectedProduct">
            <el-radio-group v-model="statusFilter" @change="fetchPackages">
                <el-radio-button value="">全部</el-radio-button>
                <el-radio-button value="draft">草稿</el-radio-button>
                <el-radio-button value="published">已发布</el-radio-button>
                <el-radio-button value="deprecated">已废弃</el-radio-button>
            </el-radio-group>
        </div>

        <!-- 更新包列表 -->
        <el-card shadow="never" v-if="selectedProduct">
            <el-table :data="packages" v-loading="loading" stripe>
                <el-table-column label="版本号" width="130">
                    <template #default="{ row }">
                        <span class="version-tag">v{{ row.version }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.type === 'full' ? 'primary' : row.type === 'incremental' ? 'success' : 'warning'" size="small" effect="plain">
                            {{ row.type === 'full' ? '完整包' : row.type === 'incremental' ? '增量补丁' : '热修复' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="文件名" min-width="200">
                    <template #default="{ row }">
                        <div class="filename-cell">
                            <el-icon><Document /></el-icon>
                            <span>{{ row.file_name }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="文件大小" width="100">
                    <template #default="{ row }">
                        <code>{{ row.file_size_human || formatBytes(row.file_size) }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="前置版本" width="100">
                    <template #default="{ row }">
                        {{ row.prev_version ? 'v' + row.prev_version : '—' }}
                    </template>
                </el-table-column>
                <el-table-column label="创建人" width="120">
                    <template #default="{ row }">
                        {{ row.creator?.name || row.created_by || '—' }}
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170">
                    <template #default="{ row }">
                        {{ formatTime(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" @click="showDetail(row)">详情</el-button>
                        <el-button
                            v-if="row.status === 'draft'"
                            text
                            size="small"
                            type="success"
                            @click="handlePublish(row)"
                        >
                            发布
                        </el-button>
                        <el-popconfirm
                            v-if="row.status === 'draft' || row.status === 'published'"
                            title="确认废弃此更新包？"
                            confirm-button-text="废弃"
                            @confirm="handleDeprecate(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="warning">废弃</el-button>
                            </template>
                        </el-popconfirm>
                        <el-popconfirm
                            title="确认删除？不可恢复"
                            confirm-button-text="删除"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button text size="small" type="danger">删除</el-button>
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

        <el-empty v-else-if="products.length > 0" :image-size="80" description="请先选择一个产品查看其更新包" />
        <el-empty v-else :image-size="80" description="暂无产品数据" v-loading="loadingProducts" />

        <!-- 上传更新包 Dialog -->
        <el-dialog v-model="showCreate" title="上传更新包" width="520px" :close-on-click-modal="false" @close="resetUploadForm">
            <el-form :model="uploadForm" ref="uploadFormRef" :rules="uploadRules" label-width="120px">
                <el-form-item label="产品">
                    <el-tag>{{ selectedProductName }}</el-tag>
                </el-form-item>
                <el-form-item label="版本号" prop="version">
                    <el-input v-model="uploadForm.version" placeholder="如：1.0.0, 2.3.1-beta" maxlength="32" />
                </el-form-item>
                <el-form-item label="更新类型" prop="type">
                    <el-select v-model="uploadForm.type" style="width: 200px">
                        <el-option label="完整包" value="full" />
                        <el-option label="增量补丁" value="incremental" />
                        <el-option label="热修复" value="hotfix" />
                    </el-select>
                </el-form-item>
                <el-form-item label="前置版本" prop="prev_version" v-if="uploadForm.type !== 'full'">
                    <el-input v-model="uploadForm.prev_version" placeholder="如：1.0.0" maxlength="32" />
                    <span class="form-hint">增量包/热修复需要指定前置版本</span>
                </el-form-item>
                <el-form-item label="更新文件" prop="file">
                    <el-upload
                        ref="uploadRef"
                        :auto-upload="false"
                        :show-file-list="true"
                        :limit="1"
                        :on-change="onFileChange"
                        :on-exceed="() => ElMessage.warning('只能上传一个文件')"
                    >
                        <el-button type="primary"><el-icon><Upload /></el-icon>选择文件</el-button>
                        <template #tip>
                            <div class="form-hint">支持 zip / tar.gz / bin 格式，最大 1GB</div>
                        </template>
                    </el-upload>
                </el-form-item>
                <el-form-item label="发布说明" prop="release_notes">
                    <el-input
                        v-model="uploadForm.release_notes"
                        type="textarea"
                        :rows="4"
                        placeholder="更新内容描述（支持 Markdown 格式或 JSON）"
                    />
                </el-form-item>
                <el-form-item label="元数据" prop="metadata">
                    <el-input
                        v-model="uploadForm.metadata"
                        type="textarea"
                        :rows="2"
                        placeholder='可选 JSON 元数据，如：{"min_app_version":"1.0.0","required":true}'
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreate = false">取消</el-button>
                <el-button type="primary" @click="handleUpload" :loading="uploading">
                    <el-icon><Upload /></el-icon> 上传
                </el-button>
            </template>
        </el-dialog>

        <!-- 详情 Dialog -->
        <el-dialog v-model="showDetailDialog" :title="'更新包详情 v' + (detailData?.version || '')" width="600px">
            <div v-if="detailData" v-loading="loadingDetail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="版本号">
                        <span class="version-tag">v{{ detailData.version }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="类型">
                        <el-tag :type="detailData.type === 'full' ? 'primary' : detailData.type === 'incremental' ? 'success' : 'warning'" size="small">
                            {{ detailData.type === 'full' ? '完整包' : detailData.type === 'incremental' ? '增量补丁' : '热修复' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="状态">
                        <el-tag :type="statusType(detailData.status)" size="small" effect="dark">
                            {{ statusLabel(detailData.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="文件大小">
                        <code>{{ detailData.file_size_human || formatBytes(detailData.file_size) }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="文件名">{{ detailData.file_name }}</el-descriptions-item>
                    <el-descriptions-item label="前置版本">
                        {{ detailData.prev_version ? 'v' + detailData.prev_version : '无' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="文件哈希">
                        <code class="hash-text">{{ detailData.file_hash || '—' }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="签名">
                        <code class="hash-text">{{ detailData.signature ? detailData.signature.substring(0, 32) + '...' : '—' }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="发布时间">
                        {{ formatTime(detailData.published_at) || '未发布' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="废弃时间">
                        {{ formatTime(detailData.deprecated_at) || '—' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="创建人">{{ detailData.creator?.name || '—' }}</el-descriptions-item>
                </el-descriptions>

                <!-- 发布说明 -->
                <div class="detail-section">
                    <h4>发布说明</h4>
                    <div class="release-notes-content" v-if="detailData.release_notes">
                        <pre>{{ typeof detailData.release_notes === 'object' ? JSON.stringify(detailData.release_notes, null, 2) : detailData.release_notes }}</pre>
                    </div>
                    <span v-else class="text-muted">无</span>
                </div>

                <!-- 下载统计 -->
                <div class="detail-section" v-if="detailStats">
                    <h4>下载统计</h4>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <div class="mini-stat">
                                <div class="mini-stat-value">{{ detailStats.total_downloads }}</div>
                                <div class="mini-stat-label">总下载</div>
                            </div>
                        </el-col>
                        <el-col :span="12">
                            <div class="mini-stat">
                                <div class="mini-stat-value">{{ detailStats.today_downloads }}</div>
                                <div class="mini-stat-label">今日下载</div>
                            </div>
                        </el-col>
                    </el-row>
                    <div v-if="detailStats.top_subnets?.length" class="top-subnets">
                        <h5>热门来源 IP 段</h5>
                        <div v-for="(s, i) in detailStats.top_subnets" :key="i" class="subnet-row">
                            <span class="subnet-rank">#{{ i + 1 }}</span>
                            <code>{{ s.subnet }}.*</code>
                            <span class="subnet-count">{{ s.count }} 次</span>
                        </div>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Refresh, Upload, Document } from '@element-plus/icons-vue';
import updatePackageApi from '@/api/updatePackage';
import productApi from '@/api/product';

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

const uploadRules = {
    version: [{ required: true, message: '请输入版本号', trigger: 'blur' }],
    type: [{ required: true, message: '请选择更新类型', trigger: 'change' }],
};

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
    const map = { draft: '草稿', published: '已发布', deprecated: '已废弃' };
    return map[status] || status;
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '—';
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
        ElMessage.error('加载产品列表失败');
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
        ElMessage.error('加载更新包列表失败');
        packages.value = [];
    } finally {
        loading.value = false;
    }
}

async function handleUpload() {
    const valid = await uploadFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    if (!selectedFile.value) {
        ElMessage.warning('请选择上传文件');
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
            ElMessage.success('更新包上传成功');
            showCreate.value = false;
            fetchPackages();
        } else {
            ElMessage.error(res.message || '上传失败');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败');
    } finally {
        uploading.value = false;
    }
}

async function handlePublish(row) {
    try {
        await ElMessageBox.confirm(`确认发布 v${row.version}？发布后将对 SDK 客户端可见。`, '发布确认', {
            confirmButtonText: '确认发布',
            cancelButtonText: '取消',
            type: 'success',
        });
        const { data: res } = await updatePackageApi.publish(row.id);
        if (res.success) {
            ElMessage.success('更新包已发布');
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
            ElMessage.success('更新包已废弃');
            fetchPackages();
        }
    } catch {
        ElMessage.error('操作失败');
    }
}

async function handleDelete(row) {
    try {
        const { data: res } = await updatePackageApi.destroy(row.id);
        if (res.success) {
            ElMessage.success('更新包已删除');
            fetchPackages();
        }
    } catch {
        ElMessage.error('删除失败');
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
        ElMessage.error('加载详情失败');
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
