<template>
    <div class="regional-compliance-page">
        <h2>多区域合规管理</h2>

        <div v-if="!initialized" style="margin-bottom:20px">
            <el-alert title="区域合规配置未初始化" type="warning" :closable="false" show-icon>
                <template #default>
                    <p>点击下方按钮从预设配置中初始化区域合规设置。</p>
                    <el-button type="primary" @click="handleInitialize" :loading="initializing" style="margin-top:8px">初始化区域配置</el-button>
                </template>
            </el-alert>
        </div>

        <el-row :gutter="20" class="stats-row">
            <el-col :span="4"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.total_regions || 0 }}</div><div class="stat-label">区域数</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value success">{{ stats.compliance_percentage || 0 }}%</div><div class="stat-label">合规完成率</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value">{{ stats.met_requirements || 0 }}/{{ stats.total_requirements || 0 }}</div><div class="stat-label">合规项</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value warning">{{ stats.active_restrictions || 0 }}</div><div class="stat-label">销售限制</div></div></el-card></el-col>
            <el-col :span="5"><el-card shadow="hover"><div class="stat-card"><div class="stat-value info">{{ stats.recent_logs?.length || 0 }}</div><div class="stat-label">近期日志</div></div></el-card></el-col>
        </el-row>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- 区域概览 -->
            <el-tab-pane label="区域概览" name="overview">
                <div v-if="Object.keys(byRegion).length === 0" style="text-align:center;padding:40px;color:#909399">
                    暂无区域配置，请先初始化
                </div>
                <el-row :gutter="20" v-else>
                    <el-col :span="12" v-for="(region, key) in byRegion" :key="key" style="margin-bottom:16px">
                        <el-card shadow="hover">
                            <template #header>
                                <div style="display:flex;justify-content:space-between;align-items:center">
                                    <span><strong>{{ region.config.region_name || key }}</strong> ({{ key }})</span>
                                    <el-tag :type="region.checks.met_count === region.checks.total_count ? 'success' : 'warning'" size="small">
                                        {{ region.checks.met_count }}/{{ region.checks.total_count }}
                                    </el-tag>
                                </div>
                            </template>
                            <div class="region-requirements">
                                <div v-for="(check, ck) in region.checks.details" :key="ck" class="requirement-item">
                                    <el-icon v-if="check.status === 'met'" color="#67c23a"><CircleCheck /></el-icon>
                                    <el-icon v-else color="#e6a23c"><Warning /></el-icon>
                                    <span>{{ check.label }}</span>
                                    <el-tag :type="check.status === 'met' ? 'success' : 'warning'" size="small" style="margin-left:auto">
                                        {{ check.status === 'met' ? '已就绪' : '未就绪' }}
                                    </el-tag>
                                </div>
                            </div>
                            <el-divider style="margin:8px 0" />
                            <div style="font-size:12px;color:#909399">
                                税种: {{ region.config.tax_type || '-' }} | 税率: {{ region.config.tax_rate }}%
                            </div>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- 合规配置 -->
            <el-tab-pane label="合规配置" name="configs">
                <el-table :data="configs" v-loading="configsLoading" stripe>
                    <el-table-column prop="region_key" label="区域" width="120">
                        <template #default="{row}"><el-tag>{{ row.region_key }}</el-tag></template>
                    </el-table-column>
                    <el-table-column prop="region_name" label="名称" width="120" />
                    <el-table-column label="合规要求" min-width="280">
                        <template #default="{row}">
                            <el-tag v-if="row.gdpr_enabled" size="small" style="margin:2px">GDPR</el-tag>
                            <el-tag v-if="row.pipl_enabled" size="small" type="warning" style="margin:2px">PIPL</el-tag>
                            <el-tag v-if="row.vat_enabled" size="small" type="success" style="margin:2px">VAT</el-tag>
                            <el-tag v-if="row.data_residency_enabled" size="small" type="info" style="margin:2px">数据本地化</el-tag>
                            <el-tag v-if="row.cookie_consent_enabled" size="small" type="danger" style="margin:2px">Cookie</el-tag>
                            <el-tag v-if="row.tax_reporting_enabled" size="small" style="margin:2px">税务申报</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="tax_type" label="税种" width="80" />
                    <el-table-column prop="tax_rate" label="税率" width="70"><template #default="{row}">{{ row.tax_rate }}%</template></el-table-column>
                    <el-table-column label="活跃" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_active?'success':'info'" size="small">{{ row.is_active?'是':'否' }}</el-tag></template></el-table-column>
                    <el-table-column label="操作" width="100">
                        <template #default="{row}">
                            <el-button link type="primary" size="small" @click="openConfigEdit(row)">编辑</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showConfigDialog" title="编辑区域合规配置" width="550px">
                    <el-form :model="editForm" label-width="130px" size="small">
                        <el-form-item label="区域名称"><el-input v-model="editForm.region_name" /></el-form-item>
                        <el-form-item label="合规要求">
                            <el-checkbox v-model="editForm.gdpr_enabled">GDPR</el-checkbox>
                            <el-checkbox v-model="editForm.pipl_enabled">PIPL</el-checkbox>
                            <el-checkbox v-model="editForm.vat_enabled">VAT/GST</el-checkbox>
                            <el-checkbox v-model="editForm.data_residency_enabled">数据本地化</el-checkbox>
                            <el-checkbox v-model="editForm.cookie_consent_enabled">Cookie 同意</el-checkbox>
                            <el-checkbox v-model="editForm.tax_reporting_enabled">税务申报</el-checkbox>
                        </el-form-item>
                        <el-form-item label="税种">
                            <el-select v-model="editForm.tax_type" style="width:100%">
                                <el-option label="无" value="" />
                                <el-option label="VAT" value="vat" />
                                <el-option label="销售税" value="sales_tax" />
                                <el-option label="GST" value="gst" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="税率 (%)"><el-input-number v-model="editForm.tax_rate" :min="0" :max="100" :precision="2" style="width:100%" /></el-form-item>
                        <el-form-item label="申报频率">
                            <el-select v-model="editForm.tax_reporting_frequency" style="width:100%">
                                <el-option label="月度" value="monthly" />
                                <el-option label="季度" value="quarterly" />
                                <el-option label="年度" value="yearly" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="数字服务税"><el-switch v-model="editForm.digital_service_tax" /></el-form-item>
                        <el-form-item label="货币"><el-input v-model="editForm.currency" maxlength="10" /></el-form-item>
                        <el-form-item label="时区"><el-input v-model="editForm.timezone" /></el-form-item>
                        <el-form-item label="启用"><el-switch v-model="editForm.is_active" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showConfigDialog = false">取消</el-button>
                        <el-button type="primary" @click="handleConfigSave">保存</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 销售限制 -->
            <el-tab-pane label="销售限制" name="restrictions">
                <div class="toolbar">
                    <el-button type="primary" @click="showAddRestriction = true">添加限制</el-button>
                    <el-select v-model="restrictionFilterRegion" placeholder="区域筛选" clearable style="width:140px;margin-left:8px" @change="loadRestrictions">
                        <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                    </el-select>
                    <el-button @click="loadRestrictions" style="margin-left:8px">刷新</el-button>
                </div>
                <el-table :data="restrictions" v-loading="restLoading" stripe>
                    <el-table-column prop="region_key" label="区域" width="90"><template #default="{row}"><el-tag size="small">{{ row.region_key }}</el-tag></template></el-table-column>
                    <el-table-column label="对象" width="150"><template #default="{row}">{{ row.restrictable_type }} #{{ row.restrictable_id }}</template></el-table-column>
                    <el-table-column label="允许" width="70" align="center"><template #default="{row}"><el-tag :type="row.is_allowed?'success':'danger'" size="small">{{ row.is_allowed?'是':'否' }}</el-tag></template></el-table-column>
                    <el-table-column prop="reason" label="原因" min-width="250" show-overflow-tooltip />
                    <el-table-column prop="effective_at" label="生效时间" width="170" />
                    <el-table-column prop="expires_at" label="过期时间" width="170" />
                    <el-table-column label="操作" width="80">
                        <template #default="{row}">
                            <el-button link type="danger" size="small" @click="handleRemoveRestriction(row.id)">移除</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <el-dialog v-model="showAddRestriction" title="添加销售限制" width="450px">
                    <el-form :model="restForm" label-width="100px" size="small">
                        <el-form-item label="对象类型">
                            <el-select v-model="restForm.restrictable_type" style="width:100%">
                                <el-option label="产品" value="product" />
                                <el-option label="套餐" value="plan" />
                                <el-option label="SKU" value="sku" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="对象ID"><el-input-number v-model="restForm.restrictable_id" :min="1" style="width:100%" /></el-form-item>
                        <el-form-item label="区域">
                            <el-select v-model="restForm.region_key" style="width:100%">
                                <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="允许销售"><el-switch v-model="restForm.is_allowed" /></el-form-item>
                        <el-form-item label="原因"><el-input v-model="restForm.reason" type="textarea" :rows="2" /></el-form-item>
                        <el-form-item label="生效时间"><el-date-picker v-model="restForm.effective_at" type="datetime" style="width:100%" /></el-form-item>
                        <el-form-item label="过期时间"><el-date-picker v-model="restForm.expires_at" type="datetime" style="width:100%" /></el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showAddRestriction = false">取消</el-button>
                        <el-button type="primary" @click="handleAddRestriction">添加</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>

            <!-- 合规报告 -->
            <el-tab-pane label="合规报告" name="report">
                <div class="toolbar">
                    <el-button type="primary" @click="handleGenerateSummary">生成合规报告</el-button>
                </div>
                <div v-if="complianceSummary.length > 0">
                    <el-card v-for="item in complianceSummary" :key="item.region" shadow="hover" style="margin-bottom:12px">
                        <template #header>
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <span><strong>{{ item.region_name }} ({{ item.region }})</strong></span>
                                <el-tag :type="item.compliance_score >= 80 ? 'success' : item.compliance_score >= 50 ? 'warning' : 'danger'" size="large">
                                    {{ item.compliance_score }}%
                                </el-tag>
                            </div>
                        </template>
                        <div v-for="(check, ck) in item.checks" :key="ck" style="display:flex;align-items:center;gap:8px;padding:4px 0">
                            <el-icon v-if="check.status === 'met'" color="#67c23a"><CircleCheck /></el-icon>
                            <el-icon v-else color="#e6a23c"><Warning /></el-icon>
                            <span>{{ check.label }}</span>
                            <el-tag :type="check.status === 'met' ? 'success' : 'warning'" size="small" style="margin-left:auto">
                                {{ check.status === 'met' ? '已就绪' : '未就绪' }}
                            </el-tag>
                        </div>
                    </el-card>
                </div>
                <div v-else style="text-align:center;padding:40px;color:#909399">
                    点击"生成合规报告"查看各区域合规状态
                </div>
            </el-tab-pane>

            <!-- 操作日志 -->
            <el-tab-pane label="操作日志" name="logs">
                <div class="toolbar">
                    <el-select v-model="logFilterRegion" placeholder="区域" clearable style="width:140px;margin-right:8px" @change="loadLogs">
                        <el-option v-for="(r, k) in availableRegions" :key="k" :label="r.name || k" :value="k" />
                    </el-select>
                    <el-select v-model="logFilterAction" placeholder="操作类型" clearable style="width:160px;margin-right:8px" @change="loadLogs">
                        <el-option label="报告生成" value="report_generated" />
                        <el-option label="税务申报" value="tax_filed" />
                        <el-option label="配置更新" value="config_updated" />
                        <el-option label="销售限制" value="sales_blocked" />
                        <el-option label="解除限制" value="sales_unblocked" />
                    </el-select>
                    <el-button @click="loadLogs">刷新</el-button>
                </div>
                <el-table :data="logs" v-loading="logsLoading" stripe>
                    <el-table-column prop="occurred_at" label="时间" width="170" />
                    <el-table-column prop="region_key" label="区域" width="90"><template #default="{row}"><el-tag size="small">{{ row.region_key }}</el-tag></template></el-table-column>
                    <el-table-column prop="action_type" label="操作" width="140"><template #default="{row}"><el-tag size="small">{{ row.action_type }}</el-tag></template></el-table-column>
                    <el-table-column label="状态" width="80"><template #default="{row}"><el-tag :type="row.status==='success'?'success':'danger'" size="small">{{ row.status }}</el-tag></template></el-table-column>
                    <el-table-column prop="description" label="描述" min-width="300" show-overflow-tooltip />
                    <el-table-column prop="performed_by" label="操作人" width="150" />
                </el-table>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { CircleCheck, Warning } from '@element-plus/icons-vue';
