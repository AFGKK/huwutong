<template>
    <div class="device-lifecycle-page">
        <div class="page-header">
            <h2>设备生命周期画像</h2>
            <p class="text-muted">追踪设备从首次出现→逐步信任→长期稳定→异常行为→标记可疑→废弃的完整生命周期</p>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading">
                    <el-icon><Refresh /></el-icon> 刷新
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
                    <template #header>信任分分布</template>
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
                        <span><span class="dot high"></span> 高≥80</span>
                        <span><span class="dot medium"></span> 中50-79</span>
                        <span><span class="dot low"></span> 低1-49</span>
                        <span><span class="dot zero"></span> 无0</span>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">平均活跃天数</div>
                    <div class="stat-value">{{ stats.avg_days_active || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card shadow="hover">
                    <div class="stat-label">平均信任分</div>
                    <div class="stat-value">{{ stats.avg_trust_score || 0 }}</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card shadow="hover">
                    <template #header>阶段转换频率</template>
                    <div class="transition-list">
                        <div v-for="(count, type) in stats.transition_frequency" :key="type" class="transition-item">
                            <span class="transition-type">{{ type }}</span>
                            <el-progress :percentage="Math.min(count * 10, 100)" :stroke-width="12" :show-text="false" />
                            <span class="transition-count">{{ count }}</span>
                        </div>
                        <el-empty v-if="!stats.transition_frequency || Object.keys(stats.transition_frequency).length === 0" description="暂无数据" />
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 设备列表 -->
        <el-card>
            <template #header>
                <div class="card-header">
                    <span>设备生命周期列表</span>
                    <div class="card-header-right">
                        <el-select v-model="stageFilter" placeholder="全部阶段" clearable size="small" style="width:160px" @change="loadList">
                            <el-option label="首次出现" value="new" />
                            <el-option label="逐步信任" value="onboarding" />
                            <el-option label="长期稳定" value="stable" />
                            <el-option label="异常/可疑" value="suspicious" />
                            <el-option label="已废弃" value="retired" />
                        </el-select>
                        <el-select v-model="trustFilter" placeholder="信任等级" clearable size="small" style="width:140px;margin-left:8px" @change="loadList">
                            <el-option label="高 (≥80)" value="high" />
                            <el-option label="中 (50-79)" value="medium" />
                            <el-option label="低 (1-49)" value="low" />
                            <el-option label="无 (0)" value="none" />
                        </el-select>
                    </div>
                </div>
            </template>

            <el-table :data="devices" stripe v-loading="loading">
                <el-table-column prop="fingerprint" label="指纹" min-width="200" show-overflow-tooltip />
                <el-table-column prop="platform" label="平台" width="100" />
                <el-table-column prop="os_version" label="系统版本" width="100" show-overflow-tooltip />
                <el-table-column label="生命周期" width="100">
                    <template #default="{ row }">
                        <el-tag :type="stageTagType(row.lifecycle_stage)" size="small">
                            {{ stageLabel(row.lifecycle_stage) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="信任分" width="140">
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
                <el-table-column label="活跃天数" width="80" align="center" prop="days_active" />
                <el-table-column label="总事件" width="70" align="center" prop="total_events" />
                <el-table-column label="License" width="180" show-overflow-tooltip>
                    <template #default="{ row }">
                        <code>{{ row.license?.license_key ?? '-' }}</code>
                    </template>
                </el-table-column>
                <el-table-column label="最后活跃" width="160">
                    <template #default="{ row }">{{ formatTime(row.last_seen_at) }}</template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="viewProfile(row)">画像</el-button>
                        <el-button size="small" type="warning" @click="openAdjustDialog(row)">调分</el-button>
                        <el-dropdown trigger="click" @command="(cmd) => handleAction(cmd, row)">
                            <el-button size="small" type="primary" class="ml-1">
                                更多<el-icon class="el-icon--right"><ArrowDown /></el-icon>
                            </el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="mark-suspicious" :disabled="row.lifecycle_stage === 'retired'">标记可疑</el-dropdown-item>
                                    <el-dropdown-item command="retire" :disabled="row.lifecycle_stage === 'retired'">废弃设备</el-dropdown-item>
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
        <el-dialog v-model="showProfileDialog" title="设备生命周期画像" width="700px">
            <template v-if="profileData">
                <el-descriptions :column="2" border class="mb-4">
                    <el-descriptions-item label="当前阶段">
                        <el-tag :type="stageTagType(profileData.profile.current_stage)" size="small">
                            {{ profileData.profile.stage_label }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="信任等级">
                        <el-tag :type="profileData.profile.trust_level === 'high' ? 'success' : profileData.profile.trust_level === 'medium' ? 'warning' : 'danger'" size="small">
                            {{ { high: '高', medium: '中', low: '低', none: '无' }[profileData.profile.trust_level] || profileData.profile.trust_level }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="信任分">
                        <el-progress :percentage="profileData.profile.trust_score" :stroke-width="14"
                            :color="trustColor(profileData.profile.trust_score)" :show-text="false" />
                        <span class="ml-1" :style="{ color: trustColor(profileData.profile.trust_score), fontWeight: 600 }">
                            {{ profileData.profile.trust_score }}
                        </span>
                    </el-descriptions-item>
                    <el-descriptions-item label="活跃天数">{{ profileData.profile.days_active }} 天</el-descriptions-item>
                    <el-descriptions-item label="首次出现">{{ formatTime(profileData.profile.first_seen_at) }}</el-descriptions-item>
                    <el-descriptions-item label="上次阶段变更">{{ formatTime(profileData.profile.last_stage_change_at) || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="事件总数">{{ profileData.profile.total_events }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <h4>生命周期时间线</h4>
                <div class="lifecycle-timeline">
                    <div v-for="(point, i) in profileData.timeline" :key="i" class="timeline-item">
                        <div class="timeline-dot" :class="point.stage"></div>
                        <div class="timeline-content">
                            <el-tag :type="stageTagType(point.stage)" size="small">{{ point.stage_label }}</el-tag>
                            <span class="timeline-time">{{ formatTime(point.timestamp) }}</span>
                            <span class="timeline-trust">信任分: {{ point.trust_score }}</span>
                        </div>
                    </div>
                    <el-empty v-if="!profileData.timeline?.length" description="暂无时间线数据" />
                </div>

                <el-divider />
                <h4>最近事件</h4>
                <el-table :data="profileData.recent_events" size="small" max-height="250">
                    <el-table-column prop="event_type" label="事件类型" width="100" />
                    <el-table-column prop="reason" label="原因" min-width="160" show-overflow-tooltip />
                    <el-table-column prop="trust_score_change" label="信任变化" width="80" align="center">
                        <template #default="{ row }">
                            <span :class="row.trust_score_change > 0 ? 'text-success' : row.trust_score_change < 0 ? 'text-danger' : ''">
                                {{ row.trust_score_change > 0 ? '+' : '' }}{{ row.trust_score_change }}
                            </span>
                        </template>
                    </el-table-column>
                    <el-table-column label="触发方式" width="80" prop="triggered_by" />
                    <el-table-column label="时间" width="160">
                        <template #default="{ row }">{{ formatTime(row.created_at) }}</template>
                    </el-table-column>
                </el-table>
            </template>
        </el-dialog>

        <!-- 调整信任分弹窗 -->
        <el-dialog v-model="showAdjustDialogVisible" title="调整信任分" width="400px">
            <el-form v-if="adjustTarget" label-position="top">
                <el-descriptions :column="2" border size="small" class="mb-4">
                    <el-descriptions-item label="设备">{{ adjustTarget.fingerprint?.substring(0, 20) }}...</el-descriptions-item>
                    <el-descriptions-item label="当前信任分">
                        <strong :style="{ color: trustColor(adjustTarget.trust_score) }">{{ adjustTarget.trust_score }}</strong>
                    </el-descriptions-item>
                </el-descriptions>
                <el-form-item label="调整值 (-100 ~ +100)">
                    <el-input-number v-model="adjustDelta" :min="-100" :max="100" style="width:100%" />
                </el-form-item>
                <el-form-item label="原因" :rules="[{ required: true }]">
                    <el-input v-model="adjustReason" type="textarea" :rows="2" placeholder="说明调整原因" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAdjustDialog = false">取消</el-button>
                <el-button type="primary" @click="doAdjust" :loading="adjusting">确认调整</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import deviceApi from '@/api/device';

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
    const map = { new: '首次出现', onboarding: '逐步信任', stable: '长期稳定', suspicious: '异常/可疑', retired: '已废弃' };
    return map[s] || s;
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

function formatTime(t) {
    return t ? new Date(t).toLocaleString('zh-CN') : '-';
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
        ElMessage.warning('请输入调整原因');
        return;
    }
    adjusting.value = true;
    try {
        await deviceApi.adjustTrust(adjustTarget.value.id, adjustDelta.value, adjustReason.value);
        ElMessage.success('信任分已调整');
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
                const { value } = await ElMessageBox.prompt('请输入标记原因', '标记可疑设备', { inputType: 'textarea' });
                await deviceApi.markSuspicious(row.id, value);
                ElMessage.success('设备已标记为可疑');
                await loadAll();
            } catch (err) { /* cancelled */ }
            break;
        }
        case 'retire': {
            try {
                const { value } = await ElMessageBox.prompt('请输入废弃原因', '废弃设备', { inputType: 'textarea' });
                await deviceApi.retire(row.id, value);
                ElMessage.success('设备已废弃');
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
.stage-indicator.onboarding { background: #409eff; }
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
.timeline-dot.onboarding { border-color: #409eff; }
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
