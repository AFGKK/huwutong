<template>
    <div class="domain-page">
        <!-- 域名列表 -->
        <el-card shadow="never" class="mb-4">
            <template #header>
                <div class="flex-between">
                    <span>自定义域名</span>
                    <el-button type="primary" size="small" @click="showCreateDialog = true">
                        <el-icon><Plus /></el-icon>绑定域名
                    </el-button>
                </div>
            </template>

            <!-- 空状态 -->
            <el-empty v-if="!loading && domains.length === 0" description="暂无自定义域名" />

            <!-- 域名列表 -->
            <div v-else class="domain-list">
                <div v-for="domain in domains" :key="domain.id" class="domain-card">
                    <div class="domain-header">
                        <div class="domain-name">
                            <el-icon :size="20" :color="domain.is_active ? '#67c23a' : '#e6a23c'">
                                <Link />
                            </el-icon>
                            <span class="ml-2 font-mono">{{ domain.domain }}</span>
                        </div>
                        <el-tag :type="domainTagType(domain.status)" size="small">
                            {{ domainStatusLabel(domain.status) }}
                        </el-tag>
                    </div>

                    <div class="domain-body">
                        <div class="domain-info">
                            <div class="info-row">
                                <span class="label">CNAME 目标:</span>
                                <code class="value">{{ domain.dns?.expected_cname || 'cname.huwutong.com.' }}</code>
                            </div>
                            <div class="info-row" v-if="domain.ssl">
                                <span class="label">SSL 证书:</span>
                                <span class="value" :class="sslStatusClass(domain.ssl)">
                                    {{ domain.ssl.is_valid ? '有效' : domain.ssl.status === 'pending' ? '待签发' : domain.ssl.status === 'failed' ? '签发失败' : '未知' }}
                                </span>
                                <span v-if="domain.ssl.expires_at" class="text-gray-400 text-sm ml-2">
                                    到期 {{ formatDate(domain.ssl.expires_at) }}
                                    <span v-if="domain.ssl.days_remaining !== null && domain.ssl.days_remaining < 30" class="text-red-500">
                                        ({{ domain.ssl.days_remaining }}天)
                                    </span>
                                </span>
                            </div>
                            <div class="info-row" v-if="domain.route">
                                <span class="label">目标地址:</span>
                                <span class="value text-sm">{{ domain.route.target_url }}</span>
                            </div>
                            <div class="info-row" v-if="domain.error_message">
                                <span class="label">错误信息:</span>
                                <span class="value text-red-500 text-sm">{{ domain.error_message }}</span>
                            </div>
                        </div>

                        <!-- 操作区域 -->
                        <div class="domain-actions">
                            <template v-if="!domain.verified">
                                <el-button type="primary" size="small" :loading="loadingVerify === domain.id"
                                    @click="handleVerify(domain.id)">
                                    验证域名
                                </el-button>
                            </template>
                            <template v-else>
                                <el-button size="small" :loading="loadingSsl === domain.id"
                                    @click="handleIssueSsl(domain.id)"
                                    :type="domain.ssl?.is_valid ? 'default' : 'primary'">
                                    {{ domain.ssl?.is_valid ? '续期 SSL' : '申请 SSL' }}
                                </el-button>
                            </template>
                            <el-button size="small" @click="handleDnsInfo(domain.id)">DNS 信息</el-button>
                            <el-popconfirm title="确定删除该域名绑定?" @confirm="handleDelete(domain.id)">
                                <template #reference>
                                    <el-button size="small" type="danger" plain>删除</el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>
        </el-card>

        <!-- 创建域名对话框 -->
        <el-dialog v-model="showCreateDialog" title="绑定自定义域名" width="520px">
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" size="large">
                <el-form-item label="域名" prop="domain">
                    <el-input v-model="form.domain" placeholder="license.example.com">
                        <template #prefix>
                            <el-icon><Link /></el-icon>
                        </template>
                    </el-input>
                    <div class="text-gray-400 text-sm mt-1">
                        例如: license.yourcompany.com
                    </div>
                </el-form-item>
                <el-form-item label="目标地址（可选）" prop="target_url">
                    <el-input v-model="form.target_url" placeholder="https://api.huwutong.com" />
                    <div class="text-gray-400 text-sm mt-1">
                        留空使用默认地址
                    </div>
                </el-form-item>
            </el-form>

            <div class="bg-blue-50 p-3 rounded text-sm text-blue-700 mb-4" v-if="showCreateDialog">
                <el-icon><InfoFilled /></el-icon>
                绑定后请添加 CNAME 记录将您的域名指向 <code>cname.huwutong.com.</code>
            </div>

            <template #footer>
                <el-button @click="showCreateDialog = false">取消</el-button>
                <el-button type="primary" :loading="creating" @click="handleCreate">绑定域名</el-button>
            </template>
        </el-dialog>

        <!-- DNS 信息对话框 -->
        <el-dialog v-model="showDnsDialog" title="DNS 解析信息" width="520px">
            <div v-if="dnsInfoData" class="dns-info">
                <div class="info-row">
                    <span class="label">域名:</span>
                    <code>{{ dnsInfoData.domain }}</code>
                </div>
                <div class="info-row">
                    <span class="label">期望 CNAME:</span>
                    <code>{{ dnsInfoData.expected_cname }}</code>
                </div>
                <div class="info-row" v-if="dnsInfoData.dns.a_records?.length">
                    <span class="label">A 记录:</span>
                    <span class="value">{{ dnsInfoData.dns.a_records.join(', ') }}</span>
                </div>
                <div class="info-row" v-if="dnsInfoData.dns.cname_records?.length">
                    <span class="label">CNAME 记录:</span>
                    <span class="value font-mono text-green-600">{{ dnsInfoData.dns.cname_records.join(', ') }}</span>
                </div>
                <div class="info-row" v-if="dnsInfoData.dns.resolved_ip">
                    <span class="label">解析 IP:</span>
                    <code>{{ dnsInfoData.dns.resolved_ip }}</code>
                </div>
                <div class="info-row" v-if="!dnsInfoData.dns.dns_ok">
                    <span class="label text-red-500">DNS 解析异常</span>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import domainApi from '@/api/domain';
