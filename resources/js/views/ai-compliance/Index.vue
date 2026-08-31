<template>
    <div class="ai-compliance-page">
        <div class="page-header">
            <div>
                <h2>{{ t('ai_compliance_page.title') }}</h2>
                <p class="text-muted">{{ t('ai_compliance_page.subtitle') }}</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">{{ t('ai_compliance_page.buttons.refresh') }}</el-button>
                <el-button type="primary" @click="activeTab = 'report'" :icon="DataBoard">{{ t('ai_compliance_page.buttons.compliance_report') }}</el-button>
            </div>
        </div>

        <!-- 合规评分 -->
        <el-alert v-if="complianceScore" :title="t('ai_compliance_page.score_alert', { score: complianceScore.score, label: complianceScore.label })"
            :type="complianceScore.level === 'compliant' ? 'success' : (complianceScore.level === 'partial' ? 'warning' : 'error')"
            show-icon :closable="false" class="mb-4" />

        <!-- Tab 导航 -->
        <el-tabs v-model="activeTab" type="border-card" class="mb-4">
            <!-- 看板总览 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.dashboard')" name="dashboard">
                <el-row :gutter="16" class="mb-4">
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.system_count') }}</div><div class="metric-value">{{ dash.system_count }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.active_systems') }}</div><div class="metric-value success">{{ dash.active_systems }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.high_risk_systems') }}</div><div class="metric-value danger">{{ dash.high_risk_systems }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.pending_reviews') }}</div><div class="metric-value warning">{{ dash.pending_reviews }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.open_bias_flags') }}</div><div class="metric-value danger">{{ dash.open_bias_flags }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.pending_overrides') }}</div><div class="metric-value warning">{{ dash.pending_overrides }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.total_decisions') }}</div><div class="metric-value">{{ dash.total_decisions }}</div></el-card>
                    </el-col>
                    <el-col :xs="12" :sm="8" :md="6" :lg="3">
                        <el-card shadow="hover" class="metric-card"><div class="metric-label">{{ t('ai_compliance_page.dashboard.recent_assessments') }}</div><div class="metric-value">{{ dash.recent_assessments }}</div></el-card>
                    </el-col>
                </el-row>
                <div class="text-center text-muted" style="padding:40px 0">{{ t('ai_compliance_page.dashboard.hint') }}</div>
            </el-tab-pane>

            <!-- AI 系统清单 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.systems')" name="systems">
                <div class="section-header"><span>{{ systemsInfo }}</span><el-button type="primary" size="small" @click="showSystemForm = true">{{ t('ai_compliance_page.buttons.add_system') }}</el-button></div>
                <el-table :data="systems" stripe v-loading="sysLoading" size="small">
                    <el-table-column prop="name" :label="t('ai_compliance_page.cols.name')" min-width="140" />
                    <el-table-column prop="version" :label="t('ai_compliance_page.cols.version')" width="80" />
                    <el-table-column prop="purpose" :label="t('ai_compliance_page.cols.purpose')" min-width="180" show-overflow-tooltip />
                    <el-table-column prop="provider" :label="t('ai_compliance_page.cols.provider')" width="120" />
                    <el-table-column :label="t('ai_compliance_page.cols.status')" width="80">
                        <template #default="{row}"><el-tag :type="row.deployment_status === 'production' ? 'success' : 'info'" size="small">{{ row.deployment_status }}</el-tag></template>
                    </el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.risk')" width="70">
                        <template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template>
                    </el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.active')" width="60">
                        <template #default="{row}"><el-icon :color="row.is_active ? '#67c23a' : '#c0c4cc'"><CircleCheck /></el-icon></template>
                    </el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.next_review')" width="120">
                        <template #default="{row}">{{ row.next_review_at ? fmtDate(row.next_review_at) : '—' }}</template>
                    </el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.actions')" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="viewSystem(row)">{{ t('ai_compliance_page.buttons.detail') }}</el-button>
                            <el-button size="small" @click="editSystem(row)">{{ t('actions.edit') }}</el-button>
                            <el-popconfirm :title="t('messages.confirm_delete')" @confirm="deleteSystem(row)">
                                <template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
                            </el-popconfirm>
                        </template>
                    </el-table-column>
                </el-table>
                <!-- 系统表单对话框 -->
                <el-dialog v-model="showSystemForm" :title="editingSystem ? t('ai_compliance_page.systems.edit_title') : t('ai_compliance_page.systems.create_title')" width="600px">
                    <el-form :model="sysForm" label-width="120px" ref="sysFormRef">
                        <el-form-item :label="t('ai_compliance_page.cols.name')" prop="name" :rules="[{required:true}]"><el-input v-model="sysForm.name" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.cols.version')" prop="version" :rules="[{required:true}]"><el-input v-model="sysForm.version" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.cols.purpose')" prop="purpose" :rules="[{required:true}]"><el-input v-model="sysForm.purpose" type="textarea" :rows="2" /></el-form-item>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item :label="t('ai_compliance_page.cols.provider')"><el-input v-model="sysForm.provider" /></el-form-item></el-col>
                            <el-col :span="12"><el-form-item :label="t('ai_compliance_page.cols.deployment_status')" prop="deployment_status" :rules="[{required:true}]">
                                <el-select v-model="sysForm.deployment_status" style="width:100%">
                                    <el-option v-for="opt in deploymentStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item></el-col>
                        </el-row>
                        <el-row :gutter="12">
                            <el-col :span="12"><el-form-item :label="t('ai_compliance_page.cols.risk_level')" prop="risk_level" :rules="[{required:true}]">
                                <el-select v-model="sysForm.risk_level" style="width:100%">
                                    <el-option v-for="opt in riskLevelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item></el-col>
                            <el-col :span="12"><el-form-item :label="t('ai_compliance_page.cols.owner_department')"><el-input v-model="sysForm.owner_department" /></el-form-item></el-col>
                        </el-row>
                        <el-form-item :label="t('ai_compliance_page.cols.owner_email')"><el-input v-model="sysForm.owner_email" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showSystemForm = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="saveSystem" :loading="saving">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
                <!-- 系统详情对话框 -->
                <el-dialog v-model="showSysDetail" :title="t('ai_compliance_page.systems.detail_title', { name: sysDetail?.name || '' })" width="700px">
                    <el-descriptions :column="2" border size="small" v-if="sysDetail">
                        <el-descriptions-item :label="t('ai_compliance_page.cols.name')">{{ sysDetail.name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.version')">{{ sysDetail.version }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.provider')">{{ sysDetail.provider || '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.risk_level')"><el-tag :type="riskTag(sysDetail.risk_level)" size="small">{{ sysDetail.risk_level }}</el-tag></el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.deployment_status')">{{ sysDetail.deployment_status }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.active')"><el-tag :type="sysDetail.is_active ? 'success' : 'info'" size="small">{{ sysDetail.is_active ? t('ai_compliance_page.yes') : t('ai_compliance_page.no') }}</el-tag></el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.owner_department')">{{ sysDetail.owner_department || '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.owner')">{{ sysDetail.owner_email || '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.next_review')">{{ sysDetail.next_review_at ? fmtDate(sysDetail.next_review_at) : '—' }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.assessments') + '/' + t('ai_compliance_page.cols.bias') + '/' + t('ai_compliance_page.cols.decisions')" :span="2">{{ t('ai_compliance_page.systems.detail_stats', { assessments: sysDetail.risk_assessments_count || 0, bias: sysDetail.bias_detections_count || 0, decisions: sysDetail.decision_logs_count || 0 }) }}</el-descriptions-item>
                        <el-descriptions-item :label="t('ai_compliance_page.cols.purpose')" :span="2">{{ sysDetail.purpose }}</el-descriptions-item>
                    </el-descriptions>
                </el-dialog>
            </el-tab-pane>

            <!-- 偏见检测 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.bias')" name="bias">
                <div class="section-header"><span>{{ t('ai_compliance_page.bias_section_title') }}</span><el-button size="small" type="primary" @click="showBiasForm = true">{{ t('ai_compliance_page.buttons.record_bias') }}</el-button></div>
                <el-table :data="biasList" stripe v-loading="biasLoading" size="small">
                    <el-table-column prop="system.name" :label="t('ai_compliance_page.cols.ai_system')" min-width="140" />
                    <el-table-column prop="metric" :label="t('ai_compliance_page.cols.metric')" width="120" />
                    <el-table-column :label="t('ai_compliance_page.cols.score')" width="80"><template #default="{row}">{{ row.score }}</template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.flagged')" width="70"><template #default="{row}"><el-tag :type="row.flagged ? 'danger' : 'success'" size="small">{{ row.flagged ? t('ai_compliance_page.yes') : t('ai_compliance_page.no') }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.severity')" width="90"><template #default="{row}"><el-tag :type="row.severity === 'critical' ? 'danger' : 'warning'" size="small">{{ row.severity }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.status')" width="80"><template #default="{row}">{{ row.status }}</template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.detected_at')" width="150"><template #default="{row}">{{ fmtDate(row.detected_at) }}</template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.actions')" width="180" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="mitigateBias(row)" v-if="row.status === 'open'">{{ t('ai_compliance_page.buttons.mitigate') }}</el-button>
                            <el-button size="small" type="success" @click="resolveBias(row)" v-if="row.status === 'mitigated'">{{ t('ai_compliance_page.buttons.resolve') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-dialog v-model="showBiasForm" :title="t('ai_compliance_page.dialogs.record_bias')" width="500px">
                    <el-form :model="biasForm" label-width="120px">
                        <el-form-item :label="t('ai_compliance_page.cols.ai_system')" :rules="[{required:true}]"><el-select v-model="biasForm.ai_system_id" style="width:100%">
                            <el-option v-for="s in systems" :key="s.id" :label="s.name" :value="s.id" />
                        </el-select></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.cols.metric')" :rules="[{required:true}]"><el-select v-model="biasForm.metric" style="width:100%">
                            <el-option v-for="opt in biasMetricOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.forms.score_range')" :rules="[{required:true}]"><el-input-number v-model="biasForm.score" :min="0" :max="1" :step="0.01" style="width:100%" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.forms.description')"><el-input v-model="biasForm.description" type="textarea" :rows="2" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showBiasForm = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="saveBias" :loading="saving">{{ t('actions.save') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 决策审计日志 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.decisions')" name="decisions">
                <el-table :data="decisionLogs" stripe v-loading="decLoading" size="small">
                    <el-table-column prop="decision_id" :label="t('ai_compliance_page.cols.decision_id')" width="180" />
                    <el-table-column prop="system.name" :label="t('ai_compliance_page.cols.ai_system')" width="120" />
                    <el-table-column prop="decision_type" :label="t('ai_compliance_page.cols.type')" width="100" />
                    <el-table-column prop="input_summary" :label="t('ai_compliance_page.cols.input_summary')" min-width="140" show-overflow-tooltip />
                    <el-table-column prop="output_summary" :label="t('ai_compliance_page.cols.output_summary')" min-width="140" show-overflow-tooltip />
                    <el-table-column :label="t('ai_compliance_page.cols.result')" width="70"><template #default="{row}"><el-tag :type="row.result === 'approved' ? 'success' : (row.result === 'rejected' ? 'danger' : 'warning')" size="small">{{ row.result }}</el-tag></template></el-table-column>
                    <el-table-column prop="confidence_score" :label="t('ai_compliance_page.cols.confidence')" width="70" />
                    <el-table-column :label="t('ai_compliance_page.cols.overridden')" width="70"><template #default="{row}"><el-tag :type="row.was_overridden ? 'warning' : 'info'" size="small">{{ row.was_overridden ? t('ai_compliance_page.yes') : t('ai_compliance_page.no') }}</el-tag></template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.time')" width="150"><template #default="{row}">{{ fmtDate(row.occurred_at) }}</template></el-table-column>
                </el-table>
            </el-tab-pane>

            <!-- 人工申诉 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.overrides')" name="overrides">
                <div class="section-header"><span>{{ t('ai_compliance_page.override_section_title') }}</span><el-button size="small" type="primary" @click="showOverrideForm = true">{{ t('ai_compliance_page.buttons.new_override') }}</el-button></div>
                <el-table :data="overrideList" stripe v-loading="ovrLoading" size="small">
                    <el-table-column prop="request_id" :label="t('ai_compliance_page.cols.request_id')" width="130" />
                    <el-table-column prop="customer_identifier" :label="t('ai_compliance_page.cols.customer')" width="120" />
                    <el-table-column prop="reason" :label="t('ai_compliance_page.cols.reason')" min-width="180" show-overflow-tooltip />
                    <el-table-column :label="t('ai_compliance_page.cols.status')" width="90"><template #default="{row}"><el-tag :type="overrideStatusTag(row.status)" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="escalation_level" :label="t('ai_compliance_page.cols.level')" width="80" />
                    <el-table-column prop="assigned_to" :label="t('ai_compliance_page.cols.assignee')" width="100" />
                    <el-table-column :label="t('ai_compliance_page.cols.time')" width="150"><template #default="{row}">{{ fmtDate(row.submitted_at) }}</template></el-table-column>
                    <el-table-column :label="t('ai_compliance_page.cols.actions')" width="160" fixed="right">
                        <template #default="{row}">
                            <el-button size="small" @click="processOverrideDialog(row)" v-if="row.status === 'pending'">{{ t('ai_compliance_page.buttons.process') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-dialog v-model="showOverrideForm" :title="t('ai_compliance_page.dialogs.new_override')" width="500px">
                    <el-form :model="overrideForm" label-width="120px">
                        <el-form-item :label="t('ai_compliance_page.forms.customer_identifier')" :rules="[{required:true}]"><el-input v-model="overrideForm.customer_identifier" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.forms.customer_email')"><el-input v-model="overrideForm.customer_email" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.forms.override_reason')" :rules="[{required:true}]"><el-input v-model="overrideForm.reason" type="textarea" :rows="3" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showOverrideForm = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="saveOverride" :loading="saving">{{ t('actions.submit') }}</el-button></template>
                </el-dialog>
                <el-dialog v-model="showProcessForm" :title="t('ai_compliance_page.dialogs.process_override')" width="500px">
                    <el-form :model="processForm" label-width="100px">
                        <el-form-item :label="t('ai_compliance_page.forms.process_result')"><el-select v-model="processForm.final_decision" style="width:100%">
                            <el-option v-for="opt in overrideDecisionOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.cols.assignee')"><el-input v-model="processForm.assigned_to" /></el-form-item>
                        <el-form-item :label="t('ai_compliance_page.forms.process_notes')"><el-input v-model="processForm.resolution_notes" type="textarea" :rows="3" /></el-form-item>
                    </el-form>
                    <template #footer><el-button @click="showProcessForm = false">{{ t('actions.cancel') }}</el-button><el-button type="primary" @click="submitProcess" :loading="saving">{{ t('ai_compliance_page.buttons.confirm_process') }}</el-button></template>
                </el-dialog>
            </el-tab-pane>

            <!-- 合规差距分析 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.gaps')" name="gaps">
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="12">
                        <el-card shadow="hover">
                            <template #header><span>{{ t('ai_compliance_page.gaps.system_gaps') }}</span></template>
                            <div v-if="gapData.system_gaps?.length">
                                <el-table :data="gapData.system_gaps" stripe size="small">
                                    <el-table-column prop="system_name" :label="t('ai_compliance_page.cols.system')" min-width="140" />
                                    <el-table-column :label="t('ai_compliance_page.cols.risk')" width="70"><template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template></el-table-column>
                                    <el-table-column :label="t('ai_compliance_page.cols.gap_count')" width="70" prop="gap_count" />
                                    <el-table-column :label="t('ai_compliance_page.cols.details')" min-width="200">
                                        <template #default="{row}"><span v-for="(g, i) in row.gaps" :key="i"><el-tag size="small" type="danger" style="margin:2px">{{ g }}</el-tag> </span></template>
                                    </el-table-column>
                                </el-table>
                            </div>
                            <el-empty v-else :description="t('ai_compliance_page.gaps.no_system_gaps')" />
                        </el-card>
                    </el-col>
                    <el-col :span="12">
                        <el-card shadow="hover">
                            <template #header><span>{{ t('ai_compliance_page.gaps.global_gaps') }}</span></template>
                            <div v-if="gapData.global_gaps?.length">
                                <el-alert v-for="(g, i) in gapData.global_gaps" :key="i" :title="g" type="warning" show-icon :closable="false" style="margin-bottom:8px" />
                            </div>
                            <el-empty v-else :description="t('ai_compliance_page.gaps.no_global_gaps')" />
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 合规报告 -->
            <el-tab-pane :label="t('ai_compliance_page.tabs.report')" name="report">
                <el-button type="primary" @click="loadReport" :loading="reportLoading" class="mb-4">{{ t('ai_compliance_page.buttons.generate_report') }}</el-button>
                <div v-if="reportData">
                    <el-card shadow="hover" class="mb-4">
                        <template #header>{{ t('ai_compliance_page.report.header', { generated_at: reportData.generated_at }) }}</template>
                        <el-descriptions :column="4" border size="small">
                            <el-descriptions-item :label="t('ai_compliance_page.report.system_count')">{{ reportData.summary?.system_count }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.high_risk')">{{ reportData.summary?.high_risk_systems }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.pending_reviews')">{{ reportData.summary?.pending_reviews }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.compliance_score')">{{ reportData.gap_analysis?.compliance_score?.score }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.open_bias_flags')">{{ reportData.summary?.open_bias_flags }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.pending_overrides')">{{ reportData.summary?.pending_overrides }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.total_decisions')">{{ reportData.summary?.total_decisions }}</el-descriptions-item>
                            <el-descriptions-item :label="t('ai_compliance_page.report.recent_assessments')">{{ reportData.summary?.recent_assessments }}</el-descriptions-item>
                        </el-descriptions>
                    </el-card>
                    <el-table :data="reportData.systems" stripe size="small" v-if="reportData.systems?.length">
                        <el-table-column prop="name" :label="t('ai_compliance_page.cols.system')" min-width="140" />
                        <el-table-column prop="risk_level" :label="t('ai_compliance_page.cols.risk')" width="70"><template #default="{row}"><el-tag :type="riskTag(row.risk_level)" size="small">{{ row.risk_level }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('ai_compliance_page.cols.assessments')" width="60" prop="risk_assessments_count" />
                        <el-table-column :label="t('ai_compliance_page.cols.bias')" width="60" prop="bias_detections_count" />
                        <el-table-column :label="t('ai_compliance_page.cols.decisions')" width="60" prop="decision_logs_count" />
                    </el-table>
                </div>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, DataBoard, CircleCheck } from '@element-plus/icons-vue';
import aiComplianceApi from '@/api/aiCompliance';

const { t, locale } = useI18n();

const loading = ref(false);
const saving = ref(false);
const activeTab = ref('dashboard');
const dash = reactive({ system_count: 0, active_systems: 0, high_risk_systems: 0, pending_reviews: 0, open_bias_flags: 0, pending_overrides: 0, total_decisions: 0, recent_assessments: 0 });
const complianceScore = ref(null);

const deploymentStatusOptions = computed(() => [
    { label: t('ai_compliance_page.deployment_status.development'), value: 'development' },
    { label: t('ai_compliance_page.deployment_status.staging'), value: 'staging' },
    { label: t('ai_compliance_page.deployment_status.production'), value: 'production' },
    { label: t('ai_compliance_page.deployment_status.retired'), value: 'retired' },
]);

const riskLevelOptions = computed(() => [
    { label: t('ai_compliance_page.risk_level.low'), value: 'low' },
    { label: t('ai_compliance_page.risk_level.medium'), value: 'medium' },
    { label: t('ai_compliance_page.risk_level.high'), value: 'high' },
    { label: t('ai_compliance_page.risk_level.critical'), value: 'critical' },
]);

const biasMetricOptions = computed(() => [
    { label: t('ai_compliance_page.bias_metric.demographic_parity'), value: 'demographic_parity' },
    { label: t('ai_compliance_page.bias_metric.equal_opportunity'), value: 'equal_opportunity' },
    { label: t('ai_compliance_page.bias_metric.predictive_parity'), value: 'predictive_parity' },
    { label: t('ai_compliance_page.bias_metric.disparate_impact'), value: 'disparate_impact' },
]);

const overrideDecisionOptions = computed(() => [
    { label: t('ai_compliance_page.override_decision.override'), value: 'override' },
    { label: t('ai_compliance_page.override_decision.uphold'), value: 'uphold' },
    { label: t('ai_compliance_page.override_decision.partially'), value: 'partially' },
]);

// Systems
const sysLoading = ref(false);
const systems = ref([]);
const systemsTotal = ref(0);
const systemsInfo = computed(() => t('ai_compliance_page.systems.count', { n: systemsTotal.value }));
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
        systemsTotal.value = d.total || 0;
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
        if (editingSystem.value) { await aiComplianceApi.updateSystem(editingSystem.value.id, sysForm); ElMessage.success(t('ai_compliance_page.messages.system_updated')); }
        else { await aiComplianceApi.storeSystem(sysForm); ElMessage.success(t('ai_compliance_page.messages.system_created')); }
        showSystemForm.value = false; editingSystem.value = null; loadSystems(); loadDashboard();
    } catch { ElMessage.error(t('messages.failed')); } finally { saving.value = false; }
}
async function deleteSystem(row) {
    await aiComplianceApi.destroySystem(row.id); ElMessage.success(t('ai_compliance_page.messages.system_deleted')); loadSystems(); loadDashboard();
}

// Bias
async function saveBias() {
    saving.value = true;
    try { await aiComplianceApi.storeBiasDetection(biasForm); ElMessage.success(t('ai_compliance_page.messages.bias_recorded')); showBiasForm.value = false; loadBias(); loadDashboard(); } catch { ElMessage.error(t('ai_compliance_page.messages.failed')); } finally { saving.value = false; }
}
async function mitigateBias(row) {
    const { value } = await ElMessageBox.prompt(t('ai_compliance_page.dialogs.mitigate_prompt_placeholder'), t('ai_compliance_page.dialogs.mitigate_prompt_title'));
    if (value) { await aiComplianceApi.mitigateBias(row.id, { mitigation_action: value }); ElMessage.success(t('ai_compliance_page.messages.bias_mitigated')); loadBias(); }
}
async function resolveBias(row) {
    await aiComplianceApi.resolveBias(row.id); ElMessage.success(t('ai_compliance_page.messages.bias_resolved')); loadBias();
}

// Override
async function saveOverride() {
    saving.value = true;
    try { await aiComplianceApi.storeOverride(overrideForm); ElMessage.success(t('ai_compliance_page.messages.override_submitted')); showOverrideForm.value = false; loadOverrides(); } catch { ElMessage.error(t('ai_compliance_page.messages.failed')); } finally { saving.value = false; }
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
        ElMessage.success(t('ai_compliance_page.messages.override_processed')); showProcessForm.value = false; loadOverrides(); loadDashboard();
    } catch { ElMessage.error(t('ai_compliance_page.messages.process_failed')); } finally { saving.value = false; }
}

function riskTag(level) { return { low: 'success', medium: 'warning', high: 'danger', critical: 'danger' }[level] || 'info'; }
function overrideStatusTag(s) { return { pending: 'warning', in_review: 'primary', resolved: 'success', rejected: 'danger' }[s] || 'info'; }
function fmtDate(time) {
    if (!time) return '—';
    const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
    return new Date(time).toLocaleString(loc, { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}
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
