<template>
    <div class="webhook-endpoints-page">
        <div class="page-header">
            <div>
                <h2>{{ t('webhook_endpoints_page.title') }}</h2>
                <p class="header-desc">{{ t('webhook_endpoints_page.subtitle') }}</p>
            </div>
            <el-button type="primary" @click="showCreateDialog">
                <el-icon><Plus /></el-icon>{{ t('webhook_endpoints_page.new') }}
            </el-button>
        </div>

        <el-alert
            :title="t('webhook_endpoints_page.alert')"
            type="info" show-icon :closable="false" class="alert-bar"
        />

        <el-table :data="endpoints" v-loading="loading" stripe>
            <el-table-column :label="t('webhook_endpoints_page.cols.name')" min-width="150" prop="name" />
            <el-table-column label="URL" min-width="250" prop="url">
                <template #default="{ row }">
                    <code class="url-text">{{ row.url }}</code>
                </template>
            </el-table-column>
            <el-table-column :label="t('webhook_endpoints_page.cols.events')" min-width="180">
                <template #default="{ row }">
                    <el-tag
                        v-for="evt in (row.events || [])" :key="evt"
                        size="small"
                        class="mr-1"
                    >{{ evt === '*' ? t('webhook_endpoints_page.all_events') : evt }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('webhook_endpoints_page.cols.status')" width="100">
                <template #default="{ row }">
                    <el-tag v-if="row.is_paused" type="warning" size="small">{{ t('webhook_endpoints_page.paused') }}</el-tag>
                    <el-tag v-else-if="row.is_active" type="success" size="small">{{ t('webhook_endpoints_page.running') }}</el-tag>
                    <el-tag v-else type="info" size="small">{{ t('webhook_endpoints_page.disabled') }}</el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('webhook_endpoints_page.cols.event_count')" width="80" prop="events_count" align="center" />
            <el-table-column :label="t('webhook_endpoints_page.cols.actions')" width="260" fixed="right">
                <template #default="{ row }">
                    <el-button text size="small" type="primary" @click="showEditDialog(row)">{{ t('actions.edit') }}</el-button>
                    <el-button
                        text size="small"
                        :type="row.is_paused ? 'success' : 'warning'"
                        :loading="togglingId === row.id"
                        @click="handleTogglePause(row)"
                    >
                        {{ row.is_paused ? t('webhook_endpoints_page.resume') : t('webhook_endpoints_page.pause') }}
                    </el-button>
                    <el-button
                        text size="small" type="info"
                        :loading="testingId === row.id"
                        @click="handleTest(row)"
                    >{{ t('webhook_endpoints_page.test') }}</el-button>
                    <el-popconfirm
                        :title="t('webhook_endpoints_page.delete_confirm')"
                        @confirm="handleDelete(row)"
                    >
                        <template #reference>
                            <el-button text size="small" type="danger">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>

        <el-dialog
            v-model="dialogVisible"
            :title="isEditing ? t('webhook_endpoints_page.edit_title') : t('webhook_endpoints_page.create_title')"
            width="640px"
        >
            <el-form :model="form" label-position="top" size="small" ref="formRef" :rules="formRules">
                <el-form-item :label="t('webhook_endpoints_page.cols.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('webhook_endpoints_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('webhook_endpoints_page.target_url')" prop="url">
                    <el-input v-model="form.url" placeholder="https://api.example.com/webhooks/hwt" />
                </el-form-item>
                <el-form-item :label="t('webhook_endpoints_page.subscribe')" prop="events">
                    <el-select v-model="form.events" multiple filterable style="width: 100%">
                        <el-option
                            v-for="ev in eventTypeOptions"
                            :key="ev.value"
                            :label="ev.label"
                            :value="ev.value"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('webhook_endpoints_page.secret')">
                    <el-input v-model="form.secret" type="password" show-password :placeholder="t('webhook_endpoints_page.secret_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="saving" @click="handleSave">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus } from '@element-plus/icons-vue'
import webhookEndpointApi from '@/api/webhookEndpoint'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const endpoints = ref([])
const eventTypeOptions = ref([])
const dialogVisible = ref(false)
const isEditing = ref(false)
const togglingId = ref(null)
const testingId = ref(null)
const formRef = ref(null)

const form = reactive({
    id: null,
    name: '',
    url: '',
    events: [],
    secret: '',
})

const formRules = computed(() => ({
    name: [{ required: true, message: t('webhook_endpoints_page.validation.name'), trigger: 'blur' }],
    url: [{ required: true, message: t('webhook_endpoints_page.validation.url'), trigger: 'blur' }],
    events: [{ required: true, message: t('webhook_endpoints_page.validation.events'), trigger: 'change' }],
}))

async function loadEndpoints() {
    loading.value = true
    try {
        const { data: res } = await webhookEndpointApi.list()
        if (res.success) endpoints.value = res.data?.data || []
    } finally {
        loading.value = false
    }
}

async function loadEventTypes() {
    try {
        const { data: res } = await webhookEndpointApi.eventTypes()
        if (res.success) eventTypeOptions.value = res.data || []
    } catch {
        // ignore
    }
}

function resetForm() {
    form.id = null
    form.name = ''
    form.url = ''
    form.events = []
    form.secret = ''
}

function showCreateDialog() {
    isEditing.value = false
    resetForm()
    dialogVisible.value = true
}

function showEditDialog(endpoint) {
    isEditing.value = true
    form.id = endpoint.id
    form.name = endpoint.name
    form.url = endpoint.url
    form.events = endpoint.events || []
    form.secret = ''
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        const payload = {
            name: form.name,
            url: form.url,
            events: form.events,
        }
        if (form.secret) payload.secret = form.secret

        let res
        if (isEditing.value) {
            res = await webhookEndpointApi.update(form.id, payload)
        } else {
            res = await webhookEndpointApi.create(payload)
        }

        if (res.data.success) {
            ElMessage.success(isEditing.value ? t('webhook_endpoints_page.messages.updated') : t('webhook_endpoints_page.messages.created'))
            dialogVisible.value = false
            await loadEndpoints()
        }
    } catch {
        ElMessage.error(t('webhook_endpoints_page.messages.save_failed'))
    } finally {
        saving.value = false
    }
}