import {
    Plus, Link, InfoFilled,
} from '@element-plus/icons-vue';

const loading = ref(true);
const domains = ref([]);
const loadingVerify = ref(null);
const loadingSsl = ref(null);
const creating = ref(false);
const showCreateDialog = ref(false);
const showDnsDialog = ref(false);
const dnsInfoData = ref(null);
const formRef = ref(null);

const form = reactive({
    domain: '',
    target_url: '',
});

const rules = {
    domain: [
        { required: true, message: '请输入域名', trigger: 'blur' },
        {
            pattern: /^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i,
            message: '请输入有效的域名格式',
            trigger: 'blur',
        },
    ],
    target_url: [
        { type: 'url', message: '请输入有效的 URL', trigger: 'blur', required: false },
    ],
};

function domainTagType(status) {
    const map = {
        active: 'success',
        pending: 'warning',
        failed: 'danger',
        verifying: 'warning',
        expired: 'info',
    };
    return map[status] || 'info';
}

function domainStatusLabel(status) {
    const map = {
        active: '已生效',
        pending: '待验证',
        verifying: '验证中',
        failed: '失败',
        expired: '已过期',
    };
    return map[status] || status;
}

function sslStatusClass(ssl) {
    if (!ssl) return '';
    if (ssl.is_valid) return 'text-green-600';
    if (ssl.status === 'failed') return 'text-red-500';
    return 'text-yellow-600';
}

function formatDate(date) {
    if (!date) return '';
    return new Date(date).toLocaleDateString('zh-CN');
}

async function loadDomains() {
    loading.value = true;
    try {
        const { data: res } = await domainApi.list();
        domains.value = res.data || [];
    } catch {
        ElMessage.error('加载域名列表失败');
    } finally {
        loading.value = false;
    }
}

async function handleCreate() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    creating.value = true;
    try {
        const { data: res } = await domainApi.create({
            domain: form.domain,
            target_url: form.target_url || undefined,
        });
        ElMessage.success(res.message || '域名绑定成功');
        showCreateDialog.value = false;
        form.domain = '';
        form.target_url = '';
        await loadDomains();
    } catch {
        // error handled by interceptor
    } finally {
        creating.value = false;
    }
}

async function handleVerify(domainId) {
    loadingVerify.value = domainId;
    try {
        const { data: res } = await domainApi.verify(domainId);
        ElMessage.success(res.message || '域名验证成功');
        await loadDomains();
    } catch {
        await loadDomains();
    } finally {
        loadingVerify.value = null;
    }
}

async function handleIssueSsl(domainId) {
    loadingSsl.value = domainId;
    try {
        const { data: res } = await domainApi.issueSsl(domainId);
        ElMessage.success(res.message || 'SSL 证书已签发');
        await loadDomains();
    } catch {
        await loadDomains();
    } finally {
        loadingSsl.value = null;
    }
}

async function handleDnsInfo(domainId) {
    try {
        const { data: res } = await domainApi.dnsInfo(domainId);
        dnsInfoData.value = res.data;
        showDnsDialog.value = true;
    } catch {
        ElMessage.error('获取 DNS 信息失败');
    }
}

async function handleDelete(domainId) {
    try {
        await domainApi.destroy(domainId);
        ElMessage.success('域名绑定已删除');
        await loadDomains();
    } catch {
        // error handled by interceptor
    }
}

onMounted(loadDomains);
</script>

<style scoped>
.domain-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.domain-card {
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    padding: 16px;
    transition: all 0.2s;
}

.domain-card:hover {
    border-color: #409eff;
    box-shadow: 0 2px 8px rgba(64, 158, 255, 0.1);
}

.domain-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.domain-name {
    display: flex;
    align-items: center;
    font-size: 16px;
    font-weight: 600;
}

.domain-body {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
}

.domain-info {
    flex: 1;
}

.info-row {
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-row .label {
    color: #909399;
    font-size: 13px;
    white-space: nowrap;
}

.info-row .value {
    font-size: 13px;
}

.info-row code {
    background: #f5f7fa;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 12px;
}

.domain-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.flex-between {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.mb-4 {
    margin-bottom: 16px;
}

.font-mono {
    font-family: 'Courier New', monospace;
}

.bg-blue-50 {
    background: #ecf5ff;
}

.p-3 {
    padding: 12px;
}

.rounded {
    border-radius: 6px;
}

.dns-info .info-row {
    padding: 8px 0;
    border-bottom: 1px solid #f5f7fa;
}
</style>
