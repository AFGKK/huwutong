<template>
    <div class="license-detail" v-loading="loading">
        <el-page-header @back="$router.push('/portal/licenses')" :content="`License 详情`" />

        <!-- 状态信息卡 -->
        <el-card class="mt-4" shadow="never">
            <div class="status-bar">
                <div class="status-section">
                    <div class="status-label">当前状态</div>
                    <el-tag :type="statusType(license.status)" size="large" effect="dark">
                        {{ statusLabel(license.status) }}
                    </el-tag>
                </div>
                <div class="status-section">
                    <div class="status-label">License Key</div>
                    <code class="license-key">{{ license.license_key }}</code>
                    <el-button text size="small" @click="copyKey">复制</el-button>
                </div>
                <div class="status-section">
                    <div class="status-label">类型</div>
                    <el-tag v-if="license.type === 'trial'" type="warning" size="small">试用</el-tag>
                    <el-tag v-else-if="license.type === 'enterprise'" type="success" size="small">企业版</el-tag>
                    <el-tag v-else-if="license.type === 'development'" size="small">开发版</el-tag>
                    <span v-else>标准</span>
                </div>
            </div>
        </el-card>

        <el-row :gutter="16" class="mt-4">
            <!-- 基本信息 -->
            <el-col :span="16">
                <el-card>
                    <template #header>
                        <span>基本信息</span>
                    </template>
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="产品" :span="1">{{ license.product?.name || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="座位数" :span="1">{{ license.seats || 1 }}</el-descriptions-item>
                        <el-descriptions-item label="设备限制" :span="1">{{ license.max_devices }}</el-descriptions-item>
                        <el-descriptions-item label="已激活设备" :span="1">{{ deviceCount }}</el-descriptions-item>
                        <el-descriptions-item label="创建时间" :span="1">{{ license.created_at }}</el-descriptions-item>
                        <el-descriptions-item label="激活时间" :span="1">{{ license.activated_at || '从未激活' }}</el-descriptions-item>
                        <el-descriptions-item label="到期时间" :span="2">
                            <span v-if="license.expires_at" :class="{ 'expiring-text': isExpiring(license.expires_at) }">
                                {{ license.expires_at }}
                            </span>
                            <span v-else>永久</span>
                        </el-descriptions-item>
                    </el-descriptions>

                    <!-- 元数据 -->
                    <div v-if="license.metadata" class="mt-4">
                        <el-divider />
                        <h4 class="section-title">自定义元数据</h4>
                        <pre class="metadata-json">{{ formatJson(license.metadata) }}</pre>
                    </div>
                </el-card>
            </el-col>

            <!-- 右侧：设备概览 & 操作 -->
            <el-col :span="8">
                <!-- 设备使用情况 -->
                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>设备使用情况</span>
                            <el-link type="primary" :underline="false" @click="$router.push('/portal/devices')">管理</el-link>
                        </div>
                    </template>
                    <div class="device-usage">
                        <el-progress
                            :percentage="devicePercent"
                            :status="devicePercent >= 80 ? 'exception' : devicePercent >= 60 ? 'warning' : 'success'"
                            :stroke-width="20"
                            :text-inside="true"
                        >
                            {{ deviceCount }} / {{ license.max_devices }}
                        </el-progress>
                        <p class="usage-hint">
                            <template v-if="devicePercent >= 80">
                                设备数量接近上限，建议解除不活跃设备的绑定。
                            </template>
                            <template v-else>
                                还有 {{ license.max_devices - deviceCount }} 个设备名额可用。
                            </template>
                        </p>
                    </div>
                </el-card>

                <!-- 关联的设备 -->
                <el-card>
                    <template #header>
                        <span>已绑定的设备</span>
                    </template>
                    <div v-if="devices.length">
                        <div v-for="dev in devices" :key="dev.id" class="device-item">
                            <div class="device-info">
                                <el-icon><Monitor /></el-icon>
                                <div>
                                    <div class="device-name">{{ dev.name || dev.hostname || '未知设备' }}</div>
                                    <div class="device-fingerprint">
                                        <code>{{ dev.fingerprint?.substring(0, 16) }}...</code>
                                    </div>
                                </div>
                            </div>
                            <el-button
                                text
                                type="danger"
                                size="small"
                                @click="handleDeactivate(dev)"
                                :loading="deactivatingId === dev.id"
                            >
                                解绑
                            </el-button>
                        </div>
                    </div>
                    <el-empty v-else description="暂无绑定的设备" :image-size="60" />
                </el-card>

                <!-- 自助操作 -->
                <el-card class="mt-4">
                    <template #header>
                        <span>自助操作</span>
                    </template>
                    <div class="self-service-actions">
                        <el-button
                            v-if="canRenew"
                            type="primary"
                            class="action-btn"
                            @click="handleRenew"
                            :icon="Refresh"
                        >
                            续期 License
                        </el-button>
                        <el-button
                            v-if="canUpgrade"
                            type="warning"
                            class="action-btn"
                            @click="handleUpgrade"
                            :icon="ArrowUp"
                        >
                            升级套餐
                        </el-button>
                        <el-button
                            v-if="canRefund"
                            type="danger"
                            class="action-btn"
                            plain
                            @click="handleRequestRefund"
                            :icon="Money"
                        >
                            申请退款
                        </el-button>
                        <p class="action-hint" v-if="!canRenew && !canUpgrade && !canRefund">
                            当前 License 状态不支持自助操作。
                        </p>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import licenseApi from '@/api/license';
import deviceApi from '@/api/device';
import billingApi from '@/api/billing';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, Refresh, ArrowUp, Money } from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const deactivatingId = ref(null);
const license = ref({});
const devices = ref([]);

const deviceCount = computed(() => devices.value.length);
const devicePercent = computed(() => {
    const max = license.value.max_devices || 1;
    return Math.min(Math.round((deviceCount.value / max) * 100), 100);
});

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
function isExpiring(dateStr) {
    if (!dateStr) return false;
    const diff = new Date(dateStr) - new Date();
    return diff / (1000 * 60 * 60 * 24) <= 30 && diff >= 0;
}

function formatJson(data) {
    try {
        return typeof data === 'object' ? JSON.stringify(data, null, 2) : data;
    } catch {
        return String(data);
    }
}

// ── 自助操作 (M1.4-09) ──

const canRenew = computed(() => {
    return ['active', 'expired', 'suspended'].includes(license.value.status);
});

const canUpgrade = computed(() => {
    return ['active'].includes(license.value.status) && license.value.type !== 'enterprise';
});

const canRefund = computed(() => {
    return ['active', 'suspended', 'frozen'].includes(license.value.status);
});

async function handleRenew() {
    try {
        await ElMessageBox.confirm(
            '确认续期此 License？续期后将延长有效期限。',
            '续期确认',
            { confirmButtonText: '确认续期', cancelButtonText: '取消', type: 'info' }
        );
        await billingApi.manualRenew(license.value.id);
        ElMessage.success('续期成功，有效期已延长');
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '续期失败');
        }
    }
}

