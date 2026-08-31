<template>
    <div class="device-lifecycle-page">
        <div class="page-header">
            <h2>{{ t(`${P}.title`) }}</h2>
            <p class="text-muted">{{ t(`${P}.subtitle`) }}</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
                </el-button>
            </div>
        </div>

        <!-- 生命周期概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stage-indicator new"></div>
                    <div class="stat-label">{{ stageLabel('new') }}</div>
                    <div class="stat-value">{{ stats.stage_distribution?.new || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stage-indicator onboarding"></div>
                    <div class="stat-label">{{ stageLabel('onboarding') }}</div>
                    <div class="stat-value">{{ stats.stage_distribution?.onboarding || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-success">
                    <div class="stage-indicator stable"></div>
                    <div class="stat-label">{{ stageLabel('stable') }}</div>
                    <div class="stat-value">{{ stats.stage_distribution?.stable || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover">
                    <div class="stage-indicator retired"></div>
                    <div class="stat-label">{{ stageLabel('retired') }}</div>
                    <div class="stat-value">{{ stats.stage_distribution?.retired || 0 }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 信任分分布 & 其他统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header>{{ t(`${P}.trust_distribution`) }}</template>
                    <div class="trust-bar">
                        <div class="trust-segment high" :style="{ width: trustPercent(stats.trust_distribution?.high) + '%' }">
                            <span>{{ stats.trust_distribution?.high || 0 }}</span>
                        </div>
                        <div class="trust-segment medium" :style="{ width: trustPercent(stats.trust_distribution?.medium) + '%' }">
                            <span>{{ stats.trust_distribution?.medium || 0 }}</span>
                        </div>
                        <div class="trust-segment low" :style="{ width: trustPercent(stats.trust_distribution?.low) + '%' }">
                            <span>{{ stats.trust_distribution?.low || 0 }}</span>
                        </div>
                        <div class="trust-segment zero" :style="{ width: trustPercent(stats.trust_distribution?.zero) + '%' }">
                            <span>{{ stats.trust_distribution?.zero || 0 }}</span>
                        </div>
                    </div>
                    <div class="trust-legend">
                        <span><span class="dot high"></span> {{ t(`${P}.trust_legend.high`) }}</span>
                        <span><span class="dot medium"></span> {{ t(`${P}.trust_legend.medium`) }}</span>
                        <span><span class="dot low"></span> {{ t(`${P}.trust_legend.low`) }}</span>
                        <span><span class="dot zero"></span> {{ t(`${P}.trust_legend.zero`) }}</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t(`${P}.avg_days_active`) }}</div>
                    <div class="stat-value">{{ stats.avg_days_active || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">{{ t(`${P}.avg_trust_score`) }}</div>
                    <div class="stat-value">{{ stats.avg_trust_score || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header>{{ t(`${P}.transition_frequency`) }}</template>
                    <div class="transition-list">
                        <div v-for="(count, type) in stats.transition_frequency" :key="type" class="transition-item">
                            <span class="transition-type">{{ type }}</span>
                            <el-progress :percentage="Math.min(count * 10, 100)" :stroke-width="12" :show-text="false" />
                            <span class="transition-count">{{ count }}</span>
                        </div>
                        <el-empty v-if="!stats.transition_frequency || Object.keys(stats.transition_frequency).length === 0" :description="t('messages.no_data')" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 设备列表 -->
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>{{ t(`${P}.list_title`) }}</span>
                    <div class="card-header-right">
                        <el-select v-model="stageFilter" :placeholder="t(`${P}.filter_all_stages`)" clearable size="small" style="width:160px" @change="loadList">
                            <el-option v-for="opt in stageOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-select v-model="trustFilter" :placeholder="t(`${P}.filter_trust_level`)" clearable size="small" style="width:140px;margin-left:8px" @change="loadList">
                            <el-option v-for="opt in trustFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                    </div>
                </div>
            </template>

            <el-table :data="devices" stripe v-loading="loading">
                <el-table-column prop="fingerprint" :label="t(`${D}.col_fingerprint`)" min-width="200" show-overflow-tooltip />
                <el-table-column prop="platform" :label="t(`${D}.col_platform`)" width="100" />
                <el-table-column prop="os_version" :label="t(`${D}.col_os_version`)" width="100" show-overflow-tooltip />
                <el-table-column :label="t(`${P}.col_lifecycle`)" width="100">
                    <template #default="{ row }">
                        <el-tag :type="stageTagType(row.lifecycle_stage)" size="small">
                            {{ stageLabel(row.lifecycle_stage) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${D}.col_trust_score`)" width="140">
                    <template #default="{ row }">
                        <div class="trust-score-display">
                            <el-progress :percentage="row.trust_score || 0" :stroke-width="14"
                                :color="trustColor(row.trust_score)" :show-text="false" />
                            <span class="trust-score-text" :style="{ color: trustColor(row.trust_score) }">
                                {{ row.trust_score ?? 0 }}
                            </span>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${P}.col_days_active`)" width="80" align="center" prop="days_active" />
                <el-table-column :label="t(`${P}.col_total_events`)" width="70" align="center" prop="total_events" />
                <el-table-column :label="t(`${D}.col_license`)" width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <code>{{ row.license?.license_key ?? '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column :label="t(`${D}.col_last_seen`)" width="160">
                    <template #default="{ row }">{{ formatTime(row.last_seen_at) }}</template>
                </el-table-column>
                <el-table-column :label="t(`${D}.col_actions`)" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewProfile(row)">{{ t(`${D}.profile`) }}</el-button>
                        <el-button size="small" type="warning" @click="openAdjustDialog(row)">{{ t(`${D}.adjust_trust`) }}</el-button>
                        <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                            <el-button size="small" type="primary" class="ml-1">
                                {{ t('actions.more') }}<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="mark-suspicious" :disabled="row.lifecycle_stage === 'retired'">{{ t(`${D}.mark_suspicious`) }}</el-dropdown-item>
                                    <el-dropdown-item command="retire" :disabled="row.lifecycle_stage === 'retired'">{{ t(`${D}.retire_device`) }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination-wrapper">
                <el-pagination v-if="pagination.total > 0"
                    :current-page="pagination.current_page" :total="pagination.total"
                    :page-size="pagination.per_page" layout="total, prev, pager, next"
                    @current-change="onPageChange" />
            </div>
        </el-card>

        <!-- 画像详情弹窗 -->
        <el-dialog v-model="showProfileDialog" :title="t(`${D}.profile_title`)" width="700px">
            <template v-if="profileData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item :label="t(`${D}.current_stage`)">
                        <el-tag :type="stageTagType(profileData.profile.current_stage)" size="small">
                            {{ profileData.profile.stage_label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_trust_level`)">
                        <el-tag :type="profileData.profile.trust_level === 'high' ? 'success' : profileData.profile.trust_level === 'medium' ? 'warning' : 'danger'" size="small">
                            {{ trustLevelShort(profileData.profile.trust_level) }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${D}.label_trust_score`)">
                        <el-progress :percentage="profileData.profile.trust_score" :stroke-width="14"
                            :color="trustColor(profileData.profile.trust_score)" :show-text="false" />
                        <span class="ml-1" :style="{ color: trustColor(profileData.profile.trust_score), fontWeight: 600 }">
                            {{ profileData.profile.trust_score }}
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item :label="t(`${D}.days_active`)">{{ profileData.profile.days_active }} {{ t(`${P}.days_suffix`) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_first_seen`)">{{ formatTime(profileData.profile.first_seen_at) }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_last_stage_change`)">{{ formatTime(profileData.profile.last_stage_change_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.label_total_events`)">{{ profileData.profile.total_events }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>{{ t(`${D}.timeline_title`) }}</h4>
                <div class="lifecycle-timeline">
                    <div v-for="(point, i) in profileData.timeline" :key="i" class="timeline-item">
                        <div class="timeline-dot" :class="point.stage"></div>
                        <div class="timeline-content">
                            <el-tag :type="stageTagType(point.stage)" size="small">{{ point.stage_label }}</el-tag>
                            <span class="timeline-time">{{ formatTime(point.timestamp) }}</span>
                            <span class="timeline-trust">{{ t(`${D}.timeline_trust`, { score: point.trust_score }) }}</span>
                        </div>
                    </div>
                    <el-empty v-if="!profileData.timeline?.length" :description="t(`${P}.no_timeline_data`)" />
                </div>

                <el-divider />
                <h4>{{ t(`${P}.recent_events_title`) }}</h4>
                <el-table :data="profileData.recent_events" size="small" max-height="250">
                    <el-table-column prop="event_type" :label="t(`${D}.col_event_type`)" width="100" />
                    <el-table-column prop="reason" :label="t(`${D}.col_reason`)" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="trust_score_change" :label="t(`${D}.col_trust_change`)" width="80" align="center">
                        <template #default="{ row }">
                            <span :class="row.trust_score_change > 0 ? 'text-success' : row.trust_score_change < 0 ? 'text-danger' : ''">
                                {{ row.trust_score_change > 0 ? '+' : '' }}{{ row.trust_score_change }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t(`${D}.col_trigger`)" width="80" prop="triggered_by" />
                    <el-table-column :label="t(`${D}.col_time`)" width="160">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
            </template>
        </el-dialog>

        <!-- 调整信任分弹窗 -->
        <el-dialog v-model="showAdjustDialogVisible" :title="t(`${D}.adjust_trust_title`)" width="400px">
            <el-form v-if="adjustTarget" label-position="top">
                <el-descriptions :column="2" border size="small" class="mb-4">
                    <el-descriptions-item :label="t(`${P}.adjust_device`)">{{ adjustTarget.fingerprint?.substring(0, 20) }}...</el-descriptions-item>
                    <el-descriptions-item :label="t(`${P}.adjust_current_score`)">
                        <strong :style="{ color: trustColor(adjustTarget.trust_score) }">{{ adjustTarget.trust_score }}</strong>
                    </el-descriptions-item>
                </el-descriptions>
                <el-form-item :label="t(`${P}.adjust_value_range`)">
                    <el-input-number v-model="adjustDelta" :min="-100" :max="100" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t(`${D}.reason`)" :rules="[{ required: true }]">
                    <el-input v-model="adjustReason" type="textarea" :rows="2" :placeholder="t(`${D}.reason_ph`)" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAdjustDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="doAdjust" :loading="adjusting">{{ t(`${P}.confirm_adjust`) }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import deviceApi from '@/api/device';

const P = 'device_lifecycle_page';
const D = 'devices_page';
const { t, locale } = useI18n();

const STAGE_KEYS = ['new', 'onboarding', 'stable', 'suspicious', 'retired'];
const TRUST_FILTER_KEYS = ['high', 'medium', 'low', 'none'];
const TRUST_LEVEL_SHORT_KEYS = ['high', 'medium', 'low', 'none'];

const stageOptions = computed(() => STAGE_KEYS.map((key) => ({
    value: key,
    label: t(`${D}.stages.${key}`),
})));

const trustFilterOptions = computed(() => TRUST_FILTER_KEYS.map((key) => ({
    value: key,
    label: t(`${P}.trust_filter.${key}`),
})));

const loading = ref(false);
const adjusting = ref(false);
const showProfileDialog = ref(false);
const showAdjustDialog = ref(false);

const devices = ref([]);
const profileData = ref(null);
const adjustTarget = ref(null);
const adjustDelta = ref(0);
const adjustReason = ref('');

const stageFilter = ref('');
const trustFilter = ref('');
const totalDevices = ref(0);

const stats = reactive({
    stage_distribution: {}, trust_distribution: {},
    avg_days_active: 0, avg_trust_score: 0, total_profile_events: 0, transition_frequency: {},
});

const pagination = reactive({
    current_page: 1, total: 0, per_page: 20,
});

function stageLabel(s) {
    return STAGE_KEYS.includes(s) ? t(`${D}.stages.${s}`) : s;
}

function trustLevelShort(level) {
    return TRUST_LEVEL_SHORT_KEYS.includes(level) ? t(`${P}.trust_levels_short.${level}`) : level;
}

function stageTagType(s) {
    const map = { new: 'info', onboarding: 'primary', stable: 'success', suspicious: 'warning', retired: 'danger' };
    return map[s] || 'info';
}

function trustColor(score) {
    if (!score && score !== 0) return '#909399';
    if (score >= 80) return '#67c23a';
    if (score >= 50) return '#e6a23c';
    if (score > 0) return '#f56c6c';
    return '#c0c4cc';
}

function trustPercent(count) {
    const total = (stats.trust_distribution?.high || 0) + (stats.trust_distribution?.medium || 0) + (stats.trust_distribution?.low || 0) + (stats.trust_distribution?.zero || 0);
    if (!total) return 0;
    return ((count || 0) / total) * 100;
}

function formatTime(timeVal) {
    if (!timeVal) return '-';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(timeVal).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

async function loadAll() {
    loading.value = true;
    try {
        const [listRes, statRes] = await Promise.all([
            deviceApi.list({
                page: pagination.current_page,
                per_page: pagination.per_page,
                ...(stageFilter.value ? { 'filter.lifecycle_stage': stageFilter.value } : {}),
                ...(trustFilter.value ? {
                    'filter.trust_score_min': trustFilter.value === 'high' ? 80 : trustFilter.value === 'medium' ? 50 : trustFilter.value === 'low' ? 1 : 0
                } : {}),
            }),
            deviceApi.profileStats(),
        ]);
        const listData = listRes.data?.data || {};
        devices.value = listData.data || [];
        pagination.current_page = listData.current_page || 1;
        pagination.total = listData.total || 0;
        totalDevices.value = pagination.total;
        Object.assign(stats, statRes.data?.data || {});
    } catch (err) {
        console.error(err);
    } finally {
        loading.value = false;
    }
}

async function loadList() {
    pagination.current_page = 1;
    await loadAll();
}

function onPageChange(page) {
    pagination.current_page = page;
    loadAll();
}

async function viewProfile(row) {
    showProfileDialog.value = true;
    profileData.value = null;
    try {
        const res = await deviceApi.profile(row.id);
        profileData.value = res.data?.data;
    } catch (err) {
        console.error(err);
    }
}

function openAdjustDialog(row) {
    adjustTarget.value = row;
    adjustDelta.value = 0;
    adjustReason.value = '';
    showAdjustDialogVisible.value = true;
}

const showAdjustDialogVisible = computed({
    get: () => showAdjustDialog.value,
    set: (v) => { showAdjustDialog.value = v; },
});

async function doAdjust() {
    if (!adjustTarget.value || !adjustReason.value) {
        ElMessage.warning(t(`${D}.reason_required`));
        return;
    }
    adjusting.value = true;
    try {
        await deviceApi.adjustTrust(adjustTarget.value.id, adjustDelta.value, adjustReason.value);
        ElMessage.success(t(`${P}.trust_adjusted`));
        showAdjustDialog.value = false;
        await loadAll();
    } catch (err) {
        console.error(err);
    } finally {
        adjusting.value = false;
    }
}

async function handleAction(cmd, row) {
    switch (cmd) {
        case 'mark-suspicious': {
            try {
                const { value } = await ElMessageBox.prompt(
                    t(`${P}.prompt_suspicious_reason`),
                    t(`${P}.prompt_suspicious_title`),
                    { inputType: 'textarea' }
                );
                await deviceApi.markSuspicious(row.id, value);
                ElMessage.success(t(`${P}.marked_suspicious`));
                await loadAll();
            } catch (err) { /* cancelled */ }
            break;
        }
        case 'retire': {
            try {
                const { value } = await ElMessageBox.prompt(
                    t(`${P}.prompt_retire_reason`),
                    t(`${P}.prompt_retire_title`),
                    { inputType: 'textarea' }
                );
                await deviceApi.retire(row.id, value);
                ElMessage.success(t(`${P}.device_retired`));
                await loadAll();
            } catch (err) { /* cancelled */ }
            break;
        }
    }
}

onMounted(loadAll);
</script>

<style scoped>
.device-lifecycle-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap; gap: 8px; flex-direction: column; }
.page-header h2 { margin: 0; font-size: 20px; }
.page-header .text-muted { margin: 4px 0 0; color: #909399; font-size: 13px; }
.header-actions { display: flex; gap: 8px; margin-top: 8px; }

.mb-4 { margin-bottom: 16px; }

.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 700; }
.stat-success .stat-value { color: #67c23a; }

.stage-indicator { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-bottom: 8px; }
.stage-indicator.new { background: #909399; }
.stage-indicator.onboarding { background: #0f172a; }
.stage-indicator.stable { background: #67c23a; }
.stage-indicator.retired { background: #f56c6c; }

.card-header { display: flex; justify-content: space-between; align-items: center; }
.card-header-right { display: flex; }

/* Trust bar */
.trust-bar { display: flex; height: 28px; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
.trust-segment { display: flex; align-items: center; justify-content: center; font-size: 11px; color: #fff; font-weight: 600; transition: width 0.3s; }
.trust-segment.high { background: #67c23a; }
.trust-segment.medium { background: #e6a23c; }
.trust-segment.low { background: #f56c6c; }
.trust-segment.zero { background: #c0c4cc; }
.trust-legend { display: flex; gap: 12px; font-size: 12px; color: #606266; }
.trust-legend .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
.trust-legend .dot.high { background: #67c23a; }
.trust-legend .dot.medium { background: #e6a23c; }
.trust-legend .dot.low { background: #f56c6c; }
.trust-legend .dot.zero { background: #c0c4cc; }

/* Trust score in table */
.trust-score-display { display: flex; align-items: center; gap: 8px; }
.trust-score-text { font-weight: 600; font-size: 14px; min-width: 30px; text-align: right; }

/* Transition frequency list */
.transition-list { max-height: 200px; overflow-y: auto; }
.transition-item { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.transition-type { min-width: 60px; font-size: 12px; color: #606266; }
.transition-count { font-size: 12px; font-weight: 600; min-width: 24px; text-align: right; color: #606266; }

/* Timeline */
.lifecycle-timeline { position: relative; padding-left: 20px; }
.lifecycle-timeline::before { content: ''; position: absolute; left: 9px; top: 0; bottom: 0; width: 2px; background: #e4e7ed; }
.timeline-item { position: relative; padding-bottom: 20px; }
.timeline-dot { position: absolute; left: -16px; top: 4px; width: 12px; height: 12px; border-radius: 50%; border: 2px solid #e4e7ed; background: #fff; z-index: 1; }
.timeline-dot.new { border-color: #909399; }
.timeline-dot.onboarding { border-color: #0f172a; }
.timeline-dot.stable { border-color: #67c23a; background: #67c23a; }
.timeline-dot.suspicious { border-color: #e6a23c; background: #e6a23c; }
.timeline-dot.retired { border-color: #f56c6c; background: #f56c6c; }
.timeline-content { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.timeline-time { font-size: 12px; color: #909399; }
.timeline-trust { font-size: 12px; color: #909399; }

.pagination-wrapper { display: flex; justify-content: flex-end; padding: 16px 0; }
.ml-1 { margin-left: 4px; }
.text-success { color: #67c23a; font-weight: 600; }
.text-danger { color: #f56c6c; font-weight: 600; }
</style>
