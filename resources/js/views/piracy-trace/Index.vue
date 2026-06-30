<template>
    <div class="piracy-trace-page">
        <h2>AI 盗版溯源</h2>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.totalScans || 0 }}</div><div class="stat-label">扫描任务</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value danger">{{ stats.openCases || 0 }}</div><div class="stat-label">未处理证据</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.confirmedLeaks || 0 }}</div><div class="stat-label">已确认泄露</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.resolvedCases || 0 }}</div><div class="stat-label">已处理</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.falsePositives || 0 }}</div><div class="stat-label">误报</div></div></el-card></el-col>
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.totalEvidence || 0 }}</div><div class="stat-label">总证据数</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 扫描任务 -->
            <el-tab-pane label="扫描任务" name="scans">
                <div class="toolbar">
                    <el-button type="primary" @click="showScanDialog = true">新建扫描</el-button>
                    <el-button @click="loadScans">刷新</el-button>
                </div>
                <el-table :data="scans" v-loading="scansLoading" stripe>
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" label="来源" width="100">
                        <template #default="{row}"><el-tag size="small">{{ row.source }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="query" label="关键词" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{row}"><el-tag :type="row.status==='completed'?'success':row.status==='running'?'warning':'info'" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="urls_found" label="发现URL" width="90" align="center" />
                    <el-table-column prop="matches_found" label="匹配" width="70" align="center" />
                    <el-table-column prop="confirmed" label="确认" width="70" align="center" />
                    <el-table-column label="操作" width="120">
                        <template #default="{row}">
                            <el-button size="small" type="primary" @click="handleRunScan(row)" :disabled="row.status==='running'">执行</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="创建时间" width="170" />
                </el-table>

                <el-dialog v-model="showScanDialog" title="新建扫描" width="500px">
                    <el-form :model="scanForm" label-width="100px">
                        <el-form-item label="来源"><el-select v-model="scanForm.source" style="width:100%"><el-option label="GitHub" value="github" /><el-option label="手动输入" value="manual" /></el-select></el-form-item>
                        <el-form-item label="关键词/URL"><el-input v-model="scanForm.query" type="textarea" :rows="4" placeholder="GitHub搜索词 或 手动输入URL(每行一个)" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showScanDialog = false">取消</el-button><el-button type="primary" @click="handleCreateScan">创建</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 证据列表 -->
            <el-tab-pane label="泄露证据" name="evidence">
                <div class="toolbar">
                    <el-select v-model="evFilter.status" placeholder="状态" clearable style="width:130px;margin-right:8px" @change="loadEvidence"><el-option label="未处理" value="open" /><el-option label="调查中" value="investigating" /><el-option label="已确认" value="confirmed" /><el-option label="误报" value="false_positive" /><el-option label="已处理" value="resolved" /></el-select>
                    <el-select v-model="evFilter.confidence_level" placeholder="可信度" clearable style="width:120px;margin-right:8px" @change="loadEvidence"><el-option label="已确认" value="confirmed" /><el-option label="高" value="high" /><el-option label="中" value="medium" /><el-option label="低" value="low" /></el-select>
                    <el-button @click="loadEvidence">刷新</el-button>
                </div>
                <el-table :data="evidenceList" v-loading="evLoading" stripe @row-click="showEvidenceDetail">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="source" label="来源" width="90" />
                    <el-table-column prop="source_url" label="泄露URL" min-width="300" show-overflow-tooltip />
                    <el-table-column label="可信度" width="100">
                        <template #default="{row}"><el-tag :type="confidenceTag(row.confidence_level)" size="small">{{ row.confidence_level }}({{ row.confidence }}%)</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{row}"><el-tag :type="statusTag(row.status)" size="small">{{ row.status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="license_key" label="License Key" width="200" show-overflow-tooltip />
                    <el-table-column prop="detected_at" label="发现时间" width="170" />
                </el-table>
            </el-tab-pane>

            <!-- 证据详情 -->
            <el-tab-pane label="证据详情" name="detail" :disabled="!selectedEvidence">
                <div v-if="selectedEvidence">
                    <el-descriptions :column="2" border>
                        <el-descriptions-item label="泄露URL" :span="2">{{ selectedEvidence.source_url }}</el-descriptions-item>
                        <el-descriptions-item label="来源">{{ selectedEvidence.source }}</el-descriptions-item>
                        <el-descriptions-item label="可信度">{{ selectedEvidence.confidence_level }}({{ selectedEvidence.confidence }}%)</el-descriptions-item>
                        <el-descriptions-item label="状态">{{ selectedEvidence.status }}</el-descriptions-item>
                        <el-descriptions-item label="License Key">{{ selectedEvidence.license_key || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="发现时间">{{ selectedEvidence.detected_at }}</el-descriptions-item>
                    </el-descriptions>

                    <el-form :model="evUpdateForm" label-width="100px" style="margin-top:16px;max-width:600px">
                        <el-form-item label="状态"><el-select v-model="evUpdateForm.status" style="width:100%"><el-option label="未处理" value="open" /><el-option label="调查中" value="investigating" /><el-option label="已确认" value="confirmed" /><el-option label="误报" value="false_positive" /><el-option label="已处理" value="resolved" /></el-select></el-form-item>
                        <el-form-item label="备注"><el-input v-model="evUpdateForm.notes" type="textarea" :rows="3" /></el-form-item>
                        <el-form-item><el-button type="primary" @click="handleUpdateEvidence">更新</el-button></el-form-item>
                    </el-form>

                    <div style="margin-top:12px">
                        <el-button type="danger" @click="handleAutoRemediate" :disabled="selectedEvidence.status==='resolved'">自动处理(吊销+取证)</el-button>
                        <el-button type="warning" @click="handleGenerateReport">生成取证报告</el-button>
                    </div>
                </div>
                <el-empty v-else description="请选择证据查看详情" />
            </el-tab-pane>

            <!-- 取证报告 -->
            <el-tab-pane label="取证报告" name="reports">
                <el-table :data="reports" v-loading="reportsLoading" stripe @row-click="showReportDetail">
                    <el-table-column prop="title" label="报告标题" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="report_type" label="类型" width="100" />
                    <el-table-column prop="status" label="状态" width="90" />
                    <el-table-column label="生成人" width="120"><template #default="{row}">{{ row.generator?.name }}</template></el-table-column>
                    <el-table-column prop="generated_at" label="生成时间" width="170" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { getPiracyDashboard, getScanTasks, createScanTask, runScanTask, getEvidence, getEvidenceDetail, updateEvidence, autoRemediate, generateReport, getForensicReports, getReportDetail } from '@/api/piracyTrace';

const activeTab = ref('scans');
const stats = ref({});

// Scans
const scans = ref([]);
const scansLoading = ref(false);
const showScanDialog = ref(false);
const scanForm = reactive({ source: 'github', query: '' });

// Evidence
const evidenceList = ref([]);
const evLoading = ref(false);
const evFilter = reactive({ status: 'open', confidence_level: '' });
const selectedEvidence = ref(null);
const evUpdateForm = reactive({ status: 'open', notes: '' });

// Reports
const reports = ref([]);
const reportsLoading = ref(false);

const confidenceTag = (l) => ({ confirmed: 'danger', high: 'warning', medium: 'info', low: 'success' }[l] || 'info');
const statusTag = (s) => ({ open: 'danger', investigating: 'warning', confirmed: 'danger', resolved: 'success', false_positive: 'info' }[s] || 'info');

async function loadDashboard() {
    try { stats.value = await getPiracyDashboard(); } catch (e) { console.error(e); }
}
async function loadScans() {
    scansLoading.value = true;
    try { const r = await getScanTasks({ per_page: 50 }); scans.value = r.data || []; }
    catch (e) { console.error(e); } finally { scansLoading.value = false; }
}
async function loadEvidence() {
    evLoading.value = true;
    try { const r = await getEvidence({ status: evFilter.status || undefined, confidence_level: evFilter.confidence_level || undefined, per_page: 50 }); evidenceList.value = r.data || []; }
    catch (e) { console.error(e); } finally { evLoading.value = false; }
}
async function loadReports() {
    reportsLoading.value = true;
    try { const r = await getForensicReports({ per_page: 50 }); reports.value = r.data || []; }
    catch (e) { console.error(e); } finally { reportsLoading.value = false; }
}
async function handleCreateScan() {
    try { await createScanTask(scanForm); ElMessage.success('扫描任务已创建'); showScanDialog.value = false; loadScans(); }
    catch (e) { ElMessage.error('创建失败'); }
}
async function handleRunScan(row) {
    try { await runScanTask(row.id); ElMessage.success('扫描完成'); loadScans(); loadDashboard(); loadEvidence(); }
    catch (e) { ElMessage.error('扫描失败'); }
}
async function showEvidenceDetail(row) {
    try { const r = await getEvidenceDetail(row.id); selectedEvidence.value = r; evUpdateForm.status = r.status; evUpdateForm.notes = r.notes || ''; activeTab.value = 'detail'; }
    catch (e) { ElMessage.error('获取详情失败'); }
}
async function handleUpdateEvidence() {
    try { await updateEvidence(selectedEvidence.value.id, evUpdateForm); ElMessage.success('已更新'); loadEvidence(); }
    catch (e) { ElMessage.error('更新失败'); }
}
async function handleAutoRemediate() {
    try { await ElMessageBox.confirm('将自动吊销关联License、通知客户、生成取证报告，确定继续？', '确认', { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' });
        await autoRemediate(selectedEvidence.value.id); ElMessage.success('自动处理完成'); loadDashboard(); loadEvidence(); }
    catch (e) { if (e !== 'cancel') ElMessage.error('处理失败'); }
}
async function handleGenerateReport() {
    try { await generateReport(selectedEvidence.value.id); ElMessage.success('取证报告已生成'); loadReports(); }
    catch (e) { ElMessage.error('生成失败'); }
}
async function showReportDetail(row) {
    try { const r = await getReportDetail(row.id); /* show detail */ ElMessage.info(`报告: ${r.title}`); }
    catch (e) { console.error(e); }
}

onMounted(() => { loadDashboard(); loadScans(); loadEvidence(); loadReports(); });
</script>

<style scoped>
.piracy-trace-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.danger { color: #f56c6c; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
</style>
