<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import api from '../../api/transfer.js'

const { t, locale } = useI18n()

const loading = ref(false)
const requests = ref([])
const pagination = ref({ total: 0, current_page: 1 })
const licenses = ref([])
const showForm = ref(false)
const form = ref({ type: 'device_transfer', license_id: null, target_customer_id: null, target_device_fingerprint: null, target_device_name: null, reason: '' })

const typeOptions = computed(() => [
    { value: 'device_transfer', label: t('portal.transfer_device') },
    { value: 'customer_transfer', label: t('portal.transfer_customer') },
    { value: 'user_transfer', label: t('portal.transfer_user') },
])

function statusLabel(status) {
    const map = {
        pending: t('portal.xfer_pending'),
        approved: t('portal.xfer_approved'),
        completed: t('portal.xfer_completed'),
        rejected: t('portal.xfer_rejected'),
        cancelled: t('portal.xfer_cancelled'),
        expired: t('portal.xfer_expired'),
    }
    return map[status] || status
}

async function loadRequests(page = 1) {
    loading.value = true
    try {
        const res = await api.myRequests({ page, per_page: 15 })
        const d = res.data.data
        requests.value = d?.data || d || []
        pagination.value = { total: d?.total || 0, current_page: d?.current_page || page }
    } catch (e) {} finally { loading.value = false }
}

async function loadLicenses() {
    try { const res = await api.transferableLicenses(); licenses.value = res.data.data || [] } catch (e) {}
}

async function openForm() {
    await loadLicenses()
    form.value = { type: 'device_transfer', license_id: null, target_customer_id: null, target_device_fingerprint: null, target_device_name: null, reason: '' }
    showForm.value = true
}

async function submitTransfer() {
    try {
        await api.create(form.value)
        ElMessage.success(t('portal.transfer_submitted'))
        showForm.value = false
        loadRequests(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(t('portal.transfer_submit_failed', { msg: e.response?.data?.message || e.message }))
    }
}

async function cancelTransfer(row) {
    try {
        await api.myCancel(row.id)
        ElMessage.success(t('portal.cancelled_ok'))
        loadRequests(pagination.value.current_page)
    } catch (e) {
        ElMessage.error(t('portal.cancel_failed'))
    }
}

function fmtDate(d) {
    if (!d) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(d).toLocaleString(loc)
}

onMounted(() => { loadRequests() })
</script>

<template>
    <div>
        <div class="page-header flex justify-between items-center">
            <div>
                <h2>{{ $t('portal.transfers_title') }}</h2>
                <p class="text-sm text-gray-400">{{ $t('portal.transfers_subtitle') }}</p>
            </div>
            <el-button type="primary" @click="openForm()">{{ $t('portal.new_transfer') }}</el-button>
        </div>

        <el-card shadow="never" v-loading="loading">
            <el-table :data="requests" stripe v-if="requests.length">
                <el-table-column prop="reference" :label="$t('portal.transfer_ref')" width="140" />
                <el-table-column :label="$t('portal.type')" width="100">
                    <template #default="{ row }">{{ typeOptions.find(opt => opt.value === row.type)?.label }}</template>
                </el-table-column>
                <el-table-column label="License" width="200">
                    <template #default="{ row }">{{ row.license?.license_key || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === 'completed' ? 'success' : row.status === 'pending' ? 'warning' : row.status === 'rejected' ? 'danger' : 'info'"
                            size="small"
                        >{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.reason')" min-width="150" show-overflow-tooltip>
                    <template #default="{ row }">{{ row.reason || '-' }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.applied_at')" width="150">
                    <template #default="{ row }">{{ fmtDate(row.created_at) }}</template>
                </el-table-column>
                <el-table-column :label="$t('portal.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="row.status === 'pending'" size="small" type="danger" text @click="cancelTransfer(row)">{{ $t('actions.cancel') }}</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-else :description="$t('portal.no_transfers')" />
            <div class="flex justify-center mt-3">
                <el-pagination
                    small
                    v-model:current-page="pagination.current_page"
                    :page-size="15"
                    :total="pagination.total"
                    layout="prev,pager,next,total"
                    @current-change="loadRequests"
                />
            </div>
        </el-card>

        <el-dialog v-model="showForm" :title="$t('portal.new_transfer_dialog')" width="500px">
            <el-form :model="form" label-width="130px">
                <el-form-item :label="$t('portal.transfer_type')">
                    <el-select v-model="form.type" class="w-full">
                        <el-option v-for="opt in typeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('portal.select_license')">
                    <el-select v-model="form.license_id" class="w-full" :placeholder="$t('portal.select_license_ph')">
                        <el-option
                            v-for="l in licenses"
                            :key="l.id"
                            :label="l.license_key + (l.product_name ? ' (' + l.product_name + ')' : '')"
                            :value="l.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item v-if="form.type === 'customer_transfer'" :label="$t('portal.target_customer_id')">
                    <el-input v-model.number="form.target_customer_id" type="number" />
                </el-form-item>
                <el-form-item v-if="form.type === 'device_transfer'" :label="$t('portal.target_device_fp')">
                    <el-input v-model="form.target_device_fingerprint" />
                </el-form-item>
                <el-form-item v-if="form.type === 'device_transfer'" :label="$t('portal.target_device_name')">
                    <el-input v-model="form.target_device_name" :placeholder="$t('portal.optional')" />
                </el-form-item>
                <el-form-item :label="$t('portal.transfer_reason')">
                    <el-input v-model="form.reason" type="textarea" :rows="3" :placeholder="$t('portal.transfer_reason_ph')" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showForm = false">{{ $t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="submitTransfer">{{ $t('portal.submit_application') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
</style>
