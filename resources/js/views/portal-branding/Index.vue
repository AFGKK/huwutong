<template>
  <div class="portal-branding">
    <h2 class="mb-4">{{ t(`${P}.title`) }}</h2>

    <el-alert :title="t(`${P}.alert`)" type="info" show-icon :closable="false" class="mb-4" />

    <el-row :gutter="20">
      <el-col :span="16">
        <el-card class="mb-4">
          <template #header><span>{{ t(`${P}.quick_themes`) }}</span></template>
          <div class="theme-grid">
            <div v-for="theme in themes" :key="theme.id" class="theme-card" :class="{ active: activeTheme === theme.id }" @click="applyTheme(theme)">
              <div class="theme-preview">
                <div class="tp-sidebar" :style="{ background: theme.sidebar_bg_color }" />
                <div class="tp-main">
                  <div class="tp-header" :style="{ background: theme.primary_color }" />
                  <div class="tp-content">
                    <div class="tp-dot" :style="{ background: theme.primary_color }" />
                    <div class="tp-dot" :style="{ background: theme.secondary_color }" />
                    <div class="tp-dot" :style="{ background: theme.background_color }" />
                  </div>
                </div>
              </div>
              <div class="theme-name">{{ theme.name }}</div>
            </div>
          </div>
        </el-card>

        <el-card class="mb-4">
          <template #header><span>{{ t(`${P}.brand_info`) }}</span></template>
          <el-form :model="form" label-width="130px" size="small" v-loading="loading">
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item :label="t(`${P}.fields.brand_name`)">
                  <el-input v-model="form.brand_name" @blur="saveConfig" />
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item :label="t(`${P}.fields.brand_slogan`)">
                  <el-input v-model="form.brand_slogan" @blur="saveConfig" />
                </el-form-item>
              </el-col>
            </el-row>
            <el-row :gutter="16">
              <el-col :span="12">
                <el-form-item :label="t(`${P}.fields.logo`)">
                  <div class="upload-row">
                    <el-upload :auto-upload="false" :on-change="handleLogoUpload" accept="image/*" :show-file-list="false">
                      <el-button size="small" type="primary">{{ t(`${P}.pick_image`) }}</el-button>
                    </el-upload>
                    <el-input v-model="form.logo_url" size="small" :placeholder="t(`${P}.or_url`)" style="flex:1;min-width:0" @blur="saveConfig" />
                    <img v-if="form.logo_url" :src="form.logo_url" class="upload-preview" @error="$event.target.style.display='none'" />
                  </div>
                </el-form-item>
              </el-col>
              <el-col :span="12">
                <el-form-item :label="t(`${P}.fields.favicon`)">
                  <div class="upload-row">
                    <el-upload :auto-upload="false" :on-change="handleFaviconUpload" accept="image/*" :show-file-list="false">
                      <el-button size="small" type="primary">{{ t(`${P}.pick_image`) }}</el-button>
                    </el-upload>
                    <el-input v-model="form.favicon_url" size="small" :placeholder="t(`${P}.or_url`)" style="flex:1;min-width:0" @blur="saveConfig" />
                    <img v-if="form.favicon_url" :src="form.favicon_url" class="upload-preview" @error="$event.target.style.display='none'" />
                  </div>
                </el-form-item>
              </el-col>
            </el-row>
            <el-form-item :label="t(`${P}.fields.login_title`)">
              <el-input v-model="form.login_page_title" @blur="saveConfig" />
            </el-form-item>
            <el-form-item :label="t(`${P}.fields.login_subtitle`)">
              <el-input v-model="form.login_page_subtitle" @blur="saveConfig" />
            </el-form-item>
            <el-form-item :label="t(`${P}.fields.login_bg`)">
              <div class="upload-row">
                <el-upload :auto-upload="false" :on-change="handleBgUpload" accept="image/*" :show-file-list="false">
                  <el-button size="small" type="primary">{{ t(`${P}.pick_image`) }}</el-button>
                </el-upload>
                <el-input v-model="form.login_bg_image" size="small" :placeholder="t(`${P}.or_url`)" style="flex:1;min-width:0" @blur="saveConfig" />
                <img v-if="form.login_bg_image" :src="form.login_bg_image" class="upload-preview" @error="$event.target.style.display='none'" />
              </div>
            </el-form-item>
            <el-form-item :label="t(`${P}.fields.footer_text`)">
              <el-input v-model="form.footer_text" @blur="saveConfig" />
            </el-form-item>
            <el-form-item :label="t(`${P}.fields.font`)">
              <el-input v-model="form.font_family" @blur="saveConfig" placeholder='"Inter", sans-serif' />
            </el-form-item>
          </el-form>
        </el-card>

        <el-card class="mb-4">
          <template #header><span>{{ t(`${P}.colors`) }}</span></template>
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

        <el-card class="mb-4">
          <template #header><span>{{ t(`${P}.custom_code`) }}</span></template>
          <el-tabs v-model="codeTab">
            <el-tab-pane :label="t(`${P}.tabs.css`)" name="css">
              <el-input v-model="form.custom_css" type="textarea" :rows="8" :placeholder="t(`${P}.ph.css`)" @blur="saveConfig" />
            </el-tab-pane>
            <el-tab-pane :label="t(`${P}.tabs.header`)" name="header">
              <el-input v-model="form.header_html" type="textarea" :rows="6" :placeholder="t(`${P}.ph.header`)" @blur="saveConfig" />
            </el-tab-pane>
            <el-tab-pane :label="t(`${P}.tabs.footer`)" name="footer">
              <el-input v-model="form.footer_html" type="textarea" :rows="6" :placeholder="t(`${P}.ph.footer`)" @blur="saveConfig" />
            </el-tab-pane>
          </el-tabs>
        </el-card>
      </el-col>

      <el-col :span="8">
        <el-card class="mb-4">
          <template #header><span>{{ t(`${P}.preview`) }}</span></template>
          <div class="preview-frame" :style="previewStyle">
            <div class="pf-header" :style="{ background: form.header_bg_color, borderBottom: '1px solid #e4e7ed' }">
              <span v-if="form.logo_url" class="pf-logo"><img :src="form.logo_url" style="height:28px" /></span>
              <span v-else class="pf-brand" :style="{ color: form.primary_color }">{{ form.brand_name || t(`${P}.preview_brand`) }}</span>
            </div>
            <div class="pf-body">
              <div class="pf-sidebar" :style="{ background: form.sidebar_bg_color, color: form.sidebar_text_color }">
                <div class="pf-menu-item active" :style="{ background: form.primary_color + '22', borderLeft: '3px solid ' + form.primary_color }">{{ t(`${P}.menu.overview`) }}</div>
                <div class="pf-menu-item">{{ t(`${P}.menu.licenses`) }}</div>
                <div class="pf-menu-item">{{ t(`${P}.menu.devices`) }}</div>
                <div class="pf-menu-item">{{ t(`${P}.menu.tickets`) }}</div>
              </div>
              <div class="pf-content" :style="{ background: form.background_color }">
                <div class="pf-card" :style="{ borderRadius: form.button_radius }">
                  <div class="pf-stat" :style="{ color: form.primary_color }">{{ t(`${P}.welcome`, { name: form.brand_name || t(`${P}.default_portal`) }) }}</div>
                  <div class="pf-desc" :style="{ color: form.text_color }">{{ t(`${P}.preview_desc`) }}</div>
                  <el-button :style="{ background: form.primary_color, borderColor: form.primary_color, borderRadius: form.button_radius }" type="primary" size="small">{{ t(`${P}.view_details`) }}</el-button>
                </div>
              </div>
            </div>
          </div>
        </el-card>

        <el-card>
          <template #header><span>{{ t(`${P}.manage`) }}</span></template>
          <div class="flex flex-col gap-3">
            <el-button type="primary" :loading="saving" @click="saveConfig"><el-icon><Check /></el-icon> {{ t(`${P}.save`) }}</el-button>
            <el-popconfirm :title="t(`${P}.confirm_reset`)" @confirm="resetConfig">
              <template #reference><el-button type="danger"><el-icon><Refresh /></el-icon> {{ t(`${P}.reset`) }}</el-button></template>
            </el-popconfirm>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage } from 'element-plus'