import {
    getRegionalComplianceDashboard, initializeRegionalCompliance,
    getRegionalComplianceConfigs, updateRegionalComplianceConfig,
    getRegionalRestrictions, addRegionalRestriction, removeRegionalRestriction,
    generateRegionalComplianceSummary, getRegionalComplianceLogs, getAvailableRegions,
} from '@/api/regionalCompliance';

const activeTab = ref('overview');
const initialized = ref(true);
const initializing = ref(false);
const stats = ref({});
const byRegion = ref({});
const configs = ref([]);
const configsLoading = ref(false);
const restrictions = ref([]);
const restLoading = ref(false);
const restrictionFilterRegion = ref('');
const logs = ref([]);
const logsLoading = ref(false);
const logFilterRegion = ref('');
const logFilterAction = ref('');
const showConfigDialog = ref(false);
const editForm = reactive({
    region_name: '', gdpr_enabled: false, pipl_enabled: false, vat_enabled: false,
    data_residency_enabled: false, cookie_consent_enabled: true, tax_reporting_enabled: true,
    tax_type: '', tax_rate: 0, tax_reporting_frequency: 'quarterly',
    digital_service_tax: false, currency: '', timezone: '', is_active: true,
});
const currentConfigKey = ref('');
const showAddRestriction = ref(false);
const restForm = reactive({
    restrictable_type: 'product', restrictable_id: 1, region_key: 'cn',
    is_allowed: false, reason: '', effective_at: null, expires_at: null,
});
const complianceSummary = ref([]);
const availableRegions = ref({});

