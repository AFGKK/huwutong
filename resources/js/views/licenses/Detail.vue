<template>
    <div class="license-detail" v-loading="loading">
        <el-page-header @back="$router.push('/licenses')" :content="`License #${id}`" />

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
            <div class="status-info-bar" v-if="statusInfo">
                <div class="info-item">
                    <span class="info-label">可转移状态：</span>
                    <template v-if="statusInfo.available_transitions?.length">
                        <el-tag v-for="t in statusInfo.available_transitions" :key="t" size="small" style="margin-right: 4px">
                            {{ statusLabel(t) }}
                        </el-tag>
                    </template>
                    <span v-else class="info-value">终态（不可变更）</span>
                </div>
                <div class="info-item">
                    <span class="info-label">活跃设备：</span>
                    <span class="info-value">{{ statusInfo.device_count }} / {{ statusInfo.max_devices }}</span>
                </div>
            </div>
        </el-card>

        <!-- 详情 + 编辑 -->
        <el-card class="mt-4">
            <template #header>
                <div class="card-header">
                    <span>基本信息</span>
                    <el-button size="small" @click="openEdit">编辑</el-button>
                </div>
            </template>
            <el-descriptions :column="3" border>
                <el-descriptions-item label="产品" :span="1">{{ license.product?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="客户" :span="1">{{ license.customer?.name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="设备限制" :span="1">{{ license.max_devices }}</el-descriptions-item>
                <el-descriptions-item label="已激活设备" :span="1">{{ statusInfo?.device_count || 0 }}</el-descriptions-item>
                <el-descriptions-item label="座位数" :span="1">{{ license.seats || 1 }}</el-descriptions-item>
                <el-descriptions-item label="过期时间" :span="1">
                    <span :class="expiryClass">{{ license.expires_at || '永久' }}</span>
                </el-descriptions-item>
                <el-descriptions-item label="创建时间" :span="1">{{ license.created_at }}</el-descriptions-item>
                <el-descriptions-item label="激活时间" :span="1">{{ license.activated_at || '从未激活' }}</el-descriptions-item>
                <el-descriptions-item label="最后修改" :span="1">{{ license.updated_at }}</el-descriptions-item>
            </el-descriptions>
        </el-card>

        <!-- 状态操作 -->
        <el-card class="mt-4">
            <template #header>
                <span>状态操作</span>
            </template>
            <div class="action-buttons">
                <el-button type="danger" v-if="can('revoke')" @click="handleAction('revoke')" :icon="Remove">
                    吊销
                </el-button>
                <el-button type="warning" v-if="can('suspend')" @click="handleAction('suspend')" :icon="VideoPause">
                    暂停
                </el-button>
                <el-button type="warning" v-if="can('freeze')" @click="handleAction('freeze')" :icon="ColdDrink">
                    冻结
                </el-button>
                <el-button type="success" v-if="can('restore')" @click="handleAction('restore')" :icon="Refresh">
                    恢复
                </el-button>
                <el-button type="danger" v-if="can('blacklist')" @click="handleAction('blacklist')" :icon="WarningFilled">
                    加入黑名单
                </el-button>
                <el-button type="info" v-if="can('refund')" @click="handleAction('refund')" :icon="Money">
                    退款
                </el-button>
                <el-divider direction="vertical" />
                <el-button type="danger" plain @click="handleDelete" :icon="Delete">
                    删除
                </el-button>
                <el-button type="warning" plain v-if="license.deleted_at" @click="handleRestoreFromTrash" :icon="Refresh">
                    从回收站恢复
                </el-button>
            </div>
        </el-card>

        <!-- 关联设备 -->
        <el-card class="mt-4">
            <template #header>
                <span>关联设备 ({{ devices.length }})</span>
            </template>
            <el-table v-if="devices.length" :data="devices" stripe>
                <el-table-column prop="fingerprint" label="设备指纹" min-width="200">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="hostname" label="主机名" width="150" />
                <el-table-column prop="platform" label="平台" width="120" />
                <el-table-column prop="trust_score" label="信任分" width="90">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.trust_score)" size="small">{{ row.trust_score }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="last_activated_at" label="最后激活" width="170" />
            </el-table>
            <el-empty v-else description="暂无关联设备" />
        </el-card>

        <!-- 激活记录 -->
        <el-card class="mt-4">
            <template #header>
                <span>激活记录 ({{ activations.length }})</span>
            </template>
            <el-table v-if="activations.length" :data="activations" stripe>
                <el-table-column prop="device_fingerprint" label="设备指纹" min-width="200">
                    <template #default="{ row }">
                        <code class="small-text">{{ row.device_fingerprint }}</code>
                    </template>
                </el-table-column>
                <el-table-column prop="ip_address" label="IP 地址" width="140" />
                <el-table-column prop="action" label="操作" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.action === 'activate' ? 'success' : 'danger'" size="small">
                            {{ row.action === 'activate' ? '激活' : '停用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="时间" width="170" />
            </el-table>
            <el-empty v-else description="暂无激活记录" />
        </el-card>

        <!-- 编辑对话框 -->
        <el-dialog v-model="showEdit" title="编辑 License" width="560px">
            <el-form ref="editFormRef" :model="editForm" label-width="100px">
                <el-form-item label="产品">
                    <el-select v-model="editForm.product_id" placeholder="选择产品" filterable style="width: 100%">
                        <el-option v-for="p in products" :key="p.id" :label="p.name" :value="p.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="客户">
                    <el-select v-model="editForm.customer_id" placeholder="选择客户" filterable style="width: 100%">
                        <el-option v-for="c in customers" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="类型">
                    <el-select v-model="editForm.type" style="width: 100%">
                        <el-option label="标准" value="standard" />
                        <el-option label="试用" value="trial" />
                        <el-option label="企业版" value="enterprise" />
                        <el-option label="开发版" value="development" />
                    </el-select>
                </el-form-item>
                <el-form-item label="过期时间">
                    <el-date-picker
                        v-model="editForm.expires_at"
                        type="datetime"
                        placeholder="留空为永久"
                        value-format="YYYY-MM-DD HH:mm:ss"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item label="设备限制">
                    <el-input-number v-model="editForm.max_devices" :min="1" :max="9999" />
                </el-form-item>
                <el-form-item label="座位数">
                    <el-input-number v-model="editForm.seats" :min="1" :max="99999" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showEdit = false">取消</el-button>
                <el-button type="primary" :loading="updating" @click="confirmEdit">保存修改</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import licenseApi from '@/api/license';
import productApi from '@/api/product';
import customerApi from '@/api/customer';
import {
    Remove, VideoPause, ColdDrink, Refresh,
    WarningFilled, Money, Delete,
} from '@element-plus/icons-vue';

const route = useRoute();
const router = useRouter();
const id = computed(() => route.params.id);
const loading = ref(false);
const updating = ref(false);
const showEdit = ref(false);
const editFormRef = ref(null);
const license = ref({});
const statusInfo = ref(null);
const devices = ref([]);
const activations = ref([]);
const products = ref([]);
const customers = ref([]);

const editForm = reactive({
    product_id: null,
    customer_id: null,
    type: 'standard',
    expires_at: null,
    max_devices: 1,
    seats: 1,
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
function scoreType(score) {
    if (score >= 80) return 'success';
    if (score >= 60) return 'warning';
    return 'danger';
}

const expiryClass = computed(() => {
    if (!license.value.expires_at) return '';
    const now = Date.now();
    const expiry = new Date(license.value.expires_at).getTime();
    if (expiry < now) return 'expired-text';
    return (expiry - now) / 86400000 <= 7 ? 'expiring-text' : '';
});

function can(action) {
    const s = license.value.status;
    const allowed = {
        revoke: ['active', 'suspended', 'frozen', 'pending'],
        suspend: ['active'],
        freeze: ['active'],
        restore: ['suspended', 'frozen'],
        blacklist: ['active', 'suspended', 'frozen', 'expired', 'pending'],
        refund: ['active', 'suspended', 'frozen'],
    };
    return allowed[action]?.includes(s);
}

async function loadOptions() {
    try {
        const [pRes, cRes] = await Promise.all([
            productApi.list({ per_page: 999 }),
            customerApi.list({ per_page: 999 }),
        ]);
        products.value = pRes.data?.data || [];
        customers.value = cRes.data?.data || [];
    } catch {
        // ignore
    }
}

async function fetchDetail() {
    loading.value = true;
    try {
        const { data: res } = await licenseApi.show(id.value);
        license.value = res.data?.license || res.data;
        statusInfo.value = res.data?.status_info || null;
        devices.value = res.data?.devices || license.value?.devices || [];
        activations.value = res.data?.activations || license.value?.activations || [];
    } catch {
        ElMessage.error('获取 License 详情失败');
    } finally {
        loading.value = false;
    }
}

function copyKey() {
    navigator.clipboard.writeText(license.value.license_key);
    ElMessage.success('License Key 已复制');
}

function openEdit() {
    Object.assign(editForm, {
        product_id: license.value.product_id,
        customer_id: license.value.customer_id,
        type: license.value.type,
        expires_at: license.value.expires_at,
        max_devices: license.value.max_devices || 1,
        seats: license.value.seats || 1,
    });
    showEdit.value = true;
}

async function confirmEdit() {
    updating.value = true;
    try {
        const payload = {};
        if (editForm.product_id) payload.product_id = editForm.product_id;
        if (editForm.customer_id) payload.customer_id = editForm.customer_id;
        if (editForm.type) payload.type = editForm.type;
        payload.expires_at = editForm.expires_at || null;
        payload.max_devices = editForm.max_devices;
        payload.seats = editForm.seats;

        await licenseApi.update(id.value, payload);
        ElMessage.success('License 已更新');
        showEdit.value = false;
        fetchDetail();
    } catch {
        ElMessage.error('更新失败');
    } finally {
        updating.value = false;
    }
}

async function handleAction(action) {
    const labels = {
        revoke: '吊销', suspend: '暂停', freeze: '冻结',
        restore: '恢复', blacklist: '加入黑名单', refund: '退款',
    };
    try {
        await ElMessageBox.confirm(`确定要${labels[action]}此 License 吗？`, '确认操作', { type: 'warning' });
        const apiMap = {
            revoke: licenseApi.revoke, suspend: licenseApi.suspend, freeze: licenseApi.freeze,
            restore: licenseApi.restore, blacklist: licenseApi.blacklist, refund: licenseApi.refund,
        };
        await apiMap[action](id.value);
        ElMessage.success(`${labels[action]}成功`);
        fetchDetail();
    } catch {
        // cancelled
    }
}

async function handleDelete() {
    try {
        await ElMessageBox.confirm(
            `确定要删除此 License 吗？删除后可在回收站恢复。`,
            '确认删除',
            { confirmButtonText: '确认删除', cancelButtonText: '取消', type: 'warning' },
        );
        await licenseApi.destroy(id.value);
        ElMessage.success('License 已移至回收站');
        router.push('/licenses');
    } catch {
        // cancelled
    }
}

async function handleRestoreFromTrash() {
    try {
        await ElMessageBox.confirm(
            `确定要从回收站恢复此 License 吗？`,
            '确认恢复',
            { confirmButtonText: '确认恢复', cancelButtonText: '取消', type: 'warning' },
        );
        await licenseApi.restoreFromTrash(id.value);
        ElMessage.success('License 已从回收站恢复');
        fetchDetail();
    } catch {
        // cancelled
    }
}

onMounted(() => {
    loadOptions();
    fetchDetail();
});
</script>

<style scoped>
.mt-4 { margin-top: 16px; }
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status-bar {
    display: flex;
    align-items: center;
    gap: 40px;
    flex-wrap: wrap;
}
.status-section {
    display: flex;
    align-items: center;
    gap: 8px;
}
.status-label { font-size: 13px; color: #909399; }
.license-key { font-size: 14px; letter-spacing: 1px; }
.status-info-bar {
    display: flex;
    gap: 32px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #ebeef5;
    flex-wrap: wrap;
}
.info-item {
    display: flex;
    align-items: center;
    gap: 6px;
}
.info-label { font-size: 13px; color: #909399; }
.info-value { font-size: 13px; color: #303133; }
.action-buttons { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
.small-text { font-size: 11px; }
.expired-text { color: #f56c6c; text-decoration: line-through; }
.expiring-text { color: #e6a23c; font-weight: 600; }
</style>