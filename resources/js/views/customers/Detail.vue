<template>
    <div class="customer-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/customers' }">{{ t('customer_detail_page.breadcrumb_list') }}</el-breadcrumb-item>
                <el-breadcrumb-item>{{ t('customer_detail_page.breadcrumb_detail') }}</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div v-if="customer" class="detail-content">
            <el-card shadow="never" class="info-card">
                <template #header>
                    <div class="card-header">
                        <div class="card-header-left" style="display: flex; align-items: center; gap: 12px;">
                            <el-avatar :size="48" :src="customer.user?.avatar_url" shape="square">
                                <span class="avatar-initial">{{ (customer.user?.name || '?').charAt(0).toUpperCase() }}</span>
                                <template #error>{{ (customer.user?.name || '?').charAt(0).toUpperCase() }}</template>
                            </el-avatar>
                            <span>{{ t('customer_detail_page.basic_info') }}</span>
                        </div>
                        <div class="header-actions">
                            <el-button size="small" @click="openEditDialog">{{ t('actions.edit') }}</el-button>
                            <el-button
                                v-if="customer.status === 'active'"
                                size="small"
                                type="warning"
                                @click="handleStatusChange('inactive')"
                            >
                                {{ t('actions.disable') }}
                            </el-button>
                            <el-button
                                v-if="customer.status !== 'active'"
                                size="small"
                                type="success"
                                @click="handleStatusChange('active')"
                            >
                                {{ t('actions.enable') }}
                            </el-button>
                        </div>
                    </div>
                </template>
                <el-descriptions :column="3" border>
                    <el-descriptions-item :label="t('customer_detail_page.customer_id')" width="120">
                        <code>{{ customer.id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.linked_user')">
                        <template v-if="customer.user">
                            <div>{{ customer.user.name }}</div>
                            <div style="font-size: 12px; color: var(--el-text-color-secondary);">
                                {{ customer.user.email }}
                                <template v-if="customer.user.email && customer.user.phone"> · </template>
                                {{ customer.user.phone }}
                            </div>
                        </template>
                        <span v-else class="text-muted">{{ t('customer_detail_page.unlinked') }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.type')">
                        <el-tag :type="customer.type === 'enterprise' ? 'warning' : 'info'" size="small">
                            {{ customer.type === 'enterprise' ? t('customer_detail_page.types.enterprise') : t('customer_detail_page.types.individual') }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.level')">
                        <el-tag :type="levelTagType(customer.level)" size="small">
                            {{ levelLabel(customer.level) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.status')">
                        <el-tag :type="customer.status === 'active' ? 'success' : customer.status === 'suspended' ? 'danger' : 'info'" size="small">
                            {{ statusLabel(customer.status) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.licenses_count')">
                        <el-tag type="primary" effect="plain" size="small">{{ customer.licenses_count || 0 }}</el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.created_at')">
                        {{ formatDate(customer.created_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t('customer_detail_page.updated_at')">
                        {{ formatDate(customer.updated_at) }}
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <el-card shadow="never" class="section-card">
                <template #header>
                    <span>{{ t('customer_detail_page.tags') }}</span>
                </template>
                <TagSelector
                    taggable-type="customer"
                    :taggable-id="customer.id"
                    :tags="customer.tags || []"
                />
            </el-card>

            <el-card shadow="never" class="section-card">
                <template #header>
                    <div class="card-header">
                        <span>{{ t('customer_detail_page.linked_licenses') }}</span>
                        <el-button
                            size="small"
                            type="primary"
                            @click="$router.push(`/licenses?customer_id=${customer.id}`)"
                        >
                            {{ t('customer_detail_page.view_all') }}
                        </el-button>
                    </div>
                </template>
                <el-table
                    :data="licenses"
                    v-loading="licensesLoading"
                    stripe
                    size="small"
                    @sort-change="handleLicenseSort"
                >
                    <el-table-column label="License Key" min-width="220">
                        <template #default="{ row }">
                            <el-link type="primary" @click="$router.push(`/licenses/${row.id}`)">
                                <code>{{ row.license_key.substring(0, 20) }}...</code>
                            </el-link>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.product')" width="150" prop="product.name">
                        <template #default="{ row }">
                            {{ row.product?.name || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.status')" width="90" prop="status" sortable="custom">
                        <template #default="{ row }">
                            <el-tag :type="licenseStatusType(row.status)" size="small">
                                {{ licenseStatusLabel(row.status) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.expires')" width="170" prop="expires_at" sortable="custom">
                        <template #default="{ row }">
                            {{ formatDate(row.expires_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.created')" width="170" prop="created_at" sortable="custom">
                        <template #default="{ row }">
                            {{ formatDate(row.created_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.actions')" width="100" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="$router.push(`/licenses/${row.id}`)">
                                {{ t('actions.view_details') }}
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="pagination-wrapper" v-if="licenseTotal > 0">
                    <el-pagination
                        v-model:current-page="licensePage"
                        v-model:page-size="licensePerPage"
                        :total="licenseTotal"
                        size="small"
                        layout="total, prev, pager, next"
                        @current-change="loadLicenses"
                    />
                </div>
            </el-card>

            <el-card shadow="never" class="section-card" v-if="devices.length > 0">
                <template #header>
                    <div class="card-header">
                        <span>{{ t('customer_detail_page.linked_devices', { n: devices.length }) }}</span>
                    </div>
                </template>
                <el-table :data="devices" stripe size="small">
                    <el-table-column :label="t('customer_detail_page.cols.fingerprint')" min-width="200">
                        <template #default="{ row }">
                            <code class="fingerprint">{{ row.fingerprint.substring(0, 24) }}...</code>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.hostname')" width="150" prop="hostname">
                        <template #default="{ row }">
                            {{ row.hostname || '-' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.platform')" width="100" prop="platform" />
                    <el-table-column :label="t('customer_detail_page.cols.trust')" width="100" prop="trust_score">
                        <template #default="{ row }">
                            <el-tag
                                :type="(row.trust_score || 0) >= 80 ? 'success' : (row.trust_score || 0) >= 50 ? 'warning' : 'danger'"
                                size="small"
                            >
                                {{ row.trust_score || 0 }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.last_activated')" width="170" prop="last_activated_at">
                        <template #default="{ row }">
                            {{ formatDate(row.last_activated_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('customer_detail_page.cols.license')" width="220">
                        <template #default="{ row }">
                            <el-link
                                v-if="row.license_id"
                                type="primary"
                                @click="$router.push(`/licenses/${row.license_id}`)"
                            >
                                <code>#{{ row.license_id }}</code>
                            </el-link>
                            <span v-else class="text-muted">-</span>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <el-dialog
            v-model="dialogVisible"
            :title="t('customer_detail_page.edit_title')"
            width="500px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item :label="t('customer_detail_page.type')" prop="type">
                    <el-radio-group v-model="form.type">
                        <el-radio value="individual">{{ t('customer_detail_page.types.individual') }}</el-radio>
                        <el-radio value="enterprise">{{ t('customer_detail_page.types.enterprise') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('customer_detail_page.level')" prop="level">
                    <el-select v-model="form.level" style="width: 100%">
                        <el-option label="Free" value="free" />
                        <el-option label="Pro" value="pro" />
                        <el-option label="Enterprise" value="enterprise" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('customer_detail_page.status')" prop="status">
                    <el-select v-model="form.status" style="width: 100%">
                        <el-option :label="t('actions.enable')" value="active" />
                        <el-option :label="t('actions.disable')" value="inactive" />
                        <el-option :label="t('customer_detail_page.statuses.suspended')" value="suspended" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import customerApi from '@/api/customer'
import TagSelector from '@/components/TagSelector.vue'

const { t, locale } = useI18n()
const route = useRoute()
const customerId = Number(route.params.id)

const loading = ref(false)
const customer = ref(null)
const devices = ref([])

const licenses = ref([])
const licensesLoading = ref(false)
const licenseTotal = ref(0)
const licensePage = ref(1)
const licensePerPage = ref(10)
const licenseSort = ref('-created_at')

const dialogVisible = ref(false)
const submitting = ref(false)
const formRef = ref(null)

const form = reactive({
    type: 'individual',
    level: 'free',
    status: 'active',
})

const formRules = computed(() => ({
    type: [{ required: true, message: t('customer_detail_page.validation.type'), trigger: 'change' }],
    level: [{ required: true, message: t('customer_detail_page.validation.level'), trigger: 'change' }],
    status: [{ required: true, message: t('customer_detail_page.validation.status'), trigger: 'change' }],
}))

function levelTagType(level) {
    const map = { free: 'info', pro: 'primary', enterprise: 'warning' }
    return map[level] || 'info'
}

function levelLabel(level) {
    const map = { free: 'Free', pro: 'Pro', enterprise: 'Enterprise' }
    return map[level] || level
}

function statusLabel(status) {
    const key = { active: 'active', inactive: 'inactive', suspended: 'suspended' }[status]
    return key ? t(`customer_detail_page.statuses.${key}`) : status
}

function licenseStatusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'info', blacklisted: 'danger' }
    return map[status] || 'info'
}

function licenseStatusLabel(status) {
    const key = { active: 'active', expired: 'expired', suspended: 'suspended', revoked: 'revoked', frozen: 'frozen', blacklisted: 'blacklisted' }[status]
    return key ? t(`customer_detail_page.license_statuses.${key}`) : status
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    })
}

async function loadDetail() {
    loading.value = true
    try {
        const { data: res } = await customerApi.show(customerId)
        if (res.success) {
            customer.value = res.data.customer
            devices.value = res.data.devices || []
        }
    } catch {
        ElMessage.error(t('customer_detail_page.messages.load_failed'))
    } finally {
        loading.value = false
    }
}

async function loadLicenses() {
    licensesLoading.value = true
    try {
        const params = {
            page: licensePage.value,
            per_page: licensePerPage.value,
            sort: licenseSort.value,
        }
        const { data: res } = await customerApi.licenses(customerId, params)
        licenses.value = res.data?.data || []
        licenseTotal.value = res.data?.total || 0
    } catch {
        licenses.value = []
    } finally {
        licensesLoading.value = false
    }
}

function handleLicenseSort({ prop, order }) {
    if (!order) {
        licenseSort.value = '-created_at'
    } else {
        licenseSort.value = (order === 'desc' ? '-' : '') + (prop || 'created_at')
    }
    loadLicenses()
}

function openEditDialog() {
    if (!customer.value) return
    form.type = customer.value.type
    form.level = customer.value.level
    form.status = customer.value.status
    dialogVisible.value = true
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return

    submitting.value = true
    try {
        await customerApi.update(customerId, { ...form })
        ElMessage.success(t('customer_detail_page.messages.updated'))
        dialogVisible.value = false
        loadDetail()
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false
    }
}

async function handleStatusChange(newStatus) {
    const action = newStatus === 'active' ? t('actions.enable') : t('actions.disable')
    try {
        await ElMessageBox.confirm(
            t('customer_detail_page.messages.status_confirm', { action }),
            t('customer_detail_page.messages.confirm_title'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        )
        await customerApi.update(customerId, { status: newStatus })
        ElMessage.success(t('customer_detail_page.messages.status_ok', { action }))
        loadDetail()
    } catch {
        // cancelled or error
    }
}

onMounted(() => {
    loadDetail()
    loadLicenses()
})
</script>

<style scoped>
.customer-detail-page {
    padding: 20px;
}

.page-breadcrumb {
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.header-actions {
    display: flex;
    gap: 8px;
}

.info-card {
    margin-bottom: 20px;
}

.section-card {
    margin-bottom: 20px;
}

.text-muted {
    color: var(--el-text-color-placeholder);
}

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.fingerprint {
    font-size: 11px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}
</style>
