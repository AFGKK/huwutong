<template>
    <div class="file-storage-page">
        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_files }}</div>
                    <div class="stat-label">{{ t('files_page.stat_total_files') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total_size_formatted || '0 B' }}</div>
                    <div class="stat-label">{{ t('files_page.stat_total_size') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.by_category?.invoice?.count || 0 }}</div>
                    <div class="stat-label">{{ t('files_page.stat_invoice_files') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.by_category?.contract?.count || 0 }}</div>
                    <div class="stat-label">{{ t('files_page.stat_contract_files') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 操作栏 -->
        <el-card class="search-card">
            <el-row :gutter="16">
                <el-col :span="5">
                    <el-input v-model="filters.search" :placeholder="t('files_page.search_ph')" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.category" :placeholder="t('files_page.category_ph')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="opt in categoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-input v-model="filters.customer_id" :placeholder="t('files_page.customer_id_ph')" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="11" style="text-align: right">
                    <el-button type="primary" @click="showUploadDialog">
                        <el-icon><Upload /></el-icon> {{ t('actions.upload') }}
                    </el-button>
                    <el-button @click="loadList">{{ t('files_page.refresh') }}</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 文件列表 -->
        <el-card class="table-card">
            <el-table :data="list" v-loading="loading" border stripe style="width: 100%">
                <el-table-column prop="original_name" :label="t('files_page.col_filename')" min-width="250">
                    <template #default="{ row }">
                        <div class="file-name-cell">
                            <el-icon :size="18" class="file-icon"><Document /></el-icon>
                            <span>{{ row.original_name }}</span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_size')" width="100">
                    <template #default="{ row }">{{ row.formattedSize?.() || formatBytes(row.file_size) }}</template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_type')" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.file_extension?.toUpperCase() || '-' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_category')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="categoryTag(row.category)" size="small">{{ categoryLabel(row.category) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_customer')" min-width="130">
                    <template #default="{ row }">{{ row.customer?.name || t('files_page.customer_fallback', { id: row.customer_id }) }}</template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_uploader')" width="120">
                    <template #default="{ row }">{{ row.uploader?.name || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_uploaded_at')" width="170">
                    <template #default="{ row }">{{ row.uploaded_at }}</template>
                </el-table-column>
                <el-table-column :label="t('files_page.col_actions')" width="240" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="handleDownload(row)">{{ t('actions.download') }}</el-button>
                        <el-button size="small" @click="showDetail(row)">{{ t('files_page.btn_detail') }}</el-button>
                        <el-button size="small" @click="showShareDialog(row)">{{ t('actions.share') }}</el-button>
                        <el-popconfirm :title="t('files_page.delete_confirm')" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 上传对话框 -->
        <el-dialog v-model="uploadDialogVisible" :title="t('files_page.upload_dialog_title')" width="500px" :close-on-click-modal="false">
            <el-form ref="uploadFormRef" :model="uploadForm" :rules="uploadRules" label-width="100px">
                <el-form-item :label="t('files_page.select_file')" prop="file">
                    <el-upload
                        ref="uploadRef"
                        :auto-upload="false"
                        :limit="1"
                        :on-change="onFileChange"
                        drag
                    >
                        <el-icon class="el-icon--upload"><upload-filled /></el-icon>
                        <div class="el-upload__text">{{ t('files_page.upload_drag') }} <em>{{ t('files_page.upload_click') }}</em></div>
                    </el-upload>
                </el-form-item>
                <el-form-item :label="t('files_page.customer_id')" prop="customer_id">
                    <el-input-number v-model="uploadForm.customer_id" :min="1" style="width: 200px" />
                </el-form-item>
                <el-form-item :label="t('files_page.category_ph')">
                    <el-select v-model="uploadForm.category" style="width: 200px">
                        <el-option v-for="opt in categoryOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('files_page.description')">
                    <el-input v-model="uploadForm.description" type="textarea" :rows="2" maxlength="1000" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="uploadDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="uploading" @click="submitUpload">{{ t('actions.upload') }}</el-button>
            </template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="detailDialogVisible" :title="t('files_page.detail_dialog_title')" width="600px">
            <template v-if="detail">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t('files_page.label_filename')" :span="2">{{ detail.original_name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_size')">{{ formatBytes(detail.file_size) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_mime_type')">{{ detail.mime_type }}</el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_category')">
                        <el-tag :type="categoryTag(detail.category)" size="small">{{ categoryLabel(detail.category) }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_visibility')">
                        <el-tag :type="detail.visibility === 'public' ? 'success' : 'info'" size="small">
                            {{ detail.visibility === 'public' ? t('files_page.visibility_public') : t('files_page.visibility_private') }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_uploader')">{{ detail.uploader?.name || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_uploaded_at')">{{ detail.uploaded_at }}</el-descriptions-item>
                    <el-descriptions-item :label="t('files_page.label_storage_path')" :span="2">
                        <el-tooltip :content="detail.storage_path" placement="top">
                            <span class="path-text">{{ detail.storage_path }}</span>
                        </el-tooltip>
                    </el-descriptions-item>
                </el-descriptions>

                <el-divider>{{ t('files_page.share_links_title') }}</el-divider>
                <el-table v-if="detail.share_links?.length" :data="detail.share_links" border size="small">
                    <el-table-column prop="token" :label="t('files_page.col_token')" width="200">
                        <template #default="{ row }">
                            <code class="token-text">{{ row.token.substring(0, 16) }}...</code>
                        </template>
                    </el-table-column>
                    <el-table-column prop="is_active" :label="t('files_page.col_status')" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                {{ row.is_active ? t('files_page.share_active') : t('files_page.share_revoked') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="download_count" :label="t('files_page.col_downloads')" width="60" />
                    <el-table-column :label="t('files_page.col_actions')" width="80">
                        <template #default="{ row }">
                            <el-button v-if="row.is_active" size="small" type="danger" @click="handleRevokeShare(row)">{{ t('files_page.btn_revoke') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-else :description="t('files_page.no_share_links')" />
            </template>
        </el-dialog>

        <!-- 分享对话框 -->
        <el-dialog v-model="shareDialogVisible" :title="t('files_page.share_dialog_title')" width="450px">
            <el-form :model="shareForm" label-width="120px">
                <el-form-item :label="t('files_page.access_password')">
                    <el-input v-model="shareForm.password" :placeholder="t('files_page.password_optional_ph')" show-password />
                </el-form-item>
                <el-form-item :label="t('files_page.expires_at')">
                    <el-date-picker v-model="shareForm.expires_at" type="datetime" :placeholder="t('files_page.expires_optional_ph')" style="width: 100%" />
                </el-form-item>
                <el-form-item :label="t('files_page.max_downloads')">
                    <el-input-number v-model="shareForm.max_downloads" :min="0" :max="1000" :placeholder="t('files_page.max_downloads_hint')" style="width: 200px" />
                </el-form-item>
            </el-form>
            <template v-if="shareResult" style="margin-top: 16px">
                <el-alert type="success" show-icon>
                    <template #title>
                        <div class="share-result">
                            <div>{{ t('files_page.share_link_created') }}</div>
                            <div class="share-url">
                                <code>{{ shareResult.url }}</code>
                                <el-button size="small" @click="copyShareUrl">{{ t('actions.copy') }}</el-button>
                            </div>
                        </div>
                    </template>
                </el-alert>
            </template>
            <template #footer>
                <el-button @click="shareDialogVisible = false">{{ t('actions.close') }}</el-button>
                <el-button v-if="!shareResult" type="primary" :loading="sharing" @click="submitShare">{{ t('files_page.generate_link') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Upload, Document, UploadFilled } from '@element-plus/icons-vue'
import {
    getFileList,
    getFileStats,
    getFileDetail,
    uploadFile,
    deleteFile,
    downloadFile,
    createShareLink,
    revokeShareLink,
} from '@/api/files'

const { t } = useI18n()

const loading = ref(false)
const stats = ref({ total_files: 0, total_size_formatted: '0 B', by_category: {} })
const list = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const total = ref(0)

const filters = ref({ search: '', category: '', customer_id: '' })

// 上传
const uploadDialogVisible = ref(false)
const uploadFormRef = ref(null)
const uploadRef = ref(null)
const uploading = ref(false)
const uploadForm = ref({ file: null, customer_id: null, category: 'other', description: '' })

const uploadRules = computed(() => ({
    customer_id: [{ required: true, message: t('files_page.customer_id_required'), trigger: 'blur' }],
}))

// 详情
const detailDialogVisible = ref(false)
const detail = ref(null)

// 分享
const shareDialogVisible = ref(false)
const shareForm = ref({ password: '', expires_at: null, max_downloads: 0 })
const sharing = ref(false)
const shareResult = ref(null)
const currentShareFile = ref(null)

const categoryKeys = ['invoice', 'receipt', 'contract', 'attachment', 'other']
const categoryTags = { invoice: 'primary', receipt: 'success', contract: 'warning', attachment: '', other: 'info' }

const categoryLabels = computed(() => Object.fromEntries(
    categoryKeys.map((key) => [key, t(`files_page.cat_${key}`)]),
))

const categoryOptions = computed(() => categoryKeys.map((value) => ({
    value,
    label: t(`files_page.cat_${value}`),
})))

function categoryLabel(c) { return categoryLabels.value[c] || c }
function categoryTag(c) { return categoryTags[c] || 'info' }

function formatBytes(bytes) {
    if (!bytes) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB']
    let size = bytes
    for (let i = 0; size >= 1024 && i < units.length - 1; i++) size /= 1024
    return size.toFixed(2) + ' ' + units[i]
}

async function loadStats() {
    try {
        const res = await getFileStats()
        stats.value = res.data || res
    } catch { /* ignore */ }
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value, ...filters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getFileList(params)
        list.value = res.data?.data || res.data || []
        total.value = res.data?.total || res.total || 0
    } catch { ElMessage.error(t('messages.load_failed')) }
    finally { loading.value = false }
}

function showUploadDialog() {
    uploadForm.value = { file: null, customer_id: null, category: 'other', description: '' }
    uploadRef.value?.clearFiles()
    uploadDialogVisible.value = true
}

function onFileChange(uploadFile) {
    if (uploadFile.raw) {
        uploadForm.value.file = uploadFile.raw
    }
}

async function submitUpload() {
    const valid = await uploadFormRef.value.validate().catch(() => false)
    if (!valid || !uploadForm.value.file) {
        ElMessage.warning(t('files_page.select_file_required'))
        return
    }
    uploading.value = true
    try {
        const fd = new FormData()
        fd.append('file', uploadForm.value.file)
        fd.append('customer_id', uploadForm.value.customer_id)
        fd.append('category', uploadForm.value.category)
        if (uploadForm.value.description) fd.append('description', uploadForm.value.description)
        await uploadFile(fd)
        ElMessage.success(t('files_page.upload_ok'))
        uploadDialogVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('files_page.upload_failed'))
    } finally {
        uploading.value = false
    }
}

async function showDetail(row) {
    try {
        const res = await getFileDetail(row.id)
        detail.value = res.data || res
        detailDialogVisible.value = true
    } catch { ElMessage.error(t('files_page.detail_load_failed')) }
}

async function handleDownload(row) {
    try {
        const res = await downloadFile(row.id)
        const data = res.data || res
        window.open(data.url, '_blank')
    } catch { ElMessage.error(t('files_page.download_link_failed')) }
}

async function handleDelete(row) {
    try {
        await deleteFile(row.id)
        ElMessage.success(t('files_page.deleted_ok'))
        loadList()
        loadStats()
    } catch { ElMessage.error(t('files_page.delete_failed')) }
}

function showShareDialog(row) {
    currentShareFile.value = row
    shareForm.value = { password: '', expires_at: null, max_downloads: 0 }
    shareResult.value = null
    shareDialogVisible.value = true
}

async function submitShare() {
    sharing.value = true
    try {
        const data = {}
        if (shareForm.value.password) data.password = shareForm.value.password
        if (shareForm.value.expires_at) data.expires_at = shareForm.value.expires_at.toISOString()
        if (shareForm.value.max_downloads > 0) data.max_downloads = shareForm.value.max_downloads
        const res = await createShareLink(currentShareFile.value.id, data)
        const link = res.data || res
        shareResult.value = {
            url: window.location.origin + '/api/shared-file/' + link.token,
            token: link.token,
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('files_page.share_create_failed'))
    } finally {
        sharing.value = false
    }
}

function copyShareUrl() {
    if (shareResult.value?.url) {
        navigator.clipboard.writeText(shareResult.value.url)
        ElMessage.success(t('files_page.copied_ok'))
    }
}

async function handleRevokeShare(link) {
    try {
        await revokeShareLink(detail.value.id, link.id)
        ElMessage.success(t('files_page.share_revoked_ok'))
        showDetail({ id: detail.value.id })
    } catch { ElMessage.error(t('files_page.revoke_failed')) }
}

onMounted(() => {
    loadStats()
    loadList()
})
</script>

<style scoped>
.file-storage-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; margin-bottom: 10px; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.search-card { margin-bottom: 16px; }
.table-card { margin-bottom: 20px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.file-name-cell { display: flex; align-items: center; gap: 6px; }
.file-name-cell .file-icon { flex-shrink: 0; color: #0f172a; }
.path-text { max-width: 200px; display: inline-block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.token-text { font-size: 12px; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; }
.share-result { line-height: 2; }
.share-url { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
.share-url code { font-size: 12px; word-break: break-all; background: #f5f7fa; padding: 4px 8px; border-radius: 3px; flex: 1; }
</style>