async function handleTogglePause(endpoint) {
    togglingId.value = endpoint.id
    try {
        const { data: res } = await webhookEndpointApi.togglePause(endpoint.id)
        if (res.success) {
            ElMessage.success(res.data?.is_paused ? t('webhook_endpoints_page.messages.paused') : t('webhook_endpoints_page.messages.resumed'))
            await loadEndpoints()
        }
    } catch {
        ElMessage.error(t('messages.failed'))
    } finally {
        togglingId.value = null
    }
}

async function handleTest(endpoint) {
    testingId.value = endpoint.id
    try {
        const { data: res } = await webhookEndpointApi.test(endpoint.id)
        if (res.success && res.data) {
            const result = res.data
            if (result.success) {
                ElMessage.success(t('webhook_endpoints_page.messages.test_ok', { code: result.status_code, ms: result.latency_ms }))
            } else {
                ElMessage.warning(t('webhook_endpoints_page.messages.test_fail', { error: result.error, ms: result.latency_ms }))
            }
        }
    } catch {
        ElMessage.error(t('webhook_endpoints_page.messages.test_error'))
    } finally {
        testingId.value = null
    }
}

async function handleDelete(endpoint) {
    try {
        const { data: res } = await webhookEndpointApi.destroy(endpoint.id)
        if (res.success) {
            ElMessage.success(t('webhook_endpoints_page.messages.disabled'))
            endpoints.value = endpoints.value.filter(e => e.id !== endpoint.id)
        }
    } catch {
        ElMessage.error(t('webhook_endpoints_page.messages.delete_failed'))
    }
}

onMounted(() => {
    loadEndpoints()
    loadEventTypes()
})
</script>

<style scoped>
.webhook-endpoints-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-desc { margin: 0; color: #909399; font-size: 13px; }
.alert-bar { margin-bottom: 16px; }
.url-text { font-size: 12px; word-break: break-all; }
.mr-1 { margin-right: 4px; }
</style>
