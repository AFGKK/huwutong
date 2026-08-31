<template>
  <div class="ai-feature-page">
    <el-page-header :content="t('sdk_generator_page.title')" @back="$router.push('/ai')" />
    <p class="text-muted" style="margin:8px 0 20px">{{ t('sdk_generator_page.desc') }}</p>

    <el-card shadow="hover" style="margin-bottom:20px">
      <el-form :inline="true" :model="form">
        <el-form-item :label="t('sdk_generator_page.language')">
          <el-select v-model="form.language" style="width:160px" @change="onLanguageChange">
            <el-option v-for="opt in options" :key="opt.language" :label="opt.language" :value="opt.language" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('sdk_generator_page.framework')">
          <el-select v-model="form.framework" style="width:160px">
            <el-option v-for="fw in currentFrameworks" :key="fw" :label="fw" :value="fw" />
          </el-select>
        </el-form-item>
        <el-form-item label="License Key">
          <el-input v-model="form.license_key" :placeholder="t('sdk_generator_page.optional')" style="width:240px" />
        </el-form-item>
        <el-form-item>
          <el-button type="primary" :loading="generating" @click="generate">
            <el-icon><MagicStick /></el-icon> {{ t('sdk_generator_page.generate') }}
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <el-card v-if="result" shadow="hover">
      <template #header>
        <span>{{ t('sdk_generator_page.result') }}</span>
        <el-tag size="small" style="margin-left:8px">{{ form.language }}/{{ form.framework }}</el-tag>
      </template>

      <el-tabs>
        <el-tab-pane :label="t('sdk_generator_page.tabs.install')" name="install">
          <pre class="code-pre" v-if="result.install_command">$ {{ result.install_command }}</pre>
          <el-empty v-else :description="t('sdk_generator_page.none')" />
        </el-tab-pane>
        <el-tab-pane :label="t('sdk_generator_page.tabs.init')" name="init">
          <pre class="code-pre">{{ result.init_code || result.setup_guide || t('sdk_generator_page.none') }}</pre>
          <el-button size="small" style="margin-top:8px" @click="copyText(result.init_code || result.setup_guide || '')">{{ t('actions.copy') }}</el-button>
        </el-tab-pane>
        <el-tab-pane :label="t('sdk_generator_page.tabs.activate')" name="activate">
          <pre class="code-pre">{{ result.activate_code || t('sdk_generator_page.none') }}</pre>
        </el-tab-pane>
        <el-tab-pane :label="t('sdk_generator_page.tabs.validate')" name="validate">
          <pre class="code-pre">{{ result.validate_code || t('sdk_generator_page.none') }}</pre>
        </el-tab-pane>
        <el-tab-pane v-if="result.full_example" :label="t('sdk_generator_page.tabs.full')" name="full">
          <pre class="code-pre">{{ result.full_example }}</pre>
        </el-tab-pane>
      </el-tabs>
    </el-card>

    <el-empty v-if="!generating && !result" :description="t('sdk_generator_page.empty')" />
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { MagicStick } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { generateSdkConfig, getSdkOptions } from '@/api/aiIntelligence'

const { t } = useI18n()
const generating = ref(false)
const options = ref([])
const result = ref(null)
const form = ref({ language: 'php', framework: 'laravel', license_key: '' })

const currentFrameworks = computed(() => {
  const opt = options.value.find(o => o.language === form.value.language)
  return opt?.frameworks || []
})

async function loadOptions() {
  try {
    const res = await getSdkOptions()
    options.value = res.data || []
  } catch (_) { /* ignore */ }
}

function onLanguageChange() {
  const fws = currentFrameworks.value
  if (fws.length && !fws.includes(form.value.framework)) {
    form.value.framework = fws[0]
  }
}

async function generate() {
  generating.value = true
  try {
    const res = await generateSdkConfig(form.value)
    result.value = res.data
    ElMessage.success(t('sdk_generator_page.messages.generated'))
  } catch (_) { /* ignore */ }
  finally { generating.value = false }
}

function copyText(text) {
  navigator.clipboard.writeText(text).then(() => ElMessage.success(t('sdk_generator_page.messages.copied')))
}

loadOptions()
</script>
<style scoped>
.ai-feature-page { padding: 20px; }
.text-muted { color: #909399; font-size: 14px; }
.code-pre { background: #f5f7fa; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 13px; line-height: 1.5; max-height: 400px; overflow-y: auto; white-space: pre-wrap; }
</style>
