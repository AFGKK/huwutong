<template>
    <div class="compliance-ai-page">
        <h2>AI 合规报告生成</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total || 0 }}</div><div class="stat-label">总报告数</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.completed || 0 }}</div><div class="stat-label">已完成</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.failed || 0 }}</div><div class="stat-label">失败</div></div></el-card></el-col>
            <el-col :span="6"><el-card shadow="hover"><div class="stat-card">
                <el-select v-model="genFramework" style="width:100%">
                    <el-option label="GDPR" value="gdpr" />
                    <el-option label="SOC 2" value="soc2" />
                    <el-option label="ISO 27001" value="iso27001" />
                </el-select>
                <el-button type="primary" size="small" style="margin-top:8px;width:100%" @click="handleGenerate" :loading="generating">生成报告</el-button>
            </div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <el-tab-pane label="报告列表" name="list">
                <el-table :data="reports" v-loading="loading" stripe @row-click="showDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column label="框架" width="100"><template #default="{row}"><el-tag size="small">{{ row.framework?.toUpperCase() }}</el-tag></template></el-table-column>
                    <el-table-column prop="title" label="标题" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="100"><template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='failed'?'danger':'warning'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column label="生成人" width="120"><template #default="{row}">{{ row.generator?.name }}</template></el-table-column>
                    <el-table-column prop="created_at" label="生成时间" width="170" />
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="报告详情" name="detail" :disabled="!selectedReport">
                <div v-if="selectedReport">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="报告标题" :span="2">{{ selectedReport.title }}</el-descriptions-item>
                        <el-descriptions-item label="框架">{{ selectedReport.framework?.toUpperCase() }}</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedReport.status }}</el-descriptions-item>
                        <el-descriptions-item label="语言">{{ selectedReport.language }}</el-descriptions-item>
                        <el-descriptions-item label="生成时间">{{ selectedReport.generated_at }}</el-descriptions-item>
                    </el-descriptions>

                    <!-- 证据统计 -->
                    <el-card shadow="never" style="margin-top:16px">
                        <template #header><span>证据项统计</span></template>
                        <div v-if="selectedReport.evidence_summary">
                            <el-tag style="margin-right:6px">总数: {{ selectedReport.evidence_summary.total_items }}</el-tag>
                            <el-tag type="success" style="margin-right:6px">合规: {{ selectedReport.evidence_summary.compliant }}</el-tag>
                            <el-tag type="warning" style="margin-right:6px">部分: {{ selectedReport.evidence_summary.partial }}</el-tag>
                            <el-tag type="danger" style="margin-right:6px">不合规: {{ selectedReport.evidence_summary.non_compliant }}</el-tag>
                            <el-tag type="info">不适用: {{ selectedReport.evidence_summary.not_applicable }}</el-tag>
                        </div>
                    </el-card>

                    <!-- 章节内容 -->
                    <el-card shadow="never" style="margin-top:16px">
                        <template #header><span>报告章节</span></template>
                        <el-timeline v-if="selectedReport.sections?.length">
                            <el-timeline-item
                                v-for="sec in selectedReport.sections"
                                :key="sec.section"
                                :color="sec.status==='compliant'?'#67c23a':sec.status==='partial'?'#e6a23c':'#f56c6c'"
                            >
                                <div style="font-weight:600">{{ sec.section }}</div>
                                <div style="font-size:13px;color:#606266;margin-top:4px">{{ sec.description }}</div>
                            </el-timeline-item>
                        </el-timeline>
                        <el-empty v-else description="暂无章节数据" />
                    </el-card>

                    <!-- 差距分析 -->
                    <el-card v-if="selectedReport.gap_analysis" shadow="never" style="margin-top:16px">
                        <template #header><span>差距分析</span></template>
                        <p>{{ selectedReport.gap_analysis.summary }}</p>
                    </el-card>

                    <!-- 改进建议 -->
                    <el-card v-if="selectedReport.recommendations?.length" shadow="never" style="margin-top:16px">
                        <template #header><span>改进建议</span></template>
                        <el-timeline>
                            <el-timeline-item
                                v-for="(rec, i) in selectedReport.recommendations"
                                :key="i"
                                :color="rec.priority==='high'?'#f56c6c':rec.priority==='medium'?'#e6a23c':'#909399'"
                            >
                                <div style="font-weight:600">{{ rec.action }}</div>
                                <el-tag :type="rec.priority==='high'?'danger':rec.priority==='medium'?'warning':'info'" size="small" style="margin-top:4px">{{ rec.priority }}</el-tag>
                            </el-timeline-item>
                        </el-timeline>
                    </el-card>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { getComplianceReportDashboard, getComplianceReports, generateComplianceReport, getComplianceReportDetail } from '@/api/complianceReportAi';

const activeTab = ref('list');
const stats = ref({});
const reports = ref([]);
const loading = ref(false);
const generating = ref(false);
const selectedReport = ref(null);
const genFramework = ref('gdpr');

async function loadDashboard() {
    try { stats.value = await getComplianceReportDashboard(); } catch (e) { console.error(e); }
}
async function loadReports() {
    loading.value = true;
    try { const r = await getComplianceReports({ per_page: 50 }); reports.value = r.data || []; }
    catch (e) { console.error(e); } finally { loading.value = false; }
}
async function handleGenerate() {
    generating.value = true;
    try {
        const r = await generateComplianceReport(genFramework.value, 'zh-CN');
        ElMessage.success('报告生成完成');
        loadReports();
        loadDashboard();
        selectedReport.value = r;
        activeTab.value = 'detail';
    } catch (e) { ElMessage.error('生成失败'); } finally { generating.value = false; }
}
async function showDetail(row) {
    try { const r = await getComplianceReportDetail(row.id); selectedReport.value = r; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error('获取详情失败'); }
}

onMounted(() => { loadDashboard(); loadReports(); });
</script>

<style scoped>
.compliance-ai-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.danger { color: #f56c6c; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
</style>