async function loadDashboard() {
    try {
        const r = await getRegionalComplianceDashboard();
        stats.value = r.stats || {};
        byRegion.value = r.byRegion || {};
    } catch (e) {
        initialized.value = false;
        console.error(e);
    }
}
async function loadConfigs() {
    configsLoading.value = true;
    try { configs.value = await getRegionalComplianceConfigs() || []; }
    catch (e) { console.error(e); } finally { configsLoading.value = false; }
}
async function loadRestrictions() {
    restLoading.value = true;
    try {
        const r = await getRegionalRestrictions({ region_key: restrictionFilterRegion.value || undefined });
        restrictions.value = r.data || [];
    } catch (e) { console.error(e); } finally { restLoading.value = false; }
}
async function loadLogs() {
    logsLoading.value = true;
    try {
        const r = await getRegionalComplianceLogs({
            region_key: logFilterRegion.value || undefined,
            action_type: logFilterAction.value || undefined,
            per_page: 50,
        });
        logs.value = r.data || [];
    } catch (e) { console.error(e); } finally { logsLoading.value = false; }
}
async function loadAvailableRegions() {
    try { availableRegions.value = await getAvailableRegions() || {}; }
    catch (e) { console.error(e); }
}

async function handleInitialize() {
    initializing.value = true;
    try {
        await initializeRegionalCompliance();
        ElMessage.success('区域配置已初始化');
        initialized.value = true;
        loadDashboard();
        loadConfigs();
    } catch (e) { ElMessage.error('初始化失败'); } finally { initializing.value = false; }
}

