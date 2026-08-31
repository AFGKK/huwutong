<template>
    <div class="passkey-manage">
        <el-page-header :content="t('passkey_page.title')" @back="$router.push('/admin/dashboard')" />

        <el-alert
            :title="t('passkey_page.alert')"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card>
            <template #header>
                <el-space>
                    <span>{{ t('passkey_page.registered') }}</span>
                    <el-button size="small" type="primary" @click="handleRegister">{{ t('passkey_page.register') }}</el-button>
                </el-space>
            </template>
            <el-table :data="credentials" stripe v-loading="loading">
                <el-table-column prop="name" :label="t('passkey_page.cols.name')" min-width="200" />
                <el-table-column :label="t('passkey_page.cols.type')" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.type === 'platform' ? t('passkey_page.type_platform') : t('passkey_page.type_cross') }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('passkey_page.cols.transports')" width="120">
                    <template #default="{ row }">
                        <el-tag v-for="tr in row.transports" :key="tr" size="small" style="margin-right:4px">{{ tr }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" :label="t('passkey_page.cols.created')" width="160" />
                <el-table-column prop="last_used_at" :label="t('passkey_page.cols.last_used')" width="160">
                    <template #default="{ row }">{{ row.last_used_at || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('passkey_page.cols.actions')" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-popconfirm :title="t('passkey_page.confirm_delete')" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!loading && credentials.length === 0" :description="t('passkey_page.empty')" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';

const { t, locale } = useI18n();
const loading = ref(false);
const credentials = ref([]);

async function fetchCredentials() {
    loading.value = true;
    try {
        const { data: res } = await apiClient.get('/auth/webauthn/credentials');
        credentials.value = res.data || [];
    } catch { credentials.value = []; } finally { loading.value = false; }
}

async function handleRegister() {
    try {
        const dateLoc = locale.value === 'en' || locale.value?.startsWith('en') ? 'en-US' : 'zh-CN';
        const { data: optionsRes } = await apiClient.post('/auth/webauthn/register/options', {
            name: `Passkey ${new Date().toLocaleDateString(dateLoc)}`,
        });

        const options = optionsRes.data;
        const publicKey = {
            ...options,
            challenge: base64ToUint8(options.challenge),
            user: { ...options.user, id: base64ToUint8(options.user.id) },
            excludeCredentials: (options.excludeCredentials || []).map(c => ({
                ...c, id: base64ToUint8(c.id),
            })),
        };

        const credential = await navigator.credentials.create({ publicKey });
        if (!credential) { ElMessage.error(t('passkey_page.messages.cancelled')); return; }

        const payload = {
            id: credential.id,
            raw_id: bufToBase64(credential.rawId),
            type: credential.type,
            response: {
                client_data_json: bufToBase64(credential.response.clientDataJSON),
                attestation_object: bufToBase64(credential.response.attestationObject),
                transports: credential.response.getTransports ? credential.response.getTransports() : [],
            },
        };

        await apiClient.post('/auth/webauthn/register/verify', payload);
        ElMessage.success(t('passkey_page.messages.registered'));
        await fetchCredentials();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('passkey_page.messages.register_failed'));
    }
}

async function handleDelete(row) {
    try {
        await apiClient.delete(`/auth/webauthn/credentials/${row.id}`);
        ElMessage.success(t('passkey_page.messages.deleted'));
        await fetchCredentials();
    } catch { ElMessage.error(t('passkey_page.messages.delete_failed')); }
}

function base64ToUint8(str) {
    const binary = atob(str.replace(/-/g, '+').replace(/_/g, '/'));
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes;
}

function bufToBase64(buf) {
    const b = new Uint8Array(buf);
    let binary = '';
    b.forEach(v => binary += String.fromCharCode(v));
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

onMounted(() => { fetchCredentials(); });
</script>

<style scoped>
.passkey-manage { padding: 20px; }
.alert-info { margin: 16px 0; }
</style>
