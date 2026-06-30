<template>
    <div class="audit-governance-page">
        <div class="page-header">
            <div class="header-left">
                <h2>审计治理中心</h2>
                <span class="header-subtitle">合规报告、审计标签、数据保留治理</span>
            </div>
            <div class="header-right">
                <el-button @click="refreshAll">
                    <el-icon><Refresh /></el-icon> 刷新
                </el-button>
            </div>
        </div>

        <!-- 概览统计 -->
        <el-row :gutter="16" class="mb-4">
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">合规框架</div>
                        <div class="stat-value primary">{{ dashboard.frameworks?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">日志标签</div>
                        <div class="stat-value success">{{ dashboard.tag_stats?.length || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">备注总数</div>
                        <div class="stat-value warning">{{ dashboard.total_annotations || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="never">
                    <div class="stat-item">
                        <div class="stat-label">数据清理记录</div>
                        <div class="stat-value info">{{ dashboard.total_cleanups || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 主标签页 -->
        <el-tabs v-model="activeTab" type="border-card">
            <!-- 合规报告 -->
            <el-tab-pane label="合规报告" name="compliance">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <el-select v-model="complianceFilter.framework_id" placeholder="选择框架" clearable style="width: 200px" @change="fetchReports">
                            <el-option v-for="fw in dashboard.frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                        </el-select>
                        <el-select v-model="complianceFilter.status" placeholder="报告状态" clearable style="width: 140px" @change="fetchReports">
                            <el-option label="草稿" value="draft" />
                            <el-option label="已生成" value="generated" />
                            <el-option label="失败" value="failed" />
                        </el-select>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openGenerateDialog">
                            <el-icon><Plus /></el-icon> 生成报告
                        </el-button>
                        <el-button @click="handleSeedFrameworks" :loading="seeding">
                            初始化框架
                        </el-button>
                    </div>
                </div>

                <el-table :data="reports" v-loading="loadingReports" stripe>
                    <el-table-column label="报告标题" min-width="200">
                        <template #default="{ row }">
                            <el-button text @click="showReportDetail(row)">{{ row.title }}</el-button>
                        </template>
                    </el-table-column>
                    <el-table-column label="合规框架" width="140">
                        <template #default="{ row }">{{ row.framework?.name || '-' }}</template>
                    </el-table-column>
                    <el-table-column label="报告期" width="200">
                        <template #default="{ row }">{{ row.period_start }} ~ {{ row.period_end }}</template>
                    </el-table-column>
                    <el-table-column label="通过率" width="120" align="center">
                        <template #default="{ row }">
                            <el-progress
                                :percentage="calcPassRate(row)"
                                :status="calcPassRate(row) >= 80 ? 'success' : calcPassRate(row) >= 50 ? 'warning' : 'exception'"
                                :stroke-width="16"
                                :text-inside="true"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column label="风险等级" width="110">
                        <template #default="{ row }">
                            <el-tag :type="riskTag(row.risk_level)" size="small">
                                {{ riskLabel(row.risk_level) }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'generated' ? 'success' : row.status === 'draft' ? 'info' : 'danger'" size="small">
                                {{ row.status === 'generated' ? '已生成' : row.status === 'draft' ? '草稿' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="生成时间" width="170">
                        <template #default="{ row }">{{ formatTime(row.generated_at) }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="showReportDetail(row)">详情</el-button>
                            <el-popconfirm title="确定删除该报告?" @confirm="handleDeleteReport(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="pagination-wrap" v-if="reportMeta.total > reportMeta.per_page">
                    <el-pagination
                        v-model:current-page="reportPage"
                        :page-size="reportMeta.per_page"
                        :total="reportMeta.total"
                        layout="total, prev, pager, next"
                        @current-change="fetchReports"
                    />
                </div>

                <!-- 生成报告对话框 -->
                <el-dialog v-model="showGenerateDialog" title="生成合规报告" width="550px">
                    <el-form ref="genFormRef" :model="genForm" :rules="genRules" label-width="120px">
                        <el-form-item label="合规框架" prop="framework_id">
                            <el-select v-model="genForm.framework_id" placeholder="选择框架" style="width: 100%">
                                <el-option v-for="fw in dashboard.frameworks" :key="fw.id" :label="fw.name" :value="fw.id" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="报告标题">
                            <el-input v-model="genForm.title" placeholder="留空自动生成" />
                        </el-form-item>
                        <el-form-item label="报告类型">
                            <el-select v-model="genForm.type" style="width: 100%">
                                <el-option label="按需生成" value="on_demand" />
                                <el-option label="定时报告" value="scheduled" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="开始日期">
                            <el-date-picker v-model="genForm.period_start" type="date" placeholder="开始日期" style="width: 100%" value-format="YYYY-MM-DD" />
                        </el-form-item>
                        <el-form-item label="结束日期">
                            <el-date-picker v-model="genForm.period_end" type="date" placeholder="结束日期" style="width: 100%" value-format="YYYY-MM-DD" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showGenerateDialog = false">取消</el-button>
                        <el-button type="primary" :loading="generating" @click="handleGenerate">生成</el-button>
                    </template>
                </el-dialog>

                <!-- 报告详情对话框 -->
                <el-dialog v-model="showReportDialog" :title="reportDetail?.title || '报告详情'" width="800px">
                    <template v-if="reportDetail">
                        <el-descriptions :column="2" border size="small">
                            <el-descriptions-item label="合规框架">{{ reportDetail.framework?.name }}</el-descriptions-item>
                            <el-descriptions-item label="风险等级">
                                <el-tag :type="riskTag(reportDetail.risk_level)" size="small">{{ riskLabel(reportDetail.risk_level) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="报告期">{{ reportDetail.period_start }} ~ {{ reportDetail.period_end }}</el-descriptions-item>
                            <el-descriptions-item label="状态">
                                <el-tag :type="reportDetail.status === 'generated' ? 'success' : 'info'" size="small">
                                    {{ reportDetail.status === 'generated' ? '已生成' : '草稿' }}
                                </el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="通过/失败/NA">
                                {{ reportDetail.passed_count }} / {{ reportDetail.failed_count }} / {{ reportDetail.na_count }}
                            </el-descriptions-item>
                            <el-descriptions-item label="生成时间">{{ formatTime(reportDetail.generated_at) }}</el-descriptions-item>
                        </el-descriptions>

                        <div class="detail-section">
                            <h4>合规摘要</h4>
                            <p>{{ reportDetail.summary || '无摘要' }}</p>
                        </div>

                        <div v-if="reportDetail.findings?.length" class="detail-section">
                            <h4>控制域评估 ({{ reportDetail.findings.length }})</h4>
                            <el-table :data="reportDetail.findings" stripe size="small">
                                <el-table-column label="控制域" prop="domain" width="150" />
                                <el-table-column label="状态" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="row.status === 'pass' ? 'success' : row.status === 'warn' ? 'warning' : 'danger'" size="small">
                                            {{ row.status === 'pass' ? '通过' : row.status === 'warn' ? '需关注' : '未通过' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="描述" prop="description" min-width="200" />
                                <el-table-column label="关联事件数" width="120">
                                    <template #default="{ row }">{{ row.details?.total_events || 0 }}</template>
                                </el-table-column>
                            </el-table>
                        </div>

                        <div v-if="reportDetail.evidence_refs" class="detail-section">
                            <h4>证据统计</h4>
                            <el-descriptions :column="3" border size="small">
                                <el-descriptions-item label="总日志">{{ reportDetail.evidence_refs.total_logs }}</el-descriptions-item>
                                <el-descriptions-item label="审计日志">{{ reportDetail.evidence_refs.audit_logs }}</el-descriptions-item>
                                <el-descriptions-item label="安全日志">{{ reportDetail.evidence_refs.security_logs }}</el-descriptions-item>
                                <el-descriptions-item label="错误日志">{{ reportDetail.evidence_refs.error_logs }}</el-descriptions-item>
                                <el-descriptions-item label="Merkle 锚点">{{ reportDetail.evidence_refs.merkle_anchors }}</el-descriptions-item>
                                <el-descriptions-item label="日期范围">{{ reportDetail.evidence_refs.date_range?.from }} ~ {{ reportDetail.evidence_refs.date_range?.to }}</el-descriptions-item>
                            </el-descriptions>
                        </div>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 审计标签 -->
            <el-tab-pane label="审计标签" name="tags">
                <div class="tab-toolbar">
                    <div class="toolbar-left">
                        <span class="toolbar-title">日志标签管理</span>
                    </div>
                    <div class="toolbar-right">
                        <el-button type="primary" @click="openTagDialog()">
                            <el-icon><Plus /></el-icon> 新建标签
                        </el-button>
                    </div>
                </div>

                <el-table :data="tags" v-loading="loadingTags" stripe>
                    <el-table-column label="标签" min-width="200">
                        <template #default="{ row }">
                            <el-tag :color="row.color" :style="{ color: isLightColor(row.color) ? '#333' : '#fff' }" effect="dark">
                                {{ row.name }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="颜色" width="100">
                        <template #default="{ row }">
                            <el-color-picker v-model="row.color" :predefine="predefineColors" size="small" @change="(val) => handleUpdateTag(row, { color: val })" />
                        </template>
                    </el-table-column>
                    <el-table-column label="使用次数" width="100" align="center" prop="logs_count" />
                    <el-table-column label="操作" width="180" fixed="right">
                        <template #default="{ row }">
                            <el-button text size="small" type="primary" @click="openTagDialog(row)">编辑</el-button>
                            <el-popconfirm title="确定删除此标签?" @confirm="handleDeleteTag(row)">
                                <template #reference>
                                    <el-button text size="small" type="danger">删除</el-button>
                                </template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>

                <!-- 标签编辑对话框 -->
                <el-dialog v-model="showTagDialog" :title="editingTagId ? '编辑标签' : '新建标签'" width="420px">
                    <el-form ref="tagFormRef" :model="tagForm" :rules="tagRules" label-width="80px">
                        <el-form-item label="名称" prop="name">
                            <el-input v-model="tagForm.name" maxlength="100" />
                        </el-form-item>
                        <el-form-item label="颜色">
                            <el-color-picker v-model="tagForm.color" :predefine="predefineColors" show-alpha />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showTagDialog = false">取消</el-button>
                        <el-button type="primary" :loading="savingTag" @click="handleSaveTag">保存</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 数据保留治理 -->
            <el-tab-pane label="数据保留治理" name="retention">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">日志总量</div>
                                <div class="stat-value primary">{{ retentionDashboard.total_logs || 0 }}</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="never">
                            <div class="stat-item">
                                <div class="stat-label">存储占用</div>
                                <div class="stat-value warning">{{ retentionDashboard.total_storage_mb || 0 }} MB</div>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="never">
                            <div class="stat-row">
                                <span class="stat-label-sm">按类型待清理：</span>
                                <span v-for="item in retentionDashboard.by_type" :key="item.type" class="type-badge">
                                    <el-tag
                                        size="small"
                                        :type="item.to_prune > 0 ? 'danger' : 'success'"
                                        effect="plain"
                                        style="margin-right:4px"
                                    >
                                        {{ typeLabel(item.type) }}: {{ item.to_prune }} 条
                                    </el-tag>
                                </span>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-row :gutter="16" class="mb-4">
                    <el-col :span="16">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>日志类型分布与保留策略</span>
                                </div>
                            </template>
                            <el-table :data="retentionDashboard.by_type" v-loading="loadingRetention" stripe size="small">
                                <el-table-column label="类型" width="100">
                                    <template #default="{ row }">
                                        <el-tag :type="typeTag(row.type)" size="small">{{ typeLabel(row.type) }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="日志量" width="90" align="center" prop="count" />
                                <el-table-column label="保留天数" width="100" align="center" prop="retention_days" />
                                <el-table-column label="待清理" width="90" align="center">
                                    <template #default="{ row }">
                                        <span :class="{ 'text-danger': row.to_prune > 0 }">{{ row.to_prune }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column label="存储 (MB)" width="100" align="center" prop="storage_mb" />
                                <el-table-column label="最早日志" width="150" prop="oldest" />
                                <el-table-column label="操作" width="140">
                                    <template #default="{ row }">
                                        <el-button
                                            text
                                            size="small"
                                            type="danger"
                                            :loading="cleaningType === row.type"
                                            @click="handleCleanup(row.type)"
                                        >
                                            立即清理
                                        </el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </el-card>
                    </el-col>
                    <el-col :span="8">
                        <el-card shadow="never">
                            <template #header>
                                <div class="card-header">
                                    <span>清理历史</span>
                                </div>
                            </template>
                            <div v-if="!cleanupHistory.length" class="empty-state">暂无清理记录</div>
                            <div v-for="item in cleanupHistory" :key="item.id" class="history-item">
                                <div class="history-header">
                                    <el-tag :type="typeTag(item.type)" size="small">{{ typeLabel(item.type) }}</el-tag>
                                    <el-tag :type="item.status === 'completed' ? 'success' : item.status === 'partial' ? 'warning' : 'danger'" size="small">
                                        {{ item.status === 'completed' ? '完成' : item.status === 'partial' ? '部分' : '失败' }}
                                    </el-tag>
                                </div>
                                <div class="history-body">
                                    清理 {{ item.pruned_count }} 条 ({{ item.total_logs_before }} → {{ item.total_logs_after }})
                                </div>
                                <div class="history-time">{{ formatTime(item.executed_at) }}</div>
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus } from '@element-plus/icons-vue';
import auditGovernanceApi from '@/api/auditGovernance';

// ─── 标签 ───
const activeTab = ref('compliance');

// ─── 概览仪表盘 ───
const dashboard = reactive({
    frameworks: [],
    tag_stats: [],
    total_annotations: 0,
    total_batch_ops: 0,
    total_cleanups: 0,
});

async function fetchDashboard() {
    try {
        const res = await auditGovernanceApi.governanceDashboard();
        if (res.success) Object.assign(dashboard, res.data || {});
    } catch { /* ignore */ }
}

// ─── 合规报告 ───
const reports = ref([]);
const reportMeta = reactive({ total: 0, per_page: 20, current_page: 1 });
const reportPage = ref(1);
const loadingReports = ref(false);
const seeding = ref(false);
const showGenerateDialog = ref(false);
const generating = ref(false);
const showReportDialog = ref(false);
const reportDetail = ref(null);
const genFormRef = ref(null);
const complianceFilter = reactive({
    framework_id: '',
    status: '',
});
const genForm = reactive({
    framework_id: '',
    title: '',
    type: 'on_demand',
    period_start: '',
    period_end: '',
});
const genRules = {
    framework_id: [{ required: true, message: '请选择合规框架', trigger: 'change' }],
};

async function fetchReports() {
    loadingReports.value = true;
    try {
        const params = { page: reportPage.value, per_page: 20 };
        if (complianceFilter.framework_id) params.framework_id = complianceFilter.framework_id;
        if (complianceFilter.status) params.status = complianceFilter.status;
        const res = await auditGovernanceApi.reports(params);
        if (res.success) {
            reports.value = res.data || [];
            Object.assign(reportMeta, res.meta || {});
        }
    } catch {
        ElMessage.error('加载报告列表失败');
    } finally {
        loadingReports.value = false;
    }
}

async function handleSeedFrameworks() {
    seeding.value = true;
    try {
        const res = await auditGovernanceApi.seedFrameworks();
        if (res.success) {
            ElMessage.success('合规框架已初始化');
            await fetchDashboard();
        }
    } catch {
        ElMessage.error('初始化失败');
    } finally {
        seeding.value = false;
    }
}

function openGenerateDialog() {
    genForm.framework_id = '';
    genForm.title = '';
    genForm.type = 'on_demand';
    genForm.period_start = '';
    genForm.period_end = '';
    showGenerateDialog.value = true;
}

async function handleGenerate() {
    if (!genFormRef.value) return;
    const valid = await genFormRef.value.validate().catch(() => false);
    if (!valid) return;

    generating.value = true;
    try {
        const data = { ...genForm };
        // 过滤空值
        Object.keys(data).forEach(k => { if (!data[k]) delete data[k]; });
        const res = await auditGovernanceApi.generateReport(data);
        if (res.success) {
            ElMessage.success('合规报告已生成');
            showGenerateDialog.value = false;
            await fetchReports();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '生成失败');
    } finally {
        generating.value = false;
    }
}

function showReportDetail(row) {
    if (row.findings || row.evidence_refs) {
        reportDetail.value = row;
    } else {
        // 如果列表数据不完整，从 API 获取详情
        auditGovernanceApi.showReport(row.id).then(res => {
            reportDetail.value = res.data || row;
        }).catch(() => {
            reportDetail.value = row;
        });
    }
    showReportDialog.value = true;
}

async function handleDeleteReport(row) {
    try {
        const res = await auditGovernanceApi.deleteReport(row.id);
        if (res.success) {
            ElMessage.success('报告已删除');
            await fetchReports();
        }
    } catch {
        ElMessage.error('删除失败');
    }
}

function calcPassRate(row) {
    const total = row.passed_count + row.failed_count;
    if (!total) return 0;
    return Math.round((row.passed_count / total) * 100);
}

// ─── 标签管理 ───
const tags = ref([]);
const loadingTags = ref(false);
const showTagDialog = ref(false);
const editingTagId = ref(null);
const savingTag = ref(false);
const tagFormRef = ref(null);
const tagForm = reactive({ name: '', color: '#409EFF' });
const tagRules = { name: [{ required: true, message: '请输入标签名称', trigger: 'blur' }] };
const predefineColors = [
    '#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399',
    '#B37FEB', '#36CFC9', '#FF85C0', '#FF9F43', '#2E86DE',
];

async function fetchTags() {
    loadingTags.value = true;
    try {
        const res = await auditGovernanceApi.tags();
        if (res.success) tags.value = res.data || [];
    } catch {
        ElMessage.error('加载标签失败');
    } finally {
        loadingTags.value = false;
    }
}

function openTagDialog(row) {
    if (row) {
        editingTagId.value = row.id;
        tagForm.name = row.name;
        tagForm.color = row.color || '#409EFF';
    } else {
        editingTagId.value = null;
        tagForm.name = '';
        tagForm.color = '#409EFF';
    }
    showTagDialog.value = true;
}

async function handleSaveTag() {
    if (!tagFormRef.value) return;
    const valid = await tagFormRef.value.validate().catch(() => false);
    if (!valid) return;

    savingTag.value = true;
    try {
        if (editingTagId.value) {
            await auditGovernanceApi.updateTag(editingTagId.value, { name: tagForm.name, color: tagForm.color });
            ElMessage.success('标签已更新');
        } else {
            await auditGovernanceApi.createTag({ name: tagForm.name, color: tagForm.color });
            ElMessage.success('标签已创建');
        }
        showTagDialog.value = false;
        await fetchTags();
        await fetchDashboard();
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '操作失败');
    } finally {
        savingTag.value = false;
    }
}

async function handleDeleteTag(row) {
    try {
        await auditGovernanceApi.deleteTag(row.id);
        ElMessage.success('标签已删除');
        await fetchTags();
        await fetchDashboard();
    } catch {
        ElMessage.error('删除失败');
    }
}

async function handleUpdateTag(row, patch) {
    try {
        await auditGovernanceApi.updateTag(row.id, patch);
    } catch {
        ElMessage.error('更新失败');
    }
}

// ─── 数据保留治理 ───
const retentionDashboard = reactive({
    by_type: [], recent_cleanups: [], storage_trend: {}, total_logs: 0, total_storage_mb: 0,
});
const loadingRetention = ref(false);
const cleanupHistory = ref([]);
const cleaningType = ref('');

async function fetchRetentionDashboard() {
    loadingRetention.value = true;
    try {
        const res = await auditGovernanceApi.retentionDashboard();
        if (res.success) {
            Object.assign(retentionDashboard, res.data || {});
        }
    } catch {
        ElMessage.error('加载治理仪表盘失败');
    } finally {
        loadingRetention.value = false;
    }
}

async function fetchCleanupHistory() {
    try {
        const res = await auditGovernanceApi.cleanupHistory();
        if (res.success) cleanupHistory.value = res.data || [];
    } catch { /* ignore */ }
}

async function handleCleanup(type) {
    try {
        await ElMessageBox.confirm(`确定要清理「${typeLabel(type)}」类型的过期日志吗？此操作不可撤销。`, '确认清理', {
            type: 'warning',
            confirmButtonText: '确认清理',
            cancelButtonText: '取消',
        });
    } catch { return; }

    cleaningType.value = type;
    try {
        const res = await auditGovernanceApi.executeCleanup({ type });
        if (res.success) {
            ElMessage.success(`已清理 ${res.data?.pruned_count || 0} 条日志`);
            await fetchRetentionDashboard();
            await fetchCleanupHistory();
        }
    } catch (err) {
        ElMessage.error(err.response?.data?.message || '清理失败');
    } finally {
        cleaningType.value = '';
    }
}

// ─── 工具函数 ───
function riskLabel(level) {
    const map = { low: '低风险', medium: '中风险', high: '高风险', critical: '严重风险' };
    return map[level] || level || '未知';
}

function riskTag(level) {
    const map = { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' };
    return map[level] || 'info';
}

function typeLabel(type) {
    const map = { audit: '审计', security: '安全', error: '错误', system: '系统' };
    return map[type] || type;
}

function typeTag(type) {
    const map = { audit: 'primary', security: 'danger', error: 'warning', system: 'info' };
    return map[type] || 'info';
}

function formatTime(time) {
    if (!time) return '—';
    return new Date(time).toLocaleString('zh-CN');
}

function isLightColor(hex) {
    if (!hex) return true;
    const c = hex.replace('#', '');
    const r = parseInt(c.substring(0, 2), 16);
    const g = parseInt(c.substring(2, 4), 16);
    const b = parseInt(c.substring(4, 6), 16);
    return (r * 299 + g * 587 + b * 114) / 1000 > 155;
}

async function refreshAll() {
    await Promise.all([
        fetchDashboard(),
        fetchReports(),
        fetchTags(),
        fetchRetentionDashboard(),
        fetchCleanupHistory(),
    ]);
}

onMounted(async () => {
    await fetchDashboard();
    await fetchReports();
    await fetchTags();
    await fetchRetentionDashboard();
    await fetchCleanupHistory();
});
</script>

<style scoped>
.audit-governance-page { padding: 20px; }

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    margin-left: 12px;
}

.mb-4 { margin-bottom: 16px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-label {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-bottom: 6px;
}
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-text-color-primary); }
.stat-value.primary { color: var(--el-color-primary); }
.stat-value.success { color: var(--el-color-success); }
.stat-value.warning { color: var(--el-color-warning); }
.stat-value.info { color: var(--el-color-info); }

.stat-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 0;
}
.stat-label-sm {
    font-size: 13px;
    color: var(--el-text-color-secondary);
    white-space: nowrap;
}
.type-badge { display: inline-flex; }

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.card-header span { font-weight: 600; font-size: 14px; }

.tab-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    gap: 12px;
}
.toolbar-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.toolbar-right {
    display: flex;
    align-items: center;
    gap: 8px;
}
.toolbar-title {
    font-size: 14px;
    font-weight: 500;
    color: var(--el-text-color-regular);
}

.pagination-wrap {
    margin-top: 16px;
    display: flex;
    justify-content: flex-end;
}

.detail-section { margin-top: 20px; }
.detail-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 8px;
}
.detail-section p {
    font-size: 14px;
    line-height: 1.6;
    color: var(--el-text-color-regular);
}

.text-danger { color: var(--el-color-danger); font-weight: 600; }

.history-item {
    padding: 10px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
}
.history-item:last-child { border-bottom: none; }
.history-header {
    display: flex;
    gap: 6px;
    margin-bottom: 4px;
}
.history-body { font-size: 13px; color: var(--el-text-color-regular); }
.history-time { font-size: 12px; color: var(--el-text-color-secondary); margin-top: 2px; }

.empty-state {
    text-align: center;
    padding: 30px 0;
    color: var(--el-text-color-placeholder);
    font-size: 13px;
}

:deep(.el-card__body) { padding: 16px; }
</style>
