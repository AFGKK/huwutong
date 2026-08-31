<template>
    <div class="domain-center-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('domain_center.title') }}</h2>
                <span class="header-subtitle">{{ t('domain_center.subtitle') }}</span>
            </div>
        </div>

        <el-tabs v-model="mainTab" type="border-card" @tab-change="onMainTabChange">
            <!-- ===== Tab 1: 域名总览 ===== -->
            <el-tab-pane :label="t('domain_center.tab_overview')" name="overview">
                <!-- 平台配置 -->
                <el-card shadow="never" class="mb-4">
                    <template #header><span>{{ t('domain_overview_page.platform_title') }}</span></template>
                    <el-form :inline="true" :model="platformForm" size="small" label-width="140px">
                        <el-form-item :label="t('domain_overview_page.canonical')"><el-input v-model="platformForm.canonical_domain" style="width:320px" /></el-form-item>
                        <el-form-item :label="t('domain_overview_page.cdn_url')"><el-input v-model="platformForm.cdn_url" style="width:320px" /></el-form-item>
                        <el-form-item :label="t('domain_overview_page.cdn_enabled')"><el-switch v-model="platformForm.cdn_enabled" /></el-form-item>
                        <el-form-item><el-button type="primary" @click="savePlatform">{{ t('actions.save') }}</el-button></el-form-item>
                    </el-form>
                </el-card>

                <!-- 统计卡片 -->
                <el-row :gutter="16" class="mb-4">
                    <el-col :span="4"><el-card shadow="hover" @click="switchToTab('list')" style="cursor:pointer">
                        <div class="stat-value">{{ stats.custom_domains?.total ?? 0 }}</div>
                        <div class="stat-label">{{ t('domain_overview_page.stats.custom') }} →</div>
                    </el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" @click="switchToTab('list')" style="cursor:pointer">
                        <div class="stat-value text-success">{{ stats.custom_domains?.active ?? 0 }}</div>
                        <div class="stat-label">{{ t('domain_overview_page.stats.active') }} →</div>
                    </el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" @click="switchToTab('list')" style="cursor:pointer">
                        <div class="stat-value text-warning">{{ stats.custom_domains?.pending ?? 0 }}</div>
                        <div class="stat-label">{{ t('domain_overview_page.stats.pending') }} →</div>
                    </el-card></el-col>
                    <el-col :span="4"><el-card shadow="hover" @click="$router.push('/domain-whitelist')" style="cursor:pointer">
                        <div class="stat-value text-danger">{{ stats.custom_domains?.failed ?? 0 }}</div>
                        <div class="stat-label">{{ t('domain_overview_page.stats.failed') }} →</div>
                    </el-card></el-col>
                    <el-col :span="4"><el-card shadow="never">
                        <div class="stat-value text-primary">{{ stats.tenants?.with_domain ?? 0 }}/{{ stats.tenants?.total ?? 0 }}</div>
                        <div class="stat-label">{{ t('domain_overview_page.stats.tenants') }}</div>
                    </el-card></el-col>
                    <el-col :span="4"><el-card shadow="never">
                        <div class="stat-value">{{ stats.ssl?.issued ?? 0 }}</div>
                        <div class="stat-label">SSL<el-tag v-if="stats.ssl?.expiring_soon>0" type="warning" size="small" style="margin-left:4px">{{ t('domain_overview_page.expiring_n', { n: stats.ssl.expiring_soon }) }}</el-tag></div>
                    </el-card></el-col>
                </el-row>

                <!-- 域名列表表格式总览 -->
                <el-card shadow="never" class="mb-4">
                    <template #header>
                        <span>{{ t('domain_overview_page.list_title') }}</span>
                        <div style="float:right;display:flex;gap:8px">
                            <el-button v-if="selectedIds.length>0" type="warning" size="small" @click="batchRenewSsl">{{ t('domain_overview_page.batch_renew', { n: selectedIds.length }) }}</el-button>
                            <el-select v-model="filterStatus" clearable :placeholder="t('domain_overview_page.filter')" size="small" style="width:120px" @change="page=1;loadDomainList()">
                                <el-option :label="t('domain_overview_page.all')" value="" />
                                <el-option :label="t('domain_overview_page.statuses.active')" value="active" />
                                <el-option :label="t('domain_overview_page.statuses.verified')" value="verified" />
                                <el-option :label="t('domain_overview_page.statuses.pending')" value="pending" />
                                <el-option :label="t('domain_overview_page.statuses.failed')" value="failed" />
                            </el-select>
                            <el-input v-model="searchKeyword" :placeholder="t('domain_overview_page.search_ph')" size="small" style="width:180px" clearable @keyup.enter="page=1;loadDomainList()" />
                            <el-button size="small" @click="page=1;loadDomainList()">{{ t('actions.search') }}</el-button>
                        </div>
                    </template>
                    <el-table :data="domainList" v-loading="loadingList" stripe @selection-change="selectedIds = $event.map(r=>r.id)">
                        <el-table-column type="selection" width="40" />
                        <el-table-column prop="domain" :label="t('domain_overview_page.cols.domain')" min-width="180">
                            <template #default="{row}"><a :href="`//${row.domain}`" target="_blank" class="domain-link">{{row.domain}}</a></template>
                        </el-table-column>
                        <el-table-column prop="tenant.name" :label="t('domain_overview_page.cols.tenant')" width="110" />
                        <el-table-column label="DNS" width="65" align="center">
                            <template #default="{row}"><el-tag :type="row.dns_resolved?'success':'danger'" size="small">{{row.dns_resolved?'✓':'✗'}}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="SSL" width="100" align="center">
                            <template #default="{row}">
                                <el-tag v-if="row.ssl_status==='issued'&&row.ssl_days_left>0" :type="row.ssl_days_left<30?'warning':'success'" size="small">{{ t('domain_overview_page.days_n', { n: row.ssl_days_left }) }}</el-tag>
                                <el-tag v-else-if="row.ssl_status==='renewing'" type="warning" size="small">{{ t('domain_overview_page.renewing') }}</el-tag>
                                <span v-else class="text-muted">-</span>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('domain_overview_page.cols.health')" width="80" align="center">
                            <template #default="{row}">
                                <el-tag v-if="row.health==='healthy'" type="success" size="small">{{ t('domain_overview_page.health.healthy') }}</el-tag>
                                <el-tag v-else-if="row.health==='ssl_expiring_soon'" type="warning" size="small">{{ t('domain_overview_page.health.ssl_expiring') }}</el-tag>
                                <el-tag v-else-if="row.health==='dns_error'" type="danger" size="small">{{ t('domain_overview_page.health.dns_error') }}</el-tag>
                                <el-tag v-else type="info" size="small">{{row.health}}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('domain_overview_page.cols.actions')" width="120" fixed="right">
                            <template #default="{row}">
                                <el-button v-if="row.ssl_status==='issued'" text size="small" @click="renewSsl(row)">{{ t('domain_overview_page.renew_ssl') }}</el-button>
                                <el-button text size="small" @click="switchToTab('list')">{{ t('actions.view_details') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="mt-3 flex justify-end">
                        <el-pagination v-model:current-page="page" :page-size="20" :total="totalDomains" layout="prev,pager,next" small background @current-change="loadDomainList" />
                    </div>
                </el-card>

                <!-- 待办 & 最近 -->
                <el-row :gutter="16">
                    <el-col :span="14">
                        <el-card shadow="never">
                            <template #header><span>{{ t('domain_overview_page.todos') }}</span></template>
                            <div v-if="todoList.length===0" class="text-muted" style="padding:20px;text-align:center">{{ t('domain_overview_page.all_ok') }}</div>
                            <div v-for="item in todoList" :key="item.type" class="todo-item" style="cursor:pointer" @click="item.route ? (item.route === '/domains' ? switchToTab('list') : $router.push(item.route)) : null">
                                <el-tag :type="item.severity" size="small" style="margin-right:8px">{{item.label}}</el-tag>
                                <span>{{item.message}}</span>
                                <el-tag type="info" size="small" style="margin-left:auto">{{item.count}}</el-tag>
                                <el-icon v-if="item.route" style="margin-left:4px"><ArrowRight /></el-icon>
                            </div>
                        </el-card>
                    </el-col>
                    <el-col :span="10">
                        <el-card shadow="never">
                            <template #header><span>{{ t('domain_overview_page.recent') }}</span></template>
                            <el-table :data="stats.custom_domains?.recent" stripe :empty-text="t('messages.no_data')" size="small">
                                <el-table-column prop="domain" :label="t('domain_overview_page.cols.domain')" min-width="140" />
                                <el-table-column prop="tenant.name" :label="t('domain_overview_page.cols.tenant')" width="100" />
                                <el-table-column prop="created_at" :label="t('domain_overview_page.cols.time')" width="130" />
                            </el-table>
                        </el-card>
                    </el-col>
                </el-row>
            </el-tab-pane>

            <!-- ===== Tab 2: 域名列表 ===== -->
            <el-tab-pane :label="t('domain_center.tab_list')" name="list">
                <el-card shadow="never">
                    <template #header>
                        <div class="flex-between">
                            <div style="display:flex;align-items:center;gap:8px">
                                <span>{{ t('domains_page.title') }}</span>
                                <el-input v-model="domainsSearchKeyword" :placeholder="t('domains_page.search_ph')" size="small" style="width:180px" clearable @keyup.enter="fetchDomains" />
                                <el-select v-model="domainFilterStatus" clearable :placeholder="t('domains_page.cols.status')" size="small" style="width:110px" @change="fetchDomains">
                                    <el-option :label="t('domains_page.all')" value="" />
                                    <el-option :label="t('domains_page.statuses.active')" value="active" />
                                    <el-option :label="t('domains_page.statuses.pending')" value="pending" />
                                    <el-option :label="t('domains_page.statuses.failed')" value="failed" />
                                </el-select>
                                <el-button size="small" @click="fetchDomains">{{ t('actions.search') }}</el-button>
                            </div>
                            <el-button type="primary" size="small" @click="showCreateDialog = true">
                                <el-icon><Plus /></el-icon>{{ t('domains_page.bind') }}
                            </el-button>
                        </div>
                    </template>

                    <el-empty v-if="!domainsLoading && domains.length === 0" :description="t('domains_page.empty')" />

                    <div v-else class="domain-list">
                        <div v-for="domain in domains" :key="domain.id" class="domain-card">
                            <div class="domain-header">
                                <div class="domain-name">
                                    <el-icon :size="20" :color="domain.is_active ? '#67c23a' : '#e6a23c'"><Link /></el-icon>
                                    <span class="ml-2 font-mono">{{ domain.domain }}</span>
                                </div>
                                <el-tag :type="domainTagType(domain.status)" size="small">{{ domainStatusLabel(domain.status) }}</el-tag>
                            </div>
                            <div class="domain-body">
                                <div class="domain-info">
                                    <div class="info-row">
                                        <span class="label">{{ t('domains_page.cname_target') }}</span>
                                        <code class="value">{{ domain.dns?.expected_cname || 'cname.huwutong.com.' }}</code>
                                    </div>
                                    <div class="info-row" v-if="domain.ssl">
                                        <span class="label">{{ t('domains_page.ssl_cert') }}</span>
                                        <span class="value" :class="sslStatusClass(domain.ssl)">{{ sslStatusLabel(domain.ssl) }}</span>
                                        <span v-if="domain.ssl.expires_at" class="text-gray-400 text-sm ml-2">
                                            {{ t('domains_page.expires', { date: formatDate(domain.ssl.expires_at) }) }}
                                            <span v-if="domain.ssl.days_remaining !== null && domain.ssl.days_remaining < 30" class="text-red-500">({{ t('domains_page.days_left', { n: domain.ssl.days_remaining }) }})</span>
                                        </span>
                                    </div>
                                    <div class="info-row" v-if="domain.route">
                                        <span class="label">{{ t('domains_page.target_url') }}</span>
                                        <span class="value text-sm">{{ domain.route.target_url }}</span>
                                    </div>
                                    <div class="info-row" v-if="domain.error_message">
                                        <span class="label">{{ t('domains_page.error') }}</span>
                                        <span class="value text-red-500 text-sm">{{ domain.error_message }}</span>
                                    </div>
                                </div>
                                <div class="domain-actions">
                                    <template v-if="!domain.verified">
                                        <el-button type="primary" size="small" :loading="loadingVerify === domain.id" @click="handleVerify(domain.id)">{{ t('domains_page.verify') }}</el-button>
                                    </template>
                                    <template v-else>
                                        <el-button size="small" @click="openSslUploadDialog(domain.id)" :type="domain.ssl?.is_valid ? 'default' : 'warning'">{{ t('domains_page.ssl_upload.submit') }}</el-button>
                                        <el-button size="small" :loading="loadingSsl === domain.id" @click="handleIssueSsl(domain.id)" :type="domain.ssl?.is_valid ? 'default' : 'primary'">{{ domain.ssl?.is_valid ? t('domains_page.renew_ssl') : t('domains_page.issue_ssl') }}</el-button>
                                    </template>
                                    <el-button size="small" @click="handleDnsInfo(domain.id)">{{ t('domains_page.dns_info') }}</el-button>
                                    <el-popconfirm :title="t('domains_page.delete_confirm')" @confirm="handleDelete(domain.id)">
                                        <template #reference><el-button size="small" type="danger" plain>{{ t('actions.delete') }}</el-button></template>
                                    </el-popconfirm>
                                </div>
                            </div>
                        </div>
                    </div>
                </el-card>

                <!-- 绑定域名对话框 -->
                <el-dialog v-model="showCreateDialog" :title="t('domains_page.bind_title')" width="520px">
                    <el-form ref="formRef" :model="form" :rules="domainFormRules" label-position="top" size="large">
                        <el-form-item :label="t('domains_page.form.domain')" prop="domain">
                            <el-input v-model="form.domain" placeholder="license.example.com">
                                <template #prefix><el-icon><Link /></el-icon></template>
                            </el-input>
                            <div class="text-gray-400 text-sm mt-1">{{ t('domains_page.form.domain_tip') }}</div>
                        </el-form-item>
                        <el-form-item :label="t('domains_page.form.target_url')" prop="target_url">
                            <el-input v-model="form.target_url" placeholder="https://api.huwutong.com" />
                            <div class="text-gray-400 text-sm mt-1">{{ t('domains_page.form.target_tip') }}</div>
                        </el-form-item>
                    </el-form>
                    <div class="bg-blue-50 p-3 rounded text-sm text-blue-700 mb-4" v-if="showCreateDialog">
                        <el-icon><InfoFilled /></el-icon> {{ t('domains_page.cname_hint') }}
                    </div>
                    <template #footer>
                        <el-button @click="showCreateDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="creating" @click="handleCreate">{{ t('domains_page.bind') }}</el-button>
                    </template>
                </el-dialog>

                <!-- DNS 信息对话框 -->
                <el-dialog v-model="showDnsDialog" :title="t('domains_page.dns_title')" width="520px">
                    <div v-if="dnsInfoData" class="dns-info">
                        <div class="info-row"><span class="label">{{ t('domains_page.form.domain') }}:</span><code>{{ dnsInfoData.domain }}</code></div>
                        <div class="info-row"><span class="label">{{ t('domains_page.expected_cname') }}</span><code>{{ dnsInfoData.expected_cname }}</code></div>
                        <div class="info-row" v-if="dnsInfoData.dns.a_records?.length"><span class="label">{{ t('domains_page.a_records') }}</span><span class="value">{{ dnsInfoData.dns.a_records.join(', ') }}</span></div>
                        <div class="info-row" v-if="dnsInfoData.dns.cname_records?.length"><span class="label">{{ t('domains_page.cname_records') }}</span><span class="value font-mono text-green-600">{{ dnsInfoData.dns.cname_records.join(', ') }}</span></div>
                        <div class="info-row" v-if="dnsInfoData.dns.resolved_ip"><span class="label">{{ t('domains_page.resolved_ip') }}</span><code>{{ dnsInfoData.dns.resolved_ip }}</code></div>
                        <div class="info-row" v-if="!dnsInfoData.dns.dns_ok"><span class="label text-red-500">{{ t('domains_page.dns_error') }}</span></div>
                    </div>
                </el-dialog>

                <!-- SSL 证书上传对话框 -->
                <el-dialog v-model="showSslUploadDialog" :title="t('domains_page.ssl_upload.title')" width="600px">
                    <el-form ref="sslFormRef" :model="sslForm" :rules="sslFormRules" label-position="top" size="large">
                        <el-form-item :label="t('domains_page.ssl_upload.certificate')" prop="certificate">
                            <el-input v-model="sslForm.certificate" type="textarea" :rows="6" :placeholder="t('domains_page.ssl_upload.certificate_ph')" />
                            <div class="text-gray-400 text-sm mt-1">{{ t('domains_page.ssl_upload.certificate_tip') }}</div>
                        </el-form-item>
                        <el-form-item :label="t('domains_page.ssl_upload.private_key')" prop="private_key">
                            <el-input v-model="sslForm.private_key" type="textarea" :rows="6" :placeholder="t('domains_page.ssl_upload.private_key_ph')" />
                            <div class="text-gray-400 text-sm mt-1">{{ t('domains_page.ssl_upload.private_key_tip') }}</div>
                        </el-form-item>
                        <el-form-item :label="t('domains_page.ssl_upload.certificate_chain')" prop="certificate_chain">
                            <el-input v-model="sslForm.certificate_chain" type="textarea" :rows="4" :placeholder="t('domains_page.ssl_upload.certificate_chain_ph')" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="showSslUploadDialog = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="uploadingSsl" @click="handleUploadSsl">{{ t('domains_page.ssl_upload.submit') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { ArrowRight, Plus, Link, InfoFilled } from '@element-plus/icons-vue';
import domainApi from '@/api/domain';
import { getDomainOverview, getDomainList, updatePlatform, renewDomainSsl, batchRenewDomainSsl } from '@/api/domainOverview';

const { t, locale } = useI18n();
const mainTab = ref('overview');

function switchToTab(tab) { mainTab.value = tab; }

// ================================================================
// Tab 1: 域名总览 (domain-overview)
// ================================================================
const platformForm = reactive({ canonical_domain: '', cdn_url: '', cdn_enabled: false });
const stats = ref({});
const domainList = ref([]);
const page = ref(1);
const totalDomains = ref(0);
const filterStatus = ref('');
const searchKeyword = ref('');
const selectedIds = ref([]);
const loadingList = ref(false);

const todoList = computed(() => {
    const s = stats.value; const items = [];
    if (s.custom_domains?.pending > 0) items.push({ type:'pending', label:t('domain_overview_page.todo.pending_label'), message:t('domain_overview_page.todo.pending_msg'), count:s.custom_domains.pending, severity:'warning', route:'/domains' });
    if (s.custom_domains?.failed > 0) items.push({ type:'failed', label:t('domain_overview_page.todo.failed_label'), message:t('domain_overview_page.todo.failed_msg'), count:s.custom_domains.failed, severity:'danger', route:'/domains' });
    if (s.custom_domains?.expired > 0) items.push({ type:'expired', label:t('domain_overview_page.todo.expired_label'), message:t('domain_overview_page.todo.expired_msg'), count:s.custom_domains.expired, severity:'danger', route:'/domains' });
    if (s.ssl?.expiring_soon > 0) items.push({ type:'ssl', label:'SSL', message:t('domain_overview_page.todo.ssl_expiring_msg'), count:s.ssl.expiring_soon, severity:'warning' });
    if (s.ssl?.failed > 0) items.push({ type:'ssl-fail', label:'SSL', message:t('domain_overview_page.todo.ssl_failed_msg'), count:s.ssl.failed, severity:'danger' });
    if (s.tenants?.without_domain > 0) items.push({ type:'tenant', label:t('domain_overview_page.todo.tenant_label'), message:t('domain_overview_page.todo.tenant_msg'), count:s.tenants.without_domain, severity:'info' });
    return items;
});

async function loadOverview() {
    try {
        const res = await getDomainOverview();
        stats.value = res.data ?? {};
        const p = res.data?.platform ?? {};
        platformForm.canonical_domain = p.canonical_domain || '';
        platformForm.cdn_url = p.cdn_url || '';
        platformForm.cdn_enabled = !!p.cdn_enabled;
    } catch {}
}
async function loadDomainList() {
    loadingList.value = true; selectedIds.value = [];
    try {
        const params = { page: page.value, per_page: 20 };
        if (filterStatus.value) params.status = filterStatus.value;
        if (searchKeyword.value) params.search = searchKeyword.value;
        const res = await getDomainList(params);
        domainList.value = res.data?.data ?? [];
        totalDomains.value = res.data?.total ?? 0;
    } catch { domainList.value = []; totalDomains.value = 0; }
    finally { loadingList.value = false; }
}
async function savePlatform() {
    try { await updatePlatform(platformForm); ElMessage.success(t('domain_overview_page.messages.saved')); } catch {}
}
async function renewSsl(row) {
    try { await renewDomainSsl(row.id); ElMessage.success(t('domain_overview_page.messages.ssl_submitted')); } catch {}
}
async function batchRenewSsl() {
    try { const res = await batchRenewDomainSsl(selectedIds.value); ElMessage.success(res.message || t('domain_overview_page.messages.batch_submitted')); loadDomainList(); } catch {}
}

// ================================================================
// Tab 2: 域名列表 (domains)
// ================================================================
const domains = ref([]);
const domainsLoading = ref(true);
const loadingVerify = ref(null);
const loadingSsl = ref(null);
const creating = ref(false);
const uploadingSsl = ref(false);
const showCreateDialog = ref(false);
const showDnsDialog = ref(false);
const showSslUploadDialog = ref(false);
const sslUploadDomainId = ref(null);
const dnsInfoData = ref(null);
const sslFormRef = ref(null);
const sslForm = reactive({ certificate: '', private_key: '', certificate_chain: '' });
const domainsSearchKeyword = ref('');
const domainFilterStatus = ref('');
const formRef = ref(null);
const form = reactive({ domain: '', target_url: '' });

const domainFormRules = computed(() => ({
    domain: [
        { required: true, message: t('domains_page.validation.domain_required'), trigger: 'blur' },
        { pattern: /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i, message: t('domains_page.validation.domain_format'), trigger: 'blur' },
    ],
    target_url: [{ type: 'url', message: t('domains_page.validation.url'), trigger: 'blur', required: false }],
}));

const sslFormRules = computed(() => ({
    certificate: [
        { required: true, message: t('domains_page.validation.cert_required'), trigger: 'blur' },
        { min: 64, message: t('domains_page.validation.cert_minlen'), trigger: 'blur' },
    ],
    private_key: [
        { required: true, message: t('domains_page.validation.key_required'), trigger: 'blur' },
        { min: 64, message: t('domains_page.validation.key_minlen'), trigger: 'blur' },
    ],
}));

function domainTagType(status) { const map = { active:'success', pending:'warning', failed:'danger', verifying:'warning', expired:'info' }; return map[status] || 'info'; }
function domainStatusLabel(status) { const key = { active:'active', pending:'pending', verifying:'verifying', failed:'failed', expired:'expired' }[status]; return key ? t(`domains_page.statuses.${key}`) : status; }
function sslStatusLabel(ssl) { if (!ssl) return ''; if (ssl.is_valid) return t('domains_page.ssl.valid'); if (ssl.status === 'pending') return t('domains_page.ssl.pending'); if (ssl.status === 'failed') return t('domains_page.ssl.failed'); return t('domains_page.ssl.unknown'); }
function sslStatusClass(ssl) { if (!ssl) return ''; if (ssl.is_valid) return 'text-green-600'; if (ssl.status === 'failed') return 'text-red-500'; return 'text-yellow-600'; }
function formatDate(date) { if (!date) return ''; const loc = locale.value?.startsWith('zh') ? 'zh-CN' : 'en-US'; return new Date(date).toLocaleDateString(loc); }

async function fetchDomains() {
    domainsLoading.value = true;
    try {
        const params = {};
        if (domainsSearchKeyword.value) params.search = domainsSearchKeyword.value;
        if (domainFilterStatus.value) params.status = domainFilterStatus.value;
        const res = await domainApi.list(params);
        domains.value = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
    } catch {}
    finally { domainsLoading.value = false; }
}
async function handleCreate() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;
    creating.value = true;
    try {
        const { data: res } = await domainApi.create({ domain: form.domain, target_url: form.target_url || undefined });
        ElMessage.success(res.message || t('domains_page.messages.bound'));
        showCreateDialog.value = false; form.domain = ''; form.target_url = ''; await fetchDomains();
    } catch {}
    finally { creating.value = false; }
}
async function handleVerify(domainId) {
    loadingVerify.value = domainId;
    try { const { data: res } = await domainApi.verify(domainId); ElMessage.success(res.message || t('domains_page.messages.verified')); await fetchDomains(); }
    catch { await fetchDomains(); }
    finally { loadingVerify.value = null; }
}
async function handleIssueSsl(domainId) {
    loadingSsl.value = domainId;
    try { const { data: res } = await domainApi.issueSsl(domainId); ElMessage.success(res.message || t('domains_page.messages.ssl_issued')); await fetchDomains(); }
    catch { await fetchDomains(); }
    finally { loadingSsl.value = null; }
}
async function handleDnsInfo(domainId) {
    try { const { data: res } = await domainApi.dnsInfo(domainId); dnsInfoData.value = res.data; showDnsDialog.value = true; }
    catch { ElMessage.error(t('domains_page.messages.dns_failed')); }
}
async function handleDelete(domainId) {
    try { await domainApi.destroy(domainId); ElMessage.success(t('domains_page.messages.deleted')); await fetchDomains(); } catch {}
}

function openSslUploadDialog(domainId) {
    sslUploadDomainId.value = domainId;
    sslForm.certificate = '';
    sslForm.private_key = '';
    sslForm.certificate_chain = '';
    showSslUploadDialog.value = true;
}

async function handleUploadSsl() {
    const valid = await sslFormRef.value?.validate().catch(() => false);
    if (!valid) return;
    uploadingSsl.value = true;
    try {
        const { data: res } = await domainApi.uploadSsl(sslUploadDomainId.value, {
            certificate: sslForm.certificate,
            private_key: sslForm.private_key,
            certificate_chain: sslForm.certificate_chain || undefined,
        });
        ElMessage.success(res.message || t('domains_page.ssl_upload.success'));
        showSslUploadDialog.value = false;
        await fetchDomains();
    } catch {}
    finally { uploadingSsl.value = false; }
}

// ===== Lazy loading =====
function onMainTabChange(tab) {
    if (tab === 'list' && domains.value.length === 0) fetchDomains();
}

onMounted(() => { loadOverview(); loadDomainList(); });
</script>

<style scoped>
.domain-center-page { padding: 20px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; }
.header-subtitle { font-size: 13px; color: var(--el-text-color-secondary); margin-left: 12px; }

/* --- Overview Tab --- */
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.stat-value { font-size: 26px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; display:flex; align-items:center; }
.text-success { color: #67c23a; } .text-warning { color: #e6a23c; } .text-danger { color: #f56c6c; } .text-primary { color: #0f172a; } .text-muted { color: #c0c4cc; }
.todo-item { display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f0f0f0; }
.todo-item:last-child { border-bottom: none; }
.todo-item:hover { background:#f5f7fa; }
.domain-link { color: #0f172a; text-decoration: none; }
.domain-link:hover { text-decoration: underline; }
.flex { display: flex; } .justify-end { justify-content: flex-end; }

/* --- List Tab --- */
.flex-between { display: flex; align-items: center; justify-content: space-between; }
.domain-list { display: flex; flex-direction: column; gap: 16px; }
.domain-card { border: 1px solid #e4e7ed; border-radius: 8px; padding: 16px; transition: all 0.2s; }
.domain-card:hover { border-color: #0f172a; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.1); }
.domain-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.domain-name { display: flex; align-items: center; font-size: 16px; font-weight: 600; }
.domain-body { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
.domain-info { flex: 1; }
.info-row { margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
.info-row .label { color: #909399; font-size: 13px; white-space: nowrap; }
.info-row .value { font-size: 13px; }
.info-row code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
.domain-actions { display: flex; gap: 8px; flex-shrink: 0; }
.font-mono { font-family: 'Courier New', monospace; }
.bg-blue-50 { background: #f1f5f9; }
.p-3 { padding: 12px; }
.rounded { border-radius: 6px; }
.text-gray-400 { color: #909399; }
.text-blue-700 { color: #1d4ed8; }
.text-red-500 { color: #f56c6c; }
.text-green-600 { color: #16a34a; }
.text-yellow-600 { color: #ca8a04; }
.text-sm { font-size: 13px; }
.ml-2 { margin-left: 8px; }
.mt-1 { margin-top: 4px; }
.dns-info .info-row { padding: 8px 0; border-bottom: 1px solid #f5f7fa; }
</style>
