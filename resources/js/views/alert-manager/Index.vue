<template>
    <div class="alert-manager-page">
        <div class="page-header">
            <div>
                <h2>告警聚合与疲劳管理</h2>
                <p class="text-muted">告警合并去重 · 分级(紧急/警告/信息) · 静默时段 · 升级策略 · 疲劳检测 · 噪音分析</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="runAggregate" :loading="aggLoading" :icon="DataBoard">执行聚合</el-button>
                <el-button type="warning" @click="runDowngrade" :loading="downgradeLoading">自动降级</el-button>
            </div>
        </div>

        <!-- 概览 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">告警规则</div><div class="metric-value">{{ dash.total_rules }}<small class="text-muted">/{{ dash.active_rules }}</small></div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">触发中</div><div class="metric-value danger">{{ dash.firing_events }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">已确认</div><div class="metric-value warning">{{ dash.acknowledged }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">今日已解决</div><div class="metric-value success">{{ dash.resolved_today }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">活跃静默</div><div class="metric-value">{{ dash.active_silences }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">已聚合</div><div class="metric-value">{{ dash.aggregated_events }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">疲劳设置</div><div class="metric-value">{{ dash.fatigue_settings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">级别分布</div><div class="metric-value" style="font-size:14px">{{ sevText }}</div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 聚合 -->
            <el-tab-pane label="🔗 告警聚合" name="aggregation">
                <el-table :data="aggGroups" stripe v-loading="aggLoading" size="small">
                    <el-table-column prop="group_key" label="分组键" min-width="180" />
                    <el-table-column label="父事件" width="80" prop="parent_count" />
                    <el-table-column label="子事件" width="80" prop="total_children" />
                    <el-table-column label="规则" width="120"><template #default="{row}">{{ row.sample_parent?.rule_name || '—' }}</template></el-table-column>
                    <el-table-column label="类型" width="100"><template #default="{row}">{{ row.sample_parent?.metric_type || '—' }}</template></el-table-column>
                    <el-table-column label="级别" width="70"><template #default="{row}"><el-tag :type="sevTag(row.sample_parent?.severity)" size="small">{{ row.sample_parent?.severity }}</el-tag></template></el-table-column>
                    <el-table-column label="状态" width="70"><template #default="{row}">{{ row.sample_parent?.status }}</template></el-table-column>
                    <el-table-column label="操作" width="100">
                        <template #default="{row}"><el-button size="small" @click="viewAggGroup(row.group_key)">查看子事件</el-button></template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!aggGroups.length && !aggLoading" description="暂无聚合数据，点击「执行聚合」" />
            </el-tab-pane>

            <!-- 静默规则 -->
            <el-tab-pane label="🔇 静默规则" name="silence">
                <div class="section-header"><span>静默规则</span><el-button size="small" type="primary" @click="showSilenceForm = true">+ 新建静默</el-button></div>
                <el-table :data="silenceRules" stripe v-loading="silenceLoading" size="small">
                    <el-table-column prop="name" label="名称" min-width="140" />
                    <el-table-column prop="match_type" label="匹配方式" width="100" />
                    <el-table-column label="条件" min-width="160"><template #default="{row}"><code style="font-size:11px">{{ JSON.stringify(row.match_rules) }}</code></template></el-table-column>
                    <el-table-column label="开始" width="140"><template #default="{row}">{{ fmtTime(row.starts_at) }}</template></el-table-column>
                    <el-table-column label="结束" width="140"><template #default="{row}">{{ fmtTime(row.ends_at) }}</template></el-table-column>
                    <el-table-column label="活跃" width="60"><template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template></el-table-column>
                    <el-table-column label="原因" min-width="120" show-overflow-tooltip prop="reason" />
                    <el-table-column label="操作" width="140" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="toggleSilence(row)">{{ row.is_active ? '停用' : '启用' }}</el-button>
                            <el-button size="small" type="danger" @click="deleteSilence(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!silenceRules.length && !silenceLoading" description="暂无静默规则" />
                <!-- 新建静默对话框 -->
                <el-dialog v-model="showSilenceForm" title="新建静默规则" width="550px">
                    <el-form :model="silenceForm" label-width="120px">
                        <el-form-item label="名称" prop="name" :rules="[{required:true}]"><el-input v-model="silenceForm.name" /></el-form-item>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item label="匹配方式" prop="match_type"><el-select v-model="silenceForm.match_type" style="width:100%">
                                <el-option label="精确" value="exact" /><el-option label="通配符" value="wildcard" /><el-option label="模式" value="pattern" />
                            </el-select></el-form-item></el-col>
                            <el-col :span="12"><el-form-item label="时区"><el-select v-model="silenceForm.timezone" style="width:100%"><el-option label="UTC" value="UTC" /><el-option label="Asia/Shanghai" value="Asia/Shanghai" /></el-select></el-form-item></el-col>
                        </el-row>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item label="开始时间" prop="starts_at"><el-date-picker v-model="silenceForm.starts_at" type="datetime" format="YYYY-MM-DD HH:mm" style="width:100%" /></el-form-item></el-col>
                            <el-col :span="12"><el-form-item label="结束时间" prop="ends_at"><el-date-picker v-model="silenceForm.ends_at" type="datetime" format="YYYY-MM-DD HH:mm" style="width:100%" /></el-form-item></el-col>
                        </el-row>
                        <el-form-item label="匹配条件"><el-input v-model="matchRulesText" type="textarea" :rows="2" placeholder='{"rule_id": "1", "severity": "critical"}' /></el-form-item>
                        <el-form-item label="原因"><el-input v-model="silenceForm.reason" type="textarea" :rows="2" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showSilenceForm = false">取消</el-button><el-button type="primary" @click="saveSilence" :loading="saving">保存</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 疲劳管理 -->
            <el-tab-pane label="📉 疲劳管理" name="fatigue">
                <el-button @click="loadFatigueSettings" :loading="fatigueLoading">刷新设置</el-button>
                <el-button type="warning" style="margin-left:8px" @click="runDowngrade" :loading="downgradeLoading">执行自动降级</el-button>
                <el-table :data="fatigueSettings" stripe size="small" class="mt-3" v-if="fatigueSettings.length">
                    <el-table-column prop="source_type" label="来源类型" width="150" />
                    <el-table-column prop="repetition_threshold" label="重复阈值" width="100" />
                    <el-table-column prop="decay_factor" label="衰减系数" width="100" />
                    <el-table-column prop="auto_downgrade" label="自动降级" width="80"><template #default="{row}"><el-tag :type="row.auto_downgrade ? 'success' : 'info'" size="small">{{ row.auto_downgrade ? '是' : '否' }}</el-tag></template></el-table-column>
                    <el-table-column prop="target_severity" label="降级级别" width="100" />
                    <el-table-column label="操作" width="120" fixed="right"><template #default="{row}"><el-button size="small" type="danger" @click="deleteFatigue(row)">删除</el-button></template></el-table-column>
                </el-table>
                <el-empty v-if="!fatigueSettings.length && !fatigueLoading" description="暂无疲劳设置，可在规则中配置 fatigue_threshold" />
            </el-tab-pane>

            <!-- 噪音分析 -->
            <el-tab-pane label="📊 噪音分析" name="noise">
                <div class="mb-4">
                    <el-radio-group v-model="noiseDays" size="small" @change="loadNoise">
                        <el-radio-button :value="1">1天</el-radio-button>
                        <el-radio-button :value="7">7天</el-radio-button>
                        <el-radio-button :value="30">30天</el-radio-button>
                    </el-radio-group>
                    <span class="ml-3 text-muted">噪音规则数: {{ noiseData.total_noisy_rules }}</span>
                </div>
                <el-table :data="noiseData.rules" stripe v-loading="noiseLoading" size="small">
                    <el-table-column prop="rule_name" label="规则" min-width="160" />
                    <el-table-column prop="metric_type" label="指标类型" width="100" />
                    <el-table-column prop="total_events" label="事件数" width="80" />
                    <el-table-column label="噪音评分" width="110"><template #default="{row}">
                        <el-progress :percentage="Math.min(row.noise_score, 100)" :status="row.is_noisy ? 'exception' : 'success'" :stroke-width="12" />
                    </template></el-table-column>
                    <el-table-column label="是否噪音" width="80"><template #default="{row}"><el-tag :type="row.is_noisy ? 'danger' : 'success'" size="small">{{ row.is_noisy ? '是' : '否' }}</el-tag></template></el-table-column>
                    <el-table-column label="建议" min-width="200" prop="suggested_action" />
                </el-table>
            </el-tab-pane>

            <!-- 通知摘要 -->
            <el-tab-pane label="📋 通知摘要" name="digest">
                <el-button @click="loadDigest" :loading="digestLoading" class="mb-4">生成摘要</el-button>
                <div v-if="digestData.total !== undefined">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">事件总数</div><div class="metric-value">{{ digestData.total }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">紧急</div><div class="metric-value danger">{{ digestData.critical }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">警告</div><div class="metric-value warning">{{ digestData.warning }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="hover" class="metric-card"><div class="metric-label">信息</div><div class="metric-value">{{ digestData.info }}</div></el-card></el-col>
                    </el-row>
                    <el-table :data="digestData.events" stripe size="small" max-height="400">
                        <el-table-column label="规则" width="140" prop="rule" />
                        <el-table-column label="级别" width="70"><template #default="{row}"><el-tag :type="sevTag(row.severity)" size="small">{{ row.severity }}</el-tag></template></el-table-column>
                        <el-table-column label="消息" min-width="240" prop="message" show-overflow-tooltip />
                        <el-table-column label="状态" width="70" prop="status" />
                        <el-table-column label="时间" width="150"><template #default="{row}">{{ row.time }}</template></el-table-column>
                    </el-table>
                </div>
                <el-empty v-else description="点击「生成摘要」查看" />
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, DataBoard, CircleCheck } from '@element-plus/icons-vue';
import alertManagerApi from '@/api/alertManager';

const loading = ref(false);
const saving = ref(false);
const aggLoading = ref(false);
const downgradeLoading = ref(false);
const silenceLoading = ref(false);
const fatigueLoading = ref(false);
const noiseLoading = ref(false);
const digestLoading = ref(false);
const activeTab = ref('aggregation');
const noiseDays = ref(7);

const dash = reactive({ total_rules: 0, active_rules: 0, firing_events: 0, acknowledged: 0, resolved_today: 0, active_silences: 0, aggregated_events: 0, fatigue_settings: 0, severity_distribution: {} });
const aggGroups = ref([]);
const silenceRules = ref([]);
const fatigueSettings = ref([]);
const noiseData = reactive({ total_noisy_rules: 0, rules: [] });
const digestData = reactive({});
const showSilenceForm = ref(false);
const matchRulesText = ref('');
const silenceForm = reactive({ name: '', match_type: 'exact', timezone: 'UTC', starts_at: '', ends_at: '', reason: '' });

const sevText = computed(() => {
    const d = dash.severity_distribution || {};
    return Object.entries(d).map(([k, v]) => `${k}:${v}`).join(' | ');
});

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadDashboard(), loadAggGroups(), loadSilenceRules(), loadFatigueSettings(), loadNoise()]); } finally { loading.value = false; }
}
async function loadDashboard() {
    try { const r = await alertManagerApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadAggGroups() {
    try { const r = await alertManagerApi.aggregationGroups(); aggGroups.value = r.data?.data || []; } catch {}
}
async function loadSilenceRules() {
    silenceLoading.value = true;
    try { const r = await alertManagerApi.listSilenceRules(); silenceRules.value = r.data?.data?.items || []; } finally { silenceLoading.value = false; }
}
async function loadFatigueSettings() {
    fatigueLoading.value = true;
    try { const r = await alertManagerApi.listFatigueSettings(); fatigueSettings.value = r.data?.data || []; } finally { fatigueLoading.value = false; }
}
async function loadNoise() {
    noiseLoading.value = true;
    try { const r = await alertManagerApi.noiseAnalysis({ days: noiseDays.value }); Object.assign(noiseData, r.data?.data || {}); } finally { noiseLoading.value = false; }
}
async function runAggregate() {
    aggLoading.value = true;
    try { const r = await alertManagerApi.aggregate(); ElMessage.success(`已聚合 ${r.data?.data?.aggregated || 0} 个事件`); loadAggGroups(); } catch { ElMessage.error('聚合失败'); } finally { aggLoading.value = false; }
}
async function runDowngrade() {
    downgradeLoading.value = true;
    try { const r = await alertManagerApi.autoDowngrade(); ElMessage.success(`已降级 ${r.data?.data?.downgraded || 0} 个事件`); } catch { ElMessage.error('降级失败'); } finally { downgradeLoading.value = false; }
}
async function toggleSilence(row) {
    await alertManagerApi.toggleSilenceRule(row.id); ElMessage.success(row.is_active ? '已停用' : '已启用'); loadSilenceRules();
}
async function deleteSilence(row) {
    await ElMessageBox.confirm(`确认删除静默规则"${row.name}"？`);
    await alertManagerApi.deleteSilenceRule(row.id); ElMessage.success('已删除'); loadSilenceRules();
}
async function saveSilence() {
    saving.value = true;
    try {
        const data = { ...silenceForm };
        try { data.match_rules = JSON.parse(matchRulesText.value || '{}'); } catch { data.match_rules = {}; }
        await alertManagerApi.storeSilenceRule(data);
        ElMessage.success('已创建'); showSilenceForm.value = false; loadSilenceRules();
    } catch { ElMessage.error('创建失败'); } finally { saving.value = false; }
}
async function deleteFatigue(row) {
    await ElMessageBox.confirm('确认删除疲劳设置？');
    await alertManagerApi.deleteFatigueSetting(row.id); ElMessage.success('已删除'); loadFatigueSettings();
}
async function viewAggGroup(key) {
    const r = await alertManagerApi.aggregationDetail(key);
    const children = r.data?.data || [];
    let html = children.map(c => `<div>#${c.id} [${c.severity}] ${c.rule_name}: ${c.message || ''} <small>${c.created_at}</small></div>`).join('');
    ElMessageBox.alert(html || '无子事件', `聚合组: ${key}`, { dangerouslyUseHTMLString: true, customClass: 'msg-wide' });
}
async function loadDigest() {
    digestLoading.value = true;
    try { const r = await alertManagerApi.generateDigest(); Object.assign(digestData, r.data?.data || {}); } finally { digestLoading.value = false; }
}
function sevTag(s) { return { critical: 'danger', warning: 'warning', info: 'info' }[s] || 'info'; }
function fmtTime(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
</script>

<style scoped>
.alert-manager-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.metric-card .metric-value small { font-size: 13px; font-weight: 400; }
.success { color: #67c23a; } .warning { color: #e6a23c; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.ml-3 { margin-left: 12px; }
</style>

<style>
.msg-wide .el-message-box__message { max-height: 400px; overflow-y: auto; }
</style>
