<template>
    <div class="cors-configs-page">
        <div class="page-header">
            <h2>{{ t('cors_page.title') }}</h2>
            <el-button type="primary" @click="openDialog()">
                <el-icon><Plus /></el-icon> {{ t('cors_page.add') }}
            </el-button>
        </div>

        <el-table :data="configs" v-loading="loading" stripe style="width: 100%">
            <el-table-column prop="name" :label="t('cors_page.cols.name')" min-width="120" />
            <el-table-column :label="t('cors_page.cols.origins')" min-width="200">
                <template #default="{ row }">
                    <el-tag v-for="origin in (row.allowed_origins || [])" :key="origin" size="small" style="margin: 1px">
                        {{ origin }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('cors_page.cols.route')" width="120">
                <template #default="{ row }">
                    <el-tag v-if="row.route_pattern" type="info" size="small">{{ row.route_pattern }}</el-tag>
                    <span v-else class="text-muted">{{ t('cors_page.all') }}</span>
                </template>
            </el-table-column>
            <el-table-column prop="max_age" label="Max Age" width="90" />
            <el-table-column prop="priority" :label="t('cors_page.cols.priority')" width="80" />
            <el-table-column :label="t('cors_page.cols.status')" width="80">
                <template #default="{ row }">
                    <el-tag :type="row.is_active ? 'success' : 'danger'" size="small">
                        {{ row.is_active ? t('actions.enable') : t('actions.disable') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column :label="t('cors_page.cols.actions')" width="180" fixed="right">
                <template #default="{ row }">
                    <el-button size="small" @click="testDialog(row)">{{ t('cors_page.test') }}</el-button>
                    <el-button size="small" @click="openDialog(row)">{{ t('actions.edit') }}</el-button>
                    <el-popconfirm :title="t('cors_page.confirm_delete')" @confirm="handleDelete(row)">
                        <template #reference>
                            <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                        </template>
                    </el-popconfirm>
                </template>
            </el-table-column>
        </el-table>

        <el-dialog v-model="dialogVisible" :title="editingId ? t('cors_page.edit_title') : t('cors_page.create_title')" width="700px">
            <el-form ref="formRef" :model="form" :rules="formRules" label-width="140px">
                <el-form-item :label="t('cors_page.cols.name')" prop="name">
                    <el-input v-model="form.name" :placeholder="t('cors_page.name_ph')" />
                </el-form-item>
                <el-form-item :label="t('cors_page.cols.origins')" prop="allowed_origins">
                    <div class="origin-list">
                        <div v-for="(origin, i) in form.allowed_origins" :key="i" class="origin-item">
                            <el-input v-model="form.allowed_origins[i]" placeholder="https://example.com or *" />
                            <el-button @click="form.allowed_origins.splice(i, 1)" type="danger" :icon="Delete" circle />
                        </div>
                        <el-button @click="form.allowed_origins.push('')" type="primary" link>
                            {{ t('cors_page.add_origin') }}
                        </el-button>
                    </div>
                </el-form-item>
                <el-form-item :label="t('cors_page.allowed_methods')">
                    <el-checkbox-group v-model="form.allowed_methods">
                        <el-checkbox value="GET">GET</el-checkbox>
                        <el-checkbox value="POST">POST</el-checkbox>
                        <el-checkbox value="PUT">PUT</el-checkbox>
                        <el-checkbox value="PATCH">PATCH</el-checkbox>
                        <el-checkbox value="DELETE">DELETE</el-checkbox>
                        <el-checkbox value="OPTIONS">OPTIONS</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>
                <el-form-item :label="t('cors_page.allowed_headers')">
                    <el-select v-model="form.allowed_headers" multiple filterable allow-create default-first-option
                        :placeholder="t('cors_page.header_ph')" style="width: 100%">
                        <el-option v-for="h in commonHeaders" :key="h" :value="h" :label="h" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('cors_page.exposed_headers')">
                    <el-select v-model="form.exposed_headers" multiple filterable allow-create default-first-option
                        :placeholder="t('cors_page.header_ph')" style="width: 100%">
                        <el-option v-for="h in commonHeaders" :key="h" :value="h" :label="h" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('cors_page.allow_credentials')">
                    <el-switch v-model="form.allow_credentials" />
                </el-form-item>
                <el-form-item :label="t('cors_page.max_age')">
                    <el-input-number v-model="form.max_age" :min="0" :max="86400" />
                </el-form-item>
                <el-form-item :label="t('cors_page.cols.route')">
                    <el-input v-model="form.route_pattern" :placeholder="t('cors_page.route_ph')" />
                </el-form-item>
                <el-form-item :label="t('cors_page.cols.priority')">
                    <el-input-number v-model="form.priority" :min="-100" :max="100" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="testVisible" :title="t('cors_page.test_title')" width="500px">
            <el-form label-width="100px">
                <el-form-item label="Origin">
                    <el-input v-model="testOrigin" placeholder="https://example.com" />
                </el-form-item>
                <el-form-item :label="t('cors_page.path')">
                    <el-input v-model="testPath" placeholder="api/license/activate" />
                </el-form-item>
            </el-form>
            <div v-if="testResult" class="test-result">
                <h4>{{ t('cors_page.result') }}</h4>
                <el-alert v-if="testResult.matched" :title="t('cors_page.matched')" type="success" show-icon />
                <el-alert v-else :title="t('cors_page.not_matched')" type="warning" show-icon />
                <pre v-if="testResult.headers">{{ JSON.stringify(testResult.headers, null, 2) }}</pre>
            </div>
            <template #footer>
                <el-button @click="testVisible = false">{{ t('actions.close') }}</el-button>
                <el-button type="primary" @click="runTest" :loading="testing">{{ t('cors_page.test') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { Plus, Delete } from '@element-plus/icons-vue'
import { getCorsConfigs, createCorsConfig, updateCorsConfig, deleteCorsConfig, testCorsConfig } from '@/api/cors'
import { ElMessage } from 'element-plus'

const { t } = useI18n()

const loading = ref(false)
const saving = ref(false)
const configs = ref([])
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)

const commonHeaders = ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key', 'X-License-Key',
    'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature',
]

const form = reactive({
    name: '',
    allowed_origins: [''],
    allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    allowed_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key', 'X-License-Key', 'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature'],
    exposed_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'X-Request-Id'],
    allow_credentials: true,
    max_age: 86400,
    route_pattern: '',
    priority: 0,
})

const formRules = computed(() => ({
    name: [{ required: true, message: t('cors_page.validation.name'), trigger: 'blur' }],
    allowed_origins: [{ required: true, message: t('cors_page.validation.origins'), trigger: 'change' }],
}))

const testVisible = ref(false)
const testOrigin = ref('')
const testPath = ref('')
const testResult = ref(null)
const testing = ref(false)

async function fetchConfigs() {
    loading.value = true
    try {
        const res = await getCorsConfigs()
        configs.value = res.data || []
    } catch (e) {
        ElMessage.error(t('cors_page.messages.load_failed'))
    } finally {
        loading.value = false
    }
}

function openDialog(row) {
    if (row) {
        editingId.value = row.id
        Object.assign(form, {
            name: row.name || '',
            allowed_origins: row.allowed_origins?.length ? [...row.allowed_origins] : [''],
            allowed_methods: row.allowed_methods || ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowed_headers: row.allowed_headers || ['Content-Type', 'Authorization'],
            exposed_headers: row.exposed_headers || [],
            allow_credentials: row.allow_credentials ?? true,
            max_age: row.max_age ?? 86400,
            route_pattern: row.route_pattern || '',
            priority: row.priority ?? 0,
        })
    } else {
        editingId.value = null
        Object.assign(form, {
            name: '',
            allowed_origins: [''],
            allowed_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            allowed_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'X-Api-Key',
                'X-License-Key', 'X-Tenant-Id', 'X-Idempotency-Key', 'X-Nonce', 'X-Signature',
            ],
            exposed_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset', 'X-Request-Id'],
            allow_credentials: true,
            max_age: 86400,
            route_pattern: '',
            priority: 0,
        })
    }
    dialogVisible.value = true
}

async function handleSave() {
    const valid = await formRef.value?.validate().catch(() => false)
    if (!valid) return

    saving.value = true
    try {
        const payload = {
            ...form,
            allowed_origins: form.allowed_origins.filter(o => o.trim() !== ''),
        }

        if (editingId.value) {
            await updateCorsConfig(editingId.value, payload)
            ElMessage.success(t('cors_page.messages.updated'))
        } else {
            await createCorsConfig(payload)
            ElMessage.success(t('cors_page.messages.created'))
        }
        dialogVisible.value = false
        await fetchConfigs()
    } catch (e) {
        ElMessage.error(t('messages.failed'))
    } finally {
        saving.value = false
    }
}

async function handleDelete(row) {
    try {
        await deleteCorsConfig(row.id)
        ElMessage.success(t('cors_page.messages.deleted'))
        await fetchConfigs()
    } catch (e) {
        ElMessage.error(t('cors_page.messages.delete_failed'))
    }
}

function testDialog(row) {
    testVisible.value = true
    testOrigin.value = ''
    testPath.value = ''
    testResult.value = null
    if (row?.allowed_origins?.length) {
        testOrigin.value = row.allowed_origins[0]
    }
}

async function runTest() {
    if (!testOrigin.value || !testPath.value) {
        ElMessage.warning(t('cors_page.messages.test_required'))
        return
    }
    testing.value = true
    try {
        const res = await testCorsConfig(testOrigin.value, testPath.value)
        testResult.value = res.data
    } catch (e) {
        ElMessage.error(t('cors_page.messages.test_failed'))
    } finally {
        testing.value = false
    }
}

onMounted(fetchConfigs)
</script>

<style scoped>
.cors-configs-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.origin-list { width: 100%; }
.origin-item { display: flex; gap: 8px; margin-bottom: 8px; }
.origin-item .el-input { flex: 1; }
.text-muted { color: #999; }
.test-result { margin-top: 16px; }
.test-result pre { background: #f5f7fa; padding: 12px; border-radius: 4px; font-size: 12px; overflow-x: auto; }
</style>
