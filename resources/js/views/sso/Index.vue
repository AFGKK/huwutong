<template>
    <div class="sso-unified-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('sso_unified.title') }}</h2>
                <span class="header-subtitle">{{ t('sso_unified.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="activeTab" type="border-card">
            <!-- Tab 1: SSO 提供者管理 -->
            <el-tab-pane :label="t('sso_unified.tab_providers')" name="providers">
                <el-alert
                    :title="t('sso_page.about_title')"
                    type="info"
                    :closable="false"
                    show-icon
                    class="mb-4"
                    :description="t('sso_page.about_desc')"
                />

                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('sso_page.providers_card_title') }}</span>
                            <el-button type="primary" size="small" @click="showConfigureDialog = true">
                                <el-icon><Plus /></el-icon>
                                {{ t('sso_page.add_btn') }}
                            </el-button>
                        </div>
                    </template>
                    <div v-loading="loadingProviders" class="provider-list">
                        <div v-if="providers.length === 0 && !loadingProviders" class="empty-state">
                            <el-empty :image-size="80" :description="t('sso_page.no_providers')" />
                        </div>
                        <div v-for="provider in providers" :key="provider.id" class="provider-card">
                            <div class="provider-icon">
                                <el-avatar :size="48" :style="{ background: providerColor(provider.provider_type) }" />
                            </div>
                            <div class="provider-body">
                                <div class="provider-name">
                                    {{ provider.name }}
                                    <el-tag :type="providerTypeTag(provider.provider_type)" size="small" effect="plain" style="margin-left: 8px">
                                        {{ providerTypeLabel(provider.provider_type) }}
                                    </el-tag>
                                </div>
                                <div class="provider-meta">
                                    <span class="meta-item">{{ t('sso_page.provider_id', { id: provider.id }) }}</span>
                                </div>
                            </div>
                            <div class="provider-actions">
                                <el-switch
                                    :model-value="provider.is_active"
                                    @change="(val) => handleToggle(provider, val)"
                                    :loading="togglingId === provider.id"
                                />
                                <el-button text size="small" type="primary" style="margin-left: 8px" @click="openEditDialog(provider)">
                                    {{ t('actions.edit') }}
                                </el-button>
                                <el-button text size="small" type="danger" @click="handleDelete(provider)">
                                    {{ t('actions.delete') }}
                                </el-button>
                            </div>
                        </div>
                    </div>
                </el-card>

                <el-card shadow="never">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('sso_page.connections_card_title') }}</span>
                            <el-button text size="small" @click="loadConnections">{{ t('sso_page.refresh') }}</el-button>
                        </div>
                    </template>
                    <el-table :data="connections" v-loading="loadingConnections" stripe>
                        <el-table-column type="index" label="#" width="50" />
                        <el-table-column prop="provider_name" :label="t('sso_page.col_provider')" min-width="160" />
                        <el-table-column prop="provider_type" :label="t('sso_page.col_type')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="providerTypeTag(row.provider_type)" size="small">
                                    {{ providerTypeLabel(row.provider_type) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="external_email" :label="t('sso_page.col_email')" min-width="200" />
                        <el-table-column prop="last_login_at" :label="t('sso_page.col_last_login')" width="180">
                            <template #default="{ row }">
                                {{ row.last_login_at ? formatDate(row.last_login_at) : '-' }}
                            </template>
                        </el-table-column>
                        <el-table-column prop="created_at" :label="t('sso_page.col_bound_at')" width="180">
                            <template #default="{ row }">
                                {{ formatDate(row.created_at) }}
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('sso_page.col_actions')" width="100">
                            <template #default="{ row }">
                                <el-button text type="danger" size="small" @click="handleDisconnect(row)">
                                    {{ t('sso_page.disconnect') }}
                                </el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="connections.length === 0 && !loadingConnections" :image-size="60" :description="t('sso_page.no_connections')" />
                </el-card>
            </el-tab-pane>

            <!-- Tab 2: 企业 SSO 深度配置 -->
            <el-tab-pane :label="t('sso_unified.tab_enterprise')" name="enterprise">
                <el-row :gutter="20" class="mb-4">
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.active_idps }}</div><div class="stat-label">{{ t('enterprise_sso_page.stats.active_idps') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_idps }}</div><div class="stat-label">{{ t('enterprise_sso_page.stats.total_idps') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_domains }}</div><div class="stat-label">{{ t('enterprise_sso_page.stats.domains') }}</div></div></el-card></el-col>
                    <el-col :span="6"><el-card shadow="hover"><div class="stat-item"><div class="stat-value">{{ stats.total_mappings }}</div><div class="stat-label">{{ t('enterprise_sso_page.stats.mappings') }}</div></div></el-card></el-col>
                </el-row>

                <el-card class="mb-4">
                    <template #header>
                        <div class="card-header">
                            <span>{{ t('enterprise_sso_page.idp_title') }}</span>
                            <el-button type="primary" size="small" @click="showCreateDialog = true">{{ t('enterprise_sso_page.new_idp') }}</el-button>
                        </div>
                    </template>
                    <el-empty v-if="!enterpriseLoading && idps.length === 0" :description="t('enterprise_sso_page.empty_idp')" />
                    <el-table v-else :data="idps" stripe size="small">
                        <el-table-column prop="name" :label="t('enterprise_sso_page.cols.name')" width="140" />
                        <el-table-column prop="provider_type" :label="t('enterprise_sso_page.cols.type')" width="100">
                            <template #default="{ row }"><el-tag>{{ row.provider_type }}</el-tag></template>
                        </el-table-column>
                        <el-table-column prop="idp_entity_id" label="IdP Entity ID" min-width="200" show-overflow-tooltip />
                        <el-table-column prop="domain_routes_count" :label="t('enterprise_sso_page.cols.domains')" width="60" />
                        <el-table-column prop="group_mappings_count" :label="t('enterprise_sso_page.cols.mappings')" width="70" />
                        <el-table-column prop="is_active" :label="t('enterprise_sso_page.cols.status')" width="70">
                            <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('enterprise_sso_page.cols.actions')" width="280" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" @click="viewDetails(row)">{{ t('actions.view_details') }}</el-button>
                                <el-button size="small" @click="downloadMetadata(row)">{{ t('enterprise_sso_page.sp_metadata') }}</el-button>
                                <el-button size="small" @click="runHealthCheck(row)">{{ t('enterprise_sso_page.health') }}</el-button>
                                <el-button size="small" type="danger" plain @click="confirmDelete(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-card>
            </el-tab-pane>
        </el-tabs>

        <!-- ==================== Dialogs ==================== -->

        <!-- SSO 提供者配置 Dialog -->
        <el-dialog
            v-model="showConfigureDialog"
            :title="editingProvider ? t('sso_page.edit_dialog_title') : t('sso_page.create_dialog_title')"
            width="640px"
        >
            <el-form ref="configFormRef" :model="configForm" :rules="configRules" label-position="top">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('sso_page.name_label')" prop="name">
                            <el-input v-model="configForm.name" :placeholder="t('sso_page.name_ph')" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('sso_page.protocol_type_label')" prop="provider_type">
                            <el-select v-model="configForm.provider_type" style="width: 100%" @change="onProviderTypeChange">
                                <el-option v-for="opt in protocolTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <template v-if="configForm.provider_type === 'saml2'">
                    <el-divider content-position="left">{{ t('sso_page.saml_config_title') }}</el-divider>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.idp_entity_id_label')" prop="config.idp_entity_id">
                                <el-input v-model="configForm.config.idp_entity_id" :placeholder="t('sso_page.idp_entity_id_ph')" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.idp_login_url_label')" prop="config.idp_login_url">
                                <el-input v-model="configForm.config.idp_login_url" placeholder="https://idp.example.com/sso" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item :label="t('sso_page.idp_x509_label')">
                        <el-input v-model="configForm.config.idp_x509_certificate" type="textarea" :rows="4" placeholder="-----BEGIN CERTIFICATE-----\n..." />
                    </el-form-item>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.sp_entity_id_label')">
                                <el-input v-model="configForm.sp_entity_id" placeholder="urn:huwutong:sso:sp" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.sp_acs_url_label')">
                                <el-input v-model="configForm.sp_acs_url" placeholder="https://your-domain.com/api/sso/callback" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                </template>
                <template v-if="configForm.provider_type === 'oidc' || configForm.provider_type === 'oauth2'">
                    <el-divider content-position="left">
                        {{ configForm.provider_type === 'oidc' ? t('sso_page.oidc_config_title') : t('sso_page.oauth2_config_title') }}
                    </el-divider>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.client_id_label')" prop="config.client_id">
                                <el-input v-model="configForm.config.client_id" :placeholder="t('sso_page.client_id_ph')" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.client_secret_label')">
                                <el-input v-model="configForm.config.client_secret" type="password" show-password :placeholder="t('sso_page.client_secret_ph')" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.authorization_url_label')" prop="config.authorization_url">
                                <el-input v-model="configForm.config.authorization_url" placeholder="https://idp.example.com/auth" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.token_url_label')" prop="config.token_url">
                                <el-input v-model="configForm.config.token_url" placeholder="https://idp.example.com/token" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.userinfo_url_label')">
                                <el-input v-model="configForm.config.userinfo_url" placeholder="https://idp.example.com/userinfo" />
                            </el-form-item>
                        </el-col>
                        <el-col :span="12">
                            <el-form-item :label="t('sso_page.jwks_url_label')">
                                <el-input v-model="configForm.config.jwks_url" placeholder="https://idp.example.com/jwks" />
                            </el-form-item>
                        </el-col>
                    </el-row>
                    <el-form-item :label="t('sso_page.scopes_label')">
                        <el-input v-model="configForm.config.scopes" placeholder="openid profile email" />
                        <div class="form-tip">{{ t('sso_page.scopes_tip') }}</div>
                    </el-form-item>
                </template>
                <el-divider content-position="left">{{ t('sso_page.attr_mapping_title') }}</el-divider>
                <div class="attr-mapping-section">
                    <div class="attr-row" v-for="(attr, idx) in attributeMappings" :key="idx">
                        <el-select v-model="attr.sso_field" :placeholder="t('sso_page.sso_attr_ph')" style="width: 200px">
                            <el-option v-for="opt in ssoFieldOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                        </el-select>
                        <el-icon class="mx-2"><ArrowRight /></el-icon>
                        <el-input v-model="attr.local_field" :placeholder="t('sso_page.local_field_ph')" style="width: 200px" />
                        <el-button text type="danger" @click="removeMapping(idx)">
                            <el-icon><Delete /></el-icon>
                        </el-button>
                    </div>
                    <el-button type="primary" text @click="addMapping">
                        <el-icon><Plus /></el-icon> {{ t('sso_page.add_mapping') }}
                    </el-button>
                </div>
            </el-form>
            <template #footer>
                <el-button @click="showConfigureDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleSaveConfig" :loading="saving">
                    {{ editingProvider ? t('actions.save') : t('actions.create') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 企业 SSO 详情 Drawer -->
        <el-drawer v-model="showDetailDrawer" :title="currentIdp?.name" size="500px" destroy-on-close>
            <el-tabs v-if="currentIdp">
                <el-tab-pane :label="t('enterprise_sso_page.tabs.basic')">
                    <el-descriptions :column="1" border size="small">
                        <el-descriptions-item :label="t('enterprise_sso_page.cols.name')">{{ currentIdp.name }}</el-descriptions-item>
                        <el-descriptions-item :label="t('enterprise_sso_page.cols.type')">{{ currentIdp.provider_type }}</el-descriptions-item>
                        <el-descriptions-item label="Entity ID">{{ currentIdp.idp_entity_id || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="SSO URL">{{ currentIdp.idp_sso_url || '-' }}</el-descriptions-item>
                        <el-descriptions-item label="Name ID Format">{{ currentIdp.name_id_format }}</el-descriptions-item>
                        <el-descriptions-item :label="t('enterprise_sso_page.sign_requests')">{{ currentIdp.sign_authn_requests ? t('enterprise_sso_page.yes') : t('enterprise_sso_page.no') }}</el-descriptions-item>
                    </el-descriptions>
                </el-tab-pane>
                <el-tab-pane :label="t('enterprise_sso_page.tabs.domains')">
                    <div class="mb-2 flex gap-2">
                        <el-input v-model="newDomain" placeholder="example.com" size="small" style="width:200px" />
                        <el-button size="small" type="primary" @click="addDomain">{{ t('enterprise_sso_page.add') }}</el-button>
                    </div>
                    <el-table :data="domains" stripe size="small">
                        <el-table-column prop="domain" :label="t('enterprise_sso_page.cols.domain')" />
                        <el-table-column prop="is_primary" :label="t('enterprise_sso_page.primary')" width="70">
                            <template #default="{ row }">{{ row.is_primary ? t('enterprise_sso_page.yes') : t('enterprise_sso_page.no') }}</template>
                        </el-table-column>
                        <el-table-column :label="t('enterprise_sso_page.cols.actions')" width="60">
                            <template #default="{ row }"><el-button size="small" type="danger" plain @click="deleteDomainRow(row)">×</el-button></template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
                <el-tab-pane :label="t('enterprise_sso_page.tabs.mappings')">
                    <div class="mb-2 flex gap-2">
                        <el-input v-model="newMapping.group" :placeholder="t('enterprise_sso_page.idp_group_ph')" size="small" style="width:150px" />
                        <el-input v-model="newMapping.role" :placeholder="t('enterprise_sso_page.local_role_ph')" size="small" style="width:150px" />
                        <el-button size="small" type="primary" @click="addMappingEnterprise">{{ t('enterprise_sso_page.add') }}</el-button>
                    </div>
                    <el-table :data="mappings" stripe size="small">
                        <el-table-column prop="idp_group_name" :label="t('enterprise_sso_page.cols.idp_group')" />
                        <el-table-column prop="local_role" :label="t('enterprise_sso_page.cols.local_role')" />
                    </el-table>
                </el-tab-pane>
                <el-tab-pane :label="t('enterprise_sso_page.tabs.jit')">
                    <el-empty v-if="jitRules.length === 0" :description="t('enterprise_sso_page.empty_jit')" :image-size="40" />
                    <div v-for="rule in jitRules" :key="rule.id" class="jit-card">
                        <div class="jit-header">{{ rule.name }}</div>
                        <div>{{ t('enterprise_sso_page.default_role') }}: {{ rule.default_role }}</div>
                        <div>{{ t('enterprise_sso_page.auto_create') }}: {{ rule.auto_create_users ? t('enterprise_sso_page.yes') : t('enterprise_sso_page.no') }}</div>
                        <div>{{ t('enterprise_sso_page.domain_filter') }}: {{ rule.email_domain_filter || t('enterprise_sso_page.none') }}</div>
                    </div>
                    <el-button size="small" class="mt-2" @click="showJitDialog = true">{{ t('enterprise_sso_page.new_jit') }}</el-button>
                </el-tab-pane>
            </el-tabs>
        </el-drawer>

        <!-- 企业 SSO 创建 Dialog -->
        <el-dialog v-model="showCreateDialog" :title="t('enterprise_sso_page.create_title')" width="550px" destroy-on-close>
            <el-form ref="cfRef" :model="cf" :rules="cfRules" label-width="120px">
                <el-form-item :label="t('enterprise_sso_page.cols.name')" prop="name"><el-input v-model="cf.name" /></el-form-item>
                <el-form-item :label="t('enterprise_sso_page.provider_type')" prop="provider_type">
                    <el-select v-model="cf.provider_type" style="width:100%">
                        <el-option label="Okta" value="okta" />
                        <el-option label="Azure AD" value="azure_ad" />
                        <el-option label="OneLogin" value="onelogin" />
                        <el-option label="Generic SAML 2.0" value="generic_saml" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('enterprise_sso_page.metadata_xml')">
                    <el-input v-model="cf.idp_metadata_xml" type="textarea" :rows="6" :placeholder="t('enterprise_sso_page.metadata_ph')" />
                </el-form-item>
                <el-form-item label="Name ID Format">
                    <el-select v-model="cf.name_id_format" style="width:100%">
                        <el-option label="Email Address" value="email" />
                        <el-option label="Unspecified" value="unspecified" />
                        <el-option label="Persistent" value="persistent" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleCreateEnterpriseIdp">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>

        <!-- JIT 规则 Dialog -->
        <el-dialog v-model="showJitDialog" :title="t('enterprise_sso_page.jit_title')" width="480px" destroy-on-close>
            <el-form :model="jitForm" label-width="100px">
                <el-form-item :label="t('enterprise_sso_page.cols.name')"><el-input v-model="jitForm.name" /></el-form-item>
                <el-form-item :label="t('enterprise_sso_page.default_role')"><el-input v-model="jitForm.default_role" placeholder="user" /></el-form-item>
                <el-form-item :label="t('enterprise_sso_page.auto_create')"><el-switch v-model="jitForm.auto_create_users" /></el-form-item>
                <el-form-item :label="t('enterprise_sso_page.sync_attrs')"><el-switch v-model="jitForm.auto_update_attributes" /></el-form-item>
                <el-form-item :label="t('enterprise_sso_page.domain_filter')"><el-input v-model="jitForm.email_domain_filter" placeholder="example.com" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showJitDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleCreateJitRule">{{ t('actions.create') }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus, Delete, ArrowRight } from '@element-plus/icons-vue';
import ssoApi from '@/api/sso';
import {
    getEnterpriseSsoStats, getIdps, createIdp, deleteIdp,
    getSpMetadata, getDomains, createDomain, deleteDomain,
    getGroupMappings, createGroupMapping, getJitRules, createJitRule, healthCheck,
} from '@/api/enterpriseSso';

const { t, locale } = useI18n();
const activeTab = ref('providers');

// ===== SSO 提供者 state =====
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
        idp_entity_id: '', idp_login_url: '', idp_x509_certificate: '',
        client_id: '', client_secret: '',
        authorization_url: '', token_url: '', userinfo_url: '', jwks_url: '',
        scopes: '',
    },
    attribute_mapping: [],
});

const attributeMappings = reactive([]);

const protocolTypeOptions = computed(() => [
    { value: 'saml2', label: t('sso_page.protocol.saml2') },
    { value: 'oidc', label: t('sso_page.protocol.oidc') },
    { value: 'oauth2', label: t('sso_page.protocol.oauth2') },
]);

const ssoFieldOptions = computed(() => [
    { value: 'email', label: t('sso_page.sso_fields.email') },
    { value: 'name', label: t('sso_page.sso_fields.name') },
    { value: 'displayName', label: t('sso_page.sso_fields.displayName') },
    { value: 'givenName', label: t('sso_page.sso_fields.givenName') },
    { value: 'sn', label: t('sso_page.sso_fields.sn') },
    { value: 'upn', label: t('sso_page.sso_fields.upn') },
    { value: 'tenant_id', label: t('sso_page.sso_fields.tenant_id') },
    { value: 'role', label: t('sso_page.sso_fields.role') },
    { value: 'department', label: t('sso_page.sso_fields.department') },
]);

const configRules = computed(() => ({
    name: [{ required: true, message: t('sso_page.rules.name_required'), trigger: 'blur' }],
    provider_type: [{ required: true, message: t('sso_page.rules.protocol_required'), trigger: 'change' }],
    'config.idp_entity_id': [{ required: true, message: t('sso_page.rules.idp_entity_id_required'), trigger: 'blur' }],
    'config.idp_login_url': [{ required: true, message: t('sso_page.rules.idp_login_url_required'), trigger: 'blur' }],
    'config.client_id': [{ required: true, message: t('sso_page.rules.client_id_required'), trigger: 'blur' }],
    'config.authorization_url': [{ required: true, message: t('sso_page.rules.authorization_url_required'), trigger: 'blur' }],
    'config.token_url': [{ required: true, message: t('sso_page.rules.token_url_required'), trigger: 'blur' }],
}));

// ===== 企业 SSO state =====
const enterpriseLoading = ref(false);
const submitting = ref(false);
const idps = ref([]);
const domains = ref([]);
const mappings = ref([]);
const jitRules = ref([]);
const currentIdp = ref(null);
const stats = reactive({ total_idps: 0, active_idps: 0, total_domains: 0, total_mappings: 0, total_jit_rules: 0 });
const showCreateDialog = ref(false);
const showDetailDrawer = ref(false);
const showJitDialog = ref(false);
const newDomain = ref('');
const newMapping = reactive({ group: '', role: '' });
const cf = reactive({ name: '', provider_type: 'generic_saml', idp_metadata_xml: '', name_id_format: 'email' });
const cfRules = { name: [{ required: true }], provider_type: [{ required: true }] };
const jitForm = reactive({ name: '', default_role: 'user', auto_create_users: true, auto_update_attributes: true, email_domain_filter: '' });

// ===== Shared helpers =====
function providerTypeTag(type) {
    const map = { saml2: 'warning', oidc: 'primary', oauth2: 'success' };
    return map[type] || 'info';
}

function providerTypeLabel(type) {
    const map = {
        saml2: t('sso_page.protocol.saml2'),
        oidc: t('sso_page.protocol.oidc'),
        oauth2: t('sso_page.protocol.oauth2'),
    };
    return map[type] || type;
}

function providerColor(type) {
    const map = { saml2: '#e6a23c', oidc: '#0f172a', oauth2: '#67c23a' };
    return map[type] || '#909399';
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString(locale.value === 'en' ? 'en-US' : 'zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

// ===== SSO 提供者 methods =====
function onProviderTypeChange() {}
function addMapping() { attributeMappings.push({ sso_field: '', local_field: '' }); }
function removeMapping(idx) { attributeMappings.splice(idx, 1); }

async function loadProviders() {
    loadingProviders.value = true;
    try {
        const { data: res } = await ssoApi.providers();
        providers.value = res.data || [];
    } catch { providers.value = []; }
    finally { loadingProviders.value = false; }
}

async function loadConnections() {
    loadingConnections.value = true;
    try {
        const { data: res } = await ssoApi.connections();
        connections.value = res.data || [];
    } catch { connections.value = []; }
    finally { loadingConnections.value = false; }
}

async function handleToggle(provider, val) {
    togglingId.value = provider.id;
    try {
        await ssoApi.toggle(provider.id, val);
        provider.is_active = val;
        ElMessage.success(val ? t('sso_page.enabled_ok') : t('sso_page.disabled_ok'));
    } catch {
        ElMessage.error(t('sso_page.action_fail'));
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
        idp_entity_id: '', idp_login_url: '', idp_x509_certificate: '',
        client_id: '', client_secret: '',
        authorization_url: '', token_url: '', userinfo_url: '', jwks_url: '',
        scopes: '',
    };
    attributeMappings.length = 0;
    editingProvider.value = null;
}

function openEditDialog(provider) {
    editingProvider.value = provider;
    configForm.name = provider.name;
    configForm.provider_type = provider.provider_type;
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
            config: Object.fromEntries(Object.entries(configForm.config).filter(([_, v]) => v !== '')),
            sp_entity_id: configForm.sp_entity_id || null,
            sp_acs_url: configForm.sp_acs_url || null,
            attribute_mapping: attributeMappings.length > 0
                ? Object.fromEntries(attributeMappings.filter(a => a.sso_field && a.local_field).map(a => [a.sso_field, a.local_field]))
                : null,
        };
        await ssoApi.configure(payload);
        ElMessage.success(editingProvider.value ? t('sso_page.update_ok') : t('sso_page.create_ok'));
        showConfigureDialog.value = false;
        resetForm();
        loadProviders();
    } catch (err) {
        ElMessage.error(err?.response?.data?.message || t('sso_page.save_fail'));
    } finally { saving.value = false; }
}

async function handleDelete(provider) {
    try {
        await ElMessageBox.confirm(
            t('sso_page.delete_confirm', { name: provider.name }),
            t('sso_page.delete_title'),
            { confirmButtonText: t('actions.delete'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await ssoApi.deleteProvider(provider.id);
        ElMessage.success(t('sso_page.delete_ok'));
        loadProviders();
    } catch { /* cancelled */ }
}

async function handleDisconnect(connection) {
    try {
        await ElMessageBox.confirm(
            t('sso_page.disconnect_confirm', { name: connection.provider_name }),
            t('sso_page.disconnect_title'),
            { confirmButtonText: t('sso_page.disconnect_confirm_btn'), cancelButtonText: t('actions.cancel'), type: 'warning' }
        );
        await ssoApi.disconnect(connection.id);
        ElMessage.success(t('sso_page.disconnect_ok'));
        loadConnections();
    } catch { /* cancelled */ }
}

// ===== 企业 SSO methods =====
async function loadStats() {
    try { const { data } = await getEnterpriseSsoStats(); Object.assign(stats, data.data); } catch {}
}

async function loadIdps() {
    enterpriseLoading.value = true;
    try { const { data } = await getIdps(); idps.value = data.data || []; } catch { idps.value = []; }
    finally { enterpriseLoading.value = false; }
}

async function handleCreateEnterpriseIdp() {
    submitting.value = true;
    try {
        await createIdp({ ...cf });
        ElMessage.success(t('enterprise_sso_page.messages.created'));
        showCreateDialog.value = false;
        cf.name = ''; cf.idp_metadata_xml = '';
        await loadIdps(); await loadStats();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'));
    } finally { submitting.value = false; }
}

function confirmDelete(row) {
    ElMessageBox.confirm(t('enterprise_sso_page.messages.delete_confirm', { name: row.name }), t('actions.confirm'), { type: 'warning' })
        .then(async () => { await deleteIdp(row.id); ElMessage.success(t('enterprise_sso_page.messages.deleted')); await loadIdps(); await loadStats(); })
        .catch(() => {});
}

async function viewDetails(row) {
    currentIdp.value = row;
    showDetailDrawer.value = true;
    try { const { data } = await getDomains(row.id); domains.value = data.data; } catch { domains.value = []; }
    try { const { data } = await getGroupMappings(row.id); mappings.value = data.data; } catch { mappings.value = []; }
    try { const { data } = await getJitRules(row.id); jitRules.value = data.data; } catch { jitRules.value = []; }
}

async function downloadMetadata(row) {
    try {
        const resp = await getSpMetadata(row.id);
        const blob = new Blob([resp.data], { type: 'application/xml' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = `sp-metadata-${row.name}.xml`; a.click();
        URL.revokeObjectURL(url);
        ElMessage.success(t('enterprise_sso_page.messages.metadata_downloaded'));
    } catch { ElMessage.error(t('enterprise_sso_page.messages.download_failed')); }
}

async function runHealthCheck(row) {
    try {
        const { data } = await healthCheck(row.id);
        const r = data.data?.result || data.data;
        ElMessage[r.is_healthy ? 'success' : 'warning'](r.message);
    } catch { ElMessage.error(t('enterprise_sso_page.messages.health_failed')); }
}

async function addDomain() {
    if (!newDomain.value || !currentIdp.value) return;
    try { await createDomain(currentIdp.value.id, { domain: newDomain.value }); newDomain.value = ''; await refreshDomains(); ElMessage.success(t('enterprise_sso_page.messages.domain_added')); } catch {}
}

async function deleteDomainRow(row) {
    try { await deleteDomain(row.id); await refreshDomains(); ElMessage.success(t('enterprise_sso_page.messages.deleted')); } catch {}
}

async function refreshDomains() {
    if (!currentIdp.value) return;
    const { data } = await getDomains(currentIdp.value.id);
    domains.value = data.data;
}

async function addMappingEnterprise() {
    if (!newMapping.group || !newMapping.role || !currentIdp.value) return;
    try {
        await createGroupMapping(currentIdp.value.id, { idp_group_name: newMapping.group, local_role: newMapping.role });
        newMapping.group = ''; newMapping.role = '';
        const { data } = await getGroupMappings(currentIdp.value.id);
        mappings.value = data.data;
        ElMessage.success(t('enterprise_sso_page.messages.mapping_added'));
    } catch {}
}

async function handleCreateJitRule() {
    if (!currentIdp.value) return;
    try {
        await createJitRule(currentIdp.value.id, { ...jitForm });
        showJitDialog.value = false;
        const { data } = await getJitRules(currentIdp.value.id);
        jitRules.value = data.data;
        ElMessage.success(t('enterprise_sso_page.messages.jit_created'));
    } catch {}
}

// 切换到企业 SSO tab 时延迟加载
watch(activeTab, (tab) => {
    if (tab === 'enterprise' && idps.value.length === 0) {
        loadStats();
        loadIdps();
    }
});

onMounted(() => {
    loadProviders();
    loadConnections();
});
</script>

<style scoped>
.sso-unified-page { padding: 20px; }

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
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }

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
.provider-body { flex: 1; min-width: 0; }
.provider-name {
    font-size: 15px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    display: flex;
    align-items: center;
}
.provider-meta { margin-top: 4px; display: flex; gap: 16px; }
.meta-item { font-size: 12px; color: var(--el-text-color-secondary); }
.provider-actions { display: flex; align-items: center; flex-shrink: 0; }

.attr-mapping-section { display: flex; flex-direction: column; gap: 8px; }
.attr-row { display: flex; align-items: center; gap: 8px; }
.mx-2 { margin: 0 8px; }
.form-tip { font-size: 12px; color: var(--el-text-color-placeholder); margin-top: 4px; }

.stat-item { text-align: center; padding: 8px 0; }
.stat-value { font-size: 28px; font-weight: 700; color: var(--el-color-primary); }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; }

.flex { display: flex; }
.items-center { align-items: center; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 8px; }

.jit-card {
    padding: 10px;
    margin-bottom: 8px;
    background: var(--el-fill-color-light);
    border-radius: 6px;
    font-size: 13px;
}
.jit-header { font-weight: 600; margin-bottom: 4px; }

:deep(.el-card__body) { padding: 16px; }
:deep(.el-divider__text) { font-size: 13px; }
</style>
