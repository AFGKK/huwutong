<template>
    <div class="portal-devices">
        <div class="page-header">
            <h2>{{ $t('portal.devices_title') }}</h2>
            <el-button @click="fetchDevices" :icon="Refresh" :loading="loading">{{ $t('portal.refresh') }}</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.stat_all_devices') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.active || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.stat_active') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #909399">{{ stats.inactive || 0 }}</div>
                        <div class="mini-label">{{ $t('portal.stat_offline') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card shadow="never">
            <el-table :data="devices" v-loading="loading" stripe>
                <el-table-column :label="$t('portal.device_name')" min-width="140">
                    <template #default="{ row }">
                        {{ row.name || row.hostname || $t('portal.unknown_device') }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.fingerprint')" min-width="160">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.platform')" width="100">
                    <template #default="{ row }">
                        {{ row.platform || row.os || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.linked_license')" min-width="160">
                    <template #default="{ row }">
                        <el-link
                            v-if="row.license"
                            type="primary"
                            :underline="'never'"
                            @click="$router.push(`/portal/licenses/${row.license_id}`)"
                        >
                            <code class="small-text">{{ row.license?.license_key || row.license_key }}</code>
                        </el-link>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.status')" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? $t('portal.online') : $t('portal.offline') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_seen_at" :label="$t('portal.last_seen')" width="160" />
                <el-table-column prop="created_at" :label="$t('portal.first_activated')" width="160" />
                <el-table-column :label="$t('portal.actions')" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="danger"
                            link
                            size="small"
                            @click="handleDeactivate(row)"
                            :loading="deactivatingId === row.id"
                            :disabled="!row.is_active"
                        >
                            {{ $t('portal.unbind') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :total="total"
                    :page-sizes="[10, 20, 50]"
                    layout="total, sizes, prev, pager, next"
                    @current-change="fetchDevices"
                    @size-change="fetchDevices"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import deviceApi from '@/api/device';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';

const { t } = useI18n();

const loading = ref(false);
const devices = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const deactivatingId = ref(null);

const stats = reactive({
    total: 0,
    active: 0,
    inactive: 0,
});

async function fetchDevices() {
    loading.value = true;
    try {
        const params = { page: page.value, per_page: perPage.value, sort: '-last_seen_at' };
        const { data: res } = await deviceApi.list(params);
        devices.value = res.data?.data || [];
        total.value = res.data?.total || 0;

        const { data: statsRes } = await deviceApi.stats();
        const s = statsRes.data || {};
        stats.total = s.total || 0;
        stats.active = s.active || 0;
        stats.inactive = (s.total || 0) - (s.active || 0);
    } catch {
        ElMessage.error(t('portal.devices_load_failed'));
    } finally {
        loading.value = false;
    }
}

async function handleDeactivate(dev) {
    try {
        const name = dev.name || dev.hostname || dev.fingerprint;
        await ElMessageBox.confirm(
            t('portal.unbind_confirm', { name }),
            t('portal.unbind_title'),
            {
                confirmButtonText: t('actions.confirm'),
                cancelButtonText: t('actions.cancel'),
                type: 'warning',
            }
        );
        deactivatingId.value = dev.id;
        await deviceApi.deactivate(dev.id);
        ElMessage.success(t('portal.unbind_ok'));
        await fetchDevices();
    } catch {
        // cancelled or error
    } finally {
        deactivatingId.value = null;
    }
}

onMounted(fetchDevices);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; }

.mb-4 { margin-bottom: 16px; }

.mini-stat {
    text-align: center;
    padding: 8px 0;
}

.mini-value {
    font-size: 28px;
    font-weight: 700;
    color: #303133;
}

.mini-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.pagination-wrap {
    display: flex;
    justify-content: flex-end;
    padding: 16px 0 0;
}

.small-text { font-size: 11px; }
</style>
