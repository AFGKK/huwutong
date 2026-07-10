<template>
  <div class="portal-branding">
    <h2 class="mb-4">客户门户品牌化</h2>

    <el-alert title="自定义客户门户的外观和品牌风格，更改实时生效。支持多语言配置。" type="info" show-icon :closable="false" class="mb-4" />

    <el-row :gutter="20">
      <el-col :span="16">
        <!-- 主题模板 -->
        <el-card class="mb-4">
          <template #header><span>快速主题</span></template>
          <div class="theme-grid">
            <div v-for="t in themes" :key="t.id" class="theme-card" :class="{ active: activeTheme === t.id }" @click="applyTheme(t)">
              <div class="theme-preview">
                <div class="tp-sidebar" :style="{ background: t.sidebar_bg_color }" />
                <div class="tp-main">
                  <div class="tp-header" :style="{ background: t.primary_color }" />
                  <div class="tp-content">
                    <div class="tp-dot" :style="{ background: t.primary_color }" />
                    <div class="tp-dot" :style="{ background: t.secondary_color }" />
                    <div class="tp-dot" :style="{ background: t.background_color }" />
                  </div>
                </div>
              </div>
              <div class="theme-name">{{ t.name }}</div>
            </div>
          </div>
        </el-card>

        <!-- 品牌配置表单 -->
        <el-card class="mb-4">
          <template #header><span>品牌信息</span></template>
          <el-form :model="form" label-width="130px" size="small" v-loading="loading">
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="品牌名称">
                  <el-input v-model="form.brand_name" @blur="saveConfig" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="品牌标语">
                  <el-input v-model="form.brand_slogan" @blur="saveConfig" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item label="Logo">
                  <div class="upload-row">
                    <el-upload :auto-upload="false" :on-change="handleLogoUpload" accept="image/*" :show-file-list="false">
                      <el-button size="small" type="primary">选择图片</el-button>
                    </el-upload>
                    <el-input v-model="form.logo_url" size="small" placeholder="或输入URL" style="flex:1;min-width:0" @blur="saveConfig" />
                    <img v-if="form.logo_url" :src="form.logo_url" class="upload-preview" @error="$event.target.style.display='none'" />
                  </div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item label="Favicon">
                  <div class="upload-row">
                    <el-upload :auto-upload="false" :on-change="handleFaviconUpload" accept="image/*" :show-file-list="false">
                      <el-button size="small" type="primary">选择图片</el-button>
                    </el-upload>
                    <el-input v-model="form.favicon_url" size="small" placeholder="或输入URL" style="flex:1;min-width:0" @blur="saveConfig" />
                    <img v-if="form.favicon_url" :src="form.favicon_url" class="upload-preview" @error="$event.target.style.display='none'" />
                  </div>
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item label="登录页标题">
              <el-input v-model="form.login_page_title" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="登录页副标题">
              <el-input v-model="form.login_page_subtitle" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="登录页背景图">
              <div class="upload-row">
                <el-upload :auto-upload="false" :on-change="handleBgUpload" accept="image/*" :show-file-list="false">
                  <el-button size="small" type="primary">选择图片</el-button>
                </el-upload>
                <el-input v-model="form.login_bg_image" size="small" placeholder="或输入URL" style="flex:1;min-width:0" @blur="saveConfig" />
                <img v-if="form.login_bg_image" :src="form.login_bg_image" class="upload-preview" @error="$event.target.style.display='none'" />
              </div>
            </el-form-item>
            <el-form-item label="底部版权文字">
              <el-input v-model="form.footer_text" @blur="saveConfig" />
            </el-form-item>
            <el-form-item label="字体">
              <el-input v-model="form.font_family" @blur="saveConfig" placeholder='"Inter", sans-serif' />
            </el-form-item>
          </el-form>
        </el-card>

        <!-- 颜色配置 -->
        <el-card class="mb-4">
          <template #header><span>颜色方案</span></template>
          <el-form :model="form" label-width="130px" size="small">
            <el-row :gutter="16">
              <el-col v-for="field in colorFields" :key="field.key" :span="8">
                <el-form-item :label="field.label">
                  <div class="color-picker-row">
                    <el-color-picker v-model="form[field.key]" @change="saveConfig" show-alpha />
                    <el-input v-model="form[field.key]" size="small" style="width:100px;margin-left:8px" @blur="saveConfig" />
                  </div>
                </el-form-item>
              </el-col>
            </el-row>
          </el-form>
        </el-card>

        <!-- 自定义 HTML/CSS -->
        <el-card class="mb-4">
          <template #header><span>自定义代码</span></template>
          <el-tabs v-model="codeTab">
            <el-tab-pane label="自定义 CSS" name="css">
              <el-input v-model="form.custom_css" type="textarea" :rows="8" placeholder="/* 在此写入自定义 CSS */" @blur="saveConfig" />
            </el-tab-pane>
            <el-tab-pane label="顶部 HTML" name="header">
              <el-input v-model="form.header_html" type="textarea" :rows="6" placeholder="<!-- 顶部自定义 HTML -->" @blur="saveConfig" />
            </el-tab-pane>
            <el-tab-pane label="底部 HTML" name="footer">
              <el-input v-model="form.footer_html" type="textarea" :rows="6" placeholder="<!-- 底部自定义 HTML -->" @blur="saveConfig" />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>

      <el-col :span="8">
        <!-- 预览 -->
        <el-card class="mb-4">
          <template #header><span>门户预览</span></template>
          <div class="preview-frame" :style="previewStyle">
            <div class="pf-header" :style="{ background: form.header_bg_color, borderBottom: '1px solid #e4e7ed' }">
              <span v-if="form.logo_url" class="pf-logo"><img :src="form.logo_url" style="height:28px" /></span>
              <span v-else class="pf-brand" :style="{ color: form.primary_color }">{{ form.brand_name || '品牌名称' }}</span>
            </div>
            <div class="pf-body">
              <div class="pf-sidebar" :style="{ background: form.sidebar_bg_color, color: form.sidebar_text_color }">
                <div class="pf-menu-item active" :style="{ background: form.primary_color + '22', borderLeft: '3px solid ' + form.primary_color }">概览</div>
                <div class="pf-menu-item">许可证</div>
                <div class="pf-menu-item">设备</div>
                <div class="pf-menu-item">工单</div>
              </div>
              <div class="pf-content" :style="{ background: form.background_color }">
                <div class="pf-card" :style="{ borderRadius: form.button_radius }">
                  <div class="pf-stat" :style="{ color: form.primary_color }">欢迎使用 {{ form.brand_name || '客户门户' }}</div>
                  <div class="pf-desc" :style="{ color: form.text_color }">这是客户门户的预览效果</div>
                  <el-button :style="{ background: form.primary_color, borderColor: form.primary_color, borderRadius: form.button_radius }" type="primary" size="small">查看详情</el-button>
                </div>
              </div>
            </div>
          </div>
        </el-card>

        <!-- 操作 -->
        <el-card>
          <template #header><span>管理操作</span></template>
          <div class="flex flex-col gap-3">
            <el-button type="primary" :loading="saving" @click="saveConfig"><el-icon><Check /></el-icon> 保存配置</el-button>
            <el-popconfirm title="确认重置为默认配置？" @confirm="resetConfig">
              <template #reference><el-button type="danger"><el-icon><Refresh /></el-icon> 重置为默认</el-button></template>
            </el-popconfirm>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { Check, Refresh } from '@element-plus/icons-vue'
