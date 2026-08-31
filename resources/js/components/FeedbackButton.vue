<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import axios from 'axios'
import api from '@/api/feedback.js'

const { t } = useI18n()

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

const typeOptions = computed(() => [
    { value: 'general', label: t('feedback_widget.type_general') },
    { value: 'bug', label: t('feedback_widget.type_bug') },
    { value: 'feature_request', label: t('feedback_widget.type_feature') },
    { value: 'ui_ux', label: t('feedback_widget.type_uiux') },
])

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
    if (!form.value.message) return ElMessage.warning(t('feedback_widget.required'))
    loading.value = true
    try {
        await api.create(form.value)
        ElMessage.success(t('feedback_widget.thanks'))
        visible.value = false
    } catch (e) { ElMessage.error(t('feedback_widget.submit_fail')) }
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
        <el-button
            class="feedback-float-btn"
            :style="feedbackBtnStyle"
            type="primary"
            circle
            size="large"
            @click="open"
            :title="t('feedback_widget.btn_title')"
        >
            <el-icon :size="20"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></el-icon>
        </el-button>

        <el-dialog v-model="visible" :title="t('feedback_widget.send_title')" width="500px" append-to-body>
            <el-form :model="form" label-width="70px">
                <el-form-item :label="t('feedback_widget.type')">
                    <el-select v-model="form.type" class="w-full">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('feedback_widget.subject')">
                    <el-input v-model="form.subject" :placeholder="t('feedback_widget.subject_ph')" />
                </el-form-item>
                <el-form-item :label="t('feedback_widget.content')">
                    <el-input v-model="form.message" type="textarea" :rows="4" :placeholder="t('feedback_widget.content_ph')" />
                </el-form-item>
            </el-form>
            <div class="text-xs text-gray-400 mb-2">{{ t('feedback_widget.current_page', { title: form.page_title }) }}</div>
            <template #footer>
                <el-button @click="visible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="submit">{{ t('actions.submit') }}</el-button>
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
