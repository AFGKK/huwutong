<template>
  <div class="zapier-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        {{ t(`${P}.title`) }}
      </h2>
      <div class="header-actions">
        <el-tag v-if="dashboard.enabled" type="success" effect="dark" size="small">{{ t(`${P}.enabled`) }}</el-tag>
        <el-tag v-else type="info" size="small">{{ t(`${P}.disabled`) }}</el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> {{ t(`${P}.refresh`) }}
        </el-button>
      </div>
    </div>

    <el-alert
      :title="t(`${P}.alert`)"
      type="success" show-icon :closable="false" class="mb-4"
    />

    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.template_count }}</div>
          <div class="stat-label">{{ t(`${P}.stats.templates`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.has_api_key ? 'text-green' : 'text-red'">
            {{ dashboard.has_api_key ? t(`${P}.yes`) : t(`${P}.no`) }}
          </div>
          <div class="stat-label">{{ t(`${P}.stats.api_key`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ categoryCount }}</div>
          <div class="stat-label">{{ t(`${P}.stats.categories`) }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ triggersCount }}/{{ actionsCount }}</div>
          <div class="stat-label">{{ t(`${P}.stats.triggers_actions`) }}</div>
        </el-card>
      </el-col>
    </el-row>

    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <el-tab-pane :label="t(`${P}.tabs.templates`)" name="templates">
          <div class="section-toolbar">
            <el-select v-model="templateFilter.category" :placeholder="t(`${P}.filter_category`)" clearable style="width:160px" @change="filterTemplates">
              <el-option v-for="(count,cat) in dashboard.categories" :key="cat" :label="cat + ' (' + count + ')'" :value="cat" />
            </el-select>
          </div>

          <el-row :gutter="16">
            <el-col :span="8" v-for="tmpl in filteredTemplates" :key="tmpl.id" class="mb-3">
              <el-card shadow="hover" class="template-card">
                <template #header>
                  <div class="template-header">
                    <el-tag size="small" :type="tmpl.platform === 'both' ? 'success' : 'primary'">{{ tmpl.platform }}</el-tag>
                    <el-tag size="small" type="info">{{ tmpl.category }}</el-tag>
                  </div>
                </template>
                <h4 class="template-title">{{ tmpl.title }}</h4>
                <p class="template-desc">{{ tmpl.description }}</p>
                <el-divider />
                <div class="template-meta">
                  <div><strong>{{ t(`${P}.trigger_label`) }}</strong> {{ tmpl.trigger }}</div>
                  <div><strong>{{ t(`${P}.action_label`) }}</strong></div>
                  <ul class="action-list">
                    <li v-for="a in tmpl.actions" :key="a">{{ a }}</li>
                  </ul>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.deploy`)" name="deploy">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.zapier_integration`) }}</span></template>
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item :label="t(`${P}.status`)">{{ t(`${P}.ready`) }}</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.app_def`)">{{ dashboard.zapier_app_dir }}index.json</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.api_key_label`)">
                    <el-tag :type="dashboard.has_api_key ? 'success' : 'danger'" size="small">
                      {{ dashboard.has_api_key ? t(`${P}.configured`) : t(`${P}.not_configured_zapier`) }}
                    </el-tag>
                  </el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 class="mb-2">{{ t(`${P}.publish_steps`) }}</h4>
                <el-steps direction="vertical" :active="-1" size="small">
                  <el-step :title="t(`${P}.zapier_steps.install`)" description="npm install -g zapier-platform-cli" />
                  <el-step :title="t(`${P}.zapier_steps.login`)" description="cd deploy/zapier-app && zapier login" />
                  <el-step :title="t(`${P}.zapier_steps.push`)" description="zapier push" />
                  <el-step :title="t(`${P}.zapier_steps.publish`)" :description="t(`${P}.zapier_steps.publish_desc`)" />
                </el-steps>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>{{ t(`${P}.make_integration`) }}</span></template>
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item :label="t(`${P}.status`)">{{ t(`${P}.ready`) }}</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.app_def`)">{{ dashboard.make_app_dir }}index.json</el-descriptions-item>
                  <el-descriptions-item :label="t(`${P}.api_key_label`)">
                    <el-tag :type="dashboard.has_api_key ? 'success' : 'danger'" size="small">
                      {{ dashboard.has_api_key ? t(`${P}.configured`) : t(`${P}.not_configured`) }}
                    </el-tag>
                  </el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 class="mb-2">{{ t(`${P}.publish_steps`) }}</h4>
                <el-steps direction="vertical" :active="-1" size="small">
                  <el-step :title="t(`${P}.make_steps.login`)" :description="t(`${P}.make_steps.login_desc`)" />
                  <el-step :title="t(`${P}.make_steps.import`)" :description="t(`${P}.make_steps.import_desc`)" />
                  <el-step :title="t(`${P}.make_steps.config`)" :description="t(`${P}.make_steps.config_desc`)" />
                  <el-step :title="t(`${P}.make_steps.publish`)" :description="t(`${P}.make_steps.publish_desc`)" />
                </el-steps>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <el-tab-pane :label="t(`${P}.tabs.endpoints`)" name="endpoints">
          <el-table :data="endpointList" stripe size="small">
            <el-table-column :label="t(`${P}.cols.platform`)" width="80">
              <template #default="{ row }"><el-tag :type="row.platform === 'Zapier' ? 'success' : 'primary'" size="small">{{ row.platform }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.type`)" width="80">
              <template #default="{ row }"><el-tag size="small">{{ row.type }}</el-tag></template>
            </el-table-column>
            <el-table-column :label="t(`${P}.cols.method`)" width="70">
              <template #default="{ row }"><el-tag :type="row.method === 'GET' ? 'info' : 'warning'" size="small">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="endpoint" :label="t(`${P}.cols.endpoint`)" min-width="350">
              <template #default="{ row }"><code>{{ row.endpoint }}</code></template>
            </el-table-column>
            <el-table-column prop="description" :label="t(`${P}.cols.description`)" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage } from 'element-plus';
