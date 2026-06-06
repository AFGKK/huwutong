<template>
    <div class="login-page">
        <div class="login-card">
            <div class="login-header">
                <el-icon :size="40" color="#409eff"><Key /></el-icon>
                <h2>HWT License 管理后台</h2>
                <p class="text-gray-400">请登录您的账户</p>
            </div>

            <el-form
                ref="formRef"
                :model="form"
                :rules="rules"
                label-position="top"
                size="large"
                @keyup.enter="handleLogin"
            >
                <el-form-item label="邮箱" prop="email">
                    <el-input
                        v-model="form.email"
                        placeholder="请输入邮箱"
                        :prefix-icon="UserFilled"
                    />
                </el-form-item>

                <el-form-item label="密码" prop="password">
                    <el-input
                        v-model="form.password"
                        type="password"
                        placeholder="请输入密码"
                        show-password
                        :prefix-icon="Lock"
                    />
                </el-form-item>

                <el-form-item>
                    <el-button
                        type="primary"
                        :loading="authStore.loading"
                        class="w-full"
                        size="large"
                        @click="handleLogin"
                    >
                        {{ authStore.loading ? '登录中...' : '登 录' }}
                    </el-button>
                </el-form-item>
            </el-form>

            <div class="login-footer">
                <router-link to="/status" class="status-link">系统状态</router-link>
                <span class="text-gray-400">© 2026 HWT License</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { UserFilled, Lock, Key } from '@element-plus/icons-vue';

const router = useRouter();
const authStore = useAuthStore();
const formRef = ref(null);

const form = reactive({
    email: '',
    password: '',
});

const rules = {
    email: [
        { required: true, message: '请输入邮箱', trigger: 'blur' },
        { type: 'email', message: '请输入有效的邮箱地址', trigger: 'blur' },
    ],
    password: [
        { required: true, message: '请输入密码', trigger: 'blur' },
        { min: 6, message: '密码至少 6 位', trigger: 'blur' },
    ],
};

async function handleLogin() {
    const valid = await formRef.value?.validate().catch(() => false);
    if (!valid) return;

    const success = await authStore.login(form);
    if (success) {
        router.push('/dashboard');
    }
}
</script>

<style scoped>
.login-page {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.login-card {
    width: 420px;
    padding: 40px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.login-header {
    text-align: center;
    margin-bottom: 32px;
}

.login-header h2 {
    margin: 16px 0 8px;
    font-size: 22px;
    color: #303133;
}

.login-footer {
    text-align: center;
    margin-top: 24px;
    font-size: 13px;
}

.w-full {
    width: 100%;
}
</style>
