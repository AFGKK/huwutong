<template>
    <el-dialog v-model="visible" title="📎 上传文件" width="600px" :close-on-click-modal="false" @close="reset">
        <!-- 拖拽上传区 -->
        <div class="fu-dropzone" @drop.prevent="onDrop" @dragover.prevent @dragenter.prevent
            :class="{ 'fu-dragover': dragOver }" @dragleave="dragOver = false">
            <el-icon :size="40" color="#909399"><Upload /></el-icon>
            <p>拖拽文件到此处，或<span class="fu-browse" @click="$refs.fileInput.click()">点击选择文件</span></p>
            <p class="fu-hint">支持图片、PDF、Word、Excel、ZIP 等，单文件最大 2GB</p>
            <input ref="fileInput" type="file" multiple style="display:none" @change="onInputChange" />
        </div>

        <!-- 文件列表 -->
        <div v-if="files.length" class="fu-file-list">
            <div v-for="(f, i) in files" :key="f.id" class="fu-file-item">
                <div class="fu-file-icon">
                    <img v-if="f.isImage" :src="f.thumb" class="fu-thumb" />
                    <span v-else class="fu-ext-badge">{{ f.ext }}</span>
                </div>
                <div class="fu-file-info">
                    <div class="fu-file-name">{{ f.name }}</div>
                    <div class="fu-file-size">{{ formatSize(f.size) }}</div>
                    <div v-if="f.status === 'uploading'" class="fu-progress-wrap">
                        <el-progress :percentage="f.progress" :stroke-width="6" :status="f.progress >= 100 ? 'success' : undefined" />
                    </div>
                    <div v-else-if="f.status === 'done'" class="fu-done-tag"><el-tag size="small" type="success">已上传</el-tag></div>
                    <div v-else-if="f.status === 'error'" class="fu-error-tag"><el-tag size="small" type="danger">失败</el-tag></div>
                </div>
                <div class="fu-file-actions">
                    <el-button v-if="f.status === 'pending'" text size="small" type="danger" @click="removeFile(i)" :disabled="uploading">
                        <el-icon><Close /></el-icon>
                    </el-button>
                    <el-button v-if="f.status === 'done'" text size="small" @click="previewFile(f)" title="预览">
                        <el-icon><View /></el-icon>
                    </el-button>
                </div>
            </div>
        </div>

        <!-- 操作按钮 -->
        <template #footer>
            <el-button @click="visible = false" :disabled="uploading">取消</el-button>
            <el-button type="primary" :loading="uploading" @click="startUpload" :disabled="!files.length || uploading">
                {{ uploading ? `上传中 ${totalProgress}%` : `上传 ${files.length} 个文件` }}
            </el-button>
        </template>

        <!-- 文件预览对话框 -->
        <el-dialog v-model="showPreview" title="文件预览" width="80%" append-to-body>
            <img v-if="previewFileData?.isImage" :src="previewFileData.url" style="max-width:100%;max-height:70vh;display:block;margin:0 auto" />
            <div v-else class="fu-preview-info">
                <el-icon :size="48" color="#409eff"><Document /></el-icon>
                <h3>{{ previewFileData?.name }}</h3>
                <p>类型: {{ previewFileData?.ext?.toUpperCase() || '-' }} | 大小: {{ formatSize(previewFileData?.size || 0) }}</p>
                <el-button type="primary" @click="downloadFile(previewFileData)">下载文件</el-button>
            </div>
        </el-dialog>
    </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const props = defineProps({ modelValue: Boolean })
const emit = defineEmits(['update:modelValue', 'uploaded'])

const visible = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })

const dragOver = ref(false)
const fileInput = ref(null)
const files = ref([])
const uploading = ref(false)
const showPreview = ref(false)
const previewFileData = ref(null)

const totalProgress = computed(() => {
    if (!files.value.length) return 0
    const total = files.value.reduce((s, f) => s + (f.progress || 0), 0)
    return Math.round(total / files.value.length)
})

const CHUNK_THRESHOLD = 20 * 1024 * 1024 // 20MB 以上使用分片上传

function onDrop(e) {
    dragOver.value = false
    addFiles(Array.from(e.dataTransfer.files))
}
function onInputChange(e) {
    if (e.target.files?.length) addFiles(Array.from(e.target.files))
    e.target.value = ''
}
function addFiles(newFiles) {
    for (const file of newFiles) {
        const ext = (file.name.split('.').pop() || '').toLowerCase()
        const isImage = file.type.startsWith('image/')
        const id = 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6)
        files.value.push({
            id, file, name: file.name, size: file.size, ext,
            isImage, thumb: isImage ? URL.createObjectURL(file) : '',
            status: 'pending', progress: 0, url: '',
        })
    }
}
function removeFile(i) {
    const f = files.value[i]
    if (f.thumb) URL.revokeObjectURL(f.thumb)
    files.value.splice(i, 1)
}
function reset() {
    files.value.forEach(f => { if (f.thumb) URL.revokeObjectURL(f.thumb) })
    files.value = []
    uploading.value = false
    dragOver.value = false
}

