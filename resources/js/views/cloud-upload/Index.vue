<template>
    <div class="cloud-upload-page">
        <h2>{{ t('cloud_upload_page.title') }}</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total_files || 0 }}</div><div class="stat-label">{{ t('cloud_upload_page.stats.total_files') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.total_size_mb || 0 }} MB</div><div class="stat-label">{{ t('cloud_upload_page.stats.total_size') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ typeStats('image') }}</div><div class="stat-label">{{ t('cloud_upload_page.stats.image_files') }}</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ typeStats('document') }}</div><div class="stat-label">{{ t('cloud_upload_page.stats.document_files') }}</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 文件列表 -->
            <el-tab-pane :label="t('cloud_upload_page.tabs.file_list')" name="list">
                <div class="toolbar">
                    <el-upload
                        :auto-upload="false"
                        :show-file-list="false"
                        :on-change="handleFileSelect"
                        accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif,.bmp,.tiff,.ico,audio/mpeg,audio/mp3,.m4a,.ogg,.oga,.wav,.wma,.ra,.aac,.flac,video/mp4,.webm,.mov,.qt,.avi,.wmv,.mkv,.flv,.3gp,.zip,.rar,.7z,.tar,.gz,.bz2,.xz,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.md,.json,.rtf,.html,.xml"
                    >
                        <el-button type="primary"><el-icon><Plus /></el-icon> {{ t('cloud_upload_page.upload_btn') }}</el-button>
                    </el-upload>
                    <el-select v-model="filterType" :placeholder="t('cloud_upload_page.filter_type_ph')" clearable style="width:140px;margin-left:12px" @change="loadFiles">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                    <el-button @click="loadFiles" style="margin-left:8px">{{ t('cloud_upload_page.refresh') }}</el-button>
                </div>

                <el-table :data="files" v-loading="loading" stripe>
                    <el-table-column :label="t('cloud_upload_page.cols.file')" min-width="300">
                        <template #default="{row}">
                            <div class="file-info">
                                <el-icon v-if="row.mime_type?.startsWith('image')" :size="32" color="#0f172a"><Picture /></el-icon>
                                <el-icon v-else :size="32" color="#909399"><Document /></el-icon>
                                <div class="file-detail">
                                    <div class="file-name">{{ row.original_name }}</div>
                                    <div class="file-meta">{{ (row.file_size / 1024).toFixed(1) }} KB · {{ typeLabels[row.type] || row.type }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('cloud_upload_page.cols.preview')" width="100" align="center">
                        <template #default="{ row }">
                            <el-button link type="primary" size="small" @click="handlePreview(row)">
                                <el-icon :size="18"><View /></el-icon>
                            </el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" :label="t('cloud_upload_page.cols.type')" width="100">
                        <template #default="{ row }">{{ typeLabels[row.type] || row.type }}</template>
                    </el-table-column>
                    <el-table-column :label="t('cloud_upload_page.cols.public')" width="80" align="center">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.is_public"
                                size="small"
                                @change="(val) => handleToggleVisibility(row, val)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" :label="t('cloud_upload_page.cols.uploaded_at')" width="170">
                        <template #default="{ row }">{{ row.created_at?.slice(0, 16) }}</template>
                    </el-table-column>
                    <el-table-column :label="t('cloud_upload_page.cols.actions')" width="120">
                        <template #default="{row}">
                            <el-button link type="primary" size="small" @click="copyUrl(row)">{{ t('cloud_upload_page.copy_url') }}</el-button>
                            <el-button link type="danger" size="small" @click="handleDelete(row)">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 上传对话框 -->
                <el-dialog v-model="showUploadDialog" :title="t('cloud_upload_page.upload_dialog_title')" width="450px">
                    <el-form :model="uploadForm" label-width="80px">
                        <el-form-item :label="t('cloud_upload_page.form.file')">
                            <div class="upload-file-name">{{ selectedFile?.name }}</div>
                        </el-form-item>
                        <el-form-item :label="t('cloud_upload_page.form.type')">
                            <el-select v-model="uploadForm.type" style="width:100%">
                                <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('cloud_upload_page.form.public')">
                            <el-switch v-model="uploadForm.is_public" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showUploadDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="handleUpload" :loading="uploading">{{ t('actions.upload') }}</el-button>
                    </template>
                </el-dialog>

                <!-- 预览对话框 -->
                <el-dialog v-model="showPreviewDialog" :title="previewData?.filename || t('cloud_upload_page.preview_title')" width="800px" destroy-on-close>
                    <!-- 图片预览 -->
                    <div v-if="previewData?.preview_type === 'image'" style="text-align:center">
                        <el-image :src="previewData.url" style="max-width:100%;max-height:70vh" fit="contain" />
                    </div>
                    <!-- 音频预览 -->
                    <div v-else-if="previewData?.preview_type === 'audio'" style="text-align:center;padding:20px">
                        <audio :src="previewData.url" controls style="width:100%"></audio>
                    </div>
                    <!-- 视频预览 -->
                    <div v-else-if="previewData?.preview_type === 'video'" style="text-align:center">
                        <video :src="previewData.url" controls style="max-width:100%;max-height:70vh"></video>
                    </div>
                    <!-- PDF 预览 -->
                    <div v-else-if="previewData?.preview_type === 'pdf'" style="height:70vh">
                        <iframe :src="previewData.url" style="width:100%;height:100%;border:none"></iframe>
                    </div>
                    <!-- 文本预览 -->
                    <div v-else-if="previewData?.preview_type === 'text'" style="max-height:70vh;overflow:auto">
                        <pre v-if="previewText" style="white-space:pre-wrap;word-break:break-all;background:#f5f5f5;padding:16px;border-radius:4px;font-size:13px;line-height:1.6">{{ previewText }}</pre>
                        <div v-else style="text-align:center;padding:40px;color:#999">Loading…</div>
                    </div>
                    <!-- Office 文档 -->
                    <div v-else-if="previewData?.preview_type === 'office'" style="height:70vh;text-align:center;display:flex;align-items:center;justify-content:center;flex-direction:column">
                        <el-icon :size="48" style="color:#409EFF"><Document /></el-icon>
                        <p style="margin:16px 0;color:#666">{{ t('cloud_upload_page.office_no_preview') }}</p>
                        <el-button type="primary" @click="downloadPreviewFile">{{ t('cloud_upload_page.download') }}</el-button>
                    </div>
                    <!-- 下载回退 -->
                    <div v-else style="text-align:center;padding:40px">
                        <el-icon :size="48" style="color:#409EFF"><FolderOpened /></el-icon>
                        <p style="margin:16px 0;color:#666">{{ t('cloud_upload_page.no_preview') }}</p>
                        <el-button type="primary" @click="downloadPreviewFile">{{ t('cloud_upload_page.download') }}</el-button>
                    </div>
                    <template #footer>
                        <el-button @click="showPreviewDialog = false">{{ t('actions.close') }}</el-button>
                        <el-button type="primary" @click="downloadPreviewFile">{{ t('cloud_upload_page.download') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Picture, Document, View, FolderOpened } from '@element-plus/icons-vue';
import { getCloudUploadDashboard, getUploadedFiles, uploadFile, deleteUploadedFile, getFileUrl, getPreviewData, toggleFileVisibility } from '@/api/cloudUpload';

const { t } = useI18n();

const activeTab = ref('list');
const stats = ref({});
const files = ref([]);
const loading = ref(false);
const uploading = ref(false);
const filterType = ref('');
const showUploadDialog = ref(false);
const selectedFile = ref(null);
const uploadForm = reactive({ type: 'image', is_public: false });

// 预览状态
const showPreviewDialog = ref(false);
const previewData = ref(null);
const previewText = ref('');

const typeOptions = computed(() => [
    { value: 'image', label: t('cloud_upload_page.types.image') },
    { value: 'audio', label: t('cloud_upload_page.types.audio') },
    { value: 'video', label: t('cloud_upload_page.types.video') },
    { value: 'file', label: t('cloud_upload_page.types.file') },
    { value: 'document', label: t('cloud_upload_page.types.document') },
    { value: 'other', label: t('cloud_upload_page.types.other') },
]);

const typeLabels = computed(() => Object.fromEntries(typeOptions.value.map(o => [o.value, o.label])));

const typeStats = (type) => {
    const item = (stats.value.by_type || []).find(t => t.type === type);
    return item?.count || 0;
};

function handleFileSelect(file) {
    selectedFile.value = file.raw;
    uploadForm.type = 'image';
    uploadForm.is_public = false;
    showUploadDialog.value = true;
}

async function handleUpload() {
    if (!selectedFile.value) return;
    uploading.value = true;
    try {
        await uploadFile(selectedFile.value, uploadForm.type, uploadForm.is_public);
        ElMessage.success(t('cloud_upload_page.messages.upload_success'));
        showUploadDialog.value = false;
        loadFiles();
        loadDashboard();
    } catch (e) {
        ElMessage.error(t('cloud_upload_page.messages.upload_failed'));
    } finally {
        uploading.value = false;
    }
}

async function loadDashboard() {
    try { const r = await getCloudUploadDashboard(); stats.value = r.data.data; } catch (e) { console.error(e); }
}

async function loadFiles() {
    loading.value = true;
    try { const r = await getUploadedFiles({ type: filterType.value || undefined, per_page: 50 }); files.value = r.data.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}

async function copyUrl(row) {
    try {
        const r = await getFileUrl(row.id);
        await navigator.clipboard.writeText(r.data.data.url || row.url);
        ElMessage.success(t('cloud_upload_page.messages.url_copied'));
    } catch (e) {
        ElMessage.error(t('cloud_upload_page.messages.url_fetch_failed'));
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm(
            t('cloud_upload_page.messages.delete_confirm'),
            t('actions.confirm'),
            { type: 'warning', confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel') },
        );
        await deleteUploadedFile(row.id);
        ElMessage.success(t('cloud_upload_page.messages.deleted'));
        loadFiles();
        loadDashboard();
    } catch (e) { if (e !== 'cancel') ElMessage.error(t('cloud_upload_page.messages.delete_failed')); }
}

async function handlePreview(row) {
    try {
        const res = await getPreviewData(row.id);
        const data = res.data.data;
        previewData.value = data;
        previewText.value = '';

        if (data.preview_type === 'text') {
            if (data.text_content) {
                previewText.value = data.text_content;
            } else {
                previewText.value = t('cloud_upload_page.text_load_failed');
            }
        }

        showPreviewDialog.value = true;
    } catch (e) {
        ElMessage.error(t('cloud_upload_page.preview_load_failed'));
    }
}

function downloadPreviewFile() {
    if (previewData.value?.url) {
        window.open(previewData.value.url, '_blank');
    }
}

async function handleToggleVisibility(row, val) {
    try {
        await toggleFileVisibility(row.id);
        ElMessage.success(val ? t('cloud_upload_page.messages.made_public') : t('cloud_upload_page.messages.made_private'));
    } catch (e) {
        // Revert on failure
        row.is_public = !val;
        ElMessage.error(t('cloud_upload_page.messages.visibility_toggle_failed'));
    }
}

onMounted(() => { loadDashboard(); loadFiles(); });
</script>

<style scoped>
.cloud-upload-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #0f172a; }
.stat-value.info { color: #909399; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; }
.file-info { display: flex; align-items: center; gap: 12px; }
.file-name { font-weight: 500; font-size: 14px; }
.file-meta { font-size: 12px; color: #909399; margin-top: 2px; }
.upload-file-name { padding: 6px 0; color: #606266; }
</style>
