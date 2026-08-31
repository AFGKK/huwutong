<template>
    <div class="devices-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t(`${P}.title`) }}</h2>
                <span class="header-subtitle">{{ t(`${P}.subtitle`) }}</span>
            </div>
        </div>

        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stats-row" v-if="stats">
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t(`${P}.stat_total`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-success);">{{ stats.active }}</div>
                    <div class="stat-label">{{ t(`${P}.stat_active`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-danger);">{{ stats.blacklisted }}</div>
                    <div class="stat-label">{{ t(`${P}.stat_blacklisted`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never" class="stat-card">
                    <div class="stat-value" style="color: var(--el-color-warning);">
                        {{ stats.by_platform ? Object.keys(stats.by_platform).length : 0 }}
                    </div>
                    <div class="stat-label">{{ t(`${P}.stat_platforms`) }}</div>
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
                    <div class="stat-label">{{ t(`${P}.stat_trust_high`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card mini">
                    <div class="stat-value" style="font-size: 20px; color: var(--el-color-warning);">
                        {{ stats.trust_buckets.medium }}
                    </div>
                    <div class="stat-label">{{ t(`${P}.stat_trust_medium`) }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="never" class="stat-card mini">
                    <div class="stat-value" style="font-size: 20px; color: var(--el-color-danger);">
                        {{ stats.trust_buckets.low }}
                    </div>
                    <div class="stat-label">{{ t(`${P}.stat_trust_low`) }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 筛选区域 -->
        <el-card shadow="never" class="filter-card">
            <el-form :model="filters" inline>
                <el-form-item :label="t('actions.search')">
                    <el-input
                        v-model="filters.search"
                        :placeholder="t(`${P}.search_ph`)"
                        clearable
                        style="width: 240px"
                        @keyup.enter="doSearch"
                    />
                </el-form-item>
                <el-form-item :label="t(`${P}.filter_platform`)">
                    <el-select v-model="filters.platform" clearable :placeholder="t(`${P}.all_platforms`)" style="width: 130px" @change="doSearch">
                        <el-option v-for="p in platformOptions" :key="p" :label="p || t(`${P}.unknown`)" :value="p" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.filter_status`)">
                    <el-select v-model="filters.status" clearable :placeholder="t(`${P}.all`)" style="width: 110px" @change="doSearch">
                        <el-option :label="t(`${P}.status_active`)" value="active" />
                        <el-option :label="t(`${P}.status_inactive`)" value="inactive" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.filter_blacklist`)">
                    <el-select v-model="filters.is_blacklisted" clearable :placeholder="t(`${P}.all`)" style="width: 110px" @change="doSearch">
                        <el-option :label="t(`${P}.yes`)" :value="true" />
                        <el-option :label="t(`${P}.no`)" :value="false" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t(`${P}.filter_trust_min`)">
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
                        {{ t('actions.search') }}
                    </el-button>
                    <el-button @click="resetFilters">{{ t('actions.reset') }}</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 批量操作栏 -->
        <div class="batch-bar" v-if="selectedIds.length > 0">
            <span class="batch-info">{{ t(`${P}.selected_count`, { n: selectedIds.length }) }}</span>
            <el-button size="small" @click="clearSelection">{{ t(`${P}.deselect`) }}</el-button>
            <el-button size="small" type="warning" @click="batchAction('deactivate')">{{ t(`${P}.batch_deactivate`) }}</el-button>
            <el-button size="small" type="danger" @click="batchAction('blacklist')">{{ t(`${P}.batch_blacklist`) }}</el-button>
            <el-button size="small" type="primary" @click="batchAction('remove_blacklist')">{{ t(`${P}.remove_blacklist`) }}</el-button>
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
                <el-table-column :label="t(`${P}.col_fingerprint`)" min-width="200" prop="fingerprint" sortable="custom">
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
                                {{ t(`${P}.tag_blacklist`) }}
                            </el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_hostname`)" width="140" prop="hostname" sortable="custom">
                    <template #default="{ row }">
                        {{ row.hostname || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_platform`)" width="100" prop="platform" sortable="custom">
                    <template #default="{ row }">
                        <el-tag v-if="row.platform" size="small" effect="plain">{{ row.platform }}</el-tag>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_os_version`)" width="120" prop="os_version">
                    <template #default="{ row }">
                        {{ row.os_version || '-' }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_trust_score`)" width="90" prop="trust_score" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.trust_score)" size="small" effect="dark">
                            {{ row.trust_score }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_virtual`)" width="90" prop="is_virtual" sortable="custom">
                    <template #default="{ row }">
                        <el-tag :type="row.is_virtual ? 'warning' : 'info'" size="small">
                            {{ yesNo(row.is_virtual) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_license`)" min-width="200">
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
                        <span v-else class="text-muted">{{ t(`${P}.unlinked`) }}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_last_seen`)" width="170" prop="last_seen_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.last_seen_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_created_at`)" width="170" prop="created_at" sortable="custom">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_actions`)" width="220" fixed="right">
                    <template #default="{ row }">
                        <el-button text size="small" type="primary" @click="openDetail(row)">
                            {{ t(`${P}.detail`) }}
                        </el-button>
                        <el-button text size="small" type="success" @click="openProfileDialog(row)">
                            {{ t(`${P}.profile`) }}
                        </el-button>
                        <el-button
                            v-if="!row.is_blacklisted"
                            text
                            size="small"
                            type="danger"
                            @click="handleDeactivate(row)"
                        >
                            {{ t(`${P}.deactivate`) }}
                        </el-button>
                        <el-dropdown ref="moreActionRef" trigger="click" @command="(cmd) => handleMoreAction(cmd, row)">
                            <el-button text size="small" type="primary">
                                {{ t('actions.more') }} <el-icon><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item
                                        v-if="!row.is_blacklisted"
                                        command="blacklist"
                                        divided
                                    >
                                        {{ t(`${P}.blacklist`) }}
                                    </el-dropdown-item>
                                    <el-dropdown-item
                                        v-if="row.is_blacklisted"
                                        command="remove_blacklist"
                                    >
                                        {{ t(`${P}.remove_blacklist`) }}
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
            :title="t(`${P}.detail_title`)"
            width="700px"
            :close-on-click-modal="false"
        >
            <div v-if="detailDevice" v-loading="detailLoading">
                <el-descriptions :column="2" border>
                    <el-descriptions-item :label="t(`${P}.label_device_id`)" width="120">
                        <code>{{ detailDevice.id }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_trust_score`)">
                        <el-tag :type="scoreType(detailDevice.trust_score)" size="small" effect="dark">
                            {{ detailDevice.trust_score }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_fingerprint`)" :span="2">
                        <code style="word-break: break-all;">{{ detailDevice.fingerprint }}</code>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_hostname`)">
                        {{ detailDevice.hostname || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_platform`)">
                        {{ detailDevice.platform || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_os_version`)">
                        {{ detailDevice.os_version || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_virtual`)">
                        <el-tag :type="detailDevice.is_virtual ? 'warning' : 'info'" size="small">
                            {{ yesNo(detailDevice.is_virtual) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_blacklist`)">
                        <el-tag :type="detailDevice.is_blacklisted ? 'danger' : 'info'" size="small">
                            {{ yesNo(detailDevice.is_blacklisted) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_license`)">
                        <template v-if="detailDevice.license">
                            <el-link type="primary" @click="$router.push(`/licenses/${detailDevice.license_id}`)">
                                {{ detailDevice.license.license_key?.substring(0, 24) }}...
                            </el-link>
                        </template>
                        <span v-else class="text-muted">{{ t(`${P}.unlinked`) }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_product`)">
                        {{ detailDevice.license?.product?.name || '-' }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_last_seen`)">
                        {{ formatDate(detailDevice.last_seen_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_created_at`)">
                        {{ formatDate(detailDevice.created_at) }}
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_metadata`)" :span="2">
                        <template v-if="detailDevice.metadata && Object.keys(detailDevice.metadata).length">
                            <pre class="metadata-json">{{ JSON.stringify(detailDevice.metadata, null, 2) }}</pre>
                        </template>
                        <span v-else class="text-muted">{{ t(`${P}.none`) }}</span>
                    </el-descriptions-item>
                </el-descriptions>
                <div class="detail-actions" style="margin-top: 16px;">
                    <el-button
                        v-if="!detailDevice.is_blacklisted"
                        type="danger"
                        @click="handleDeactivate(detailDevice); detailVisible = false"
                    >
                        {{ t(`${P}.deactivate_device`) }}
                    </el-button>
                    <el-button
                        v-if="!detailDevice.is_blacklisted"
                        type="warning"
                        @click="handleMoreAction('blacklist', detailDevice); detailVisible = false"
                    >
                        {{ t(`${P}.blacklist`) }}
                    </el-button>
                    <el-button
                        v-if="detailDevice.is_blacklisted"
                        type="primary"
                        @click="handleMoreAction('remove_blacklist', detailDevice); detailVisible = false"
                    >
                        {{ t(`${P}.remove_blacklist`) }}
                    </el-button>
                </div>
            </div>
            <template #footer>
                <el-button @click="detailVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 设备画像 Dialog (M3-24) -->
        <el-dialog v-model="profileVisible" :title="t(`${P}.profile_title`)" width="700px" top="5vh">
            <template v-if="profileLoading">
                <div class="text-center py-4"><el-icon class="is-loading" :size="32"><Loading /></el-icon></div>
            </template>
            <template v-else-if="profileData">
                <!-- 画像摘要 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" :style="{ color: stageColor(profileData.profile.current_stage) }">{{ profileData.profile.stage_label }}</div>
                            <div class="profile-label">{{ t(`${P}.current_stage`) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" :style="{ color: trustColor(profileData.profile.trust_level) }">{{ profileData.profile.trust_score }}</div>
                            <div class="profile-label">{{ t(`${P}.trust_score_with_level`, { level: trustLevelLabel(profileData.profile.trust_level) }) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="hover" class="profile-stat-card">
                            <div class="profile-stat" style="color: #0f172a">{{ profileData.profile.days_active }}</div>
                            <div class="profile-label">{{ t(`${P}.days_active`) }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <!-- 生命周期时间线 -->
                <h4 class="section-title">{{ t(`${P}.timeline_title`) }}</h4>
                <el-timeline v-if="profileData.timeline.length">
                    <el-timeline-item
                        v-for="(item, idx) in profileData.timeline"
                        :key="idx"
                        :timestamp="formatDate(item.timestamp)"
                        :type="timelineType(item.stage)"
                        :hollow="idx < profileData.timeline.length - 1"
                    >
                        <strong>{{ item.stage_label }}</strong>
                        <span class="ml-2 text-gray-400">{{ t(`${P}.timeline_trust`, { score: item.trust_score }) }}</span>
                    </el-timeline-item>
                </el-timeline>
                <div v-else class="text-gray-400 text-sm">{{ t(`${P}.no_timeline`) }}</div>

                <!-- 最近事件 -->
                <h4 class="section-title mt-3">{{ t(`${P}.recent_events_title`) }}</h4>
                <el-table :data="profileData.recent_events" v-if="profileData.recent_events.length" size="small" stripe>
                    <el-table-column :label="t(`${P}.col_event_type`)" prop="event_type" min-width="120" />
                    <el-table-column :label="t(`${P}.col_stage`)" width="100">
                        <template #default="{ row }">
                            <el-tag :type="stageTagType(row.stage)" size="small" effect="plain">{{ stageLabel(row.stage) }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${P}.col_trust_change`)" width="100">
                        <template #default="{ row }">
                            <span :style="{ color: (row.trust_score_change || 0) >= 0 ? '#67c23a' : '#f56c6c' }">
                                {{ row.trust_score_change > 0 ? '+' : '' }}{{ row.trust_score_change || 0 }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${P}.col_reason`)" prop="reason" min-width="150" show-overflow-tooltip />
                    <el-table-column :label="t(`${P}.col_trigger`)" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.triggered_by === 'auto_detect' ? 'warning' : (row.triggered_by === 'admin' ? 'primary' : 'info')" size="small">
                                {{ triggerLabel(row.triggered_by) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${P}.col_time`)" width="150">
                        <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
                <div v-else class="text-gray-400 text-sm">{{ t(`${P}.no_events`) }}</div>

                <!-- 操作按钮 -->
                <div class="mt-3">
                    <el-button size="small" type="warning" @click="handleAdjustTrust(profileDevice)">{{ t(`${P}.adjust_trust`) }}</el-button>
                    <el-button size="small" type="danger" @click="handleMarkSuspicious(profileDevice)" v-if="profileDevice && profileDevice.lifecycle_stage !== 'suspicious'">{{ t(`${P}.mark_suspicious`) }}</el-button>
                    <el-button size="small" type="danger" @click="handleRetire(profileDevice)" v-if="profileDevice && profileDevice.lifecycle_stage !== 'retired'">{{ t(`${P}.retire_device`) }}</el-button>
                </div>
            </template>
            <template #footer>
                <el-button @click="profileVisible = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- 调整信任分 Dialog -->
        <el-dialog v-model="trustDialogVisible" :title="t(`${P}.adjust_trust_title`)" width="400px">
            <el-form label-width="100px" size="small">
                <el-form-item :label="t(`${P}.adjust_value`)">
                    <el-input-number v-model="trustDelta" :min="-100" :max="100" style="width:200px" />
                    <div class="text-gray-400 text-xs mt-1">{{ t(`${P}.adjust_hint`) }}</div>
                </el-form-item>
                <el-form-item :label="t(`${P}.reason`)" required>
                    <el-input v-model="trustReason" type="textarea" :rows="2" maxlength="500" :placeholder="t(`${P}.reason_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="trustDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="trustSubmitting" @click="confirmAdjustTrust">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Search, ArrowDown, Loading } from '@element-plus/icons-vue';
import deviceApi from '@/api/device';

const P = 'devices_page';
const { t, locale } = useI18n();

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

const TRUST_LEVEL_KEYS = { high: 'high', medium: 'medium', low: 'low', none: 'none' };
const STAGE_KEYS = { new: 'new', onboarding: 'onboarding', stable: 'stable', suspicious: 'suspicious', retired: 'retired' };
const TRIGGER_KEYS = { system: 'system', admin: 'admin', auto_detect: 'auto_detect' };
const BATCH_ACTION_KEYS = {
    deactivate: { labelKey: 'action_deactivate', verbKey: 'action_deactivate' },
    blacklist: { labelKey: 'action_blacklist', verbKey: 'action_blacklist' },
    remove_blacklist: { labelKey: 'action_remove_blacklist', verbKey: 'action_remove_blacklist' },
};

function yesNo(val) {
    return val ? t(`${P}.yes`) : t(`${P}.no`);
}

function scoreType(score) {
    if (score >= 80) return 'success';
    if (score >= 50) return 'warning';
    return 'danger';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function trustLevelLabel(level) {
    const key = TRUST_LEVEL_KEYS[level];
    return key ? t(`${P}.trust_levels.${key}`) : level;
}

function stageLabel(stage) {
    const key = STAGE_KEYS[stage];
    return key ? t(`${P}.stages.${key}`) : stage;
}

function triggerLabel(triggeredBy) {
    const key = TRIGGER_KEYS[triggeredBy];
    return key ? t(`${P}.triggers.${key}`) : triggeredBy;
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
            t(`${P}.confirm_deactivate`, { fp: `${row.fingerprint.substring(0, 16)}...` }),
            t('actions.confirm'),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await deviceApi.deactivate(row.id);
        ElMessage.success(t(`${P}.deactivated`));
        loadDevices();
        loadStats();
    } catch {
        // cancelled
    }
}

// 更多操作
async function handleMoreAction(cmd, row) {
    const actions = {
        blacklist: { confirm: t(`${P}.confirm_blacklist`), msg: t(`${P}.blacklisted`) },
        remove_blacklist: { confirm: t(`${P}.confirm_remove_blacklist`), msg: t(`${P}.removed_blacklist`) },
    };
    const action = actions[cmd];
    if (!action) return;

    try {
        await ElMessageBox.confirm(action.confirm, t('actions.confirm'), {
            confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning',
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
        ElMessage.warning(t(`${P}.select_first`));
        return;
    }
    const keys = BATCH_ACTION_KEYS[action];
    if (!keys) return;
    const verb = t(`${P}.${keys.verbKey}`);
    const label = t(`${P}.${keys.labelKey}`);

    try {
        await ElMessageBox.confirm(
            t(`${P}.batch_confirm`, { action: verb, n: selectedIds.value.length }),
            t(`${P}.batch_title`),
            { confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await deviceApi.batch(selectedIds.value, action);
        ElMessage.success(t(`${P}.batch_success`, { action: label }));
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
        ElMessage.error(t(`${P}.profile_load_fail`));
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
    if (!trustReason.value.trim()) { ElMessage.warning(t(`${P}.reason_required`)); return; }
    trustSubmitting.value = true;
    try {
        const { data: res } = await deviceApi.adjustTrust(profileDevice.value.id, trustDelta.value, trustReason.value);
        if (res.success) {
            ElMessage.success(t(`${P}.trust_adjusted`, { score: res.data.new_trust_score }));
            trustDialogVisible.value = false;
            openProfileDialog(profileDevice.value);
        }
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t(`${P}.adjust_fail`));
    } finally {
        trustSubmitting.value = false;
    }
}
async function handleMarkSuspicious(row) {
    try {
        await ElMessageBox.confirm(t(`${P}.confirm_suspicious`), t('actions.confirm'), { type: 'warning' });
        const { data: res } = await deviceApi.markSuspicious(row.id, t(`${P}.suspicious_reason`));
        if (res.success) { ElMessage.success(t(`${P}.marked_suspicious`)); openProfileDialog(row); }
    } catch {
        // cancelled
    }
}
async function handleRetire(row) {
    try {
        await ElMessageBox.confirm(
            t(`${P}.confirm_retire`),
            t('actions.confirm'),
            { type: 'warning', confirmButtonText: t(`${P}.confirm_retire_btn`), cancelButtonText: t('actions.cancel') }
        );
        const { data: res } = await deviceApi.retire(row.id, t(`${P}.retire_reason`));
        if (res.success) { ElMessage.success(t(`${P}.retired`)); openProfileDialog(row); }
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