import brandingApi from '../../api/portalBranding'

const loading = ref(false)
const saving = ref(false)
const themes = ref([])
const activeTheme = ref(null)
const codeTab = ref('css')

const form = reactive({
  brand_name: '', brand_slogan: '', logo_url: '', favicon_url: '',
  primary_color: '#409eff', secondary_color: '#67c23a',
  background_color: '#f5f7fa', text_color: '#303133',
  link_color: '#409eff', header_bg_color: '#ffffff',
  sidebar_bg_color: '#304156', sidebar_text_color: '#bfcbd9',
  button_radius: '4px', font_family: '',
  custom_css: '', header_html: '', footer_html: '',
  login_page_title: '', login_page_subtitle: '', login_bg_image: '',
  footer_text: '',
})

const colorFields = [
  { key: 'primary_color', label: '主色调' },
  { key: 'secondary_color', label: '辅助色' },
  { key: 'background_color', label: '背景色' },
  { key: 'text_color', label: '文字颜色' },
  { key: 'link_color', label: '链接颜色' },
  { key: 'header_bg_color', label: '顶部背景' },
  { key: 'sidebar_bg_color', label: '侧边栏背景' },
  { key: 'sidebar_text_color', label: '侧边栏文字' },
]

const previewStyle = computed(() => ({
  '--brand-primary': form.primary_color,
  '--brand-secondary': form.secondary_color,
  '--brand-bg': form.background_color,
  '--brand-text': form.text_color,
  borderRadius: form.button_radius,
}))

