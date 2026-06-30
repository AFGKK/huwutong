<template>
    <div class="cloud-upload-page">
        <h2>云文件存储</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total_files || 0 }}</div><div class="stat-label">总文件数</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.total_size_mb || 0 }} MB</div><div class="stat-label">总存储量</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ typeStats('logo') }}</div><div class="stat-label">Logo文件</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ typeStats('brand_asset') }}</div><div class="stat-label">品牌素材</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 文件列表 -->
            <el-tab-pane label="文件列表" name="list">
                <div class="toolbar">
                    <el-upload
                        :auto-upload="false"
                        :show-file-list="false"
                        :on-change="handleFileSelect"
                        accept="image/*,.pdf,.doc,.docx"
                    >
                        <el-button type="primary"><el-icon><Plus /></el-icon> 上传文件</el-button>
                    </el-upload>
                    <el-select v-model="filterType" placeholder="类型筛选" clearable style="width:140px;margin-left:12px" @change="loadFiles">
                        <el-option label="Logo" value="logo" />
                        <el-option label="品牌素材" value="brand_asset" />
                        <el-option label="文档" value="document" />
                        <el-option label="截图" value="screenshot" />
                        <el-option label="其他" value="other" />
                    </el-select>
                    <el-button @click="loadFiles" style="margin-left:8px">刷新</el-button>
                </div>

                <el-table :data="files" v-loading="loading" stripe>
                    <el-table-column label="文件" min-width="300">
                        <template #default="{row}">
                            <div class="file-info">
                                <el-icon v-if="row.mime_type?.startsWith('image')" :size="32" color="#409eff"><Picture /></el-icon>
                                <el-icon v-else :size="32" color="#909399"><Document /></el-icon>
                                <div class="file-detail">
                                    <div class="file-name">{{ row.original_name }}</div>
                                    <div class="file-meta">{{ (row.file_size / 1024).toFixed(1) }} KB · {{ row.type }}</div>
                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column label="预览" width="100">
                        <template #default="{row}">
                            <el-image v-if="row.thumbnail_url" :src="row.thumbnail_url" style="width:50px;height:50px;border-radius:4px" fit="cover" />
                            <span v-else style="color:#c0c4cc">-</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="type" label="类型" width="100" />
                    <el-table-column label="公开" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_public?'success':'info'" size="small">{{ row.is_public?'是':'否' }}</el-tag></template></el-table-column>
                    <el-table-column prop="created_at" label="上传时间" width="170" />
                    <el-table-column label="操作" width="120">
                        <template #default="{row}">
                            <el-button link type="primary" size="small" @click="copyUrl(row)">复制URL</el-button>
                            <el-button link type="danger" size="small" @click="handleDelete(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 上传对话框 -->
                <el-dialog v-model="showUploadDialog" title="上传文件" width="450px">
                    <el-form :model="uploadForm" label-width="80px">
                        <el-form-item label="文件">
                            <div class="upload-file-name">{{ selectedFile?.name }}</div>
                        </el-form-item>
                        <el-form-item label="类型">
                            <el-select v-model="uploadForm.type" style="width:100%">
                                <el-option label="Logo" value="logo" />
                                <el-option label="品牌素材" value="brand_asset" />
                                <el-option label="文档" value="document" />
                                <el-option label="截图" value="screenshot" />
                                <el-option label="其他" value="other" />
                            </el-select>
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showUploadDialog = false">取消</el-button>
                        <el-button type="primary" @click="handleUpload" :loading="uploading">上传</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Picture, Document } from '@element-plus/icons-vue';
import { getCloudUploadDashboard, getUploadedFiles, uploadFile, deleteUploadedFile, getFileUrl } from '@/api/cloudUpload';

const activeTab = ref('list');
const stats = ref({});
const files = ref([]);
const loading = ref(false);
const uploading = ref(false);
const filterType = ref('');
const showUploadDialog = ref(false);
const selectedFile = ref(null);
const uploadForm = reactive({ type: 'logo' });

const typeStats = (type) => {
    const item = (stats.value.by_type || []).find(t => t.type === type);
    return item?.count || 0;
};

function handleFileSelect(file) {
    selectedFile.value = file.raw;
    showUploadDialog.value = true;
}

async function handleUpload() {
    if (!selectedFile.value) return;
    uploading.value = true;
    try {
        await uploadFile(selectedFile.value, uploadForm.type);
        ElMessage.success('上传成功');
        showUploadDialog.value = false;
        loadFiles();
        loadDashboard();
    } catch (e) {
        ElMessage.error('上传失败');
    } finally {
        uploading.value = false;
    }
}

async function loadDashboard() {
    try { stats.value = await getCloudUploadDashboard(); } catch (e) { console.error(e); }
}

async function loadFiles() {
    loading.value = true;
    try { const r = await getUploadedFiles({ type: filterType.value || undefined, per_page: 50 }); files.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}

async function copyUrl(row) {
    try {
        const r = await getFileUrl(row.id);
        await navigator.clipboard.writeText(r.url || row.url);
        ElMessage.success('URL已复制');
    } catch (e) {
        ElMessage.error('获取URL失败');
    }
}

async function handleDelete(row) {
    try {
        await ElMessageBox.confirm('确定删除此文件？', '确认', { type: 'warning' });
        await deleteUploadedFile(row.id);
        ElMessage.success('已删除');
        loadFiles();
        loadDashboard();
    } catch (e) { if (e !== 'cancel') ElMessage.error('删除失败'); }
}

onMounted(() => { loadDashboard(); loadFiles(); });
</script>

<style scoped>
.cloud-upload-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
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
