<template>
    <div class="devices-page">
        <div class="page-header">
            <div class="header-left">
                <h2>设备管理</h2>
                <span class="header-subtitle">监控和管理所有激活设备</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">设备总数</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-success);">{{ stats.active }}</div>
                    <div class="stat-label">活跃设备</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-danger);">{{ stats.blacklisted }}</div>
                    <div class="stat-label">黑名单</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-warning);">
                        {{ stats.by_platform ? Object.keys(stats.by_platform).length : 0 }}
                    </div>
                    <div class="stat-label">平台种类</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 第二行统计：信任分分布 -->
        <el-row :gutter="16" class="stats-row" v-if="stats?.trust_buckets">
            <el-col :span="8">
                <el-card shadow="never" class="stat-card mini">
                    <div class="stat-value" style="font-size: 20px; color: var(--el-color-success);">
                        {{ stats.trust_buckets.high }}
                    </div>
                    <div class="stat-label">高信任 (≥80)</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card mini">
                    <div class="stat-value" style="font-size: 20px; color: var(--el-color-warning);">
                        {{ stats.trust_buckets.medium }}
                    </div>
                    <div class="stat-label">中信任 (50-79)</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card mini">
                    <div class="stat-value" style="font-size: 20px; color: var(--el-color-danger);">
                        {{ stats.trust_buckets.low }}
                    </div>
                    <div class="stat-label">低信任 (&lt;50)</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选区域 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item label="搜索">
                    <el-input
                        v-model="filters.search"
                        placeholder="设备指纹 / 主机名 / 平台"
                        clearable
                        style="width: 240px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item label="平台">
                    <el-select v-model="filters.platform" clearable placeholder="全部平台" style="width: 130px" @change="doSearch">
                        <el-option v-for="p in platformOptions" :key="p" :label="p || '未知'" :value="p" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="filters.status" clearable placeholder="全部" style="width: 110px" @change="doSearch">
                        <el-option label="活跃" value="active" />
                        <el-option label="未关联" value="inactive" />
                    </el-select>
                </el-form-item>
                <el-form-item label="黑名单">
                    <el-select v-model="filters.is_blacklisted" clearable placeholder="全部" style="width: 110px" @change="doSearch">
                        <el-option label="是" :value="true" />
                        <el-option label="否" :value="false" />
                    </el-select>
                </el-form-item>
                <el-form-item label="最低信任分">
                    <el-input-number
                        v-model="filters.trust_score_min"
                        :min="0"
                        :max="100"
                        :step="10"
                        size="small"
                        controls-position="right"
                        style="width: 120px"
                        @change="doSearch"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="doSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetFilters">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 批量操作栏 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">已选择 {{ selectedIds.length }} 项</span>
            <el-button size="small" @click="clearSelection">取消选择</el-button>
            <el-button size="small" type="warning" @click="batchAction('deactivate')">批量停用</el-button>
            <el-button size="small" type="danger" @click="batchAction('blacklist')">批量加入黑名单</el-button>
            <el-button size="small" type="primary" @click="batchAction('remove_blacklist')">移出黑名单</el-button>
        </div>

        <!-- 表格 -->
        <el-card shadow="never">
            <el-table
                :data="devices"
                v-loading="loading"
                stripe
                row-key="id"
                @sort-change="handleSortChange"
                @selection-change="(rows) => selectedIds = rows.map(r => r.id)"
            >
                <el-table-column type="selection" width="40" />
                <el-table-column label="设备指纹" min-width="200" prop="fingerprint" sortable="custom">
                    <template #default="{ row }">
                        <div class="fingerprint-cell">
                            <code class="fingerprint-text">{{ row.fingerprint.substring(0, 24) }}...</code>
                            <el-tag
                                v-if="row.is_blacklisted"
                                size="small"
                                type="danger"
                                effect="dark"
                                style="margin-left: 6px;"
                            >
                                黑名单
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="主机名" width="140" prop="hostname" sortable="custom">
                    <template #default="{ row }">
                        {{ row.hostname || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="平台" width="100" prop="platform" sortable="custom">
                    <template #default="{ row }">
                        <el-tag v-if="row.platform" size="small" effect="plain">{{ row.platform }}</el-tag>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="系统版本" width="120" prop="os_version">
                    <template #default="{ row }">
                        {{ row.os_version || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="信任分" width="90" prop="trust_score" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.trust_score)" size="small" effect="dark">
                            {{ row.trust_score }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="虚拟环境" width="90" prop="is_virtual" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.is_virtual ? 'warning' : 'info'" size="small">
                            {{ row.is_virtual ? '是' : '否' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="关联 License" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.license">
                            <el-link type="primary" @click="$router.push(`/licenses/${row.license_id}`)">
                                <code>{{ row.license.license_key?.substring(0, 16) }}...</code>
                            </el-link>
                            <div style="font-size: 12px; color: var(--el-text-color-secondary);">
                                {{ row.license.product?.name || '' }}
                                <template v-if="row.license.product?.name && row.license.customer?.user?.name"> · </template>
                                {{ row.license.customer?.user?.name || '' }}
                            </div>
                        </template>
                        <span v-else class="text-muted">未关联</span>
                    </template>
                </el-table-column>
                <el-table-column label="最后活跃" width="170" prop="last_seen_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.last_seen_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" width="170" prop="created_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openDetail(row)">
                            详情
                        </el-button>
                        <el-button text size="small" type="success" @click="openProfileDialog(row)">
                            画像
                        </el-button>
                        <el-button
                            v-if="!row.is_blacklisted"
                            text
                            size="small"
                            type="danger"
                            @click="handleDeactivate(row)"
                        >
                            停用
                        </el-button>
                        <el-dropdown ref="moreActionRef" trigger="click" @command="(cmd) => handleMoreAction(cmd, row)">
                            <el-button text size="small" type="primary">
                                更多 <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-if="!row.is_blacklisted"
                                        command="blacklist"
                                        divided
                                    >
                                        加入黑名单
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.is_blacklisted"
                                        command="remove_blacklist"
                                    >
                                        移出黑名单
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-wrapper" v-if="total > 0">
                <el-pagination
                    v-model:current-page="page"
                    v-model:page-size="perPage"
                    :page-sizes="[10, 20, 50, 100]"
                    :total="total"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="loadDevices"
                    @current-change="loadDevices"
                />
            </div>
        </el-card>

        <!-- 设备详情 Dialog -->
        <el-dialog
            v-model="detailVisible"
            title="设备详情"
            width="700px"
            :close-on-click-modal="false"
        >
            <div v-if="detailDevice" v-loading="detailLoading">
                <el-descriptions :column="2" border>
                    <el-descriptions-item label="设备 ID" width="120">
                        <code>{{ detailDevice.id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="信任分">
                        <el-tag :type="scoreType(detailDevice.trust_score)" size="small" effect="dark">
                            {{ detailDevice.trust_score }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="设备指纹" :span="2">
                        <code style="word-break: break-all;">{{ detailDevice.fingerprint }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item label="主机名">
                        {{ detailDevice.hostname || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="平台">
                        {{ detailDevice.platform || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="系统版本">
                        {{ detailDevice.os_version || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="虚拟环境">
                        <el-tag :type="detailDevice.is_virtual ? 'warning' : 'info'" size="small">
                            {{ detailDevice.is_virtual ? '是' : '否' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="黑名单">
                        <el-tag :type="detailDevice.is_blacklisted ? 'danger' : 'info'" size="small">
                            {{ detailDevice.is_blacklisted ? '是' : '否' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="关联 License">
                        <template v-if="detailDevice.license">
                            <el-link type="primary" @click="$router.push(`/licenses/${detailDevice.license_id}`)">
                                {{ detailDevice.license.license_key?.substring(0, 24) }}...
                            </el-link>
                        </template>
                        <span v-else class="text-muted">未关联</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="关联产品">
                        {{ detailDevice.license?.product?.name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item label="最后活跃">
                        {{ formatDate(detailDevice.last_seen_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="创建时间">
                        {{ formatDate(detailDevice.created_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="扩展信息" :span="2">
                        <template v-if="detailDevice.metadata && Object.keys(detailDevice.metadata).length">
                            <pre class="metadata-json">{{ JSON.stringify(detailDevice.metadata, null, 2) }}</pre>
                        </template>
                        <span v-else class="text-muted">无</span>
                    </el-descriptions-item>
                </el-descriptions>
                <div class="detail-actions" style="margin-top: 16px;">
                    <el-button
                        v-if="!detailDevice.is_blacklisted"
                        type="danger"
                        @click="handleDeactivate(detailDevice); detailVisible = false"
                    >
                        停用设备
                    </el-button>
                    <el-button
                        v-if="!detailDevice.is_blacklisted"
                        type="warning"
                        @click="handleMoreAction('blacklist', detailDevice); detailVisible = false"
                    >
                        加入黑名单
                    </el-button>
                    <el-button
                        v-if="detailDevice.is_blacklisted"
                        type="primary"
                        @click="handleMoreAction('remove_blacklist', detailDevice); detailVisible = false"
                    >
                        移出黑名单
                    </el-button>
                </div>
            </div>
            <template #footer>
                <el-button @click="detailVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 设备画像 Dialog (M3-24) -->
        <el-dialog v-model="profileVisible" title="设备生命周期画像" width="700px" top="5vh">
            <template v-if="profileLoading">
                <div class="text-center py-4"><el-icon class="is-loading" :size="32"><Loading /></el-icon></div>
            </template>
            <template v-else-if="profileData">
                <!-- 画像摘要 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" :style="{ color: stageColor(profileData.profile.current_stage) }">{{ profileData.profile.stage_label }}</div>
                            <div class="profile-label">当前阶段</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" :style="{ color: trustColor(profileData.profile.trust_level) }">{{ profileData.profile.trust_score }}</div>
                            <div class="profile-label">信任分 / {{ trustLevelLabel(profileData.profile.trust_level) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" style="color: #409eff">{{ profileData.profile.days_active }}</div>
                            <div class="profile-label">活跃天数</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 生命周期时间线 -->
                <h4 class="section-title">生命周期时间线</h4>
                <el-timeline v-if="profileData.timeline.length">
                    <el-timeline-item
                        v-for="(item, idx) in profileData.timeline"
                        :key="idx"
                        :timestamp="formatDate(item.timestamp)"
                        :type="timelineType(item.stage)"
                        :hollow="idx < profileData.timeline.length - 1"
                    >
                        <strong>{{ item.stage_label }}</strong>
                        <span class="ml-2 text-gray-400">信任分: {{ item.trust_score }}</span>
                    </el-timeline-item>
                </el-timeline>
                <div v-else class="text-gray-400 text-sm">暂无事件记录</div>

                <!-- 最近事件 -->
                <h4 class="section-title mt-3">最近生命周期事件</h4>
                <el-table :data="profileData.recent_events" v-if="profileData.recent_events.length" size="small" stripe>
                    <el-table-column label="事件类型" prop="event_type" min-width="120" />
                    <el-table-column label="阶段" width="100">
                        <template #default="{ row }">
                            <el-tag :type="stageTagType(row.stage)" size="small" effect="plain">{{ stageLabel(row.stage) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="信任分变化" width="100">
                        <template #default="{ row }">
                            <span :style="{ color: (row.trust_score_change || 0) >= 0 ? '#67c23a' : '#f56c6c' }">
                                {{ row.trust_score_change > 0 ? '+' : '' }}{{ row.trust_score_change || 0 }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="原因" prop="reason" min-width="150" show-overflow-tooltip />
                    <el-table-column label="触发方式" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.triggered_by === 'auto_detect' ? 'warning' : (row.triggered_by === 'admin' ? 'primary' : 'info')" size="small">
                                {{ { system: '系统', admin: '管理员', auto_detect: '自动检测' }[row.triggered_by] || row.triggered_by }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="时间" width="150">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <div v-else class="text-gray-400 text-sm">暂无生命周期事件</div>

                <!-- 操作按钮 -->
                <div class="mt-3">
                    <el-button size="small" type="warning" @click="handleAdjustTrust(profileDevice)">调整信任分</el-button>
                    <el-button size="small" type="danger" @click="handleMarkSuspicious(profileDevice)" v-if="profileDevice && profileDevice.lifecycle_stage !== 'suspicious'">标记可疑</el-button>
                    <el-button size="small" type="danger" @click="handleRetire(profileDevice)" v-if="profileDevice && profileDevice.lifecycle_stage !== 'retired'">废弃设备</el-button>
                </div>
            </template>
            <template #footer>
                <el-button @click="profileVisible = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 调整信任分 Dialog -->
        <el-dialog v-model="trustDialogVisible" title="调整信任分" width="400px">
            <el-form label-width="100px" size="small">
                <el-form-item label="调整值">
                    <el-input-number v-model="trustDelta" :min="-100" :max="100" style="width:200px" />
                    <div class="text-gray-400 text-xs mt-1">正值提高信任分，负值降低信任分</div>
                </el-form-item>
                <el-form-item label="原因" required>
                    <el-input v-model="trustReason" type="textarea" :rows="2" maxlength="500" placeholder="说明调整原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="trustDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="trustSubmitting" @click="confirmAdjustTrust">确认</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, ArrowDown, Loading } from '@element-plus/icons-vue';
import deviceApi from '@/api/device';

const loading = ref(false);
const devices = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);
const selectedIds = ref([]);
const stats = ref(null);

// 详情 Dialog
const detailVisible = ref(false);
const detailLoading = ref(false);
const detailDevice = ref(null);

const filters = reactive({
    search: '',
    platform: '',
    status: '',
    is_blacklisted: '',
    trust_score_min: 0,
});
const sortField = ref('-last_seen_at');

// 平台选项（从平台分布统计中解析 + 常见平台）
const commonPlatforms = ['windows', 'linux', 'macos', 'android', 'ios', 'docker', 'k8s', 'unknown'];
const platformOptions = ref([...commonPlatforms]);

function scoreType(score) {
    if (score >= 80) return 'success';
    if (score >= 50) return 'warning';
    return 'danger';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadStats() {
    try {
        const { data: res } = await deviceApi.stats();
        if (res.success) {
            stats.value = res.data;
            // 合并平台选项
            if (res.data.by_platform) {
                const extraPlatforms = Object.keys(res.data.by_platform).filter(p => !platformOptions.value.includes(p));
                if (extraPlatforms.length) {
                    platformOptions.value = [...new Set([...commonPlatforms, ...extraPlatforms])];
                }
            }
        }
    } catch {
        // ignore
    }
}

async function loadDevices() {
    loading.value = true;
    try {
        const params = {
            page: page.value,
            per_page: perPage.value,
            sort: sortField.value,
        };
        if (filters.search) params.search = filters.search;
        if (filters.platform) params['filter.platform'] = filters.platform;
        if (filters.is_blacklisted !== '') params['filter.is_blacklisted'] = filters.is_blacklisted;
        if (filters.trust_score_min > 0) params['filter.trust_score_min'] = filters.trust_score_min;
        if (filters.status) {
            if (filters.status === 'active') params['filter.license_id'] = 'not_null';
            if (filters.status === 'inactive') params['filter.license_id'] = 'null';
        }

        const { data: res } = await deviceApi.list(params);
        devices.value = res.data?.data || [];
        total.value = res.data?.total || 0;
    } catch {
        devices.value = [];
    } finally {
        loading.value = false;
    }
}

function doSearch() {
    page.value = 1;
    loadDevices();
}

function resetFilters() {
    filters.search = '';
    filters.platform = '';
    filters.status = '';
    filters.is_blacklisted = '';
    filters.trust_score_min = 0;
    doSearch();
}

function handleSortChange({ prop, order }) {
    if (!order) {
        sortField.value = '-last_seen_at';
    } else {
        sortField.value = (order === 'desc' ? '-' : '') + (prop || 'last_seen_at');
    }
    loadDevices();
}

function clearSelection() {
    selectedIds.value = [];
}

// 详情
async function openDetail(row) {
    detailLoading.value = true;
    detailVisible.value = true;
    try {
        const { data: res } = await deviceApi.show(row.id);
        detailDevice.value = res.data || res;
    } catch {
        detailDevice.value = row;
    } finally {
        detailLoading.value = false;
    }
}

// 停用
async function handleDeactivate(row) {
    try {
        await ElMessageBox.confirm(
            `确定要停用设备 ${row.fingerprint.substring(0, 16)}... 吗？`,
            '确认操作',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await deviceApi.deactivate(row.id);
        ElMessage.success('设备已停用');
        loadDevices();
        loadStats();
    } catch {
        // cancelled
    }
}

// 更多操作
async function handleMoreAction(cmd, row) {
    const actions = {
        blacklist: { confirm: '确定要加入黑名单吗？', msg: '已加入黑名单' },
        remove_blacklist: { confirm: '确定要移出黑名单吗？', msg: '已移出黑名单' },
    };
    const action = actions[cmd];
    if (!action) return;

    try {
        await ElMessageBox.confirm(action.confirm, '确认操作', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning',
        });
        if (cmd === 'blacklist') {
            await deviceApi.deactivate(row.id, true);
        } else if (cmd === 'remove_blacklist') {
            await deviceApi.batch([row.id], 'remove_blacklist');
        }
        ElMessage.success(action.msg);
        loadDevices();
        loadStats();
    } catch {
        // cancelled
    }
}

// 批量操作
async function batchAction(action) {
    if (selectedIds.value.length === 0) {
        ElMessage.warning('请先选择设备');
        return;
    }
    const actionLabels = {
        deactivate: { label: '停用', verb: '停用' },
        blacklist: { label: '加入黑名单', verb: '加入黑名单' },
        remove_blacklist: { label: '移出黑名单', verb: '移出黑名单' },
    };
    const info = actionLabels[action];
    if (!info) return;

    try {
        await ElMessageBox.confirm(
            `确定要${info.verb}选中的 ${selectedIds.value.length} 台设备吗？`,
            '批量操作',
            { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
        );
        await deviceApi.batch(selectedIds.value, action);
        ElMessage.success(`批量${info.label}成功`);
        clearSelection();
        loadDevices();
        loadStats();
    } catch {
        // cancelled
    }
}

// ═══════════════ 生命周期画像 (M3-24) ═══════════════

const profileVisible = ref(false);
const profileLoading = ref(false);
const profileData = ref(null);
const profileDevice = ref(null);
const trustDialogVisible = ref(false);
const trustDelta = ref(0);
const trustReason = ref('');
const trustSubmitting = ref(false);

function stageColor(stage) {
    return { new: '#909399', onboarding: '#e6a23c', stable: '#67c23a', suspicious: '#f56c6c', retired: '#c0c4cc' }[stage] || '#909399';
}
function trustColor(level) {
    return { high: '#67c23a', medium: '#e6a23c', low: '#f56c6c', none: '#c0c4cc' }[level] || '#909399';
}
function trustLevelLabel(level) {
    return { high: '高信任', medium: '中信任', low: '低信任', none: '无信任' }[level] || level;
}
function stageLabel(stage) {
    return { new: '首次出现', onboarding: '逐步信任', stable: '长期稳定', suspicious: '异常/可疑', retired: '已废弃' }[stage] || stage;
}
function stageTagType(stage) {
    return { new: 'info', onboarding: 'warning', stable: 'success', suspicious: 'danger', retired: 'info' }[stage] || 'info';
}
function timelineType(stage) {
    return { new: 'info', onboarding: 'warning', stable: 'success', suspicious: 'danger', retired: 'info' }[stage] || 'info';
}

async function openProfileDialog(row) {
    profileDevice.value = row;
    profileVisible.value = true;
    profileLoading.value = true;
    profileData.value = null;
    try {
        const { data: res } = await deviceApi.profile(row.id);
        if (res.success) profileData.value = res.data;
    } catch {
        ElMessage.error('加载画像失败');
    } finally {
        profileLoading.value = false;
    }
}

async function handleAdjustTrust(row) {
    trustDelta.value = 0;
    trustReason.value = '';
    trustDialogVisible.value = true;
}
async function confirmAdjustTrust() {
    if (!trustReason.value.trim()) { ElMessage.warning('请输入调整原因'); return; }
    trustSubmitting.value = true;
    try {
        const { data: res } = await deviceApi.adjustTrust(profileDevice.value.id, trustDelta.value, trustReason.value);
        if (res.success) {
            ElMessage.success(`信任分已调整，新分数: ${res.data.new_trust_score}`);
            trustDialogVisible.value = false;
            // Reload profile
            openProfileDialog(profileDevice.value);
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '调整失败');
    } finally {
        trustSubmitting.value = false;
    }
}
async function handleMarkSuspicious(row) {
    try {
        await ElMessageBox.confirm(`确定将设备标记为可疑？`, '确认操作', { type: 'warning' });
        const { data: res } = await deviceApi.markSuspicious(row.id, '管理员手动标记可疑');
        if (res.success) { ElMessage.success('已标记为可疑'); openProfileDialog(row); }
    } catch {
        // cancelled
    }
}
async function handleRetire(row) {
    try {
        await ElMessageBox.confirm(`确定废弃该设备？此操作将移除License关联并加入黑名单。`, '确认操作', { type: 'warning', confirmButtonText: '确认废弃', cancelButtonText: '取消' });
        const { data: res } = await deviceApi.retire(row.id, '管理员手动废弃');
        if (res.success) { ElMessage.success('设备已废弃'); openProfileDialog(row); }
    } catch {
        // cancelled
    }
}

onMounted(() => {
    loadDevices();
    loadStats();
});
</script>

<style scoped>
.devices-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 {
    margin: 0;
    font-size: 20px;
}
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.stats-row { margin-bottom: 12px; }
.stat-card { text-align: center; }
.stat-card.mini { padding: 4px 0; }
.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--el-color-primary);
    line-height: 1.2;
}
.stat-label {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
}

.filter-card { margin-bottom: 16px; }

.fingerprint-cell {
    display: flex;
    align-items: center;
}
.fingerprint-text {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 11px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.text-muted { color: var(--el-text-color-placeholder); }

.metadata-json {
    font-size: 11px;
    background: var(--el-fill-color-lighter);
    padding: 8px;
    border-radius: 4px;
    max-height: 200px;
    overflow: auto;
    margin: 0;
}

.batch-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 0 8px;
}
.batch-info {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-right: 8px;
}

.pagination-wrapper {
    display: flex;
    justify-content: flex-end;
    margin-top: 16px;
}

.el-card :deep(.el-card__body) { padding: 16px; }
.filter-card :deep(.el-card__body) { padding: 12px 16px; }
:deep(.el-form--inline .el-form-item) { margin-bottom: 0; }

/* 画像 dialog */
.profile-stat-card { text-align: center; }
.profile-stat-card .profile-stat { font-size: 24px; font-weight: 700; }
.profile-stat-card .profile-label { font-size: 12px; color: #909399; margin-top: 4px; }
.section-title { font-size: 14px; font-weight: 600; margin: 0 0 10px; padding: 0; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.ml-2 { margin-left: 8px; }
.text-center { text-align: center; }
.text-gray-400 { color: #909399; }
.text-xs { font-size: 12px; }
.text-sm { font-size: 13px; }
.py-4 { padding: 16px 0; }
</style>
