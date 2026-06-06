<template>
    <div class="sso-page">
        <div class="page-header">
            <div class="header-left">
                <h2>SSO 单点登录</h2>
                <span class="header-subtitle">管理 SAML 2.0 / OIDC / OAuth 2.0 身份提供商和 SSO 连接</span>
            </div>
            <div class="header-right">
                <el-button type="primary" @click="showConfigureDialog = true">
                    <el-icon><Plus /></el-icon>
                    新增 SSO 提供者
                </el-button>
            </div>
        </div>

        <el-alert
            title="关于 SSO"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
            description="配置单点登录后，您的团队成员可以使用企业身份提供商（如 Okta、Azure AD、飞书、Google Workspace）直接登录管理后台。支持 SAML 2.0、OIDC 和 OAuth 2.0 协议。"
        />

        <!-- SSO 提供者列表 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="card-header">
                    <span>SSO 提供者配置</span>
                </div>
            </template>
            <div v-loading="loadingProviders" class="provider-list">
                <div v-if="providers.length === 0 && !loadingProviders" class="empty-state">
                    <el-empty :image-size="80" description="尚未配置任何 SSO 提供者" />
                </div>

                <div v-for="provider in providers" :key="provider.id" class="provider-card">
                    <div class="provider-icon">
                        <el-avatar :size="48" :style="{ background: providerColor(provider.provider_type) }" :icon="providerIcon(provider.provider_type)" />
                    </div>
                    <div class="provider-body">
                        <div class="provider-name">
                            {{ provider.name }}
                            <el-tag :type="providerTypeTag(provider.provider_type)" size="small" effect="plain" style="margin-left: 8px">
                                {{ providerTypeLabel(provider.provider_type) }}
                            </el-tag>
                        </div>
                        <div class="provider-meta">
                            <span class="meta-item">提供者 ID: #{{ provider.id }}</span>
                        </div>
                    </div>
                    <div class="provider-actions">
                        <el-switch
                            :model-value="provider.is_active"
                            @change="(val) => handleToggle(provider, val)"
                            :loading="togglingId === provider.id"
                        />
                        <el-button text size="small" type="primary" style="margin-left: 8px;" @click="openEditDialog(provider)">
                            编辑
                        </el-button>
                        <el-button text size="small" type="danger" @click="handleDelete(provider)">
                            删除
                        </el-button>
                    </div>
                </div>
            </div>
        </el-card>

        <!-- SSO 绑定连接 -->
        <el-card shadow="never">
            <template #header>
                <div class="card-header">
                    <span>已绑定的 SSO 连接</span>
                    <el-button text size="small" @click="loadConnections">刷新</el-button>
                </div>
            </template>
            <el-table :data="connections" v-loading="loadingConnections" stripe>
                <el-table-column type="index" label="#" width="50" />
                <el-table-column prop="provider_name" label="提供者" min-width="160" />
                <el-table-column prop="provider_type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag :type="providerTypeTag(row.provider_type)" size="small">
                            {{ providerTypeLabel(row.provider_type) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="external_email" label="关联邮箱" min-width="200" />
                <el-table-column prop="last_login_at" label="最后登录" width="180">
                    <template #default="{ row }">
                        {{ row.last_login_at ? formatDate(row.last_login_at) : '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="绑定时间" width="180">
                    <template #default="{ row }">
                        {{ formatDate(row.created_at) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">
                        <el-button text type="danger" size="small" @click="handleDisconnect(row)">
                            解绑
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="connections.length === 0 && !loadingConnections" :image-size="60" description="暂无 SSO 绑定" />
        </el-card>

        <!-- 配置 SSO Dialog -->
        <el-dialog v-model="showConfigureDialog" :title="editingProvider ? '编辑 SSO 提供者' : '新增 SSO 提供者'" width="640px">
            <el-form ref="configFormRef" :model="configForm" :rules="configRules" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="名称" prop="name">
                            <el-input v-model="configForm.name" placeholder="如：企业 Okta、Azure AD" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="协议类型" prop="provider_type">
                            <el-select v-model="configForm.provider_type" style="width: 100%" @change="onProviderTypeChange">
                                <el-option label="SAML 2.0" value="saml2" />
                                <el-option label="OIDC" value="oidc" />
                                <el-option label="OAuth 2.0" value="oauth2" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>

                <!-- SAML 2.0 配置 -->
                <template v-if="configForm.provider_type === 'saml2'">
                    <el-divider content-position="left">SAML 2.0 配置</el-divider>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="IdP Entity ID" prop="config.idp_entity_id">
                                <el-input v-model="configForm.config.idp_entity_id" placeholder="身份提供商实体 ID" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="IdP 登录 URL" prop="config.idp_login_url">
                                <el-input v-model="configForm.config.idp_login_url" placeholder="https://idp.example.com/sso" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item label="IdP X.509 证书">
                        <el-input v-model="configForm.config.idp_x509_certificate" type="textarea" :rows="4" placeholder="-----BEGIN CERTIFICATE-----&#10;..." />
                    </el-form-item>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="SP Entity ID">
                                <el-input v-model="configForm.sp_entity_id" placeholder="urn:huwutong:sso:sp" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="SP ACS URL">
                                <el-input v-model="configForm.sp_acs_url" placeholder="https://your-domain.com/api/sso/callback" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </template>

                <!-- OIDC / OAuth 配置 -->
                <template v-if="configForm.provider_type === 'oidc' || configForm.provider_type === 'oauth2'">
                    <el-divider content-position="left">{{ configForm.provider_type === 'oidc' ? 'OIDC' : 'OAuth 2.0' }} 配置</el-divider>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="Client ID" prop="config.client_id">
                                <el-input v-model="configForm.config.client_id" placeholder="应用客户端 ID" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="Client Secret">
                                <el-input v-model="configForm.config.client_secret" type="password" show-password placeholder="客户端密钥" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="授权 URL" prop="config.authorization_url">
                                <el-input v-model="configForm.config.authorization_url" placeholder="https://idp.example.com/auth" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="Token URL" prop="config.token_url">
                                <el-input v-model="configForm.config.token_url" placeholder="https://idp.example.com/token" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item label="UserInfo URL">
                                <el-input v-model="configForm.config.userinfo_url" placeholder="https://idp.example.com/userinfo" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item label="JWKS URL">
                                <el-input v-model="configForm.config.jwks_url" placeholder="https://idp.example.com/jwks" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item label="Scopes">
                        <el-input v-model="configForm.config.scopes" placeholder="openid profile email" />
                        <div class="form-tip">用空格分隔多个 scope</div>
                    </el-form-item>
                </template>

                <!-- 属性映射 -->
                <el-divider content-position="left">属性映射</el-divider>
                <div class="attr-mapping-section">
                    <div class="attr-row" v-for="(attr, idx) in attributeMappings" :key="idx">
                        <el-select v-model="attr.sso_field" placeholder="SSO 属性" style="width: 200px">
                            <el-option label="邮箱 (email)" value="email" />
                            <el-option label="姓名 (name)" value="name" />
                            <el-option label="姓名 (displayName)" value="displayName" />
                            <el-option label="名 (givenName)" value="givenName" />
                            <el-option label="姓 (sn)" value="sn" />
                            <el-option label="UPN" value="upn" />
                            <el-option label="租户 ID (tenant_id)" value="tenant_id" />
                            <el-option label="角色 (role)" value="role" />
                            <el-option label="部门 (department)" value="department" />
                        </el-select>
                        <el-icon class="mx-2"><ArrowRight /></el-icon>
                        <el-input v-model="attr.local_field" placeholder="映射到本地字段" style="width: 200px" />
                        <el-button text type="danger" @click="removeMapping(idx)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button type="primary" text @click="addMapping">
                        <el-icon><Plus /></el-icon> 添加映射
                    </el-button>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showConfigureDialog = false">取消</el-button>
                <el-button type="primary" @click="handleSaveConfig" :loading="saving">
                    {{ editingProvider ? '保存修改' : '创建' }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, ArrowRight } from '@element-plus/icons-vue';
import apiClient from '@/api/client';

const loadingProviders = ref(false);
const loadingConnections = ref(false);
const saving = ref(false);
const togglingId = ref(null);
const showConfigureDialog = ref(false);
const editingProvider = ref(null);

const providers = ref([]);
const connections = ref([]);

const configFormRef = ref(null);
const configForm = reactive({
    name: '',
    provider_type: 'saml2',
    sp_entity_id: '',
    sp_acs_url: '',
    config: {
        idp_entity_id: '',
        idp_login_url: '',
        idp_x509_certificate: '',
        client_id: '',
        client_secret: '',
        authorization_url: '',
        token_url: '',
        userinfo_url: '',
        jwks_url: '',
        scopes: '',
    },
    attribute_mapping: [],
});

const configRules = {
    name: [{ required: true, message: '请输入名称', trigger: 'blur' }],
    provider_type: [{ required: true, message: '请选择协议类型', trigger: 'change' }],
    'config.idp_entity_id': [
        { required: true, message: '请输入 IdP Entity ID', trigger: 'blur' },
    ],
    'config.idp_login_url': [
        { required: true, message: '请输入 IdP 登录 URL', trigger: 'blur' },
    ],
    'config.client_id': [
        { required: true, message: '请输入 Client ID', trigger: 'blur' },
    ],
    'config.authorization_url': [
        { required: true, message: '请输入授权 URL', trigger: 'blur' },
    ],
    'config.token_url': [
        { required: true, message: '请输入 Token URL', trigger: 'blur' },
    ],
};

const attributeMappings = reactive([]);

function providerTypeTag(type) {
    const map = { saml2: 'warning', oidc: 'primary', oauth2: 'success' };
    return map[type] || 'info';
}

function providerTypeLabel(type) {
    const map = { saml2: 'SAML 2.0', oidc: 'OIDC', oauth2: 'OAuth 2.0' };
    return map[type] || type;
}

function providerColor(type) {
    const map = { saml2: '#e6a23c', oidc: '#409eff', oauth2: '#67c23a' };
    return map[type] || '#909399';
}

function providerIcon(type) {
    return null; // Use colored avatar background
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function onProviderTypeChange() {
    // Reset validation when type changes
}

function addMapping() {
    attributeMappings.push({ sso_field: '', local_field: '' });
}

function removeMapping(idx) {
    attributeMappings.splice(idx, 1);
}

async function loadProviders() {
    loadingProviders.value = true;
    try {
        const { data: res } = await apiClient.get('/sso/providers');
        providers.value = res.data || [];
    } catch {
        providers.value = [];
    } finally {
        loadingProviders.value = false;
    }
}

async function loadConnections() {
    loadingConnections.value = true;
    try {
        const { data: res } = await apiClient.get('/sso/connections');
        connections.value = res.data || [];
    } catch {
        connections.value = [];
    } finally {
        loadingConnections.value = false;
    }
}

async function handleToggle(provider, val) {
    togglingId.value = provider.id;
    try {
        await apiClient.post(`/sso/providers/${provider.id}/toggle`, { is_active: val });
        provider.is_active = val;
        ElMessage.success(val ? 'SSO 已启用' : 'SSO 已停用');
    } catch {
        ElMessage.error('操作失败');
    } finally {
        togglingId.value = null;
    }
}

function resetForm() {
    configForm.name = '';
    configForm.provider_type = 'saml2';
    configForm.sp_entity_id = '';
    configForm.sp_acs_url = '';
    configForm.config = {
        idp_entity_id: '',
        idp_login_url: '',
        idp_x509_certificate: '',
        client_id: '',
        client_secret: '',
        authorization_url: '',
        token_url: '',
        userinfo_url: '',
        jwks_url: '',
        scopes: '',
    };
    attributeMappings.length = 0;
    editingProvider.value = null;
}

function openEditDialog(provider) {
    editingProvider.value = provider;
    configForm.name = provider.name;
    configForm.provider_type = provider.provider_type;
    // TODO: Load full config via API if needed
    showConfigureDialog.value = true;
}

async function handleSaveConfig() {
    const valid = await configFormRef.value?.validate().catch(() => false);
    if (!valid) return;

    saving.value = true;
    try {
        const payload = {
            name: configForm.name,
            provider_type: configForm.provider_type,
            config: Object.fromEntries(
                Object.entries(configForm.config).filter(([_, v]) => v !== '')
            ),
            sp_entity_id: configForm.sp_entity_id || null,
            sp_acs_url: configForm.sp_acs_url || null,
            attribute_mapping: attributeMappings.length > 0
                ? Object.fromEntries(attributeMappings.filter(a => a.sso_field && a.local_field).map(a => [a.sso_field, a.local_field]))
                : null,
        };

        await apiClient.post('/sso/providers', payload);
        ElMessage.success(editingProvider.value ? 'SSO 配置已更新' : 'SSO 提供者已添加');
        showConfigureDialog.value = false;
        resetForm();
        loadProviders();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleDelete(provider) {
    try {
        await ElMessageBox.confirm(
            `确定要删除 SSO 提供者 "${provider.name}" 吗？此操作不可撤销。`,
            '确认删除',
            { confirmButtonText: '确定删除', cancelButtonText: '取消', type: 'warning' }
        );
        await apiClient.delete(`/sso/providers/${provider.id}`);
        ElMessage.success('SSO 提供者已删除');
        loadProviders();
    } catch { /* cancelled */ }
}

async function handleDisconnect(connection) {
    try {
        await ElMessageBox.confirm(
            `确定要解除与 "${connection.provider_name}" 的 SSO 绑定吗？`,
            '确认解绑',
            { confirmButtonText: '确定解绑', cancelButtonText: '取消', type: 'warning' }
        );
        await apiClient.delete(`/sso/connections/${connection.id}`);
        ElMessage.success('SSO 绑定已解除');
        loadConnections();
    } catch { /* cancelled */ }
}

onMounted(() => {
    loadProviders();
    loadConnections();
});
</script>

<style scoped>
.sso-page { padding: 20px; }

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

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}

.provider-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.empty-state { padding: 40px 0; }

.provider-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    transition: all 0.2s;
}
.provider-card:hover {
    border-color: var(--el-color-primary-light-5);
    background: var(--el-color-info-light-9);
}

.provider-body {
    flex: 1;
    min-width: 0;
}
.provider-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    display: flex;
    align-items: center;
}
.provider-meta {
    margin-top: 4px;
    display: flex;
    gap: 16px;
}
.meta-item {
    font-size: 12px;
    color: var(--el-text-color-secondary);
}

.provider-actions {
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.attr-mapping-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.attr-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.mx-2 { margin: 0 8px; }

.form-tip {
    font-size: 12px;
    color: var(--el-text-color-placeholder);
    margin-top: 4px;
}

:deep(.el-card__body) { padding: 16px; }
:deep(.el-divider__text) { font-size: 13px; }
</style>
