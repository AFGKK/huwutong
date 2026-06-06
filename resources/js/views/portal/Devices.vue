<template>
    <div class="portal-devices">
        <div class="page-header">
            <h2>我的设备</h2>
            <el-button @click="fetchDevices" :icon="Refresh" :loading="loading">刷新</el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total || 0 }}</div>
                        <div class="mini-label">全部设备</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.active || 0 }}</div>
                        <div class="mini-label">活跃中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #909399">{{ stats.inactive || 0 }}</div>
                        <div class="mini-label">已离线</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 设备列表 -->
        <el-card shadow="never">
            <el-table :data="devices" v-loading="loading" stripe>
                <el-table-column label="设备名称" min-width="140">
                    <template #default="{ row }">
                        {{ row.name || row.hostname || '未知设备' }}
                    </template>
                </el-table-column>
                <el-table-column label="设备指纹" min-width="160">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="平台" width="100">
                    <template #default="{ row }">
                        {{ row.platform || row.os || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="关联 License" min-width="160">
                    <template #default="{ row }">
                        <el-link
                            v-if="row.license"
                            type="primary"
                            :underline="false"
                            @click="$router.push(`/portal/licenses/${row.license_id}`)"
                        >
                            <code class="small-text">{{ row.license?.license_key || row.license_key }}</code>
                        </el-link>
                        <span v-else>-</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="70">
                    <template #default="{ row }">
                        <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                            {{ row.is_active ? '在线' : '离线' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_seen_at" label="最后活动" width="160" />
                <el-table-column prop="created_at" label="首次激活" width="160" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button
                            type="danger"
                            link
                            size="small"
                            @click="handleDeactivate(row)"
                            :loading="deactivatingId === row.id"
                            :disabled="!row.is_active"
                        >
                            解绑
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
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
import deviceApi from '@/api/device';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh } from '@element-plus/icons-vue';

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
        ElMessage.error('获取设备列表失败');
    } finally {
        loading.value = false;
    }
}

async function handleDeactivate(dev) {
    try {
        await ElMessageBox.confirm(
            `确定要解绑设备 "${dev.name || dev.hostname || dev.fingerprint}"？`,
            '确认解绑',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        deactivatingId.value = dev.id;
        await deviceApi.deactivate(dev.id);
        ElMessage.success('设备解绑成功');
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
