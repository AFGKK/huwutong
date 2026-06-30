<template>
    <el-dialog v-model="visible" title="📄 文件预览" :width="previewType === 'image' ? 'auto' : '80%'"
        :close-on-click-modal="false" top="5vh" destroy-on-close @closed="onClosed">
        <!-- 加载中 -->
        <div v-if="loading" class="fp-loading"><el-icon :size="32" class="is-loading"><Loading /></el-icon><p>加载中...</p></div>
        <!-- 错误 -->
        <div v-else-if="error" class="fp-error">
            <el-icon :size="48" color="#f56c6c"><WarningFilled /></el-icon>
            <p>{{ error }}</p>
        </div>
        <!-- 图片预览 -->
        <img v-else-if="previewType === 'image'" :src="fileUrl" class="fp-image" />
        <!-- PDF 预览 -->
        <iframe v-else-if="previewType === 'pdf'" :src="pdfViewerUrl" class="fp-iframe" frameborder="0"></iframe>
        <!-- Office 文档预览 -->
        <iframe v-else-if="previewType === 'office'" :src="officeViewerUrl" class="fp-iframe" frameborder="0"></iframe>
        <!-- 视频预览 -->
        <video v-else-if="previewType === 'video'" :src="fileUrl" class="fp-media" controls playsinline></video>
        <!-- 音频预览 -->
        <audio v-else-if="previewType === 'audio'" :src="fileUrl" class="fp-audio" controls></audio>
        <!-- 文本预览 -->
        <div v-else-if="previewType === 'text'" class="fp-text">
            <pre>{{ textContent }}</pre>
        </div>
        <!-- 未知类型 -->
        <div v-else class="fp-unknown">
            <el-icon :size="48" color="#909399"><Document /></el-icon>
            <h3>{{ fileName }}</h3>
            <p class="fp-meta">类型: {{ ext?.toUpperCase() || '-' }} | 大小: {{ formatSize }}</p>
            <el-button type="primary" @click="downloadFile">下载文件</el-button>
        </div>

        <template #footer>
            <div class="fp-footer">
                <span class="fp-file-name">{{ fileName }}</span>
                <span class="fp-file-size">{{ formatSize }}</span>
                <el-button text @click="downloadFile">
                    <el-icon><Download /></el-icon> 下载
                </el-button>
                <el-button @click="visible = false">关闭</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { ElMessage } from 'element-plus'
import apiClient from '@/utils/request'

const props = defineProps({
    modelValue: Boolean,
    fileUrl: { type: String, default: '' },
    fileName: { type: String, default: '' },
    fileSize: { type: Number, default: 0 },
    fileMime: { type: String, default: '' },
    ext: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue'])

const visible = computed({
    get: () => props.modelValue,
    set: v => emit('update:modelValue', v),
})

const loading = ref(false)
const error = ref('')
const textContent = ref('')
const previewType = ref('')

const formatSize = computed(() => {
    if (!props.fileSize) return '-'
    const sizes = ['B', 'KB', 'MB', 'GB']
    let i = 0
    let s = props.fileSize
    while (s >= 1024 && i < sizes.length - 1) { s /= 1024; i++ }
    return s.toFixed(i > 0 ? 1 : 0) + ' ' + sizes[i]
})

const pdfViewerUrl = computed(() => {
    // 使用浏览器内置 PDF 查看器或 PDF.js
    return props.fileUrl
})

const officeViewerUrl = computed(() => {
    return `https://view.officeapps.live.com/op/view.aspx?src=${encodeURIComponent(props.fileUrl)}`
})

function detectPreviewType() {
    const ext = props.ext?.toLowerCase() || ''
    const imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp']
    const videoExts = ['mp4', 'webm', 'mov', 'avi']
    const audioExts = ['mp3', 'wav', 'ogg', 'aac', 'm4a']
    const officeExts = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp']
    const textExts = ['txt', 'csv', 'json', 'xml', 'md', 'log', 'yaml', 'yml']

    if (imageExts.includes(ext)) return 'image'
    if (['pdf'].includes(ext)) return 'pdf'
    if (videoExts.includes(ext)) return 'video'
    if (audioExts.includes(ext)) return 'audio'
    if (officeExts.includes(ext)) return 'office'
    if (textExts.includes(ext)) return 'text'
    return null
}

async function loadPreview() {
    loading.value = true
    error.value = ''
    textContent.value = ''
    previewType.value = detectPreviewType()

    // 文本类型：加载内容
    if (previewType.value === 'text') {
        try {
            const res = await fetch(props.fileUrl)
            textContent.value = await res.text()
        } catch (e) {
            error.value = '无法加载文件内容'
        }
    }

    loading.value = false
}

function downloadFile() {
    if (props.fileUrl) {
        const a = document.createElement('a')
        a.href = props.fileUrl
        a.download = props.fileName || 'download'
        a.click()
    }
}

function onClosed() {
    textContent.value = ''
    error.value = ''
}

watch(() => props.modelValue, (v) => {
    if (v) loadPreview()
})
</script>

<style scoped>
.fp-loading, .fp-error, .fp-unknown {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    color: #909399;
    gap: 12px;
}
.fp-image {
    max-width: 100%;
    max-height: 75vh;
    display: block;
    margin: 0 auto;
}
.fp-iframe {
    width: 100%;
    height: 75vh;
    border: none;
}
.fp-media {
    max-width: 100%;
    max-height: 75vh;
    display: block;
    margin: 0 auto;
}
.fp-audio {
    width: 100%;
    margin: 40px 0;
}
.fp-text {
    max-height: 70vh;
    overflow: auto;
    background: #f5f7fa;
    border-radius: 8px;
    padding: 16px;
}
.fp-text pre {
    margin: 0;
    white-space: pre-wrap;
    word-break: break-all;
    font-size: 13px;
    line-height: 1.6;
    font-family: 'Courier New', monospace;
}
.fp-footer {
    display: flex;
    align-items: center;
    gap: 12px;
}
.fp-file-name {
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
    color: #303133;
}
.fp-file-size {
    color: #909399;
    font-size: 13px;
}
</style>