async function loadConfig() {
  loading.value = true
  try {
    const { data } = await brandingApi.show()
    if (data?.config) {
      Object.assign(form, data.config)
    }
  } catch (e) { console.error(e) } finally { loading.value = false }
}

async function loadThemes() {
  try {
    const { data } = await brandingApi.themeTemplates()
    themes.value = Array.isArray(data?.data) ? data.data : []
  } catch { }
}

async function applyTheme(t) {
  activeTheme.value = t.id
  form.primary_color = t.primary_color
  form.secondary_color = t.secondary_color
  form.background_color = t.background_color
  form.text_color = t.text_color
  form.sidebar_bg_color = t.sidebar_bg_color
  await saveConfig()
  ElMessage.success(`已应用主题「${t.name}」`)
}

async function saveConfig() {
  saving.value = true
  try {
    await brandingApi.update({ ...form })
    ElMessage.success('已保存')
  } catch {
    ElMessage.error('保存失败')
  } finally { saving.value = false }
}

async function handleLogoUpload(file) {
  const url = await uploadImage(file, 'logo')
  if (url) { form.logo_url = url; await saveConfig() }
}
async function handleFaviconUpload(file) {
  const url = await uploadImage(file, 'logo')
  if (url) { form.favicon_url = url; await saveConfig() }
}
async function handleBgUpload(file) {
  const url = await uploadImage(file, 'logo')
  if (url) { form.login_bg_image = url; await saveConfig() }
}

async function uploadImage(file, type) {
  const formData = new FormData()
  formData.append('file', file.raw)
  formData.append('type', type)
  try {
    const { data } = await brandingApi.uploadImage(formData)
    return data?.data?.url || ''
  } catch { ElMessage.error('上传失败'); return '' }
}

async function resetConfig() {
  try {
    await brandingApi.reset()
    ElMessage.success('已重置')
    await loadConfig()
  } catch { ElMessage.error('重置失败') }
}

onMounted(() => {
  loadConfig()
  loadThemes()
})
</script>

<style scoped>
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
.theme-card { cursor: pointer; border: 2px solid #e4e7ed; border-radius: 8px; padding: 8px; text-align: center; transition: all 0.2s; }
.theme-card:hover { border-color: #409eff; }
.theme-card.active { border-color: #409eff; background: #ecf5ff; }
.theme-preview { display: flex; height: 50px; border-radius: 4px; overflow: hidden; margin-bottom: 6px; }
.tp-sidebar { width: 30%; }
.tp-main { flex: 1; display: flex; flex-direction: column; }
.tp-header { height: 10px; }
.tp-content { flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px; background: #fff; }
.tp-dot { width: 8px; height: 8px; border-radius: 50%; }
.theme-name { font-size: 12px; color: #606266; }

.color-picker-row { display: flex; align-items: center; }
.upload-row { display: flex; align-items: center; gap: 8px; width: 100%; }
.upload-preview { height: 32px; border-radius: 4px; border: 1px solid #e4e7ed; flex-shrink: 0; }

.preview-frame { border: 1px solid #e4e7ed; border-radius: 8px; overflow: hidden; font-size: 12px; }
.pf-header { padding: 8px 12px; }
.pf-brand { font-weight: 600; font-size: 14px; }
.pf-body { display: flex; min-height: 200px; }
.pf-sidebar { width: 80px; padding: 8px 4px; }
.pf-menu-item { padding: 4px 6px; margin-bottom: 2px; border-radius: 3px; font-size: 11px; cursor: default; }
.pf-content { flex: 1; padding: 12px; }
.pf-card { background: #fff; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.pf-stat { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
.pf-desc { font-size: 12px; margin-bottom: 12px; }
</style>
