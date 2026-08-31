<template>
    <div class="tenant-select-page">
        <div class="tenant-card">
            <div class="tenant-header">
                <el-icon :size="40" color="#0f172a"><Key /></el-icon>
                <h2>{{ t('tenant_select_page.title') }}</h2>
                <p class="text-gray-500">{{ t('tenant_select_page.desc') }}</p>
            </div>

            <div v-if="loading" class="flex justify-center py-12">
                <el-icon class="is-loading" :size="32">
                    <Loading />
                </el-icon>
            </div>

            <div v-else class="tenant-list">
                <div
                    v-for="tenant in tenants"
                    :key="tenant.id"
                    class="tenant-item"
                    :class="{ active: tenant.id === selectedId }"
                    @click="selectedId = tenant.id"
                    @dblclick="confirmSelect"
                >
                    <div class="tenant-icon">
                        <el-avatar :size="48" :icon="OfficeBuilding">
                            {{ tenant.name.charAt(0) }}
                        </el-avatar>
                    </div>
                    <div class="tenant-info">
                        <div class="tenant-name">{{ tenant.name }}</div>
                        <div class="tenant-id">ID: {{ tenant.id }}</div>
                    </div>
                    <div class="tenant-check">
                        <el-icon v-if="tenant.id === selectedId" color="#0f172a" :size="20">
                            <CircleCheck />
                        </el-icon>
                    </div>
                </div>
            </div>

            <div class="tenant-actions" v-if="tenants.length > 0">
                <el-button type="primary" size="large" class="w-full" @click="confirmSelect">
                    {{ t('tenant_select_page.enter', { name: selectedName }) }}
                </el-button>
            </div>

            <div class="tenant-actions" v-else-if="!loading">
                <p class="text-gray-400 mb-4">{{ t('tenant_select_page.no_tenants') }}</p>
                <el-button @click="handleLogout">{{ t('tenant_select_page.back_login') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import tenantApi from '@/api/tenant';
import {
    Key, Loading, CircleCheck, OfficeBuilding,
} from '@element-plus/icons-vue';

const { t } = useI18n();
const router = useRouter();
const authStore = useAuthStore();

const loading = ref(true);
const tenants = ref([]);
const selectedId = ref(null);

const selectedName = computed(() => {
    const item = tenants.value.find((tenant) => tenant.id === selectedId.value);
    return item ? item.name : '';
});

async function loadTenants() {
    loading.value = true;
    try {
        if (authStore.user?.tenants?.length) {
            tenants.value = authStore.user.tenants;
            selectedId.value = authStore.activeTenantId || tenants.value[0]?.id;
            loading.value = false;
            return;
        }

        const { data: res } = await tenantApi.list();
        tenants.value = res.data.tenants || [];
        selectedId.value = res.data.current_tenant_id || tenants.value[0]?.id;
    } catch {
        tenants.value = [];
    } finally {
        loading.value = false;
    }
}

async function confirmSelect() {
    if (!selectedId.value) return;
    const success = await authStore.switchTenant(selectedId.value);
    if (success) {
        router.push('/dashboard');
    }
}

function handleLogout() {
    authStore.logout();
    router.push('/login');
}

onMounted(loadTenants);
</script>

<style scoped>
.tenant-select-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.tenant-card {
    width: 480px;
    padding: 40px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.tenant-header {
    text-align: center;
    margin-bottom: 32px;
}

.tenant-header h2 {
    margin: 16px 0 8px;
    font-size: 22px;
    color: #303133;
}

.tenant-header p {
    font-size: 14px;
}

.tenant-list {
    margin-bottom: 24px;
}

.tenant-item {
    display: flex;
    align-items: center;
    padding: 16px;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    margin-bottom: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.tenant-item:hover {
    border-color: #0f172a;
    background: #f0f7ff;
}

.tenant-item.active {
    border-color: #0f172a;
    background: #f1f5f9;
}

.tenant-icon {
    margin-right: 16px;
}

.tenant-info {
    flex: 1;
}

.tenant-name {
    font-size: 16px;
    font-weight: 600;
    color: #303133;
    margin-bottom: 4px;
}

.tenant-id {
    font-size: 12px;
    color: #909399;
}

.tenant-check {
    margin-left: 12px;
}

.tenant-actions {
    text-align: center;
}

.w-full {
    width: 100%;
}

.flex {
    display: flex;
}

.justify-center {
    justify-content: center;
}

.py-12 {
    padding-top: 48px;
    padding-bottom: 48px;
}

.mb-4 {
    margin-bottom: 16px;
}
</style>