function openConfigEdit(row) {
    Object.assign(editForm, {
        region_name: row.region_name,
        gdpr_enabled: row.gdpr_enabled,
        pipl_enabled: row.pipl_enabled,
        vat_enabled: row.vat_enabled,
        data_residency_enabled: row.data_residency_enabled,
        cookie_consent_enabled: row.cookie_consent_enabled,
        tax_reporting_enabled: row.tax_reporting_enabled,
        tax_type: row.tax_type || '',
        tax_rate: row.tax_rate || 0,
        tax_reporting_frequency: row.tax_reporting_frequency || 'quarterly',
        digital_service_tax: row.digital_service_tax || false,
        currency: row.currency || '',
        timezone: row.timezone || '',
        is_active: row.is_active,
    });
    currentConfigKey.value = row.region_key;
    showConfigDialog.value = true;
}

async function handleConfigSave() {
    try {
        await updateRegionalComplianceConfig(currentConfigKey.value, { ...editForm });
        ElMessage.success('配置已更新');
        showConfigDialog.value = false;
        loadConfigs();
        loadDashboard();
    } catch (e) { ElMessage.error('更新失败'); }
}

async function handleAddRestriction() {
    try {
        await addRegionalRestriction({ ...restForm });
        ElMessage.success('限制已添加');
        showAddRestriction.value = false;
        loadRestrictions();
        loadDashboard();
    } catch (e) { ElMessage.error('添加失败'); }
}

async function handleRemoveRestriction(id) {
    try {
        await removeRegionalRestriction(id);
        ElMessage.success('限制已移除');
        loadRestrictions();
        loadDashboard();
    } catch (e) { ElMessage.error('移除失败'); }
}

async function handleGenerateSummary() {
    try {
        complianceSummary.value = await generateRegionalComplianceSummary() || [];
        ElMessage.success('合规报告已生成');
    } catch (e) { ElMessage.error('生成失败'); }
}

onMounted(() => {
    loadDashboard();
    loadConfigs();
    loadRestrictions();
    loadLogs();
    loadAvailableRegions();
});
</script>

<style scoped>
.regional-compliance-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; padding: 6px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: #409eff; }
.stat-value.success { color: #67c23a; }
.stat-value.warning { color: #e6a23c; }
.stat-value.info { color: #909399; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.toolbar { margin-bottom: 16px; display: flex; align-items: center; flex-wrap: wrap; }
.region-requirements { display: flex; flex-direction: column; gap: 6px; }
.requirement-item { display: flex; align-items: center; gap: 8px; font-size: 14px; }
</style>
