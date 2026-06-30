<script setup>
import { ref, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import axios from 'axios'
import api from '@/api/feedback.js'

const visible = ref(false)
const loading = ref(false)
const form = ref({
    type: 'general',
    subject: '',
    message: '',
    page_url: window.location.href,
    page_title: document.title,
    screen_resolution: `${window.screen?.width || ''}x${window.screen?.height || ''}`,
})

const typeOptions = [
    { value: 'general', label: '一般反馈' },
    { value: 'bug', label: '报告 Bug' },
    { value: 'feature_request', label: '功能建议' },
    { value: 'ui_ux', label: 'UI/UX 建议' },
]

const feedbackPosition = ref('right')
const feedbackBottom = ref(80)

const feedbackBtnStyle = computed(() => {
    const bottom = `${feedbackBottom.value}px`;
    if (feedbackPosition.value === 'left') {
        return { left: '20px', right: 'auto', bottom };
    }
    return { right: '20px', left: 'auto', bottom };
})

function open() {
    form.value.page_url = window.location.href
    form.value.page_title = document.title
    form.value.message = ''
    form.value.subject = ''
    form.value.type = 'general'
    visible.value = true
}

async function submit() {
    if (!form.value.message) return ElMessage.warning('请输入反馈内容')
    loading.value = true
    try {
        await api.create(form.value)
        ElMessage.success('感谢您的反馈！')
        visible.value = false
    } catch (e) { ElMessage.error('提交失败，请稍后重试') }
    finally { loading.value = false }
}

onMounted(async () => {
    try {
        const res = await axios.get('/api/settings/public');
        if (res.data?.success) {
            const data = res.data.data || {};
            feedbackPosition.value = data.feedback_widget_position || 'right';
            feedbackBottom.value = parseInt(data.feedback_widget_bottom) || 80;
        }
    } catch {}
})
</script>

<template>
    <div>
        <!-- 浮动按钮 -->
        <el-button
            class="feedback-float-btn"
            :style="feedbackBtnStyle"
            type="primary"
            circle
            size="large"
            @click="open"
            title="反馈"
        >
            <el-icon :size="20"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></el-icon>
        </el-button>

        <!-- 反馈弹窗 -->
        <el-dialog v-model="visible" title="发送反馈" width="500px" append-to-body>
            <el-form :model="form" label-width="70px">
                <el-form-item label="类型"><el-select v-model="form.type" class="w-full"><el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" /></el-select></el-form-item>
                <el-form-item label="主题"><el-input v-model="form.subject" placeholder="简要描述（选填）" /></el-form-item>
                <el-form-item label="内容"><el-input v-model="form.message" type="textarea" :rows="4" placeholder="请描述您的反馈..." /></el-form-item>
            </el-form>
            <div class="text-xs text-gray-400 mb-2">当前页面：{{ form.page_title }}</div>
            <template #footer>
                <el-button @click="visible = false">取消</el-button>
                <el-button type="primary" :loading="loading" @click="submit">提交</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.feedback-float-btn {
    position: fixed !important;
    z-index: 9999;
    width: 44px;
    height: 44px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.feedback-float-btn:hover {
    transform: scale(1.1);
}
</style>
