<template>
    <div class="trial-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('trials_page.title') }}</h2>
                <span class="header-subtitle">{{ t('trials_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="resetForm">
                    <el-icon><Refresh /></el-icon>
                    {{ t('trials_page.refresh') }}
                </el-button>
                <el-button type="primary" @click="showCreate = true">
                    <el-icon><Plus /></el-icon>
                    {{ t('trials_page.create') }}
                </el-button>
            </div>
        </div>

        <el-alert
            :title="t('trials_page.alert_title')"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            :description="t('trials_page.alert_desc')"
        />

        <el-row :gutter="16">
            <el-col :span="10">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('trials_page.query_title') }}</span>
                    </template>
                    <el-form :model="queryForm" label-width="100px">
                        <el-form-item label="License ID">
                            <el-input-number v-model="queryForm.license_id" :min="1" style="width: 100%" :placeholder="t('trials_page.license_id_ph')" />
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="queryTrial" :loading="querying">{{ t('trials_page.query') }}</el-button>
                        </el-form-item>
                    </el-form>

                    <div v-if="trialStatus" class="trial-result">
                        <el-divider />
                        <h4>{{ t('trials_page.status_title') }}</h4>
                        <el-descriptions :column="1" border size="small">
                            <el-descriptions-item label="License Key">
                                <code>{{ trialStatus.license_key }}</code>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('trials_page.cols.type')">{{ trialStatus.type }}</el-descriptions-item>
                            <el-descriptions-item :label="t('trials_page.cols.status')">
                                <el-tag :type="trialStatus.status === 'active' ? 'success' : 'danger'" size="small" effect="dark">
                                    {{ trialStatus.status }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('trials_page.cols.expires')">{{ formatTime(trialStatus.expires_at) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('trials_page.cols.activated')">{{ formatTime(trialStatus.activated_at) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('trials_page.cols.usage')">
                                <span v-if="trialStatus.is_expired" class="text-danger">{{ t('trials_page.expired') }}</span>
                                <span v-else-if="trialStatus.remaining_days !== undefined">
                                    {{ t('trials_page.remaining_days', { n: trialStatus.remaining_days }) }}
                                </span>
                                <span v-else>—</span>
                            </el-descriptions-item>
                        </el-descriptions>

                        <div v-if="trialStatus.type === 'trial'" class="convert-section">
                            <el-divider />
                            <h4>{{ t('trials_page.convert_title') }}</h4>
                            <el-form :model="convertForm" label-width="100px" size="small">
                                <el-form-item :label="t('trials_page.license_type')">
                                    <el-select v-model="convertForm.type" style="width: 200px">
                                        <el-option label="Standard" value="standard" />
                                        <el-option label="Enterprise" value="enterprise" />
                                        <el-option label="Development" value="development" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item :label="t('trials_page.days')">
                                    <el-input-number v-model="convertForm.days" :min="30" :max="3650" :step="30" style="width: 200px" />
                                </el-form-item>
                                <el-form-item :label="t('trials_page.max_devices')">
                                    <el-input-number v-model="convertForm.max_devices" :min="1" :max="1000" style="width: 200px" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" @click="convertTrial" :loading="converting" size="small">{{ t('trials_page.convert_btn') }}</el-button>
                                </el-form-item>
                            </el-form>
                        </div>
                    </div>
                </el-card>
            </el-col>

            <el-col :span="14">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('trials_page.create_title') }}</span>
                    </template>
                    <el-form :model="createForm" ref="createFormRef" :rules="createRules" label-width="120px">
                        <el-form-item :label="t('trials_page.product')" prop="product_id">
                            <el-select
                                v-model="createForm.product_id"
                                filterable
                                :placeholder="t('trials_page.select_product')"
                                style="width: 100%"
                                :loading="loadingProducts"
                            >
                                <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('trials_page.tenant')" prop="tenant_id">
                            <el-select
                                v-model="createForm.tenant_id"
                                filterable
                                :placeholder="t('trials_page.select_tenant')"
                                style="width: 100%"
                            >
                                <el-option v-for="ten in tenants" :key="ten.id" :label="ten.name" :value="ten.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('trials_page.customer')" prop="customer_id">
                            <el-select
                                v-model="createForm.customer_id"
                                filterable
                                remote
                                :remote-method="searchCustomers"
                                :placeholder="t('trials_page.search_customer')"
                                style="width: 100%"
                                :loading="searchingCustomers"
                            >
                                <el-option v-for="c in customerOptions" :key="c.id" :label="c.name + ' (' + c.email + ')'" :value="c.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="handleCreate" :loading="creating">
                                <el-icon><Plus /></el-icon> {{ t('trials_page.create') }}
                            </el-button>
                        </el-form-item>
                    </el-form>

                    <el-divider v-if="recentTrials.length" />
                    <div v-if="recentTrials.length">
                        <h4 class="recent-title">{{ t('trials_page.recent_title') }}</h4>
                        <el-table :data="recentTrials" size="small" stripe>
                            <el-table-column label="ID" width="60" prop="id" />
                            <el-table-column label="License Key" min-width="180">
                                <template #default="{ row }">
                                    <code>{{ row.license_key }}</code>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('trials_page.cols.customer')" width="120">
                                <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
                            </el-table-column>
                            <el-table-column :label="t('trials_page.cols.status')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">
                                        {{ row.status }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('trials_page.cols.expires')" width="150">
                                <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('trials_page.cols.actions')" width="80">
                                <template #default="{ row }">
                                    <el-button text size="small" @click="queryForm.license_id = row.id; queryTrial()">{{ t('actions.view') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import trialApi from '@/api/trial'
import productApi from '@/api/product'
import customerApi from '@/api/customer'
import licenseApi from '@/api/license'

const { t, locale } = useI18n()

const loadingProducts = ref(false)
const searchingCustomers = ref(false)
const querying = ref(false)
const creating = ref(false)
const converting = ref(false)
const showCreate = ref(false)
const createFormRef = ref(null)

const products = ref([])
const tenants = ref([])
const customerOptions = ref([])
const trialStatus = ref(null)
const recentTrials = ref([])

const queryForm = reactive({
    license_id: null,
})

const createForm = reactive({
    product_id: null,
    tenant_id: null,
    customer_id: null,
})

const convertForm = reactive({
    type: 'standard',
    days: 365,
    max_devices: 3,
})

const createRules = computed(() => ({
    product_id: [{ required: true, message: t('trials_page.validation.product'), trigger: 'change' }],
    tenant_id: [{ required: true, message: t('trials_page.validation.tenant'), trigger: 'change' }],
    customer_id: [{ required: true, message: t('trials_page.validation.customer'), trigger: 'change' }],
}))

function formatTime(time) {
    if (!time) return '—'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(time).toLocaleString(loc)
}

async function loadProducts() {
    loadingProducts.value = true
    try {
        const { data: res } = await productApi.list({ per_page: 100 })
        if (res.success) {
            products.value = res.data?.data || []
        }
    } catch { /* ignore */ }
    finally { loadingProducts.value = false }
}

async function loadTenants() {
    try {
        await customerApi.list({ per_page: 100 })
        tenants.value = [
            { id: 1, name: t('trials_page.default_tenant') },
            { id: 2, name: t('trials_page.test_tenant') },
        ]
    } catch { /* ignore */ }
}

async function searchCustomers(query) {
    if (!query) return
    searchingCustomers.value = true
    try {
        const { data: res } = await customerApi.list({ search: query, per_page: 20 })
        if (res.success) {
            customerOptions.value = res.data?.data || []
        }
    } catch { /* ignore */ }
    finally { searchingCustomers.value = false }
}

async function loadRecentTrials() {
    try {
        const { data: res } = await licenseApi.list({ type: 'trial', per_page: 10, sort: '-created_at' })
        if (res.success) {
            recentTrials.value = res.data?.data || []
        }
    } catch { /* ignore */ }
}

async function queryTrial() {
    if (!queryForm.license_id) {
        ElMessage.warning(t('trials_page.messages.need_id'))
        return
    }
    querying.value = true
    trialStatus.value = null
    try {
        const { data: res } = await trialApi.status(queryForm.license_id)
        if (res.success) {
            trialStatus.value = res.data
        }
    } catch {
        ElMessage.error(t('trials_page.messages.query_failed'))
    } finally {
        querying.value = false
    }
}

async function handleCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false)
    if (!valid) return

    creating.value = true
    try {
        const { data: res } = await trialApi.create(createForm)
        if (res.success) {
            ElMessage.success(t('trials_page.messages.created'))
            loadRecentTrials()
            if (res.data?.license?.id) {
                queryForm.license_id = res.data.license.id
                queryTrial()
            }
            createForm.product_id = null
            createForm.tenant_id = null
            createForm.customer_id = null
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('trials_page.messages.create_failed'))
    } finally {
        creating.value = false
    }
}

async function convertTrial() {
    if (!trialStatus.value?.license_key) return
    converting.value = true
    try {
        const { data: res } = await trialApi.convert(queryForm.license_id, {
            type: convertForm.type,
            days: convertForm.days,
            max_devices: convertForm.max_devices,
        })
        if (res.success) {
            ElMessage.success(res.message || t('trials_page.messages.converted'))
            queryTrial()
            loadRecentTrials()
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.error?.message || t('trials_page.messages.convert_failed'))
    } finally {
        converting.value = false
    }
}

function resetForm() {
    loadRecentTrials()
    loadProducts()
}

onMounted(() => {
    loadProducts()
    loadTenants()
    loadRecentTrials()
})
</script>

<style scoped>
.trial-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

.text-danger { color: var(--el-color-danger); }

.trial-result {
    margin-top: 8px;
}
.trial-result h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

.convert-section {
    margin-top: 8px;
}
.convert-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

.recent-title {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 12px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
