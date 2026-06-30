<template>
    <div class="portal-licenses">
        <div class="page-header">
            <h2>我的 License</h2>
            <div class="header-actions">
                <el-input
                    v-model="searchKey"
                    placeholder="搜索 License Key..."
                    clearable
                    style="width: 240px"
                    :prefix-icon="Search"
                    @clear="fetchLicenses"
                    @keyup.enter="fetchLicenses"
                />
                <el-button @click="fetchLicenses" :icon="Refresh">刷新</el-button>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value">{{ stats.total }}</div>
                        <div class="mini-label">全部</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #67c23a">{{ stats.active }}</div>
                        <div class="mini-label">活跃中</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never">
                    <div class="mini-stat">
                        <div class="mini-value" style="color: #f56c6c">{{ stats.expired }}</div>
                        <div class="mini-label">已过期</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- License 列表 -->
        <!-- 批量操作栏 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">已选择 {{ selectedIds.length }} 个 License</span>
            <el-button size="small" @click="clearSelection">取消选择</el-button>
            <el-divider direction="vertical" />
            <el-button size="small" type="primary" @click="handleBatchRenew">
                📤 批量续费
            </el-button>
            <el-button size="small" type="success" @click="handleBatchActivate">
                ✅ 批量激活
            </el-button>
            <el-button size="small" @click="handleBatchExport">
                📊 批量导出报表
            </el-button>
        </div>

        <!-- 批量续费对话框 -->
        <el-dialog v-model="renewDialog.visible" title="批量续费" width="450px">
            <el-form :model="renewDialog" label-position="top">
                <el-form-item label="续费时长">
                    <el-radio-group v-model="renewDialog.days">
                        <el-radio-button :value="30">1 个月</el-radio-button>
                        <el-radio-button :value="90">3 个月</el-radio-button>
                        <el-radio-button :value="180">6 个月</el-radio-button>
                        <el-radio-button :value="365">1 年</el-radio-button>
                        <el-radio-button :value="730">2 年</el-radio-button>
                    </el-radio-group>
                </el-form-item>
                <el-form-item label="涉及 License">
                    <el-tag v-for="id in selectedIds" :key="id" size="small" style="margin:2px">
                        #{{ id }}
                    </el-tag>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="renewDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="renewDialog.loading" @click="confirmBatchRenew">
                    确认续费
                </el-button>
            </template>
        </el-dialog>

        <el-card shadow="never">
            <el-table
                :data="licenses"
                v-loading="loading"
                stripe
                @selection-change="onSelectionChange"
            >
                <el-table-column type="selection" width="50" />
                <el-table-column label="License Key" min-width="200">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="$router.push(`/portal/licenses/${row.id}`)">
                            <code>{{ row.license_key }}</code>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="product?.name" label="产品" min-width="120">
                    <template #default="{ row }">{{ row.product?.name || '-' }}</template>
                </el-table-column>
                <el-table-column label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag v-if="row.type === 'trial'" type="warning" size="small">试用</el-tag>
                        <el-tag v-else-if="row.type === 'enterprise'" type="success" size="small">企业版</el-tag>
                        <el-tag v-else-if="row.type === 'development'" size="small">开发版</el-tag>
                        <span v-else>标准</span>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small" effect="dark">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="seats" label="座位数" width="70" align="center" />
                <el-table-column prop="max_devices" label="设备限制" width="80" align="center" />
                <el-table-column prop="expires_at" label="到期时间" width="180">
                    <template #default="{ row }">
                        <template v-if="row.expires_at">
                            <span v-if="expiryInfo(row.expires_at).class" :class="'expiry-badge ' + expiryInfo(row.expires_at).class">
                                {{ expiryInfo(row.expires_at).text }}
                            </span>
                            <span v-else>{{ row.expires_at }}</span>
                        </template>
                        <span v-else>永久</span>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="$router.push(`/portal/licenses/${row.id}`)">
                            详情
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
                    @current-change="fetchLicenses"
                    @size-change="fetchLicenses"
                />
            </div>
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import licenseApi from '@/api/license';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, Refresh } from '@element-plus/icons-vue';

const loading = ref(false);
const licenses = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(10);
const searchKey = ref('');

// 批量选择
const selectedIds = ref([]);
function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id);
}
function clearSelection() {
    selectedIds.value = [];
}

// 批量续费对话框
const renewDialog = reactive({
    visible: false,
    days: 365,
    loading: false,
});

