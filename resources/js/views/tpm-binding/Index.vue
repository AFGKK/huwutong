<template>
    <div class="tpm-page">
        <div class="page-header">
            <div>
                <h2>TPM 硬件安全绑定</h2>
                <p class="text-muted">TPM 2.0/SGX 安全芯片级设备指纹 · 硬件认证链验证 · 军工/金融高安全场景</p>
            </div>
            <div class="header-actions">
                <el-button @click="loadAll" :loading="loading" :icon="Refresh">刷新</el-button>
                <el-button @click="showRegisterForm = true" type="primary" :icon="Key">注册绑定</el-button>
            </div>
        </div>

        <!-- ── 概览卡片 ── -->
        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">总绑定</div><div class="metric-value">{{ dash.total_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">活跃</div><div class="metric-value success">{{ dash.active_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">已吊销</div><div class="metric-value text-muted">{{ dash.revoked_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">锁定</div><div class="metric-value danger">{{ dash.locked_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">TPM 2.0</div><div class="metric-value">{{ dash.tpm2_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">SGX</div><div class="metric-value">{{ dash.sgx_bindings }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">今日验证</div><div class="metric-value">{{ dash.today_verifications }}</div></el-card></el-col>
            <el-col :xs="12" :sm="6" :md="3"><el-card shadow="hover" class="metric-card"><div class="metric-label">今日失败</div><div class="metric-value danger">{{ dash.failed_today }}</div></el-card></el-col>
        </el-row>

        <!-- ── 绑定列表 ── -->
        <el-card shadow="hover">
            <template #header>
                <div class="card-header">
                    <span><el-icon><Key /></el-icon> TPM 绑定列表</span>
                    <div style="display:flex;gap:8px">
                        <el-select v-model="filterStatus" placeholder="状态" clearable size="small" style="width:120px" @change="loadList">
                            <el-option label="活跃" value="active" /><el-option label="已吊销" value="revoked" /><el-option label="锁定" value="locked" />
                        </el-select>
                        <el-select v-model="filterType" placeholder="类型" clearable size="small" style="width:120px" @change="loadList">
                            <el-option label="TPM 2.0" value="tpm2" /><el-option label="SGX" value="sgx" /><el-option label="混合" value="hybrid" />
                        </el-select>
                    </div>
                </div>
            </template>
            <el-table :data="bindings" stripe v-loading="listLoading" size="small">
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column label="License" width="120"><template #default="{row}">{{ row.license?.license_key || '—' }}</template></el-table-column>
                <el-table-column prop="tpm_manufacturer" label="TPM 厂商" width="100" />
                <el-table-column label="类型" width="80"><template #default="{row}"><el-tag size="small">{{ row.binding_type }}</el-tag></template></el-table-column>
                <el-table-column label="AK Name" min-width="140" show-overflow-tooltip><template #default="{row}"><code style="font-size:11px">{{ row.ak_name ? row.ak_name.substring(0, 20)+'...' : '—' }}</code></template></el-table-column>
                <el-table-column label="状态" width="80"><template #default="{row}">
                    <el-tag :type="row.status === 'active' ? 'success' : (row.status === 'locked' ? 'danger' : 'info')" size="small">{{ row.status }}</el-tag>
                </template></el-table-column>
                <el-table-column label="验证日志" width="70" prop="verification_logs_count" />
                <el-table-column label="失败次数" width="70" prop="failed_attempts" />
                <el-table-column label="最后验证" width="140"><template #default="{row}">{{ row.last_verified_at ? fmtDate(row.last_verified_at) : '—' }}</template></el-table-column>
                <el-table-column label="绑定时间" width="140"><template #default="{row}">{{ fmtDate(row.bound_at) }}</template></el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{row}">
                        <el-button size="small" @click="showDetail(row)">详情</el-button>
                        <el-button size="small" type="warning" @click="showVerifyDialog(row)" v-if="row.status === 'active'">验证</el-button>
                        <el-button size="small" type="primary" @click="unlockBinding(row)" v-if="row.status === 'locked'">解锁</el-button>
                        <el-button size="small" type="danger" @click="revokeBinding(row)" v-if="row.status === 'active'">吊销</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!bindings.length && !listLoading" description="暂无 TPM 绑定记录" />
        </el-card>

        <!-- 注册绑定对话框 -->
        <el-dialog v-model="showRegisterForm" title="注册 TPM 绑定" width="550px">
            <el-form :model="regForm" label-width="120px">
                <el-form-item label="License ID" :rules="[{required:true}]"><el-input-number v-model="regForm.license_id" :min="1" style="width:100%" /></el-form-item>
                <el-form-item label="设备 ID"><el-input-number v-model="regForm.device_id" :min="1" style="width:100%" /></el-form-item>
                <el-row :gutter="12">
                    <el-col :span="12"><el-form-item label="TPM 厂商"><el-input v-model="regForm.tpm_manufacturer" /></el-form-item></el-col>
                    <el-col :span="12"><el-form-item label="绑定类型" :rules="[{required:true}]">
                        <el-select v-model="regForm.binding_type" style="width:100%"><el-option label="TPM 2.0" value="tpm2" /><el-option label="SGX" value="sgx" /><el-option label="混合" value="hybrid" /></el-select>
                    </el-form-item></el-col>
                </el-row>
                <el-form-item label="AK 公钥"><el-input v-model="regForm.ak_public_key" type="textarea" :rows="2" /></el-form-item>
                <el-form-item label="EK 证书"><el-input v-model="regForm.ek_certificate" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showRegisterForm = false">取消</el-button><el-button type="primary" @click="registerBinding" :loading="saving">注册</el-button></template>
        </el-dialog>

        <!-- 详情对话框 -->
        <el-dialog v-model="showDetailDialog" :title="'TPM 绑定 #' + (detail?.id || '')" width="750px" top="5vh">
            <template v-if="detail">
                <el-descriptions :column="2" border size="small">
                    <el-descriptions-item label="License">{{ detail.license?.license_key }}</el-descriptions-item>
                    <el-descriptions-item label="绑定类型">{{ detail.binding_type }}</el-descriptions-item>
                    <el-descriptions-item label="TPM 厂商">{{ detail.tpm_manufacturer || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="TPM 版本">{{ detail.tpm_version || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="状态"><el-tag :type="detail.status === 'active' ? 'success' : (detail.status === 'locked' ? 'danger' : 'info')" size="small">{{ detail.status }}</el-tag></el-descriptions-item>
                    <el-descriptions-item label="失败次数">{{ detail.failed_attempts }} / {{ maxAttempts }}</el-descriptions-item>
                    <el-descriptions-item label="最后验证">{{ detail.last_verified_at ? fmtDate(detail.last_verified_at) : '—' }}</el-descriptions-item>
                    <el-descriptions-item label="最后认证">{{ detail.last_attestation_at ? fmtDate(detail.last_attestation_at) : '—' }}</el-descriptions-item>
                    <el-descriptions-item label="绑定 IP">{{ detail.bound_ip || '—' }}</el-descriptions-item>
                    <el-descriptions-item label="绑定时间">{{ fmtDate(detail.bound_at) }}</el-descriptions-item>
                    <el-descriptions-item label="AK Name" :span="2"><code style="font-size:11px">{{ detail.ak_name || '—' }}</code></el-descriptions-item>
                </el-descriptions>
                <!-- 验证日志 -->
                <div class="detail-section" v-if="detail.verification_logs?.length">
                    <h4>最近验证记录</h4>
                    <el-table :data="detail.verification_logs" size="small" max-height="200">
                        <el-table-column label="结果" width="80"><template #default="{row}"><el-tag :type="row.result === 'passed' ? 'success' : 'danger'" size="small">{{ row.result }}</el-tag></template></el-table-column>
                        <el-table-column prop="duration_ms" label="耗时" width="80" />
                        <el-table-column prop="error_message" label="错误" min-width="200" show-overflow-tooltip />
                        <el-table-column label="时间" width="150"><template #default="{row}">{{ fmtDate(row.verified_at) }}</template></el-table-column>
                    </el-table>
                </div>
            </template>
            <template #footer>
                <el-button @click="showDetailDialog = false">关闭</el-button>
            </template>
        </el-dialog>

        <!-- 验证对话框 -->
        <el-dialog v-model="showVerifyDialog_" title="TPM 验证" width="450px">
            <el-form :model="verifyForm" label-width="100px">
                <el-form-item label="Nonce" :rules="[{required:true}]"><el-input v-model="verifyForm.nonce" /></el-form-item>
                <el-form-item label="时间戳"><el-input-number v-model="verifyForm.timestamp" :min="0" style="width:100%" /></el-form-item>
            </el-form>
            <template #footer><el-button @click="showVerifyDialog_ = false">取消</el-button><el-button type="primary" @click="submitVerify" :loading="verifyLoading">验证</el-button></template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Key } from '@element-plus/icons-vue';
import tpmBindingApi from '@/api/tpmBinding';

const loading = ref(false);
const saving = ref(false);
const listLoading = ref(false);
const verifyLoading = ref(false);
const dash = reactive({ total_bindings: 0, active_bindings: 0, revoked_bindings: 0, locked_bindings: 0, tpm2_bindings: 0, sgx_bindings: 0, hybrid_bindings: 0, today_verifications: 0, failed_today: 0, tpm_available_devices: 0, hardware_bound_devices: 0 });
const bindings = ref([]);
const detail = ref(null);
const filterStatus = ref('');
const filterType = ref('');
const maxAttempts = ref(5);

// Dialogs
const showRegisterForm = ref(false);
const showDetailDialog = ref(false);
const showVerifyDialog_ = ref(false);
const verifyTargetId = ref(null);
const regForm = reactive({ license_id: null, device_id: null, tpm_manufacturer: '', binding_type: 'tpm2', ak_public_key: '', ek_certificate: '' });
const verifyForm = reactive({ nonce: '', timestamp: 0 });

onMounted(loadAll);

async function loadAll() {
    loading.value = true;
    try { await Promise.all([loadDashboard(), loadList()]); } finally { loading.value = false; }
}

async function loadDashboard() {
    try { const r = await tpmBindingApi.dashboard(); Object.assign(dash, r.data?.data || {}); } catch {}
}
async function loadList() {
    listLoading.value = true;
    try {
        const params = { per_page: 50 };
        if (filterStatus.value) params.status = filterStatus.value;
        if (filterType.value) params.binding_type = filterType.value;
        const r = await tpmBindingApi.listBindings(params);
        bindings.value = r.data?.data?.items || [];
    } finally { listLoading.value = false; }
}

async function registerBinding() {
    saving.value = true;
    try {
        await tpmBindingApi.registerBinding(regForm);
        ElMessage.success('绑定注册成功'); showRegisterForm.value = false; loadAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || '注册失败'); }
    finally { saving.value = false; }
}

async function showDetail(row) {
    try {
        const r = await tpmBindingApi.showBinding(row.id);
        detail.value = r.data?.data;
        showDetailDialog.value = true;
    } catch { ElMessage.error('加载详情失败'); }
}

function showVerifyDialog(row) {
    verifyTargetId.value = row.id;
    verifyForm.nonce = Array.from({ length: 32 }, () => Math.random().toString(16)[2]).join('');
    verifyForm.timestamp = Math.floor(Date.now() / 1000);
    showVerifyDialog_.value = true;
}

async function submitVerify() {
    verifyLoading.value = true;
    try {
        const r = await tpmBindingApi.verifyBinding(verifyTargetId.value, verifyForm);
        if (r.data?.data?.result === 'passed') ElMessage.success('验证通过');
        else ElMessage.error('验证失败: ' + (r.data?.data?.error || ''));
        showVerifyDialog_.value = false; loadList();
    } catch { ElMessage.error('验证失败'); }
    finally { verifyLoading.value = false; }
}

async function unlockBinding(row) {
    await tpmBindingApi.unlockBinding(row.id);
    ElMessage.success('已解锁'); loadList();
}

async function revokeBinding(row) {
    try {
        const { value } = await ElMessageBox.prompt('输入吊销原因', '吊销绑定');
        await tpmBindingApi.revokeBinding(row.id, value);
        ElMessage.success('已吊销'); loadList();
    } catch { if (value !== null) ElMessage.error('吊销失败'); }
}

function fmtDate(t) { if (!t) return '—'; return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }); }
</script>

<style scoped>
.tpm-page { padding: 16px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 20px; }
.header-actions { display: flex; gap: 8px; }
.mb-4 { margin-bottom: 16px; }
.metric-card { padding: 8px; }
.metric-card .metric-label { font-size: 12px; color: #909399; margin-bottom: 4px; }
.metric-card .metric-value { font-size: 20px; font-weight: 700; }
.success { color: #67c23a; } .danger { color: #f56c6c; }
.text-muted { color: #c0c4cc; }
.card-header { display: flex; justify-content: space-between; align-items: center; }
.detail-section { margin-top: 16px; }
.detail-section h4 { margin: 0 0 8px; font-size: 14px; }
</style>
