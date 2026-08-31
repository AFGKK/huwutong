<template>
    <div class="device-trust-page">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>{{ t('device_trust_page.title') }}</span>
                    <div class="header-actions">
                        <el-button
                            type="danger"
                            size="small"
                            plain
                            :icon="Delete"
                            :disabled="!devices.length"
                            @click="handleClearAll"
                        >
                            {{ t('device_trust_page.btn.clear_all') }}
                        </el-button>
                    </div>
                </div>
            </template>

            <el-alert
                type="info"
                :closable="false"
                show-icon
                class="mb-4"
            >
                <template #title>
                    {{ t('device_trust_page.alert') }}
                </template>
            </el-alert>

            <el-table :data="devices" v-loading="loading" stripe style="width: 100%">
                <el-table-column :label="t('device_trust_page.columns.device_name')" min-width="200">
                    <template #default="{ row }">
                        <el-icon class="mr-1"><Monitor /></el-icon>
                        {{ row.device_name || t('device_trust_page.unknown_device') }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('device_trust_page.columns.fingerprint')" min-width="120">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">
                            {{ row.device_fingerprint?.substring(0, 16) + '...' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('device_trust_page.columns.ip_address')" width="150" prop="ip_address" />
                <el-table-column :label="t('device_trust_page.columns.trusted_at')" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.trusted_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('device_trust_page.columns.last_seen')" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.last_seen_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t('device_trust_page.columns.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-popconfirm
                            :title="t('device_trust_page.confirm.remove')"
                            @confirm="handleRemove(row.id)"
                        >
                            <template #reference>
                                <el-button type="danger" size="small" :icon="Delete" plain>
                                    {{ t('device_trust_page.btn.remove') }}
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="!loading && !devices.length" :description="t('device_trust_page.empty')" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Monitor } from '@element-plus/icons-vue'
import { getTrustedDevices, removeTrustedDevice, clearAllTrustedDevices } from '@/api/device-trust'

const { t, locale } = useI18n()

const devices = ref([])
const loading = ref(false)

const clearAllConfirmOptions = computed(() => ({
    confirmButtonText: t('device_trust_page.btn.confirm_clear'),
    cancelButtonText: t('actions.cancel'),
    type: 'warning',
}))

async function fetchDevices() {
    loading.value = true
    try {
        const res = await getTrustedDevices()
        devices.value = res.data?.data || []
    } catch (e) {
        ElMessage.error(t('device_trust_page.messages.fetch_failed'))
    } finally {
        loading.value = false
    }
}

async function handleRemove(id) {
    try {
        await removeTrustedDevice(id)
        ElMessage.success(t('device_trust_page.messages.remove_success'))
        fetchDevices()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    }
}

async function handleClearAll() {
    try {
        await ElMessageBox.confirm(
            t('device_trust_page.confirm.clear_all'),
            t('device_trust_page.confirm.title'),
            clearAllConfirmOptions.value,
        )
        await clearAllTrustedDevices()
        ElMessage.success(t('device_trust_page.messages.clear_success'))
        fetchDevices()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(t('messages.failed'))
        }
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN'
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

onMounted(() => {
    fetchDevices()
})
</script>

<style scoped>
.device-trust-page {
    max-width: 1000px;
    margin: 0 auto;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.mb-4 {
    margin-bottom: 16px;
}

.mr-1 {
    margin-right: 4px;
}
</style>
