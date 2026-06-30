<template>
    <el-dialog
        v-model="visible"
        :title="uploadType === 'package' ? '上传安装包' : '上传截图'"
        width="500px"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-alert v-if="uploadedFile" type="success" :closable="false" class="mb-4">
            <template #title>
                <div>上传成功！</div>
                <div class="text-muted small">{{ uploadedFile.original_name }} ({{ formatSize(uploadedFile.size) }})</div>
            </template>
        </el-alert>

        <el-upload
            ref="uploadRef"
            drag
            :auto-upload="false"
            :limit="1"
            :accept="uploadType === 'package' ? '.apk,.ipa,.AppImage,.appimage,.zip' : '.jpg,.jpeg,.png,.webp'"
            :on-change="handleFileChange"
            :file-list="fileList"
        >
            <el-icon class="upload-icon" :size="40"><UploadFilled /></el-icon>
            <div class="upload-text" v-if="uploadType === 'package'">
                将 APK / IPA / AppImage 安装包拖拽到此处，或 <em>点击选择文件</em>
            </div>
            <div class="upload-text" v-else>
                将应用截图拖拽到此处，或 <em>点击选择文件</em>
            </div>
            <template #tip>
                <div class="upload-tip" v-if="uploadType === 'package'">
                    支持格式：APK / IPA / AppImage，最大 500MB
                </div>
                <div class="upload-tip" v-else>
                    支持格式：JPG / PNG / WebP，最大 5MB
                </div>
            </template>
        </el-upload>

        <div v-if="uploadResult" class="result-box">
            <div class="result-label">文件 URL：</div>
            <el-input :model-value="uploadResult.url" readonly>
                <template #append>
                    <el-button @click="copyUrl(uploadResult.url)">复制</el-button>
                </template>
            </el-input>
        </div>

        <template #footer>
            <el-button @click="visible = false">关闭</el-button>
            <el-button v-if="!uploadedFile" type="primary" :loading="uploading" @click="startUpload">
                {{ selectedFile ? '开始上传' : '请选择文件' }}
            </el-button>
            <el-button v-else type="primary" @click="confirmResult">
                使用此文件
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup>
import { ref } from 'vue';
import { ElMessage } from 'element-plus';
import { UploadFilled } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';

const props = defineProps({
    uploadType: { type: String, default: 'package' }, // package / screenshot
});

const emit = defineEmits(['confirm']);

const visible = ref(false);
const uploadRef = ref(null);
const uploading = ref(false);
const selectedFile = ref(null);
const uploadedFile = ref(null);
const uploadResult = ref(null);
const fileList = ref([]);

function open() {
    visible.value = true;
    resetForm();
}

function resetForm() {
    selectedFile.value = null;
    uploadedFile.value = null;
    uploadResult.value = null;
    fileList.value = [];
    if (uploadRef.value) uploadRef.value.clearFiles();
}

function handleFileChange(file) {
    selectedFile.value = file.raw;
    uploadResult.value = null;
    uploadedFile.value = null;
}

async function startUpload() {
    if (!selectedFile.value) {
        ElMessage.warning('请先选择文件');
        return;
    }

    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);

        const endpoint = props.uploadType === 'package' ? 'uploadPackage' : 'uploadScreenshot';
        const { data: res } = await api[endpoint](formData);

        if (res.success) {
            uploadedFile.value = res.data;
            uploadResult.value = res.data;
            ElMessage.success('上传成功');
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '上传失败');
    } finally {
        uploading.value = false;
    }
}

function confirmResult() {
    if (uploadResult.value) {
        emit('confirm', uploadResult.value);
        visible.value = false;
    }
}

function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        ElMessage.success('已复制到剪贴板');
    });
}

function formatSize(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let size = bytes;
    while (size >= 1024 && i < units.length - 1) { size /= 1024; i++; }
    return size.toFixed(1) + ' ' + units[i];
}

defineExpose({ open });
</script>

<style scoped>
.upload-icon { margin-bottom: 8px; }
.upload-text { font-size: 14px; color: #606266; }
.upload-text em { color: #409eff; font-style: normal; }
.upload-tip { font-size: 12px; color: #909399; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.small { font-size: 12px; }
.text-muted { color: #909399; }
.result-box { margin-top: 16px; padding: 12px; background: #f5f7fa; border-radius: 6px; }
.result-label { font-size: 13px; color: #606266; margin-bottom: 6px; }
</style>
