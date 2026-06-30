<template>
    <div class="feedback-widget">
        <!-- 浮动按钮 -->
        <el-button
            v-if="!expanded"
            class="feedback-fab"
            type="primary"
            circle
            size="large"
            @click="expanded = true"
            title="反馈意见"
        >
            <el-icon><ChatDotRound /></el-icon>
        </el-button>

        <!-- 反馈面板 -->
        <transition name="slide-up">
            <el-card v-if="expanded" class="feedback-panel" shadow="xl">
                <template #header>
                    <div class="panel-header">
                        <span>发送反馈</span>
                        <el-button text @click="closePanel">
                            <el-icon><Close /></el-icon>
                        </el-button>
                    </div>
                </template>

                <el-form :model="form" ref="formRef" label-position="top" size="small">
                    <el-form-item label="反馈类型" prop="type">
                        <el-select v-model="form.type" style="width:100%">
                            <el-option label="问题反馈" value="bug" />
                            <el-option label="功能建议" value="feature_request" />
                            <el-option label="改进意见" value="improvement" />
                            <el-option label="一般反馈" value="general" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="标题" prop="title" v-if="form.type !== 'general'">
                        <el-input v-model="form.title" placeholder="简要描述" maxlength="200" />
                    </el-form-item>
                    <el-form-item label="详细描述" prop="description" :rules="[{ required: true, message: '请描述您的反馈' }]">
                        <el-input v-model="form.description" type="textarea" :rows="4" placeholder="请详细描述..." maxlength="2000" />
                    </el-form-item>
                    <el-form-item label="截图" v-if="allowScreenshot">
                        <div class="screenshot-upload">
                            <el-upload
                                :auto-upload="false"
                                :limit="3"
                                list-type="picture-card"
                                :on-change="handleScreenshotChange"
                                :on-remove="handleScreenshotRemove"
                            >
                                <el-icon><Plus /></el-icon>
                            </el-upload>
                        </div>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="submitFeedback" :loading="submitting" style="width:100%">
                            提交反馈
                        </el-button>
                    </el-form-item>
                </el-form>

                <div class="feedback-footer">
                    <span class="footer-text">帮助我们改进产品</span>
                </div>
            </el-card>
        </transition>

        <!-- 成功提示 -->
        <el-dialog v-model="showSuccess" title="感谢您的反馈" width="360px" :close-on-click-modal="false">
            <div style="text-align:center;padding:20px">
                <el-icon :size="48" color="#67c23a"><CircleCheck /></el-icon>
                <p style="margin-top:12px;font-size:15px">感谢您的反馈！我们会认真评估。</p>
            </div>
            <template #footer>
                <el-button type="primary" @click="showSuccess = false; closePanel()">知道了</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { ChatDotRound, Close, Plus, CircleCheck } from '@element-plus/icons-vue';
import { ElMessage } from 'element-plus';

const props = defineProps({
    apiBaseUrl: { type: String, default: '/api' },
    allowScreenshot: { type: Boolean, default: true },
});

const expanded = ref(false);
const submitting = ref(false);
const showSuccess = ref(false);
const formRef = ref(null);
const screenshots = ref([]);

const form = reactive({
    type: 'general',
    title: '',
    description: '',
});

function closePanel() {
    expanded.value = false;
    form.type = 'general';
    form.title = '';
    form.description = '';
    screenshots.value = [];
}

function handleScreenshotChange(uploadFile) {
    screenshots.value.push(uploadFile.raw);
}

function handleScreenshotRemove() {
    // handled by el-upload
}

async function submitFeedback() {
    if (!form.description.trim()) {
        ElMessage.warning('请描述您的反馈');
        return;
    }
    submitting.value = true;

    try {
        // Collect context
        const context = {
            url: window.location.href,
            user_agent: navigator.userAgent,
            browser: getBrowser(),
            os: getOS(),
            screen_resolution: `${screen.width}x${screen.height}`,
            app_version: document.querySelector('meta[name="app-version"]')?.content || '',
        };

        const formData = new FormData();
        formData.append('type', form.type);
        formData.append('title', form.title);
        formData.append('description', form.description);
        formData.append('source', 'widget');
        formData.append('context', JSON.stringify(context));

        screenshots.value.forEach((file, i) => {
            formData.append(`screenshots[${i}]`, file);
        });

        const response = await fetch(`${props.apiBaseUrl}/feedback`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
        });

        if (response.ok) {
            showSuccess.value = true;
            screenshots.value = [];
        } else {
            ElMessage.error('提交失败，请稍后重试');
        }
    } catch (e) {
        ElMessage.error('提交失败，请检查网络');
    } finally {
        submitting.value = false;
    }
}

function getBrowser() {
    const ua = navigator.userAgent;
    if (ua.includes('Chrome')) return 'Chrome';
    if (ua.includes('Firefox')) return 'Firefox';
    if (ua.includes('Safari')) return 'Safari';
    if (ua.includes('Edge')) return 'Edge';
    return 'Unknown';
}

function getOS() {
    const ua = navigator.userAgent;
    if (ua.includes('Windows')) return 'Windows';
    if (ua.includes('Mac')) return 'macOS';
    if (ua.includes('Linux')) return 'Linux';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iOS')) return 'iOS';
    return 'Unknown';
}
</script>

<style scoped>
.feedback-widget {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
}
.feedback-fab {
    width: 56px;
    height: 56px;
    font-size: 24px;
    box-shadow: 0 4px 14px rgba(64, 158, 255, 0.4);
}
.feedback-panel {
    width: 380px;
    max-height: 600px;
    overflow-y: auto;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}
.feedback-footer {
    text-align: center;
    margin-top: 8px;
}
.footer-text {
    font-size: 11px;
    color: #c0c4cc;
}
.screenshot-upload :deep(.el-upload--picture-card) {
    width: 80px;
    height: 80px;
    line-height: 80px;
}
.slide-up-enter-active, .slide-up-leave-active {
    transition: all 0.3s ease;
}
.slide-up-enter-from, .slide-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
}
</style>
