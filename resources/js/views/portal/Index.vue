<template>
    <div class="portal-dashboard">
        <div class="page-header">
            <div>
                <h2>我的仪表盘</h2>
                <p class="text-muted">欢迎回来，{{ authStore.userName }}！以下是您的 License 概览。</p>
            </div>
            <el-button type="primary" @click="refreshAll" :loading="loading" :icon="Refresh">
                刷新
            </el-button>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6" v-for="card in statCards" :key="card.label">
                <el-card shadow="hover" class="stat-card" @click="card.action">
                    <div class="stat-content">
                        <div class="stat-info">
                            <div class="stat-value" :style="{ color: card.color }">{{ card.value }}</div>
                            <div class="stat-label">{{ card.label }}</div>
                        </div>
                        <el-icon :size="36" :color="card.color">
                            <component :is="card.icon" />
                        </el-icon>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-row :gutter="16">
            <!-- 左侧：我的 License 列表 -->
            <el-col :span="14">
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>我的 License</span>
                            <el-link type="primary" :underline="false" @click="$router.push('/portal/licenses')">查看全部</el-link>
                        </div>
                    </template>
                    <el-table v-if="licenses.length" :data="licenses" stripe style="width: 100%" v-loading="loading">
                        <el-table-column prop="license_key" label="License Key" min-width="160">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="false" @click="$router.push(`/portal/licenses/${row.id}`)">
                                    <code class="small-text">{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="product?.name" label="产品" width="100">
                            <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                        </el-table-column>
                        <el-table-column prop="status" label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="statusType(row.status)" size="small" effect="dark">
                                    {{ statusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="expires_at" label="到期时间" width="160">
                            <template #default="{ row }">
                                <span v-if="row.expires_at" :class="isExpiring(row.expires_at) ? 'expiring-text' : ''">
                                    {{ row.expires_at }}
                                </span>
                                <span v-else>永久</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!loading" description="暂无 License" />
                </el-card>

                <!-- 最近设备 -->
                <el-card>
                    <template #header>
                        <div class="card-header">
                            <span>最近激活的设备</span>
                            <el-link type="primary" :underline="false" @click="$router.push('/portal/devices')">查看全部</el-link>
                        </div>
                    </template>
                    <el-table v-if="devices.length" :data="devices" stripe style="width: 100%">
                        <el-table-column prop="name" label="设备名称" min-width="120">
                            <template #default="{ row }">{{ row.name || row.hostname || '未知设备' }}</template>
                        </el-table-column>
                        <el-table-column prop="fingerprint" label="指纹" width="120">
                            <template #default="{ row }"><code class="small-text">{{ row.fingerprint?.substring(0, 12) }}...</code></template>
                        </el-table-column>
                        <el-table-column prop="last_seen_at" label="最后活动" width="160" />
                        <el-table-column prop="is_active" label="状态" width="70">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">
                                    {{ row.is_active ? '在线' : '离线' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!loading" description="暂无设备" />
                </el-card>
            </el-col>

            <!-- 右侧：状态分布 & 即将到期 -->
            <el-col :span="10">
                <!-- 状态分布 -->
                <el-card class="mb-4">
                    <template #header>
                        <span>License 状态分布</span>
                    </template>
                    <div v-if="Object.keys(licenseStats.by_status || {}).length" class="status-distribution">
                        <div v-for="(count, status) in licenseStats.by_status" :key="status" class="status-row">
                            <span class="status-name">
                                <el-tag :type="statusType(status)" size="small" effect="dark">
                                    {{ statusLabel(status) }}
                                </el-tag>
                            </span>
                            <el-progress
                                :percentage="calcPercent(count, licenseStats.total)"
                                :color="statusColor(status)"
                                :stroke-width="16"
                                :text-inside="true"
                            >
                                <span>{{ count }}</span>
                            </el-progress>
                        </div>
                    </div>
                    <el-empty v-else description="暂无数据" />
                </el-card>

                <!-- 即将到期 -->
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>即将到期 (30天内)</span>
                            <el-tag v-if="licenseStats.expiring_soon" type="warning" size="small">
                                {{ licenseStats.expiring_soon }} 个
                            </el-tag>
                        </div>
                    </template>
                    <el-table v-if="expiringLicenses.length" :data="expiringLicenses" stripe>
                        <el-table-column prop="license_key" label="License" minWidth="140">
                            <template #default="{ row }">
                                <el-link type="primary" :underline="false" @click="$router.push(`/portal/licenses/${row.id}`)">
                                    <code class="small-text">{{ row.license_key }}</code>
                                </el-link>
                            </template>
                        </el-table-column>
                        <el-table-column prop="product?.name" label="产品" width="80" />
                        <el-table-column prop="expires_at" label="到期" width="110">
                            <template #default="{ row }">
                                <span class="expiring-text">{{ row.expires_at }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="暂无即将到期的 License" />
                </el-card>

                <!-- 快捷入口 -->
                <el-card>
                    <template #header>
                        <span>快捷操作</span>
                    </template>
                    <el-row :gutter="12">
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/licenses')">
                                <el-icon><Key /></el-icon> 查看 License
                            </el-button>
                        </el-col>
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/devices')">
                                <el-icon><Monitor /></el-icon> 管理设备
                            </el-button>
                        </el-col>
                    </el-row>
                    <el-row :gutter="12">
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/billing')">
                                <el-icon><Goods /></el-icon> 账单发票
                            </el-button>
                        </el-col>
                        <el-col :span="12" class="mb-2">
                            <el-button class="action-btn" @click="$router.push('/portal/settings')">
                                <el-icon><Setting /></el-icon> 个人设置
                            </el-button>
                        </el-col>
                    </el-row>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import licenseApi from '@/api/license';
import deviceApi from '@/api/device';
import customerApi from '@/api/customer';
import { ElMessage } from 'element-plus';
import {
    Key, Monitor, Goods, Setting, Refresh, Odometer,
} from '@element-plus/icons-vue';

const router = useRouter();
const authStore = useAuthStore();

const loading = ref(false);
const licenses = ref([]);
const expiringLicenses = ref([]);
const devices = ref([]);

const licenseStats = reactive({
    total: 0,
    active: 0,
    expired: 0,
    expiring_soon: 0,
    by_status: {},
    by_type: {},
});

const statCards = reactive([
    { label: '全部 License', value: '0', icon: Key, color: '#409eff', action: () => router.push('/portal/licenses') },
    { label: '活跃中', value: '0', icon: Odometer, color: '#67c23a', action: () => router.push('/portal/licenses') },
    { label: '已过期', value: '0', icon: Key, color: '#f56c6c', action: () => router.push('/portal/licenses') },
    { label: '已激活设备', value: '0', icon: Monitor, color: '#e6a23c', action: () => router.push('/portal/devices') },
]);

const STATUS_MAP = {
    pending: { type: 'info', label: '待激活' },
    active: { type: 'success', label: '活跃' },
    suspended: { type: 'warning', label: '已暂停' },
    frozen: { type: 'warning', label: '已冻结' },
    expired: { type: 'info', label: '已过期' },
    revoked: { type: 'danger', label: '已吊销' },
    refunded: { type: 'danger', label: '已退款' },
    blacklisted: { type: 'danger', label: '黑名单' },
};

function statusType(status) { return STATUS_MAP[status]?.type || 'info'; }
function statusLabel(status) { return STATUS_MAP[status]?.label || status; }

function statusColor(status) {
    const map = {
        pending: '#909399', active: '#67c23a', suspended: '#e6a23c',
        frozen: '#e6a23c', expired: '#909399', revoked: '#f56c6c',
        refunded: '#f56c6c', blacklisted: '#f56c6c',
    };
    return map[status] || '#409eff';
}

function calcPercent(count, total) {
    if (!total) return 0;
    return Math.round((count / total) * 100);
}

function isExpiring(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    const days = diff / (1000 * 60 * 60 * 24);
    return days <= 30 && days >= 0;
}

async function refreshAll() {
    loading.value = true;
    try {
        // License 统计
        const { data: statsRes } = await licenseApi.stats();
        const stats = statsRes.data || {};
        licenseStats.total = stats.total || 0;
        licenseStats.active = stats.active || 0;
        licenseStats.expired = stats.expired || 0;
        licenseStats.expiring_soon = stats.expiring_soon || 0;
        licenseStats.by_status = stats.by_status || {};
        licenseStats.by_type = stats.by_type || {};

        statCards[0].value = String(stats.total || 0);
        statCards[1].value = String(stats.active || 0);
        statCards[2].value = String(stats.expired || 0);

        // 我的 License 列表
        const { data: listRes } = await licenseApi.list({ per_page: 5, sort: '-created_at' });
        licenses.value = listRes.data?.data || [];

        // 即将到期
        const { data: expRes } = await licenseApi.list({ per_page: 5, expiring: true, sort: 'expires_at' });
        expiringLicenses.value = expRes.data?.data || [];

        // 设备统计 & 最近设备
        const { data: devStatsRes } = await deviceApi.stats();
        statCards[3].value = String(devStatsRes.data?.active || devStatsRes.data?.total || 0);

        const { data: devRes } = await deviceApi.list({ per_page: 5, sort: '-last_seen_at' });
        devices.value = devRes.data?.data || [];
    } catch (e) {
        ElMessage.error('获取数据失败，请稍后重试');
    } finally {
        loading.value = false;
    }
}

onMounted(refreshAll);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.page-header h2 {
    margin: 0 0 4px;
}

.text-muted {
    color: #909399;
    font-size: 14px;
    margin: 0;
}

.mb-4 { margin-bottom: 16px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stat-card {
    cursor: pointer;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.stat-value {
    font-size: 30px;
    font-weight: 700;
}

.stat-label {
    font-size: 14px;
    color: #909399;
    margin-top: 4px;
}

.status-distribution {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-name {
    min-width: 80px;
}

.small-text { font-size: 11px; }
.expiring-text { color: #e6a23c; font-weight: 500; }

.action-btn {
    width: 100%;
}
</style>