async function handleUpgrade() {
    try {
        await ElMessageBox.confirm(
            `确认将 License 升级为企业版？升级后将获得更多功能和企业级支持。`,
            '升级确认',
            { confirmButtonText: '确认升级', cancelButtonText: '取消', type: 'info' }
        );
        await licenseApi.update(license.value.id, { type: 'enterprise' });
        ElMessage.success('升级成功');
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '升级失败');
        }
    }
}

async function handleRequestRefund() {
    try {
        await ElMessageBox.confirm(
            '确定要申请退款？退款后此 License 将被标记为已退款状态，所有关联设备将无法使用。此操作不可撤销。',
            '申请退款',
            {
                confirmButtonText: '申请退款',
                cancelButtonText: '取消',
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            }
        );
        await licenseApi.refund(license.value.id);
        ElMessage.success('退款申请已提交');
        await fetchDetail();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '退款申请失败');
        }
    }
}

function copyKey() {
    navigator.clipboard.writeText(license.value.license_key).then(() => {
        ElMessage.success('License Key 已复制到剪贴板');
    }).catch(() => {
        ElMessage.warning('复制失败，请手动复制');
    });
}

async function fetchDetail() {
    const id = route.params.id;
    if (!id) return;
    loading.value = true;
    try {
        const { data: res } = await licenseApi.show(id);
        license.value = res.data || {};

        // 获取关联的设备
        const { data: devRes } = await deviceApi.list({ license_id: id, per_page: 50 });
        devices.value = devRes.data?.data || [];
    } catch {
        ElMessage.error('获取 License 详情失败');
    } finally {
        loading.value = false;
    }
}

async function handleDeactivate(dev) {
    try {
        await ElMessageBox.confirm(
            `确定要解绑设备 "${dev.name || dev.hostname || dev.fingerprint}"？解绑后该设备将无法使用此 License。`,
            '确认解绑',
            { confirmButtonText: '确定解绑', cancelButtonText: '取消', type: 'warning' }
        );
        deactivatingId.value = dev.id;
        await deviceApi.deactivate(dev.id);
        ElMessage.success('设备解绑成功');
        devices.value = devices.value.filter(d => d.id !== dev.id);
    } catch {
        // cancelled or error
    } finally {
        deactivatingId.value = null;
    }
}

onMounted(fetchDetail);
</script>

<style scoped>
.mt-4 { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

.status-bar {
    display: flex;
    align-items: center;
    gap: 32px;
    flex-wrap: wrap;
}

.status-section {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.status-label {
    font-size: 12px;
    color: #909399;
}

.license-key {
    font-size: 14px;
    font-weight: 600;
    color: #409eff;
    user-select: all;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.section-title {
    margin: 0 0 8px;
    font-size: 14px;
    color: #606266;
}

.metadata-json {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.6;
    overflow-x: auto;
}

.expiring-text { color: #e6a23c; font-weight: 500; }

.device-usage {
    padding: 8px 0;
}

.usage-hint {
    margin: 12px 0 0;
    font-size: 13px;
    color: #909399;
}

.device-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.device-item:last-child {
    border-bottom: none;
}

.device-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.device-name {
    font-size: 13px;
    font-weight: 500;
    color: #303133;
}

.device-fingerprint code {
    font-size: 11px;
    color: #909399;
}

.self-service-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.self-service-actions .action-btn {
    width: 100%;
}

.action-hint {
    text-align: center;
    color: #909399;
    font-size: 13px;
    margin: 0;
}
</style>