const stats = reactive({
    total: 0,
    active: 0,
    expired: 0,
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

// 过期倒计时
const now = ref(Date.now());
let countdownTimer = null;

function daysUntilExpiry(dateStr) {
    if (!dateStr) return Infinity;
    const diff = new Date(dateStr).getTime() - now.value;
    return diff / (1000 * 60 * 60 * 24);
}

function expiryInfo(dateStr) {
    if (!dateStr) return { text: '永久', class: '', urgent: false };
    const days = daysUntilExpiry(dateStr);
    if (days < 0) return { text: `已过期 ${Math.ceil(Math.abs(days))} 天`, class: 'expiry-overdue', urgent: true };
    if (days < 1) return { text: '今天到期', class: 'expiry-urgent', urgent: true };
    const d = Math.ceil(days);
    if (d <= 3) return { text: `${d} 天后到期`, class: 'expiry-urgent', urgent: true };
    if (d <= 7) return { text: `${d} 天后到期`, class: 'expiry-warning', urgent: false };
    if (d <= 30) return { text: `${d} 天后到期`, class: 'expiry-soon', urgent: false };
    return { text: dateStr, class: '', urgent: false };
}

onMounted(() => {
    // 每分钟更新一次倒计时
    countdownTimer = setInterval(() => { now.value = Date.now(); }, 60000);
});

// 清理定时器（组件卸载时）
import { onUnmounted } from 'vue';
onUnmounted(() => { if (countdownTimer) clearInterval(countdownTimer); });

function statusType(status) { return STATUS_MAP[status]?.type || 'info'; }
function statusLabel(status) { return STATUS_MAP[status]?.label || status; }
function isExpiring(dateStr) {
    if (!dateStr) return false;
    const days = daysUntilExpiry(dateStr);
    return days >= 0 && days <= 30;
}

async function fetchLicenses() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: '-created_at',
        };
        if (searchKey.value) {
            params.search = searchKey.value;
        }
        const { data: res } = await licenseApi.list(params);
        licenses.value = res.data || [];
        total.value = res.meta?.total || res.data?.length || 0;

        // 获取统计数据
        const { data: statsRes } = await licenseApi.stats();
        const s = statsRes.data || {};
        stats.total = s.total || 0;
        stats.active = s.active || 0;
        stats.expired = s.expired || 0;
    } catch {
        ElMessage.error('获取 License 列表失败');
    } finally {
        loading.value = false;
    }
}

// ── 批量操作 ──

/** 批量续费 */
async function handleBatchRenew() {
    if (selectedIds.value.length === 0) return;
    renewDialog.days = 365;
    renewDialog.visible = true;
}

async function confirmBatchRenew() {
    renewDialog.loading = true;
    try {
        const { data: res } = await licenseApi.batchOperation({
            license_ids: selectedIds.value,
            action: 'renew',
            payload: { days: renewDialog.days },
        });
        ElMessage.success(res.message || `批量续费成功`);
        renewDialog.visible = false;
        clearSelection();
        fetchLicenses();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '批量续费失败');
    } finally {
        renewDialog.loading = false;
    }
}

/** 批量激活 */
async function handleBatchActivate() {
    if (selectedIds.value.length === 0) return;
    try {
        await ElMessageBox.confirm(
            `确定要激活选中的 ${selectedIds.value.length} 个 License 吗？`,
            '批量激活',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'info' }
        );
        const { data: res } = await licenseApi.batchOperation({
            license_ids: selectedIds.value,
            action: 'activate',
        });
        ElMessage.success(res.message || `批量激活成功`);
        clearSelection();
        fetchLicenses();
    } catch (e) {
        if (e !== 'cancel') {
            ElMessage.error(e.response?.data?.message || '批量激活失败');
        }
    }
}

/** 批量导出报表 */
async function handleBatchExport() {
    try {
        const params = {};
        if (selectedIds.value.length > 0) {
            params.ids = selectedIds.value.join(',');
        }
        const res = await licenseApi.exportCsv(params);
        // 处理文件下载
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `licenses-export-${new Date().toISOString().slice(0, 10)}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        ElMessage.success('报表导出成功');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '导出失败');
    }
}

onMounted(fetchLicenses);
</script>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-header h2 { margin: 0; }

.header-actions {
    display: flex;
    gap: 8px;
}

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

.expiring-text { color: #e6a23c; font-weight: 500; }
.expiry-badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600; white-space:nowrap; }
.expiry-overdue { background:#fef0f0; color:#f56c6c; }
.expiry-urgent { background:#fdf6ec; color:#e6a23c; animation:pulse 1.5s infinite; }
.expiry-warning { background:#fdf6ec; color:#e6a23c; }
.expiry-soon { background:#f0f9eb; color:#67c23a; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }

/* 批量操作栏 */
.batch-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    margin-bottom: 12px;
    background: #ecf5ff;
    border: 1px solid #b3d8ff;
    border-radius: 6px;
    font-size: 14px;
}
.batch-info {
    font-weight: 600;
    color: #409eff;
}
</style>
