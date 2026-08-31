<template>
    <div class="license-health-page">
        <div class="page-header">
            <h2>{{ $t('portal.health_title') }}</h2>
            <p class="text-muted">{{ $t('portal.health_subtitle') }}</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #0f172a;">{{ dashboard.total_licenses }}</div>
                        <div class="stat-label">{{ $t('portal.total_licenses') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" :style="{ color: avgScoreColor }">{{ dashboard.average_score }}</div>
                        <div class="stat-label">{{ $t('portal.avg_health') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #67c23a;">{{ dashboard.healthy_count }}</div>
                        <div class="stat-label">{{ $t('portal.healthy_ge80') }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #f56c6c;">{{ dashboard.critical_count }}</div>
                        <div class="stat-label">{{ $t('portal.need_attention') }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 改进建议 -->
        <el-card v-if="dashboard.top_suggestions?.length" class="mb-4">
            <template #header><span>{{ $t('portal.top_suggestions') }}</span></template>
            <el-timeline>
                <el-timeline-item
                    v-for="(s, i) in dashboard.top_suggestions"
                    :key="i"
                    :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                    :timestamp="suggestionTypeLabel(s.type)"
                >
                    {{ s.message }}
                </el-timeline-item>
            </el-timeline>
        </el-card>

        <!-- License 健康评分列表 -->
        <el-card>
            <template #header>
                <span>{{ $t('portal.health_details') }}</span>
                <div class="card-extra">
                    <el-button size="small" @click="refresh" :icon="Refresh" :loading="loading">{{ $t('portal.refresh') }}</el-button>
                </div>
            </template>

            <el-table :data="list" stripe v-loading="loading">
                <el-table-column label="License Key" min-width="180">
                    <template #default="{ row }">
                        <el-link type="primary" :underline="'never'" @click="goLicense(row.license_id)">
                            <code>{{ row.license_key }}</code>
                        </el-link>
                    </template>
                </el-table-column>
                <el-table-column prop="product_name" :label="$t('portal.product')" width="120" />
                <el-table-column prop="status" :label="$t('portal.status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.health_score_col')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.score)" size="large">{{ row.score }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.level')" width="80">
                    <template #default="{ row }">
                        <el-tag :type="levelType(row.level)" size="small" effect="plain">
                            {{ levelLabel(row.level) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.sub_scores')" min-width="240">
                    <template #default="{ row }">
                        <div class="sub-scores">
                            <el-tooltip :content="$t('portal.tip_expiry_score')" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.expiry_score)">
                                    {{ $t('portal.score_expiry', { n: row.details.expiry_score }) }}
                                </span>
                            </el-tooltip>
                            <el-tooltip :content="$t('portal.tip_device_score')" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.device_score)">
                                    {{ $t('portal.score_device', { n: row.details.device_score }) }}
                                </span>
                            </el-tooltip>
                            <el-tooltip :content="$t('portal.tip_security_score')" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.security_score)">
                                    {{ $t('portal.score_security', { n: row.details.security_score }) }}
                                </span>
                            </el-tooltip>
                            <el-tooltip :content="$t('portal.tip_activity_score')" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.activity_score)">
                                    {{ $t('portal.score_activity', { n: row.details.activity_score }) }}
                                </span>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('portal.device')" width="100">
                    <template #default="{ row }">
                        {{ row.device_count }} / {{ row.max_devices || '∞' }}
                    </template>
                </el-table-column>
                <el-table-column prop="expires_at" :label="$t('portal.expires_at')" width="140" />
                <el-table-column :label="$t('portal.suggestions_col')" min-width="240">
                    <template #default="{ row }">
                        <div v-if="row.suggestions?.length">
                            <div v-for="(s, i) in row.suggestions.slice(0, 2)" :key="i" class="suggestion-item">
                                <el-tag
                                    :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'info'"
                                    size="small"
                                    class="suggestion-tag"
                                >
                                    {{ s.message }}
                                </el-tag>
                            </div>
                            <el-tag v-if="row.suggestions.length > 2" size="small" type="info">
                                {{ $t('portal.more_suggestions', { n: row.suggestions.length - 2 }) }}
                            </el-tag>
                        </div>
                        <span v-else class="no-issues">{{ $t('portal.no_suggestions') }}</span>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 空状态 -->
            <el-empty v-if="!loading && !list.length" :description="$t('portal.no_license_data')" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { Refresh } from '@element-plus/icons-vue';
import licenseHealth from '@/api/licenseHealth';

const router = useRouter();
const { t } = useI18n();

const loading = ref(false);
const list = ref([]);
const dashboard = reactive({
    total_licenses: 0,
    average_score: 0,
    healthy_count: 0,
    warning_count: 0,
    critical_count: 0,
    top_suggestions: [],
});

const avgScoreColor = computed(() => {
    const s = dashboard.average_score;
    if (s >= 80) return '#67c23a';
    if (s >= 60) return '#e6a23c';
    return '#f56c6c';
});

function scoreType(score) {
    if (score >= 80) return 'success';
    if (score >= 60) return 'warning';
    return 'danger';
}

function levelType(level) {
    if (level === 'healthy') return 'success';
    if (level === 'warning') return 'warning';
    return 'danger';
}

function levelLabel(level) {
    if (level === 'healthy') return t('portal.health_ok');
    if (level === 'warning') return t('portal.health_warn');
    return t('portal.health_danger');
}

function suggestionTypeLabel(type) {
    if (type === 'critical') return t('portal.sug_critical');
    if (type === 'warning') return t('portal.sug_warning');
    return t('portal.sug_info');
}

function subScoreClass(score) {
    if (score >= 80) return 'score-good';
    if (score >= 60) return 'score-warn';
    return 'score-bad';
}

function statusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'danger', trial: 'warning' };
    return map[status] || 'info';
}

function statusLabel(status) {
    const map = {
        active: t('portal.st_active'),
        expired: t('portal.st_expired'),
        suspended: t('portal.st_suspended_alt'),
        revoked: t('portal.st_revoked_alt'),
        trial: t('portal.type_trial'),
        inactive: t('portal.st_inactive'),
    };
    return map[status] || status;
}

function goLicense(id) {
    router.push(`/portal/licenses/${id}`);
}

async function refresh() {
    loading.value = true;
    try {
        const [dashRes, listRes] = await Promise.all([
            licenseHealth.dashboard(),
            licenseHealth.list(),
        ]);
        Object.assign(dashboard, dashRes.data.data);
        list.value = listRes.data.data || [];
    } catch {} finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>

<style scoped>
.license-health-page { padding: 24px; }
.page-header { margin-bottom: 20px; }
.text-muted { color: #909399; font-size: 13px; margin-top: 4px; }
.mb-4 { margin-bottom: 16px; }
.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 32px; font-weight: 700; }
.stat-label { font-size: 14px; color: #909399; margin-top: 4px; }
.card-extra { float: right; }
.sub-scores { display: flex; gap: 8px; flex-wrap: wrap; }
.sub-score { font-size: 12px; padding: 2px 6px; border-radius: 4px; }
.score-good { background: #f0f9eb; color: #67c23a; }
.score-warn { background: #fdf6ec; color: #e6a23c; }
.score-bad { background: #fef0f0; color: #f56c6c; }
.suggestion-item { margin-bottom: 4px; }
.suggestion-tag { white-space: normal; height: auto; line-height: 1.4; padding: 2px 6px; }
.no-issues { color: #67c23a; font-size: 13px; }
</style>
