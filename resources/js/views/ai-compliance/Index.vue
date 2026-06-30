<template>
    <div class="ai-compliance-page">
        <div class="page-header">
            <div>
                <h2>ISO 42001 AI 合规管理</h2>
                <p class="text-muted">AI 系统清单 · 风险影响评估 · 偏见检测 · 透明度披露 · 人工申诉 · 合规差距分析</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button type="primary" @click="activeTab = 'report'" :icon="DataBoard">合规报告</el-button>
            </div>
        </div>

        <!-- 合规评分 -->
        <el-alert v-if="complianceScore" :title="'ISO 42001 合规评分: ' + complianceScore.score + '分 — ' + complianceScore.label"
            :type="complianceScore.level === 'compliant' ? 'success' : (complianceScore.level === 'partial' ? 'warning' : 'error')"
            show-icon :closable="false" class="mb-4" />

        <!-- Tab 导航 -->
        <el-tabs v-model="activeTab" type="border-card" class="mb-4">
            <!-- 看板总览 -->
            <el-tab-pane label="📊 看板总览" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">AI 系统总数</div><div class="metric-value">{{ dash.system_count }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">活跃系统</div><div class="metric-value success">{{ dash.active_systems }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">高风险系统</div><div class="metric-value danger">{{ dash.high_risk_systems }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">待评审</div><div class="metric-value warning">{{ dash.pending_reviews }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">偏见标记</div><div class="metric-value danger">{{ dash.open_bias_flags }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">待处理申诉</div><div class="metric-value warning">{{ dash.pending_overrides }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">AI 决策总数</div><div class="metric-value">{{ dash.total_decisions }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">近期评估</div><div class="metric-value">{{ dash.recent_assessments }}</div></el-card>
                    </el-col>
                </el-row>
                <div class="text-center text-muted" style="padding:40px 0">上方卡片展示所有 AI 合规关键指标。切换到各标签页进行详细管理。</div>
            </el-tab-pane>

            <!-- AI 系统清单 -->
            <el-tab-pane label="🤖 AI 系统清单" name="systems">
                <div class="section-header"><span>{{ systemsInfo }}</span><el-button type="primary" size="small" @click="showSystemForm = true">+ 新增系统</el-button></div>
                <el-table :data="systems" stripe v-loading="sysLoading" size="small">
                    <el-table-column prop="name" label="名称" min-width="140" />
                    <el-table-column prop="version" label="版本" width="80" />
                    <el-table-column prop="purpose" label="用途" min-width="180" show-overflow-tooltip />
                    <el-table-column prop="provider" label="供应商" width="120" />
                    <el-table-column label="状态" width="80">
                        <template #default="{row}"><el-tag :type="row.deployment_status === 'production' ? 'success' : 'info'" size="small">{{ row.deployment_status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="风险" width="70">
                        <template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template>
                    </el-table-column>
                    <el-table-column label="活跃" width="60">
                        <template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template>
                    </el-table-column>
                    <el-table-column label="下次评审" width="120">
                        <template #default="{row}">{{ row.next_review_at ? fmtDate(row.next_review_at) : '—' }}</template>
                    </el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="viewSystem(row)">详情</el-button>
                            <el-button size="small" @click="editSystem(row)">编辑</el-button>
                            <el-popconfirm title="确认删除?" @confirm="deleteSystem(row)">
                                <template #reference><el-button size="small" type="danger">删除</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
                <!-- 系统表单对话框 -->
                <el-dialog v-model="showSystemForm" :title="editingSystem ? '编辑AI系统' : '新增AI系统'" width="600px">
                    <el-form :model="sysForm" label-width="120px" ref="sysFormRef">
                        <el-form-item label="名称" prop="name" :rules="[{required:true}]"><el-input v-model="sysForm.name" /></el-form-item>
                        <el-form-item label="版本" prop="version" :rules="[{required:true}]"><el-input v-model="sysForm.version" /></el-form-item>
                        <el-form-item label="用途" prop="purpose" :rules="[{required:true}]"><el-input v-model="sysForm.purpose" type="textarea" :rows="2" /></el-form-item>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item label="供应商"><el-input v-model="sysForm.provider" /></el-form-item></el-col>
                            <el-col :span="12"><el-form-item label="部署状态" prop="deployment_status" :rules="[{required:true}]">
                                <el-select v-model="sysForm.deployment_status" style="width:100%">
                                    <el-option label="开发" value="development" /><el-option label="预发布" value="staging" /><el-option label="生产" value="production" /><el-option label="已退役" value="retired" />
                                </el-select>
                            </el-form-item></el-col>
                        </el-row>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item label="风险等级" prop="risk_level" :rules="[{required:true}]">
                                <el-select v-model="sysForm.risk_level" style="width:100%">
                                    <el-option label="低风险" value="low" /><el-option label="中风险" value="medium" /><el-option label="高风险" value="high" /><el-option label="极高风险" value="critical" />
                                </el-select>
                            </el-form-item></el-col>
                            <el-col :span="12"><el-form-item label="负责部门"><el-input v-model="sysForm.owner_department" /></el-form-item></el-col>
                        </el-row>
                        <el-form-item label="负责人邮箱"><el-input v-model="sysForm.owner_email" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showSystemForm = false">取消</el-button>
                        <el-button type="primary" @click="saveSystem" :loading="saving">保存</el-button>
                    </template>
                </el-dialog>
                <!-- 系统详情对话框 -->
                <el-dialog v-model="showSysDetail" :title="'系统详情: ' + (sysDetail?.name || '')" width="700px">
                    <el-descriptions :column="2" border size="small" v-if="sysDetail">
                        <el-descriptions-item label="名称">{{ sysDetail.name }}</el-descriptions-item>
                        <el-descriptions-item label="版本">{{ sysDetail.version }}</el-descriptions-item>
                        <el-descriptions-item label="供应商">{{ sysDetail.provider || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="风险等级"><el-tag :type="riskTag(sysDetail.risk_level)" size="small">{{ sysDetail.risk_level }}</el-tag></el-descriptions-item>
                        <el-descriptions-item label="部署状态">{{ sysDetail.deployment_status }}</el-descriptions-item>
                        <el-descriptions-item label="活跃"><el-tag :type="sysDetail.is_active ? 'success' : 'info'" size="small">{{ sysDetail.is_active ? '是' : '否' }}</el-tag></el-descriptions-item>
                        <el-descriptions-item label="负责部门">{{ sysDetail.owner_department || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="负责人">{{ sysDetail.owner_email || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="下次评审">{{ sysDetail.next_review_at ? fmtDate(sysDetail.next_review_at) : '—' }}</el-descriptions-item>
                        <el-descriptions-item label="评估/偏见/日志" :span="2">{{ sysDetail.risk_assessments_count || 0 }}次评估 · {{ sysDetail.bias_detections_count || 0 }}偏见 · {{ sysDetail.decision_logs_count || 0 }}决策</el-descriptions-item>
                        <el-descriptions-item label="用途" :span="2">{{ sysDetail.purpose }}</el-descriptions-item>
                    </el-descriptions>
                </el-dialog>
            </el-tab-pane>

            <!-- 偏见检测 -->
            <el-tab-pane label="⚖️ 偏见检测" name="bias">
                <div class="section-header"><span>偏见检测记录</span><el-button size="small" type="primary" @click="showBiasForm = true">+ 记录偏见</el-button></div>
                <el-table :data="biasList" stripe v-loading="biasLoading" size="small">
                    <el-table-column prop="system.name" label="AI 系统" min-width="140" />
                    <el-table-column prop="metric" label="指标" width="120" />
                    <el-table-column label="得分" width="80"><template #default="{row}">{{ row.score }}</template></el-table-column>
                    <el-table-column label="标记" width="70"><template #default="{row}"><el-tag :type="row.flagged ? 'danger' : 'success'" size="small">{{ row.flagged ? '是' : '否' }}</el-tag></template></el-table-column>
                    <el-table-column label="严重程度" width="90"><template #default="{row}"><el-tag :type="row.severity === 'critical' ? 'danger' : 'warning'" size="small">{{ row.severity }}</el-tag></template></el-table-column>
                    <el-table-column label="状态" width="80"><template #default="{row}">{{ row.status }}</template></el-table-column>
                    <el-table-column label="检测时间" width="150"><template #default="{row}">{{ fmtDate(row.detected_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="180" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="mitigateBias(row)" v-if="row.status === 'open'">缓解</el-button>
                            <el-button size="small" type="success" @click="resolveBias(row)" v-if="row.status === 'mitigated'">解决</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-dialog v-model="showBiasForm" title="记录偏见检测" width="500px">
                    <el-form :model="biasForm" label-width="120px">
                        <el-form-item label="AI 系统" :rules="[{required:true}]"><el-select v-model="biasForm.ai_system_id" style="width:100%">
                            <el-option v-for="s in systems" :key="s.id" :label="s.name" :value="s.id" />
                        </el-select></el-form-item>
                        <el-form-item label="指标" :rules="[{required:true}]"><el-select v-model="biasForm.metric" style="width:100%">
                            <el-option label="人口统计平等" value="demographic_parity" /><el-option label="均等机会" value="equal_opportunity" />
                            <el-option label="预测平等" value="predictive_parity" /><el-option label="差异化影响" value="disparate_impact" />
                        </el-select></el-form-item>
                        <el-form-item label="得分 0-1" :rules="[{required:true}]"><el-input-number v-model="biasForm.score" :min="0" :max="1" :step="0.01" style="width:100%" /></el-form-item>
                        <el-form-item label="描述"><el-input v-model="biasForm.description" type="textarea" :rows="2" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showBiasForm = false">取消</el-button><el-button type="primary" @click="saveBias" :loading="saving">保存</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 决策审计日志 -->
            <el-tab-pane label="📝 AI 决策日志" name="decisions">
                <el-table :data="decisionLogs" stripe v-loading="decLoading" size="small">
                    <el-table-column prop="decision_id" label="决策ID" width="180" />
                    <el-table-column prop="system.name" label="AI 系统" width="120" />
                    <el-table-column prop="decision_type" label="类型" width="100" />
                    <el-table-column prop="input_summary" label="输入摘要" min-width="140" show-overflow-tooltip />
                    <el-table-column prop="output_summary" label="输出摘要" min-width="140" show-overflow-tooltip />
                    <el-table-column label="结果" width="70"><template #default="{row}"><el-tag :type="row.result === 'approved' ? 'success' : (row.result === 'rejected' ? 'danger' : 'warning')" size="small">{{ row.result }}</el-tag></template></el-table-column>
                    <el-table-column prop="confidence_score" label="置信度" width="70" />
                    <el-table-column label="Override" width="70"><template #default="{row}"><el-tag :type="row.was_overridden ? 'warning' : 'info'" size="small">{{ row.was_overridden ? '是' : '否' }}</el-tag></template></el-table-column>
                    <el-table-column label="时间" width="150"><template #default="{row}">{{ fmtDate(row.occurred_at) }}</template></el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 人工申诉 -->
            <el-tab-pane label="🛡️ 人工申诉" name="overrides">
                <div class="section-header"><span>申诉列表</span><el-button size="small" type="primary" @click="showOverrideForm = true">+ 新建申诉</el-button></div>
                <el-table :data="overrideList" stripe v-loading="ovrLoading" size="small">
                    <el-table-column prop="request_id" label="编号" width="130" />
                    <el-table-column prop="customer_identifier" label="客户" width="120" />
                    <el-table-column prop="reason" label="理由" min-width="180" show-overflow-tooltip />
                    <el-table-column label="状态" width="90"><template #default="{row}"><el-tag :type="overrideStatusTag(row.status)" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="escalation_level" label="级别" width="80" />
                    <el-table-column prop="assigned_to" label="处理人" width="100" />
                    <el-table-column label="时间" width="150"><template #default="{row}">{{ fmtDate(row.submitted_at) }}</template></el-table-column>
                    <el-table-column label="操作" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="processOverrideDialog(row)" v-if="row.status === 'pending'">处理</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-dialog v-model="showOverrideForm" title="新建人工申诉" width="500px">
                    <el-form :model="overrideForm" label-width="120px">
                        <el-form-item label="客户标识" :rules="[{required:true}]"><el-input v-model="overrideForm.customer_identifier" /></el-form-item>
                        <el-form-item label="客户邮箱"><el-input v-model="overrideForm.customer_email" /></el-form-item>
                        <el-form-item label="申诉理由" :rules="[{required:true}]"><el-input v-model="overrideForm.reason" type="textarea" :rows="3" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showOverrideForm = false">取消</el-button><el-button type="primary" @click="saveOverride" :loading="saving">提交</el-button></template>
                </el-dialog>
                <el-dialog v-model="showProcessForm" title="处理申诉" width="500px">
                    <el-form :model="processForm" label-width="100px">
                        <el-form-item label="处理结果"><el-select v-model="processForm.final_decision" style="width:100%">
                            <el-option label="覆盖 AI 决策 (Override)" value="override" />
                            <el-option label="维持 AI 决策 (Uphold)" value="uphold" />
                            <el-option label="部分采纳" value="partially" />
                        </el-select></el-form-item>
                        <el-form-item label="处理人"><el-input v-model="processForm.assigned_to" /></el-form-item>
                        <el-form-item label="处理备注"><el-input v-model="processForm.resolution_notes" type="textarea" :rows="3" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showProcessForm = false">取消</el-button><el-button type="primary" @click="submitProcess" :loading="saving">确认处理</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 合规差距分析 -->
            <el-tab-pane label="📋 差距分析" name="gaps">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="hover">
                            <template #header><span>系统级差距</span></template>
                            <div v-if="gapData.system_gaps?.length">
                                <el-table :data="gapData.system_gaps" stripe size="small">
                                    <el-table-column prop="system_name" label="系统" min-width="140" />
                                    <el-table-column label="风险" width="70"><template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template></el-table-column>
                                    <el-table-column label="差距数" width="70" prop="gap_count" />
                                    <el-table-column label="详情" min-width="200">
                                        <template #default="{row}"><span v-for="(g, i) in row.gaps" :key="i"><el-tag size="small" type="danger" style="margin:2px">{{ g }}</el-tag> </span></template>
                                    </el-table-column>
                                </el-table>
                            </div>
                            <el-empty v-else description="所有系统均无差距" />
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover">
                            <template #header><span>全局差距</span></template>
                            <div v-if="gapData.global_gaps?.length">
                                <el-alert v-for="(g, i) in gapData.global_gaps" :key="i" :title="g" type="warning" show-icon :closable="false" style="margin-bottom:8px" />
                            </div>
                            <el-empty v-else description="无全局差距" />
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 合规报告 -->
            <el-tab-pane label="📄 合规报告" name="report">
                <el-button type="primary" @click="loadReport" :loading="reportLoading" class="mb-4">生成合规报告</el-button>
                <div v-if="reportData">
                    <el-card shadow="hover" class="mb-4">
                        <template #header>合规报告 — {{ reportData.generated_at }}</template>
                        <el-descriptions :column="4" border size="small">
                            <el-descriptions-item label="系统数">{{ reportData.summary?.system_count }}</el-descriptions-item>
                            <el-descriptions-item label="高风险">{{ reportData.summary?.high_risk_systems }}</el-descriptions-item>
                            <el-descriptions-item label="待评审">{{ reportData.summary?.pending_reviews }}</el-descriptions-item>
                            <el-descriptions-item label="合规分">{{ reportData.gap_analysis?.compliance_score?.score }}</el-descriptions-item>
                            <el-descriptions-item label="偏见标记">{{ reportData.summary?.open_bias_flags }}</el-descriptions-item>
                            <el-descriptions-item label="待处理申诉">{{ reportData.summary?.pending_overrides }}</el-descriptions-item>
                            <el-descriptions-item label="总决策数">{{ reportData.summary?.total_decisions }}</el-descriptions-item>
                            <el-descriptions-item label="近期评估">{{ reportData.summary?.recent_assessments }}</el-descriptions-item>
                        </el-descriptions>
                    </el-card>
                    <el-table :data="reportData.systems" stripe size="small" v-if="reportData.systems?.length">
                        <el-table-column prop="name" label="系统" min-width="140" />
                        <el-table-column prop="risk_level" label="风险" width="70"><template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template></el-table-column>
                        <el-table-column label="评估" width="60" prop="risk_assessments_count" />
                        <el-table-column label="偏见" width="60" prop="bias_detections_count" />
                        <el-table-column label="决策" width="60" prop="decision_logs_count" />
                    </el-table>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Refresh, DataBoard, CircleCheck } from '@element-plus/icons-vue';
import aiComplianceApi from '@/api/aiCompliance';

const loading = ref(false);
const saving = ref(false);
const activeTab = ref('dashboard');
const dash = reactive({ system_count: 0, active_systems: 0, high_risk_systems: 0, pending_reviews: 0, open_bias_flags: 0, pending_overrides: 0, total_decisions: 0, recent_assessments: 0 });
const complianceScore = ref(null);

// Systems
const sysLoading = ref(false);
const systems = ref([]);
const systemsInfo = ref('');
const showSystemForm = ref(false);
const showSysDetail = ref(false);
const editingSystem = ref(null);
const sysDetail = ref(null);
const sysForm = reactive({ name: '', version: '', purpose: '', provider: '', deployment_status: 'development', risk_level: 'low', owner_department: '', owner_email: '' });

// Bias
const biasLoading = ref(false);
const biasList = ref([]);
const showBiasForm = ref(false);
const biasForm = reactive({ ai_system_id: '', metric: 'demographic_parity', score: 0, description: '' });

// Decisions
const decLoading = ref(false);
const decisionLogs = ref([]);

// Overrides
const ovrLoading = ref(false);
const overrideList = ref([]);
const showOverrideForm = ref(false);
const showProcessForm = ref(false);
const overrideForm = reactive({ customer_identifier: '', customer_email: '', reason: '' });
const processForm = reactive({ final_decision: 'override', assigned_to: '', resolution_notes: '' });
const processingId = ref(null);

// Gaps
const gapData = reactive({ system_gaps: [], global_gaps: [], total_gaps: 0 });

// Report
const reportLoading = ref(false);
const reportData = ref(null);

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try {
        await Promise.all([loadDashboard(), loadSystems(), loadBias(), loadDecisions(), loadOverrides(), loadGaps()]);
    } finally { loading.value = false; }
}

async function loadDashboard() {
    try { const r = await aiComplianceApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadSystems() {
    sysLoading.value = true;
    try {
        const r = await aiComplianceApi.listSystems({ per_page: 100 });
        const d = r.data?.data || {};
        systems.value = d.items || [];
        systemsInfo.value = `共 ${d.total || 0} 个 AI 系统`;
    } finally { sysLoading.value = false; }
}
async function loadBias() {
    biasLoading.value = true;
    try { const r = await aiComplianceApi.listBiasDetections({ per_page: 50 }); biasList.value = r.data?.data?.items || []; } finally { biasLoading.value = false; }
}
async function loadDecisions() {
    decLoading.value = true;
    try { const r = await aiComplianceApi.listDecisionLogs({ per_page: 50 }); decisionLogs.value = r.data?.data?.items || []; } finally { decLoading.value = false; }
}
async function loadOverrides() {
    ovrLoading.value = true;
    try { const r = await aiComplianceApi.listOverrides({ per_page: 50 }); overrideList.value = r.data?.data?.items || []; } finally { ovrLoading.value = false; }
}
async function loadGaps() {
    try {
        const r = await aiComplianceApi.gapAnalysis();
        const d = r.data?.data || {};
        Object.assign(gapData, d);
        complianceScore.value = d.compliance_score || null;
    } catch {}
}
async function loadReport() {
    reportLoading.value = true;
    try { const r = await aiComplianceApi.complianceReport(); reportData.value = r.data?.data; } finally { reportLoading.value = false; }
}

// Systems CRUD
function viewSystem(row) { sysDetail.value = row; showSysDetail.value = true; }
function editSystem(row) {
    editingSystem.value = row;
    Object.assign(sysForm, { name: row.name, version: row.version, purpose: row.purpose, provider: row.provider || '', deployment_status: row.deployment_status, risk_level: row.risk_level, owner_department: row.owner_department || '', owner_email: row.owner_email || '' });
    showSystemForm.value = true;
}
async function saveSystem() {
    saving.value = true;
    try {
        if (editingSystem.value) { await aiComplianceApi.updateSystem(editingSystem.value.id, sysForm); ElMessage.success('已更新'); } 
        else { await aiComplianceApi.storeSystem(sysForm); ElMessage.success('已创建'); }
        showSystemForm.value = false; editingSystem.value = null; loadSystems(); loadDashboard();
    } catch { ElMessage.error('操作失败'); } finally { saving.value = false; }
}
async function deleteSystem(row) {
    await aiComplianceApi.destroySystem(row.id); ElMessage.success('已删除'); loadSystems(); loadDashboard();
}

// Bias
async function saveBias() {
    saving.value = true;
    try { await aiComplianceApi.storeBiasDetection(biasForm); ElMessage.success('已记录'); showBiasForm.value = false; loadBias(); loadDashboard(); } catch { ElMessage.error('失败'); } finally { saving.value = false; }
}
async function mitigateBias(row) {
    const { value } = await ElMessageBox.prompt('输入缓解措施', '缓解偏见');
    if (value) { await aiComplianceApi.mitigateBias(row.id, { mitigation_action: value }); ElMessage.success('已缓解'); loadBias(); }
}
async function resolveBias(row) {
    await aiComplianceApi.resolveBias(row.id); ElMessage.success('已解决'); loadBias();
}

// Override
async function saveOverride() {
    saving.value = true;
    try { await aiComplianceApi.storeOverride(overrideForm); ElMessage.success('申诉已提交'); showOverrideForm.value = false; loadOverrides(); } catch { ElMessage.error('失败'); } finally { saving.value = false; }
}
function processOverrideDialog(row) {
    processingId.value = row.id;
    processForm.final_decision = 'override'; processForm.assigned_to = ''; processForm.resolution_notes = '';
    showProcessForm.value = true;
}
async function submitProcess() {
    saving.value = true;
    try {
        const data = { ...processForm, status: 'resolved' };
        await aiComplianceApi.processOverride(processingId.value, data);
        ElMessage.success('已处理'); showProcessForm.value = false; loadOverrides(); loadDashboard();
    } catch { ElMessage.error('处理失败'); } finally { saving.value = false; }
}

function riskTag(level) { return { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' }[level] || 'info'; }
function overrideStatusTag(s) { return { pending: 'warning', in_review: 'primary', resolved: 'success', rejected: 'danger' }[s] || 'info'; }
function fmtDate(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
</script>

<style scoped>
.ai-compliance-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .danger { color: #f56c6c; } .warning { color: #e6a23c; }
.text-muted { color: #c0c4cc; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; font-size: 14px; }
.text-center { text-align: center; }
</style>
