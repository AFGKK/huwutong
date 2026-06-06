<template>
    <div class="device-trust-page">
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>已信任的设备</span>
                    <div class="header-actions">
                        <el-button
                            type="danger"
                            size="small"
                            plain
                            :icon="Delete"
                            :disabled="!devices.length"
                            @click="handleClearAll"
                        >
                            清除所有信任
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
                    信任的设备在登录时将跳过安全验证（如 MFA）。新设备登录时会收到站内通知提醒。
                </template>
            </el-alert>

            <el-table :data="devices" v-loading="loading" stripe style="width: 100%">
                <el-table-column label="设备名称" min-width="200">
                    <template #default="{ row }">
                        <el-icon class="mr-1"><Monitor /></el-icon>
                        {{ row.device_name || '未知设备' }}
                    </template>
                </el-table-column>
                <el-table-column label="指纹" min-width="120">
                    <template #default="{ row }">
                        <el-tag size="small" type="info">
                            {{ row.device_fingerprint?.substring(0, 16) + '...' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="IP 地址" width="150" prop="ip_address" />
                <el-table-column label="信任时间" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.trusted_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="最近活跃" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.last_seen_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-popconfirm
                            title="确定取消对此设备的信任？"
                            @confirm="handleRemove(row.id)"
                        >
                            <template #reference>
                                <el-button type="danger" size="small" :icon="Delete" plain>
                                    移除
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <el-empty v-if="!loading && !devices.length" description="暂无已信任设备" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Delete, Monitor } from '@element-plus/icons-vue'
import { getTrustedDevices, removeTrustedDevice, clearAllTrustedDevices } from '@/api/device-trust'

const devices = ref([])
const loading = ref(false)

async function fetchDevices() {
    loading.value = true
    try {
        const res = await getTrustedDevices()
        devices.value = res.data?.data || []
    } catch (e) {
        ElMessage.error('获取信任设备列表失败')
    } finally {
        loading.value = false
    }
}

async function handleRemove(id) {
    try {
        await removeTrustedDevice(id)
        ElMessage.success('设备信任已取消')
        fetchDevices()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '操作失败')
    }
}

async function handleClearAll() {
    try {
        await ElMessageBox.confirm('确定清除所有已信任设备？之后所有设备登录都需要重新验证。', '确认操作', {
            confirmButtonText: '确认清除',
            cancelButtonText: '取消',
            type: 'warning',
        })
        await clearAllTrustedDevices()
        ElMessage.success('已清除所有信任设备')
        fetchDevices()
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error('操作失败')
        }
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString('zh-CN', {
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
