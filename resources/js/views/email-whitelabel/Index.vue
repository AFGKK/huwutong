<template>
    <div class="email-whitelabel-page">
        <div class="page-header">
            <div class="header-left">
                <h2>邮件白标 DKIM/SPF/DMARC</h2>
                <span class="header-subtitle">配置自有域名邮件认证，提升送达率和品牌可信度</span>
            </div>
            <div class="header-right">
                <el-button @click="activeTab = 'dns'">DNS 配置引导</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">保存配置</el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="基本设置" name="basic">
                <el-card shadow="never">
                    <el-form :model="form" label-width="150px" label-position="left">
                        <el-form-item label="发件人名称">
                            <el-input v-model="form.from_name" placeholder="如：互物通授权中心" maxlength="100" />
                        </el-form-item>
                        <el-form-item label="发件人邮箱">
                            <el-input v-model="form.from_email" placeholder="noreply@yourdomain.com" />
                        </el-form-item>
                        <el-form-item label="回复地址">
                            <el-input v-model="form.reply_to" placeholder="support@yourdomain.com" />
                        </el-form-item>
                        <el-form-item label="Return-Path">
                            <el-input v-model="form.return_path" placeholder="bounce@yourdomain.com" />
                        </el-form-item>
                        <el-form-item label="启用白标">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="DKIM" name="dkim">
                <el-card shadow="never">
                    <template #header>
                        <span>DKIM（DomainKeys Identified Mail）</span>
                        <el-switch v-model="form.dkim_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.dkim_enabled" :model="form" label-width="150px">
                        <el-form-item label="Selectors">
                            <el-input v-model="form.dkim_selector" placeholder="default" />
                        </el-form-item>
                        <el-form-item label="私钥（PEM）">
                            <el-input v-model="form.dkim_private_key" type="textarea" :rows="5" placeholder="-----BEGIN RSA PRIVATE KEY-----\n..." />
                        </el-form-item>
                        <el-form-item label="公钥（PEM）">
                            <el-input v-model="form.dkim_public_key" type="textarea" :rows="3" placeholder="-----BEGIN PUBLIC KEY-----\n..." />
                        </el-form-item>
                        <el-form-item>
                            <el-alert type="info" :closable="false" show-icon>
                                <template #title>
                                    <span>将公钥添加到 DNS：</span>
                                    <code>{{ dnsRecords.dkim?.name || 'default._domainkey.yourdomain.com' }}</code>
                                </template>
                            </el-alert>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="SPF" name="spf">
                <el-card shadow="never">
                    <template #header>
                        <span>SPF（Sender Policy Framework）</span>
                        <el-switch v-model="form.spf_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.spf_enabled" :model="form" label-width="150px">
                        <el-form-item label="SPF 记录">
                            <el-input v-model="form.spf_record" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-alert type="info" :closable="false" show-icon>
                                <template #title>
                                    <span>推荐的 SPF 记录：<code>v=spf1 include:_spf.huwutong.com ~all</code></span>
                                </template>
                            </el-alert>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="DMARC" name="dmarc">
                <el-card shadow="never">
                    <template #header>
                        <span>DMARC（Domain-based Message Authentication）</span>
                        <el-switch v-model="form.dmarc_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.dmarc_enabled" :model="form" label-width="150px">
                        <el-form-item label="策略">
                            <el-select v-model="form.dmarc_policy" style="width:200px">
                                <el-option label="none（仅监控）" value="none" />
                                <el-option label="quarantine（隔离）" value="quarantine" />
                                <el-option label="reject（拒绝）" value="reject" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="DMARC 记录">
                            <el-input v-model="form.dmarc_record" type="textarea" :rows="2" placeholder="v=DMARC1; p=none; rua=mailto:dmarc-reports@yourdomain.com" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="DNS 配置引导" name="dns">
                <el-card shadow="never">
                    <template #header>
                        <span>DNS 记录配置</span>
                        <el-button size="small" @click="loadDnsGuide">刷新</el-button>
                        <el-button size="small" type="primary" @click="handleVerify">验证配置</el-button>
                    </template>
                    <el-table v-if="dnsRecords.records?.length" :data="dnsRecords.records" stripe>
                        <el-table-column prop="type" label="类型" width="80" />
                        <el-table-column prop="name" label="主机记录" />
                        <el-table-column prop="value" label="记录值" show-overflow-tooltip />
                        <el-table-column prop="purpose" label="用途" width="140" />
                        <el-table-column label="操作" width="100">
                            <template #default="{ row }">
                                <el-button size="small" @click="copyToClipboard(row.guide)">复制指引</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else description="请先在 DKIM/SPF/DMARC 标签页中启用并保存配置" />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import api from '@/api/emailWhitelabel';

const activeTab = ref('basic');
const form = ref({});
const dnsRecords = ref({ records: [] });
const saving = ref(false);

function unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadConfig() {
    try {
        const res = await api.getConfig();
        form.value = unwrap(res) || {};
    } catch (e) {
        ElMessage.error('加载配置失败');
    }
}

async function loadDnsGuide() {
    try {
        const res = await api.getDnsGuide();
        dnsRecords.value = unwrap(res) || { records: [] };
    } catch (e) {
        ElMessage.error('加载 DNS 引导失败');
    }
}

async function handleSave() {
    saving.value = true;
    try {
        await api.updateConfig(form.value);
        ElMessage.success('配置已保存');
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '保存失败');
    } finally {
        saving.value = false;
    }
}

async function handleVerify() {
    try {
        const res = await api.verify();
        const data = unwrap(res);
        ElMessage.info(`验证完成：${data.overall_status}`);
    } catch (e) {
        ElMessage.error('验证失败');
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success('已复制到剪贴板');
    });
}

onMounted(() => {
    loadConfig();
    loadDnsGuide();
});
</script>

<style scoped>
.email-whitelabel-page { padding: 20px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.header-left h2 { margin: 0; font-size: 20px; display: inline; }
.header-subtitle { font-size: 13px; color: #999; margin-left: 8px; }
</style>
