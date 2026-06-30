<template>
  <div class="register-container">
    <div class="register-card">
      <div class="register-header">
        <div class="logo">
          <div class="logo-icon">互</div>
          <span class="logo-text">互物通</span>
        </div>
        <h2 class="register-title">创建账户</h2>
        <p class="register-desc">免费注册，开始使用 HWT License 管理后台</p>
      </div>

      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-position="top"
        class="register-form"
        @submit.prevent="handleRegister"
      >
        <el-form-item label="姓名" prop="name">
          <el-input
            v-model="form.name"
            placeholder="请输入您的姓名"
            :prefix-icon="User"
            size="large"
          />
        </el-form-item>

        <el-form-item label="邮箱" prop="email">
          <el-input
            v-model="form.email"
            placeholder="请输入邮箱地址"
            :prefix-icon="Message"
            size="large"
          />
        </el-form-item>

        <el-form-item label="手机号" prop="phone">
          <el-input
            v-model="form.phone"
            placeholder="请输入手机号（选填）"
            :prefix-icon="Iphone"
            size="large"
          />
        </el-form-item>

        <el-form-item label="密码" prop="password">
          <el-input
            v-model="form.password"
            type="password"
            placeholder="请设置密码（至少 8 位）"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-form-item label="确认密码" prop="password_confirmation">
          <el-input
            v-model="form.password_confirmation"
            type="password"
            placeholder="请再次输入密码"
            :prefix-icon="Lock"
            show-password
            size="large"
          />
        </el-form-item>

        <el-button
          type="primary"
          size="large"
          class="register-btn"
          :loading="loading"
          native-type="submit"
        >
          免费注册
        </el-button>
      </el-form>

      <div class="register-footer">
        <span>已有账户？</span>
        <router-link to="/login" class="login-link">立即登录</router-link>
      </div>

      <div class="register-meta">
        <span>注册即表示同意</span>
        <a href="#" class="meta-link">服务条款</a>
        <span>和</span>
        <a href="#" class="meta-link">隐私政策</a>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import { User, Message, Iphone, Lock } from '@element-plus/icons-vue';
import authApi from '@/api/auth';

const router = useRouter();
const route = useRoute();
const formRef = ref(null);
const loading = ref(false);

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
});

const validatePassword = (_rule, value, callback) => {
  if (value && value.length < 8) {
    callback(new Error('密码长度不能少于 8 位'));
  } else {
    callback();
  }
};

const validatePasswordConfirm = (_rule, value, callback) => {
  if (value !== form.password) {
    callback(new Error('两次输入的密码不一致'));
  } else {
    callback();
  }
};

const rules = {
  name: [{ required: true, message: '请输入姓名', trigger: 'blur' }],
  email: [
    { required: true, message: '请输入邮箱', trigger: 'blur' },
    { type: 'email', message: '请输入有效的邮箱地址', trigger: 'blur' },
  ],
  password: [
    { required: true, message: '请设置密码', trigger: 'blur' },
    { validator: validatePassword, trigger: 'blur' },
  ],
  password_confirmation: [
    { required: true, message: '请再次输入密码', trigger: 'blur' },
    { validator: validatePasswordConfirm, trigger: 'blur' },
  ],
};

async function handleRegister() {
  if (!formRef.value) return;

  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;

  loading.value = true;
  try {
    const payload = {
      name: form.name,
      email: form.email,
      phone: form.phone || undefined,
      password: form.password,
      password_confirmation: form.password_confirmation,
    };
    if (route.query.ref) {
      payload.invite_code = route.query.ref;
    }
    const response = await authApi.register(payload);

    const data = response.data || response;
    ElMessage.success('注册成功！正在跳转...');
    if (data.data?.token) {
      localStorage.setItem('auth_token', data.data.token);
    }
    const redirectTo = route.query.redirect || '/dashboard';
    setTimeout(() => router.push(redirectTo), 1000);
  } catch (err) {
    const errorData = err?.response?.data?.error;
    if (errorData?.details) {
      // Set field-level errors on the form
      const fieldErrors = {};
      for (const [field, messages] of Object.entries(errorData.details)) {
        if (formRef.value?.fields?.some(f => f.prop === field)) {
          fieldErrors[field] = messages.join('; ');
        }
      }
      if (Object.keys(fieldErrors).length > 0) {
        formRef.value.setFields(fieldErrors);
      }
      // Show first error as message
      const firstMsg = Object.values(errorData.details)[0]?.[0] || errorData.message;
      ElMessage.error(firstMsg);
    } else {
      const msg = errorData?.message || '注册失败，请重试';
      ElMessage.error(msg);
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.register-container {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 20px;
}

.register-card {
  width: 100%;
  max-width: 420px;
  background: #fff;
  border-radius: 16px;
  padding: 40px 32px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
}

.register-header {
  text-align: center;
  margin-bottom: 32px;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-bottom: 24px;
}

.logo-icon {
  width: 36px;
  height: 36px;
  background: #409eff;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-weight: bold;
  font-size: 16px;
}

.logo-text {
  font-size: 22px;
  font-weight: bold;
  color: #1a1a2e;
}

.register-title {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a2e;
  margin: 0 0 8px;
}

.register-desc {
  font-size: 14px;
  color: #6b7280;
  margin: 0;
}

.register-form {
  margin-bottom: 24px;
}

.register-btn {
  width: 100%;
  margin-top: 8px;
}

.register-footer {
  text-align: center;
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 16px;
}

.login-link {
  color: #409eff;
  text-decoration: none;
  font-weight: 500;
}

.login-link:hover {
  text-decoration: underline;
}

.register-meta {
  text-align: center;
  font-size: 12px;
  color: #9ca3af;
}

.meta-link {
  color: #409eff;
  text-decoration: none;
}

.meta-link:hover {
  text-decoration: underline;
}
</style>
