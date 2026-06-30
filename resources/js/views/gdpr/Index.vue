<template>
    <div class="gdpr-page">
        <div class="page-header">
            <h2>GDPR 合规管理</h2>
            <el-button-group>
                <el-button type="primary" @click="activeTab = 'overview'">概览</el-button>
                <el-button @click="activeTab = 'requests'">数据主体请求</el-button>
                <el-button @click="activeTab = 'dpa'">DPA 协议</el-button>
                <el-button @click="activeTab = 'retention'">留存策略</el-button>
            </el-button-group>
        </div>

        <!-- ─── 概览 ─── -->
        <div v-if="activeTab === 'overview'">
            <el-row :gutter="16" class="mb-4">
                <el-col :span="8" v-for="s in overviewStats" :key="s.label">
                    <el-card shadow="hover" class="stat-card">
                        <div class="stat-value" :style="{ color: s.color }">{{ s.value }}</div>
                        <div class="stat-label">{{ s.label }}</div>
                    </el-card>
                </el-col>
            </el-row>

            <el-card class="mb-4">
                <template #header><span>📋 合规检查清单</span></template>
                <el-table :data="checklist" stripe>
                    <el-table-column prop="item" label="合规项" min-width="220" />
                    <el-table-column prop="status" label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'done' ? 'success' : row.status === 'partial' ? 'warning' : 'danger'" size="small">
                                {{ row.status === 'done' ? '✅ 完成' : row.status === 'partial' ? '⚠️ 部分' : '❌ 缺失' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="note" label="说明" min-width="280" />
                </el-table>
            </el-card>
        </div>

        <!-- ─── 数据主体请求 ─── -->
        <div v-if="activeTab === 'requests'">
            <el-card>
                <template #header>
                    <div class="card-header">
                        <span>数据主体请求（DSR）</span>
                        <el-button size="small" @click="showNewRequest = true">新建请求</el-button>
                    </div>
                </template>
                <el-table :data="requests" stripe v-loading="requestsLoading">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="user.email" label="用户" min-width="150" />
                    <el-table-column label="类型" width="120">
                        <template #default="{ row }">{{ requestTypeLabel(row.type) }}</template>
                    </el-table-column>
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="statusType(row.status)" size="small">{{ row.status }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="created_at" label="提交时间" width="170" />
                    <el-table-column label="操作" width="120">
                        <template #default="{ row }">
                            <el-button size="small" v-if="row.status === 'pending'" @click="processRequest(row)">处理</el-button>
                            <el-button size="small" v-if="row.output_file" @click="downloadExport(row)">下载</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- ─── DPA 协议管理 ─── -->
        <div v-if="activeTab === 'dpa'">
            <el-card>
                <template #header>
                    <div class="card-header">
                        <span>数据处理协议（DPA）</span>
                        <el-button size="small" type="primary" @click="showNewDpa = true">新建 DPA</el-button>
                    </div>
                </template>

                <el-table :data="dpas" stripe v-loading="dpasLoading">
                    <el-table-column prop="id" label="ID" width="60" />
                    <el-table-column prop="title" label="标题" min-width="200" />
                    <el-table-column prop="version" label="版本" width="80" />
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 'published' ? 'success' : row.status === 'draft' ? 'info' : 'danger'" size="small">
                                {{ row.status === 'published' ? '已发布' : row.status === 'draft' ? '草稿' : '已归档' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="signatures_count" label="签署数" width="80" align="center" />
                    <el-table-column label="操作" width="200">
                        <template #default="{ row }">
                            <el-button size="small" @click="previewDpa(row)">预览</el-button>
                            <el-button size="small" v-if="row.status === 'draft'" @click="publishDpa(row)">发布</el-button>
                            <el-button size="small" @click="editDpa(row)">编辑</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-card>
        </div>

        <!-- ─── 留存策略 ─── -->
        <div v-if="activeTab === 'retention'">
            <el-card>
                <template #header><span>🗑️ 数据留存策略</span></template>
                <el-table :data="retentionPolicies" stripe>
                    <el-table-column prop="category" label="数据类别" min-width="200" />
                    <el-table-column prop="retention_days" label="留存天数" width="120" align="center" />
                    <el-table-column prop="action" label="到期操作" width="120" />
                    <el-table-column prop="legal_basis" label="法律依据" min-width="200" />
                </el-table>
            </el-card>
        </div>

        <!-- 新建请求弹窗 -->
        <el-dialog v-model="showNewRequest" title="新建 DSR 请求" width="450px">
            <el-form label-width="100px">
                <el-form-item label="用户 ID">
                    <el-input-number v-model="newRequest.userId" :min="1" />
                </el-form-item>
                <el-form-item label="请求类型">
                    <el-select v-model="newRequest.type" style="width:100%">
                        <el-option label="数据访问 (Art.15)" value="access" />
                        <el-option label="数据可移植性 (Art.20)" value="portability" />
                        <el-option label="数据删除/被遗忘权 (Art.17)" value="erasure" />
                        <el-option label="限制处理 (Art.18)" value="restrict" />
                        <el-option label="更正数据 (Art.16)" value="rectify" />
                    </el-select>
                </el-form-item>
                <el-form-item label="原因说明">
                    <el-input v-model="newRequest.reason" type="textarea" :rows="3" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showNewRequest = false">取消</el-button>
                <el-button type="primary" @click="submitRequest" :loading="submitLoading">提交</el-button>
            </template>
        </el-dialog>

        <!-- 新建 DPA 弹窗 -->
        <el-dialog v-model="showNewDpa" title="新建 DPA" width="650px">
            <el-form label-width="100px">
                <el-form-item label="标题">
                    <el-input v-model="dpaForm.title" placeholder="数据处理协议" />
                </el-form-item>
                <el-form-item label="版本">
                    <el-input v-model="dpaForm.version" placeholder="1.0.0" style="width:120px" />
                </el-form-item>
                <el-form-item label="内容（Markdown）">
                    <el-input v-model="dpaForm.content" type="textarea" :rows="15" />
                </el-form-item>
            </el-form>
            <div class="dpa-preview" v-if="dpaForm.content">
                <div class="preview-label">预览：</div>
                <div class="preview-content">{{ dpaForm.content.substring(0, 300) }}...</div>
            </div>
            <template #footer>
                <el-button @click="showNewDpa = false">取消</el-button>
                <el-button type="primary" @click="saveDpa" :loading="dpaLoading">保存草稿</el-button>
            </template>
        </el-dialog>

        <!-- DPA 预览弹窗 -->
        <el-dialog v-model="showDpaPreview" :title="previewDpaData?.title || 'DPA'" width="700px">
            <div class="dpa-meta">
                <el-tag size="small">v{{ previewDpaData?.version }}</el-tag>
                <el-tag size="small" :type="previewDpaData?.status === 'published' ? 'success' : 'info'" class="ml-2">
                    {{ previewDpaData?.status === 'published' ? '已发布' : '草稿' }}
                </el-tag>
                <span class="ml-2 text-secondary">{{ previewDpaData?.signatures_count || 0 }} 次签署</span>
            </div>
            <div class="dpa-body">
                <pre>{{ previewDpaData?.content }}</pre>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import apiClient from '@/api/client';

const activeTab = ref('overview');
const requests = ref([]);
const requestsLoading = ref(false);
const dpas = ref([]);
const dpasLoading = ref(false);
const submitLoading = ref(false);
const dpaLoading = ref(false);
const showNewRequest = ref(false);
const showNewDpa = ref(false);
const showDpaPreview = ref(false);
const previewDpaData = ref({});

const newRequest = ref({ userId: 1, type: 'access', reason: '' });
const dpaForm = ref({ title: '数据处理协议', version: '1.0.0', content: '' });

const overviewStats = [
    { label: '待处理请求', value: '0', color: '#e6a23c' },
    { label: '已签署 DPA', value: '0', color: '#67c23a' },
    { label: '合规完成度', value: '92%', color: '#409eff' },
    { label: '数据导出次数', value: '0', color: '#909399' },
];

const checklist = [
    { item: 'Cookie 同意横幅', status: 'done', note: '已实现 Cookie Consent Banner + 偏好面板' },
    { item: '隐私政策/服务条款', status: 'done', note: '注册强制勾选 + 版本历史 + 更新后重新确认' },
    { item: '数据访问请求 (Art.15)', status: 'done', note: '支持用户请求查看所有个人数据' },
    { item: '被遗忘权/删除 (Art.17)', status: 'done', note: '账号注销 + 数据匿名化处理' },
    { item: '数据可移植性 (Art.20)', status: 'done', note: 'JSON 格式导出个人数据' },
    { item: '数据处理协议 (Art.28)', status: 'partial', note: 'DPA 模板已创建，需客户签署流程' },
    { item: '数据留存策略', status: 'partial', note: '审计日志 365天 / 用户数据 3年 / 已删除 90天' },
    { item: '数据泄露通知 (Art.33/34)', status: 'partial', note: '72小时内通知义务流程待完善' },
    { item: 'DPIA 数据保护影响评估', status: 'partial', note: '模板已就绪，需定期执行' },
    { item: '数据保护官 (DPO)', status: 'done', note: 'privacy@huwutong.com' },
];

const retentionPolicies = [
    { category: '审计日志', retention_days: 365, action: '归档', legal_basis: 'GDPR Art.5(1)(e) 法律义务' },
    { category: '用户账户数据', retention_days: 1095, action: '匿名化', legal_basis: 'GDPR Art.6(1)(b) 合同履行' },
    { category: 'License 激活记录', retention_days: 730, action: '匿名化', legal_basis: 'GDPR Art.6(1)(c) 法律义务' },
    { category: '已删除账户', retention_days: 90, action: '清除', legal_basis: 'GDPR Art.17 被遗忘权 — 保留缓冲期' },
    { category: '支付记录', retention_days: 2555, action: '归档', legal_basis: '税法保留义务' },
    { category: 'Cookie 偏好', retention_days: 365, action: '清除', legal_basis: 'GDPR Art.7(1) 同意管理' },
    { category: '会话数据', retention_days: 30, action: '清除', legal_basis: 'GDPR Art.5(1)(e) 存储限制' },
];

function requestTypeLabel(type) {
    const map = { access: '数据访问', portability: '数据可移植', erasure: '被遗忘权', restrict: '限制处理', rectify: '更正数据' };
    return map[type] || type;
}
function statusType(s) { return s === 'completed' ? 'success' : s === 'pending' ? 'warning' : s === 'failed' ? 'danger' : 'info'; }

async function loadRequests() {
    requestsLoading.value = true;
    try {
        const { data } = await apiClient.get('/gdpr/requests');
        requests.value = data?.data || [];
        overviewStats[0].value = String((data?.data || []).filter(r => r.status === 'pending').length);
    } catch { requests.value = []; }
    finally { requestsLoading.value = false; }
}

async function loadDpas() {
    dpasLoading.value = true;
    try {
        const { data } = await apiClient.get('/gdpr/dpas');
        dpas.value = data?.data || [];
        overviewStats[1].value = String(dpas.value.reduce((s, d) => s + (d.signatures_count || 0), 0));
    } catch { dpas.value = []; }
    finally { dpasLoading.value = false; }
}

async function submitRequest() {
    submitLoading.value = true;
    try {
        await apiClient.post('/gdpr/requests', newRequest.value);
        ElMessage.success('DSR 请求已提交');
        showNewRequest.value = false;
        loadRequests();
    } catch (e) { ElMessage.error('提交失败'); }
    finally { submitLoading.value = false; }
}

async function processRequest(row) {
    try {
        await apiClient.post(`/gdpr/requests/${row.id}/process`);
        ElMessage.success('请求处理完成');
        loadRequests();
    } catch (e) { ElMessage.error('处理失败'); }
}

function downloadExport(row) {
    window.open(`/api/gdpr/requests/${row.id}/download`, '_blank');
}

async function saveDpa() {
    dpaLoading.value = true;
    try {
        await apiClient.post('/gdpr/dpas', dpaForm.value);
        ElMessage.success('DPA 草稿已保存');
        showNewDpa.value = false;
        loadDpas();
    } catch (e) { ElMessage.error('保存失败'); }
    finally { dpaLoading.value = false; }
}

async function publishDpa(row) {
    try {
        await ElMessageBox.confirm('发布后客户将看到此版本 DPA，确认发布？');
        await apiClient.post(`/gdpr/dpas/${row.id}/publish`);
        ElMessage.success('DPA 已发布');
        loadDpas();
    } catch { /* cancelled */ }
}

function previewDpa(row) {
    previewDpaData.value = row;
    showDpaPreview.value = true;
}

function editDpa(row) {
    showNewDpa.value = true;
    dpaForm.value = { title: row.title, version: row.version, content: row.content };
}

onMounted(() => {
    loadRequests();
    loadDpas();
});
</script>

<style scoped>
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.stat-card { text-align: center; padding: 8px; }
.stat-value { font-size: 28px; font-weight: 700; }
.stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.ml-2 { margin-left: 8px; }
.text-secondary { color: #909399; font-size: 13px; }
.dpa-preview { margin-top: 12px; padding: 12px; background: #f5f7fa; border-radius: 4px; }
.preview-label { font-size: 13px; font-weight: 600; margin-bottom: 4px; }
.preview-content { font-size: 12px; color: #606266; white-space: pre-wrap; }
.dpa-meta { margin-bottom: 16px; }
.dpa-body pre { white-space: pre-wrap; word-break: break-word; font-size: 13px; background: #f5f7fa; padding: 16px; border-radius: 4px; max-height: 400px; overflow-y: auto; }
</style>
