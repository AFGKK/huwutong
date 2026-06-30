<template>
    <div class="license-health-page">
        <div class="page-header">
            <h2>License 健康评分</h2>
            <p class="text-muted">综合评估您所有 License 的健康状况，及时发现问题并获取改进建议。</p>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #409eff;">{{ dashboard.total_licenses }}</div>
                        <div class="stat-label">License 总数</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" :style="{ color: avgScoreColor }">{{ dashboard.average_score }}</div>
                        <div class="stat-label">平均健康评分</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #67c23a;">{{ dashboard.healthy_count }}</div>
                        <div class="stat-label">健康 (≥80)</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-value" style="color: #f56c6c;">{{ dashboard.critical_count }}</div>
                        <div class="stat-label">需关注 (&lt;60)</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 改进建议 -->
        <el-card v-if="dashboard.top_suggestions?.length" class="mb-4">
            <template #header><span>⚠️ 综合改进建议</span></template>
            <el-timeline>
                <el-timeline-item
                    v-for="(s, i) in dashboard.top_suggestions"
                    :key="i"
                    :type="s.type === 'critical' ? 'danger' : s.type === 'warning' ? 'warning' : 'primary'"
                    :timestamp="s.type === 'critical' ? '紧急' : s.type === 'warning' ? '提醒' : '建议'"
                >
                    {{ s.message }}
                </el-timeline-item>
            </el-timeline>
        </el-card>

        <!-- License 健康评分列表 -->
        <el-card>
            <template #header>
                <span>License 健康详情</span>
                <div class="card-extra">
                    <el-button size="small" @click="refresh" :icon="Refresh" :loading="loading">刷新</el-button>
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
                <el-table-column prop="product_name" label="产品" width="120" />
                <el-table-column prop="status" label="状态" width="90">
                    <template #default="{ row }">
                        <el-tag :type="statusType(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="健康评分" width="100">
                    <template #default="{ row }">
                        <el-tag :type="scoreType(row.score)" size="large">{{ row.score }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="等级" width="80">
                    <template #default="{ row }">
                        <el-tag :type="levelType(row.level)" size="small" effect="plain">
                            {{ levelLabel(row.level) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="子评分" min-width="240">
                    <template #default="{ row }">
                        <div class="sub-scores">
                            <el-tooltip content="到期时间评分" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.expiry_score)">
                                    到期: {{ row.details.expiry_score }}
                                </span>
                            </el-tooltip>
                            <el-tooltip content="设备占比评分" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.device_score)">
                                    设备: {{ row.details.device_score }}
                                </span>
                            </el-tooltip>
                            <el-tooltip content="安全评分" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.security_score)">
                                    安全: {{ row.details.security_score }}
                                </span>
                            </el-tooltip>
                            <el-tooltip content="活跃度评分" placement="top">
                                <span class="sub-score" :class="subScoreClass(row.details.activity_score)">
                                    活跃: {{ row.details.activity_score }}
                                </span>
                            </el-tooltip>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="设备" width="100">
                    <template #default="{ row }">
                        {{ row.device_count }} / {{ row.max_devices || '∞' }}
                    </template>
                </el-table-column>
                <el-table-column prop="expires_at" label="到期时间" width="140" />
                <el-table-column label="改进建议" min-width="240">
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
                                +{{ row.suggestions.length - 2 }} 条建议
                            </el-tag>
                        </div>
                        <span v-else class="no-issues">✅ 暂无改进建议</span>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 空状态 -->
            <el-empty v-if="!loading && !list.length" description="暂无 License 数据" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Refresh } from '@element-plus/icons-vue';
import licenseHealth from '@/api/licenseHealth';

const router = useRouter();
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
    if (level === 'healthy') return '健康';
    if (level === 'warning') return '警告';
    return '危险';
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
    const map = { active: '活跃', expired: '已过期', suspended: '已挂起', revoked: '已撤销', trial: '试用', inactive: '未激活' };
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
