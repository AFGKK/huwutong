<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '../../api/feedback.js'

const { t, locale } = useI18n()

const loading = ref(false)
const feedbackList = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const showForm = ref(false)
const form = ref({ type: 'general', subject: '', message: '', rating: null, page_url: '', page_title: '', screen_resolution: '' })
const detailVisible = ref(false)
const detailData = ref(null)

const typeOptions = computed(() => [
    { value: 'general', label: t('portal.fb_general') },
    { value: 'bug', label: t('portal.fb_bug') },
    { value: 'feature_request', label: t('portal.fb_feature') },
    { value: 'performance', label: t('portal.fb_performance') },
    { value: 'ui_ux', label: t('portal.fb_uiux') },
])

function statusLabel(status) {
    const map = {
        new: t('portal.fb_new'),
        under_review: t('portal.fb_under_review'),
        acknowledged: t('portal.fb_acknowledged'),
        in_progress: t('portal.ticket_progress'),
        resolved: t('portal.ticket_resolved'),
        closed: t('portal.ticket_closed'),
        wont_fix: t('portal.fb_wont_fix'),
    }
    return map[status] || status
}

async function loadList(page = 1) {
    loading.value = true
    try {
        const res = await api.myFeedback({ page, per_page: 15 })
        const d = res.data.data
        feedbackList.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

function openForm() {
    form.value = {
        type: 'general', subject: '', message: '', rating: null,
        page_url: window.location.href, page_title: document.title,
        screen_resolution: `${window.screen?.width || ''}x${window.screen?.height || ''}`,
    }
    showForm.value = true
}

async function submit() {
    if (!form.value.message) return ElMessage.warning(t('portal.feedback_required'))
    try {
        await api.create(form.value)
        ElMessage.success(t('portal.feedback_thanks'))
        showForm.value = false
        loadList()
    } catch (e) { ElMessage.error(t('portal.submit_failed')) }
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}

function showDetail(fb) { detailData.value = fb; detailVisible.value = true }

onMounted(() => { loadList() })
</script>

<template>
    <div>
        <div class="page-header flex justify-between items-center">
            <div>
                <h2>{{ $t('portal.feedback_title') }}</h2>
                <p class="text-sm text-gray-400">{{ $t('portal.feedback_subtitle') }}</p>
            </div>
            <el-button type="primary" @click="openForm()">{{ $t('portal.submit_feedback') }}</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="feedbackList" stripe v-loading="loading">
                <el-table-column :label="$t('portal.type')" width="90">
                    <template #default="{ row }"><el-tag size="small">{{ typeOptions.find(opt => opt.value === row.type)?.label || row.type }}</el-tag></template>
                </el-table-column>
                <el-table-column :label="$t('portal.subject')" min-width="200" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.subject || row.message?.substring(0, 60) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.rating')" width="80">
                    <template #default="{ row }"><span v-if="row.rating">{'★'.repeat(row.rating)}{'☆'.repeat(5-row.rating)}</span><span v-else>-</span></template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 'resolved' ? 'success' : row.status === 'closed' ? 'info' : 'warning'" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.time')" width="150">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="80">
                    <template #default="{ row }"><el-button size="small" text @click="showDetail(row)">{{ $t('portal.detail') }}</el-button></template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!feedbackList.length && !loading" :description="$t('portal.no_feedback')" />
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadList" /></div>
        </el-card>

        <el-dialog v-model="detailVisible" :title="$t('portal.feedback_detail')" width="500px">
            <div v-if="detailData">
                <p class="font-bold">{{ detailData.subject || $t('portal.no_subject') }}</p>
                <el-tag size="small" class="my-2">{{ typeOptions.find(opt => opt.value === detailData.type)?.label }}</el-tag>
                <p class="text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded">{{ detailData.message }}</p>
                <div v-if="detailData.admin_reply" class="mt-3">
                    <el-divider>{{ $t('portal.admin_reply') }}</el-divider>
                    <div class="text-sm bg-blue-50 p-3 rounded">{{ detailData.admin_reply }}<div class="text-xs text-gray-400 mt-1">{{ fmtDate(detailData.replied_at) }}</div></div>
                </div>
            </div>
        </el-dialog>

        <el-dialog v-model="showForm" :title="$t('portal.submit_feedback')" width="550px">
            <el-form :model="form" label-width="80px">
                <el-form-item :label="$t('portal.type')">
                    <el-select v-model="form.type" class="w-full">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.subject')"><el-input v-model="form.subject" :placeholder="$t('portal.subject_ph')" /></el-form-item>
                <el-form-item :label="$t('portal.content')"><el-input v-model="form.message" type="textarea" :rows="5" :placeholder="$t('portal.feedback_content_ph')" /></el-form-item>
                <el-form-item :label="$t('portal.rating')"><el-rate v-model="form.rating" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showForm = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submit">{{ $t('portal.submit_feedback') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.whitespace-pre-wrap { white-space: pre-wrap; }
</style>
