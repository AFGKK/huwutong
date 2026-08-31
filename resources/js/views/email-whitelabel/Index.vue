<template>
    <div class="email-whitelabel-page">
        <div class="page-header">
            <div class="header-left">
                <h2>{{ t('email_whitelabel_page.title') }}</h2>
                <span class="header-subtitle">{{ t('email_whitelabel_page.subtitle') }}</span>
            </div>
            <div class="header-right">
                <el-button @click="activeTab = 'dns'">{{ t('email_whitelabel_page.tabs.dns') }}</el-button>
                <el-button type="primary" @click="handleSave" :loading="saving">{{ t('actions.save') }}</el-button>
            </div>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane :label="t('email_whitelabel_page.tabs.basic')" name="basic">
                <el-card shadow="never">
                    <el-form :model="form" label-width="150px" label-position="left">
                        <el-form-item :label="t('email_whitelabel_page.form.from_name')">
                            <el-input v-model="form.from_name" :placeholder="t('email_whitelabel_page.form.from_name_ph')" maxlength="100" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.from_email')">
                            <el-input v-model="form.from_email" :placeholder="t('email_whitelabel_page.form.from_email_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.reply_to')">
                            <el-input v-model="form.reply_to" :placeholder="t('email_whitelabel_page.form.reply_to_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.return_path')">
                            <el-input v-model="form.return_path" :placeholder="t('email_whitelabel_page.form.return_path_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.enable_whitelabel')">
                            <el-switch v-model="form.is_active" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('email_whitelabel_page.tabs.dkim')" name="dkim">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('email_whitelabel_page.dkim.title') }}</span>
                        <el-switch v-model="form.dkim_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.dkim_enabled" :model="form" label-width="150px">
                        <el-form-item :label="t('email_whitelabel_page.form.dkim_selector')">
                            <el-input v-model="form.dkim_selector" :placeholder="t('email_whitelabel_page.form.dkim_selector_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.dkim_private_key')">
                            <el-input v-model="form.dkim_private_key" type="textarea" :rows="5" :placeholder="t('email_whitelabel_page.form.dkim_private_key_ph')" />
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.dkim_public_key')">
                            <el-input v-model="form.dkim_public_key" type="textarea" :rows="3" :placeholder="t('email_whitelabel_page.form.dkim_public_key_ph')" />
                        </el-form-item>
                        <el-form-item>
                            <el-alert type="info" :closable="false" show-icon>
                                <template #title>
                                    <span>{{ t('email_whitelabel_page.dkim.dns_hint') }}</span>
                                    <code>{{ dnsRecords.dkim?.name || t('email_whitelabel_page.dkim.dns_name_fallback') }}</code>
                                </template>
                            </el-alert>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('email_whitelabel_page.tabs.spf')" name="spf">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('email_whitelabel_page.spf.title') }}</span>
                        <el-switch v-model="form.spf_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.spf_enabled" :model="form" label-width="150px">
                        <el-form-item :label="t('email_whitelabel_page.form.spf_record')">
                            <el-input v-model="form.spf_record" type="textarea" :rows="2" />
                        </el-form-item>
                        <el-form-item>
                            <el-alert type="info" :closable="false" show-icon>
                                <template #title>
                                    <span>{{ t('email_whitelabel_page.spf.recommended_prefix') }} <code>{{ t('email_whitelabel_page.spf.recommended_record') }}</code></span>
                                </template>
                            </el-alert>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('email_whitelabel_page.tabs.dmarc')" name="dmarc">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('email_whitelabel_page.dmarc.title') }}</span>
                        <el-switch v-model="form.dmarc_enabled" style="margin-left: 12px;" />
                    </template>
                    <el-form v-if="form.dmarc_enabled" :model="form" label-width="150px">
                        <el-form-item :label="t('email_whitelabel_page.form.dmarc_policy')">
                            <el-select v-model="form.dmarc_policy" style="width:200px">
                                <el-option
                                    v-for="opt in dmarcPolicyOptions"
                                    :key="opt.value"
                                    :label="opt.label"
                                    :value="opt.value"
                                />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t('email_whitelabel_page.form.dmarc_record')">
                            <el-input v-model="form.dmarc_record" type="textarea" :rows="2" :placeholder="t('email_whitelabel_page.form.dmarc_record_ph')" />
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane :label="t('email_whitelabel_page.tabs.dns')" name="dns">
                <el-card shadow="never">
                    <template #header>
                        <span>{{ t('email_whitelabel_page.dns.title') }}</span>
                        <el-button size="small" @click="loadDnsGuide">{{ t('email_whitelabel_page.dns.refresh') }}</el-button>
                        <el-button size="small" type="primary" @click="handleVerify">{{ t('email_whitelabel_page.dns.verify') }}</el-button>
                    </template>
                    <el-table v-if="dnsRecords.records?.length" :data="dnsRecords.records" stripe>
                        <el-table-column prop="type" :label="t('email_whitelabel_page.columns.type')" width="80" />
                        <el-table-column prop="name" :label="t('email_whitelabel_page.columns.name')" />
                        <el-table-column prop="value" :label="t('email_whitelabel_page.columns.value')" show-overflow-tooltip />
                        <el-table-column prop="purpose" :label="t('email_whitelabel_page.columns.purpose')" width="140" />
                        <el-table-column :label="t('email_whitelabel_page.columns.actions')" width="100">
                            <template #default="{ row }">
                                <el-button size="small" @click="copyToClipboard(row.guide)">{{ t('email_whitelabel_page.dns.copy_guide') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else :description="t('email_whitelabel_page.dns.empty')" />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import api from '@/api/emailWhitelabel';

const { t } = useI18n();

const activeTab = ref('basic');
const form = ref({});
const dnsRecords = ref({ records: [] });
const saving = ref(false);

const dmarcPolicyOptions = computed(() => [
    { label: t('email_whitelabel_page.dmarc_policy.none'), value: 'none' },
    { label: t('email_whitelabel_page.dmarc_policy.quarantine'), value: 'quarantine' },
    { label: t('email_whitelabel_page.dmarc_policy.reject'), value: 'reject' },
]);

function unwrap(res) {
    const body = res?.data ?? res;
    return body?.data ?? body;
}

async function loadConfig() {
    try {
        const res = await api.getConfig();
        form.value = unwrap(res) || {};
    } catch (e) {
        ElMessage.error(t('messages.load_failed'));
    }
}

async function loadDnsGuide() {
    try {
        const res = await api.getDnsGuide();
        dnsRecords.value = unwrap(res) || { records: [] };
    } catch (e) {
        ElMessage.error(t('email_whitelabel_page.messages.dns_load_failed'));
    }
}

async function handleSave() {
    saving.value = true;
    try {
        await api.updateConfig(form.value);
        ElMessage.success(t('email_whitelabel_page.messages.saved'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('email_whitelabel_page.messages.save_failed'));
    } finally {
        saving.value = false;
    }
}

async function handleVerify() {
    try {
        const res = await api.verify();
        const data = unwrap(res);
        ElMessage.info(t('email_whitelabel_page.messages.verify_done', { status: data.overall_status }));
    } catch (e) {
        ElMessage.error(t('email_whitelabel_page.messages.verify_failed'));
    }
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        ElMessage.success(t('email_whitelabel_page.messages.copied'));
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
