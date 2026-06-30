<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import api from '../../api/feedback.js'

const loading = ref(false)
const feedbackList = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const showForm = ref(false)
const form = ref({ type: 'general', subject: '', message: '', rating: null, page_url: '', page_title: '', screen_resolution: '' })
const detailVisible = ref(false)
const detailData = ref(null)

const typeOptions = [
    { value: 'general', label: '一般反馈' },
    { value: 'bug', label: 'Bug 报告' },
    { value: 'feature_request', label: '功能建议' },
    { value: 'performance', label: '性能问题' },
    { value: 'ui_ux', label: 'UI/UX 建议' },
]

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
    if (!form.value.message) return ElMessage.warning('请输入反馈内容')
    try {
        await api.create(form.value)
        ElMessage.success('感谢您的反馈！')
        showForm.value = false
        loadList()
    } catch (e) { ElMessage.error('提交失败') }
}

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-' }

function showDetail(fb) { detailData.value = fb; detailVisible.value = true }

onMounted(() => { loadList() })
</script>

<template>
    <div>
        <div class="page-header flex justify-between items-center">
            <div>
                <h2>我的反馈</h2>
                <p class="text-sm text-gray-400">提交反馈或查看历史回复</p>
            </div>
            <el-button type="primary" @click="openForm()">提交反馈</el-button>
        </div>

        <el-card shadow="never">
            <el-table :data="feedbackList" stripe v-loading="loading">
                <el-table-column label="类型" width="90"><template #default="{ row }"><el-tag size="small">{{ typeOptions.find(t => t.value === row.type)?.label || row.type }}</el-tag></template></el-table-column>
                <el-table-column label="主题" min-width="200" show-overflow-tooltip><template #default="{ row }">{{ row.subject || row.message?.substring(0, 60) }}</template></el-table-column>
                <el-table-column label="评分" width="80"><template #default="{ row }"><span v-if="row.rating">{'★'.repeat(row.rating)}{'☆'.repeat(5-row.rating)}</span><span v-else>-</span></template></el-table-column>
                <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status === 'resolved' ? 'success' : row.status === 'closed' ? 'info' : 'warning'" size="small">{{ {new:'待审核',under_review:'审核中',acknowledged:'已确认',in_progress:'处理中',resolved:'已解决',closed:'已关闭',wont_fix:'不予修复'}[row.status] }}</el-tag></template></el-table-column>
                <el-table-column label="时间" width="150"><template #default="{ row }">{{ fmtDate(row.created_at) }}</template></el-table-column>
                <el-table-column label="操作" width="80"><template #default="{ row }"><el-button size="small" text @click="showDetail(row)">详情</el-button></template></el-table-column>
            </el-table>
            <el-empty v-if="!feedbackList.length && !loading" description="暂无反馈记录" />
            <div class="flex justify-center mt-3"><el-pagination small v-model:current-page="pagination.current_page" :page-size="15" :total="pagination.total" layout="prev,pager,next,total" @current-change="loadList" /></div>
        </el-card>

        <!-- 详情 -->
        <el-dialog v-model="detailVisible" title="反馈详情" width="500px">
            <div v-if="detailData">
                <p class="font-bold">{{ detailData.subject || '无主题' }}</p>
                <el-tag size="small" class="my-2">{{ typeOptions.find(t => t.value === detailData.type)?.label }}</el-tag>
                <p class="text-sm whitespace-pre-wrap bg-gray-50 p-3 rounded">{{ detailData.message }}</p>
                <div v-if="detailData.admin_reply" class="mt-3"><el-divider>管理员回复</el-divider><div class="text-sm bg-blue-50 p-3 rounded">{{ detailData.admin_reply }}<div class="text-xs text-gray-400 mt-1">{{ fmtDate(detailData.replied_at) }}</div></div></div>
            </div>
        </el-dialog>

        <!-- 提交表单 -->
        <el-dialog v-model="showForm" title="提交反馈" width="550px">
            <el-form :model="form" label-width="80px">
                <el-form-item label="类型"><el-select v-model="form.type" class="w-full"><el-option v-for="t in typeOptions" :key="t.value" :label="t.label" :value="t.value" /></el-select></el-form-item>
                <el-form-item label="主题"><el-input v-model="form.subject" placeholder="简要描述" /></el-form-item>
                <el-form-item label="内容"><el-input v-model="form.message" type="textarea" :rows="5" placeholder="请详细描述您的反馈..." /></el-form-item>
                <el-form-item label="评分"><el-rate v-model="form.rating" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showForm = false">取消</el-button><el-button type="primary" @click="submit">提交反馈</el-button></template>
        </el-dialog>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.whitespace-pre-wrap { white-space: pre-wrap; }
</style>