import { Connection, Refresh } from '@element-plus/icons-vue';
import zapierApi from '@/api/zapier';

const { t } = useI18n();
const P = 'zapier_page';

const loading = ref(false);
const activeTab = ref('templates');
const templateFilter = reactive({ category: '' });

const dashboard = reactive({
  enabled: false, has_api_key: false, template_count: 0,
  categories: {}, zapier_app_dir: '', make_app_dir: '',
});

const templates = ref([]);
const filteredTemplates = ref([]);

const endpointList = computed(() => [
  { platform: 'Zapier', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/zapier/triggers/new_license', description: t(`${P}.endpoints.new_license`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/zapier/triggers/expiring_licenses', description: t(`${P}.endpoints.expiring`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/zapier/triggers/new_customer', description: t(`${P}.endpoints.new_customer`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/zapier/triggers/license_activated', description: t(`${P}.endpoints.license_activated`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.action`), method: 'POST', endpoint: '/api/zapier/actions/create_license', description: t(`${P}.endpoints.create_license`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.action`), method: 'POST', endpoint: '/api/zapier/actions/suspend_license', description: t(`${P}.endpoints.suspend_license`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.action`), method: 'POST', endpoint: '/api/zapier/actions/revoke_license', description: t(`${P}.endpoints.revoke_license`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.search`), method: 'POST', endpoint: '/api/zapier/searches/find_license', description: t(`${P}.endpoints.find_license`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.resource`), method: 'GET', endpoint: '/api/zapier/resources/products', description: t(`${P}.endpoints.products`) },
  { platform: 'Zapier', type: t(`${P}.ep_types.resource`), method: 'GET', endpoint: '/api/zapier/resources/customers', description: t(`${P}.endpoints.customers`) },
  { platform: 'Make', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/make/triggers/licenses', description: t(`${P}.endpoints.new_license_short`) },
  { platform: 'Make', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/make/triggers/expiring-licenses', description: t(`${P}.endpoints.expiring_short`) },
  { platform: 'Make', type: t(`${P}.ep_types.trigger`), method: 'GET', endpoint: '/api/make/triggers/customers', description: t(`${P}.endpoints.new_customer_short`) },
  { platform: 'Make', type: t(`${P}.ep_types.action`), method: 'POST', endpoint: '/api/make/actions/create-license', description: t(`${P}.endpoints.create_license`) },
  { platform: 'Make', type: t(`${P}.ep_types.action`), method: 'POST', endpoint: '/api/make/actions/suspend-license', description: t(`${P}.endpoints.suspend_short`) },
  { platform: 'Make', type: t(`${P}.ep_types.search`), method: 'POST', endpoint: '/api/make/searches/find-license', description: t(`${P}.endpoints.find_short`) },
]);

const categoryCount = computed(() => Object.keys(dashboard.categories).length);
const triggersCount = computed(() => 4);
const actionsCount = computed(() => 3);

function filterTemplates() {
  if (!templateFilter.category) {
    filteredTemplates.value = templates.value;
  } else {
    filteredTemplates.value = templates.value.filter(tmpl => tmpl.category === templateFilter.category);
  }
}

async function refreshAll() {
  loading.value = true;
  try {
    const [dashRes, tmplRes] = await Promise.all([
      zapierApi.getDashboard(),
      zapierApi.getWorkflowTemplates(),
    ]);

    if (dashRes.success) Object.assign(dashboard, dashRes.data);
    if (tmplRes.success) {
      templates.value = tmplRes.data;
      filterTemplates();
    }
  } catch {
    ElMessage.error(t('messages.load_failed'));
  } finally {
    loading.value = false;
  }
}

onMounted(refreshAll);
</script>

<style scoped>
.zapier-page { padding: 0; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.text-green { color: #67c23a; }
.text-red { color: #f56c6c; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #0f172a; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { margin-bottom: 16px; }
.template-card { transition: transform 0.2s, box-shadow 0.2s; cursor: default; }
.template-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.template-header { display: flex; gap: 6px; }
.template-title { font-size: 15px; margin: 0 0 8px; min-height: 42px; }
.template-desc { font-size: 13px; color: #606266; line-height: 1.5; min-height: 40px; }
.template-meta { font-size: 13px; }
.action-list { margin: 4px 0 0 16px; padding: 0; }
.action-list li { margin-bottom: 2px; color: #606266; }
code { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 0.9em; background: #f5f7fa; padding: 1px 4px; border-radius: 3px; }
</style>