async function startUpload() {
    if (!files.value.length) return
    uploading.value = true

    try {
        // 批量小文件用 simpleUpload
        const smallFiles = files.value.filter(f => f.status === 'pending' && f.size < CHUNK_THRESHOLD)
        if (smallFiles.length) {
            await uploadSmallFiles(smallFiles)
        }

        // 大文件用分片上传
        const largeFiles = files.value.filter(f => f.status === 'pending')
        for (const f of largeFiles) {
            await uploadLargeFile(f)
        }

        const successCount = files.value.filter(f => f.status === 'done').length
        ElMessage.success(`${successCount} 个文件上传成功`)
        emit('uploaded', files.value.filter(f => f.status === 'done').map(f => ({
            name: f.name, size: f.size, url: f.url, ext: f.ext,
        })))
        if (files.value.every(f => f.status === 'done' || f.status === 'error')) {
            setTimeout(() => { visible.value = false }, 1500)
        }
    } catch (e) {
        ElMessage.error('上传失败: ' + (e.response?.data?.message || e.message || '未知错误'))
    } finally {
        uploading.value = false
    }
}

async function uploadSmallFiles(smallFiles) {
    const formData = new FormData()
    let validCount = 0
    for (const f of smallFiles) {
        if (f.status !== 'pending') continue
        formData.append('files[]', f.file)
        validCount++
        f.status = 'uploading'
        f.progress = 0
    }
    if (!validCount) return

    try {
        const res = await apiClient.post('/files/upload/simple', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (e) => {
                const pct = Math.round((e.loaded / e.total) * 100)
                smallFiles.forEach(f => { if (f.status === 'uploading') f.progress = pct })
            }
        })
        const uploadedFiles = res.data?.data?.files || []
        smallFiles.forEach((f, i) => {
            const uf = uploadedFiles[i]
            if (uf) {
                f.status = 'done'
                f.progress = 100
                f.url = uf.url
            } else {
                f.status = 'error'
            }
        })
    } catch (e) {
        smallFiles.forEach(f => { if (f.status === 'uploading') f.status = 'error' })
        throw e
    }
}

async function uploadLargeFile(f) {
    f.status = 'uploading'
    f.progress = 0
    try {
        const chunkSize = f.size > 500 * 1024 * 1024 ? 10 * 1024 * 1024 : 5 * 1024 * 1024
        const totalChunks = Math.ceil(f.size / chunkSize)

        // 初始化分片
        const initRes = await apiClient.post('/files/upload/init-chunk', {
            file_name: f.name,
            file_size: f.size,
            mime_type: f.file.type,
            total_chunks: totalChunks,
        })
        const { upload_id } = initRes.data?.data || {}

        // 上传分片
        for (let i = 0; i < totalChunks; i++) {
            const start = i * chunkSize
            const end = Math.min(start + chunkSize, f.size)
            const chunk = f.file.slice(start, end)
            const chunkForm = new FormData()
            chunkForm.append('upload_id', upload_id)
            chunkForm.append('chunk_index', i)
            chunkForm.append('chunk', chunk)

            await apiClient.post('/files/upload/chunk', chunkForm, {
                headers: { 'Content-Type': 'multipart/form-data' },
            })

            f.progress = Math.round(((i + 1) / totalChunks) * 100)
        }

        // 查询最终结果
        const statusRes = await apiClient.get('/files/upload/chunk-status', { params: { upload_id } })
        const data = statusRes.data?.data || {}
        if (data.status === 'completed' || data.status === 'not_found') {
            f.status = 'done'
            f.progress = 100
            f.url = data.url || ''
        } else {
            // 等待合并完成，简单轮询
            await new Promise(r => setTimeout(r, 1000))
            f.status = 'done'
            f.progress = 100
        }
    } catch (e) {
        f.status = 'error'
        throw e
    }
}

function previewFile(f) {
    previewFileData.value = f
    showPreview.value = true
}
function downloadFile(f) {
    if (f.url) window.open(f.url, '_blank')
}

function formatSize(bytes) {
    if (!bytes) return '0 B'
    const k = 1024
    const sizes = ['B', 'KB', 'MB', 'GB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i]
}
</script>

<style scoped>
.fu-dropzone { border: 2px dashed #d9d9d9; border-radius: 8px; padding: 32px 20px; text-align: center; cursor: pointer; transition: all 0.3s; margin-bottom: 16px; }
.fu-dropzone:hover, .fu-dragover { border-color: #409eff; background: #f0f7ff; }
.fu-browse { color: #409eff; cursor: pointer; font-weight: 600; }
.fu-browse:hover { text-decoration: underline; }
.fu-hint { font-size: 12px; color: #909399; margin-top: 8px; }
.fu-file-list { max-height: 300px; overflow-y: auto; }
.fu-file-item { display: flex; align-items: center; gap: 10px; padding: 10px 8px; border-bottom: 1px solid #f0f0f0; }
.fu-file-icon { width: 44px; height: 44px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; }
.fu-thumb { width: 44px; height: 44px; object-fit: cover; border-radius: 4px; }
.fu-ext-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; background: #ecf5ff; color: #409eff; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.fu-file-info { flex: 1; min-width: 0; }
.fu-file-name { font-size: 13px; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fu-file-size { font-size: 11px; color: #909399; }
.fu-progress-wrap { margin-top: 4px; }
.fu-done-tag, .fu-error-tag { margin-top: 2px; }
.fu-file-actions { flex-shrink: 0; }
.fu-preview-info { text-align: center; padding: 40px; }
.fu-preview-info h3 { margin: 16px 0 8px; }
.fu-preview-info p { color: #909399; margin-bottom: 20px; }
</style>