import { Check, Refresh } from '@element-plus/icons-vue'
import brandingApi from '../../api/portalBranding'

const { t } = useI18n()
const P = 'portal_branding_page'

const loading = ref(false)
const saving = ref(false)
const themes = ref([])
const activeTheme = ref(null)
const codeTab = ref('css')

const form = reactive({
  brand_name: '', brand_slogan: '', logo_url: '', favicon_url: '',
  primary_color: '#0f172a', secondary_color: '#67c23a',
  background_color: '#f5f7fa', text_color: '#303133',
  link_color: '#0f172a', header_bg_color: '#ffffff',
  sidebar_bg_color: '#304156', sidebar_text_color: '#bfcbd9',
  button_radius: '4px', font_family: '',
  custom_css: '', header_html: '', footer_html: '',
  login_page_title: '', login_page_subtitle: '', login_bg_image: '',
  footer_text: '',
})

const colorFields = computed(() => [
  { key: 'primary_color', label: t(`${P}.color.primary`) },
  { key: 'secondary_color', label: t(`${P}.color.secondary`) },
  { key: 'background_color', label: t(`${P}.color.background`) },
  { key: 'text_color', label: t(`${P}.color.text`) },
  { key: 'link_color', label: t(`${P}.color.link`) },
  { key: 'header_bg_color', label: t(`${P}.color.header_bg`) },
  { key: 'sidebar_bg_color', label: t(`${P}.color.sidebar_bg`) },
  { key: 'sidebar_text_color', label: t(`${P}.color.sidebar_text`) },
])

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

async function applyTheme(theme) {
  activeTheme.value = theme.id
  form.primary_color = theme.primary_color
  form.secondary_color = theme.secondary_color
  form.background_color = theme.background_color
  form.text_color = theme.text_color
  form.sidebar_bg_color = theme.sidebar_bg_color
  await saveConfig()
  ElMessage.success(t(`${P}.messages.theme_applied`, { name: theme.name }))
}

async function saveConfig() {
  saving.value = true
  try {
    await brandingApi.update({ ...form })
    ElMessage.success(t(`${P}.messages.saved`))
  } catch {
    ElMessage.error(t(`${P}.messages.save_failed`))
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
  } catch { ElMessage.error(t(`${P}.messages.upload_failed`)); return '' }
}

async function resetConfig() {
  try {
    await brandingApi.reset()
    ElMessage.success(t(`${P}.messages.reset`))
    await loadConfig()
  } catch { ElMessage.error(t(`${P}.messages.reset_failed`)) }
}

onMounted(() => {
  loadConfig()
  loadThemes()
})
</script>

<style scoped>
.theme-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 12px; }
.theme-card { cursor: pointer; border: 2px solid #e4e7ed; border-radius: 8px; padding: 8px; text-align: center; transition: all 0.2s; }
.theme-card:hover { border-color: #0f172a; }
.theme-card.active { border-color: #0f172a; background: #f1f5f9; }
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
