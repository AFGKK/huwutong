<template>
  <div>
    <el-row :gutter="16" class="mb-4">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span class="font-medium">门户设置</span></template>
          <el-form label-width="140px" size="small">
            <el-form-item label="启用自助注册">
              <el-switch v-model="config.enabled" @change="saveConfig" />
            </el-form-item>
            <el-form-item label="需要邀请码">
              <el-switch v-model="config.require_invite" @change="saveConfig" />
            </el-form-item>
            <el-form-item label="需要邮箱验证">
              <el-switch v-model="config.require_email_verify" @change="saveConfig" />
            </el-form-item>
            <el-form-item label="接受条款">
              <el-switch v-model="config.accept_terms" @change="saveConfig" />
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span class="font-medium">品牌信息</span></template>
          <el-form label-width="120px" size="small">
            <el-form-item label="品牌名称">
              <el-input v-model="config.brand_name" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="标题">
              <el-input v-model="config.title" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="副标题">
              <el-input v-model="config.subtitle" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="Logo URL">
              <el-input v-model="config.logo_url" @blur="saveConfig" />
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>
    </el-row>

    <el-row :gutter="16">
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span class="font-medium">链接</span></template>
          <el-form label-width="120px" size="small">
            <el-form-item label="条款URL">
              <el-input v-model="config.terms_url" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="隐私URL">
              <el-input v-model="config.privacy_url" @blur="saveConfig" />
            </el-form-item>
          </el-form>
        </el-card>
      </el-col>
      <el-col :span="12">
        <el-card shadow="never">
          <template #header><span class="font-medium">功能特色</span></template>
          <div v-for="(feat, idx) in config.features" :key="idx" class="feature-row">
            <el-row :gutter="8">
              <el-col :span="7">
                <el-input v-model="feat.icon" placeholder="图标名" size="small" @blur="saveConfig" />
              </el-col>
              <el-col :span="7">
                <el-input v-model="feat.title" placeholder="标题" size="small" @blur="saveConfig" />
              </el-col>
              <el-col :span="8">
                <el-input v-model="feat.desc" placeholder="描述" size="small" @blur="saveConfig" />
              </el-col>
              <el-col :span="2">
                <el-button size="small" type="danger" link @click="config.features.splice(idx, 1); saveConfig()">
                  <el-icon><Delete /></el-icon>
                </el-button>
              </el-col>
            </el-row>
          </div>
          <el-button type="primary" link size="small" class="mt-2"
            @click="config.features.push({ icon: 'Star', title: '', desc: '' })">
            + 添加特色
          </el-button>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="never" class="mt-4">
      <template #header><span class="font-medium">自定义 CSS/HTML</span></template>
      <el-row :gutter="12">
        <el-col :span="12">
          <el-input v-model="config.custom_css" type="textarea" :rows="4"
            placeholder="自定义 CSS 样式..." @blur="saveConfig" />
        </el-col>
        <el-col :span="12">
          <el-input v-model="config.custom_html" type="textarea" :rows="4"
            placeholder="自定义 HTML 内容..." @blur="saveConfig" />
        </el-col>
      </el-row>
    </el-card>

    <!-- 预览 -->
    <el-card shadow="never" class="mt-4">
      <template #header><span class="font-medium">注册门户预览</span></template>
      <div class="portal-preview">
        <div class="preview-card">
          <div class="preview-brand" v-if="config.logo_url">
            <img :src="config.logo_url" class="preview-logo" />
          </div>
          <h3 class="preview-title">{{ config.title || '创建您的账户' }}</h3>
          <p class="preview-subtitle">{{ config.subtitle }}</p>

          <div class="preview-features">
            <div v-for="feat in config.features" :key="feat.title" class="preview-feat">
              <span class="preview-icon">✦</span>
              <div>
                <strong>{{ feat.title }}</strong>
                <p class="text-xs text-gray-400">{{ feat.desc }}</p>
              </div>
            </div>
          </div>

          <div class="preview-form">
            <input placeholder="邮箱" disabled />
            <input placeholder="密码" disabled />
            <button disabled>{{ config.require_invite ? '使用邀请码注册' : '注册' }}</button>
          </div>

          <div class="preview-terms" v-if="config.accept_terms">
            <small>注册即表示同意<a>条款</a>和<a>隐私政策</a></small>
          </div>
        </div>
      </div>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Delete } from '@element-plus/icons-vue'
import { getPortalConfig, updatePortalConfig } from '../../../api/invite-codes'

const config = reactive({
  enabled: false,
  require_invite: true,
  require_email_verify: false,
  accept_terms: true,
  title: '创建您的账户',
  subtitle: '请使用邀请码注册',
  brand_name: '',
  logo_url: '',
  terms_url: '/terms',
  privacy_url: '/privacy',
  custom_css: '',
  custom_html: '',
  features: [],
})

let saveTimer = null

async function loadConfig() {
  try {
    const { data } = await getPortalConfig()
    if (data) {
      Object.assign(config, {
        enabled: data.enabled?.value ?? false,
        require_invite: data.require_invite?.value ?? true,
        require_email_verify: data.require_email_verify?.value ?? false,
        accept_terms: data.accept_terms?.value ?? true,
        title: data.title?.value || '创建您的账户',
        subtitle: data.subtitle?.value || '',
        brand_name: data.brand_name?.value || '',
        logo_url: data.logo_url?.value || '',
        terms_url: data.terms_url?.value || '/terms',
        privacy_url: data.privacy_url?.value || '/privacy',
        custom_css: data.custom_css?.value || '',
        custom_html: data.custom_html?.value || '',
        features: data.features?.value || [],
      })
    }
  } catch (e) { /* ignore */ }
}

async function saveConfig() {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(async () => {
    try {
      await updatePortalConfig({ ...config })
    } catch (e) {
      ElMessage.warning('保存失败')
    }
  }, 800)
}

onMounted(() => loadConfig())
</script>

<style scoped>
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }
.mt-2 { margin-top: 8px; }
.font-medium { font-weight: 500; }
.text-xs { font-size: 12px; }
.text-gray-400 { color: #909399; }
.feature-row { margin-bottom: 6px; }
.portal-preview { display: flex; justify-content: center; }
.preview-card {
  max-width: 400px; width: 100%; padding: 24px; border: 1px solid #e4e7ed;
  border-radius: 8px; background: #fff;
}
.preview-brand { text-align: center; margin-bottom: 16px; }
.preview-logo { max-height: 48px; }
.preview-title { text-align: center; font-size: 20px; font-weight: 700; margin-bottom: 4px; }
.preview-subtitle { text-align: center; color: #909399; font-size: 13px; margin-bottom: 20px; }
.preview-features { margin-bottom: 20px; }
.preview-feat { display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; }
.preview-icon { font-size: 18px; color: #409eff; }
.preview-form input { width: 100%; padding: 8px 12px; border: 1px solid #dcdfe6; border-radius: 4px; margin-bottom: 10px; background: #f5f7fa; }
.preview-form button { width: 100%; padding: 10px; background: #409eff; color: #fff; border: none; border-radius: 4px; }
.preview-terms { text-align: center; margin-top: 16px; color: #909399; }
.preview-terms a { color: #409eff; cursor: pointer; }
</style>
