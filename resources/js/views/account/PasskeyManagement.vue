<template>
    <div class="passkey-manage">
        <el-page-header :content="'Passkey 凭据管理'" @back="$router.push('/admin/dashboard')" />

        <el-alert
            title="Passkey（WebAuthn）支持指纹、面部识别、Windows Hello 和 YubiKey 等硬件安全密钥，取代传统密码登录。"
            type="info"
            show-icon
            :closable="false"
            class="alert-info"
        />

        <el-card>
            <template #header>
                <el-space>
                    <span>已注册的 Passkey</span>
                    <el-button size="small" type="primary" @click="handleRegister">注册新 Passkey</el-button>
                </el-space>
            </template>
            <el-table :data="credentials" stripe v-loading="loading">
                <el-table-column prop="name" label="名称" min-width="200" />
                <el-table-column label="类型" width="120">
                    <template #default="{ row }">
                        <el-tag size="small">{{ row.type === 'platform' ? '平台内置' : '跨平台' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="传输方式" width="120">
                    <template #default="{ row }">
                        <el-tag v-for="t in row.transports" :key="t" size="small" style="margin-right:4px">{{ t }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="created_at" label="创建时间" width="160" />
                <el-table-column prop="last_used_at" label="最后使用" width="160">
                    <template #default="{ row }">{{ row.last_used_at || '-' }}</template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-popconfirm title="确认删除此 Passkey?" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">删除</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <el-empty v-if="!loading && credentials.length === 0" description="暂未注册 Passkey" :image-size="80" />
        </el-card>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import apiClient from '@/api/client';

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
        // 1. Get registration options
        const { data: optionsRes } = await apiClient.post('/auth/webauthn/register/options', {
            name: `Passkey ${new Date().toLocaleDateString()}`,
        });

        // 2. Convert to credentials creation options
        const options = optionsRes.data;
        const publicKey = {
            ...options,
            challenge: base64ToUint8(options.challenge),
            user: { ...options.user, id: base64ToUint8(options.user.id) },
            excludeCredentials: (options.excludeCredentials || []).map(c => ({
                ...c, id: base64ToUint8(c.id),
            })),
        };

        // 3. Create credential via browser WebAuthn API
        const credential = await navigator.credentials.create({ publicKey });
        if (!credential) { ElMessage.error('用户取消注册'); return; }

        // 4. Send to server for verification
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
        ElMessage.success('Passkey 注册成功');
        await fetchCredentials();
    } catch (e) {
        ElMessage.error(e.response?.data?.message || '注册失败');
    }
}

async function handleDelete(row) {
    try {
        await apiClient.delete(`/auth/webauthn/credentials/${row.id}`);
        ElMessage.success('已删除');
        await fetchCredentials();
    } catch { ElMessage.error('删除失败'); }
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
