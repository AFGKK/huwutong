<template>
  <div class="zapier-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Connection /></el-icon>
        Zapier / Make 无代码集成
      </h2>
      <div class="header-actions">
        <el-tag v-if="dashboard.enabled" type="success" effect="dark" size="small">已启用</el-tag>
        <el-tag v-else type="info" size="small">已禁用</el-tag>
        <el-button @click="refreshAll" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="发布到 Zapier/Make 平台 + 预建 12 个常用工作流模板 + 触发/动作定义，让非技术用户也能自动化业务流程"
      type="success" show-icon :closable="false" class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.template_count }}</div>
          <div class="stat-label">预建工作流模板</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.has_api_key ? 'text-green' : 'text-red'">
            {{ dashboard.has_api_key ? '✅' : '❌' }}
          </div>
          <div class="stat-label">API Key 已配置</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ categoryCount }}</div>
          <div class="stat-label">分类数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ triggersCount }}/{{ actionsCount }}</div>
          <div class="stat-label">触发器/动作</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 工作流模板 -->
        <el-tab-pane label="工作流模板" name="templates">
          <div class="section-toolbar">
            <el-select v-model="templateFilter.category" placeholder="分类筛选" clearable style="width:160px" @change="filterTemplates">
              <el-option v-for="(count,cat) in dashboard.categories" :key="cat" :label="cat + ' (' + count + ')'" :value="cat" />
            </el-select>
          </div>

          <el-row :gutter="16">
            <el-col :span="8" v-for="t in filteredTemplates" :key="t.id" class="mb-3">
              <el-card shadow="hover" class="template-card">
                <template #header>
                  <div class="template-header">
                    <el-tag size="small" :type="t.platform === 'both' ? 'success' : 'primary'">{{ t.platform }}</el-tag>
                    <el-tag size="small" type="info">{{ t.category }}</el-tag>
                  </div>
                </template>
                <h4 class="template-title">{{ t.title }}</h4>
                <p class="template-desc">{{ t.description }}</p>
                <el-divider />
                <div class="template-meta">
                  <div><strong>触发器:</strong> {{ t.trigger }}</div>
                  <div><strong>动作:</strong></div>
                  <ul class="action-list">
                    <li v-for="a in t.actions" :key="a">{{ a }}</li>
                  </ul>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- Tab 2: 部署配置 -->
        <el-tab-pane label="部署配置" name="deploy">
          <el-row :gutter="16">
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>Zapier 集成</span></template>
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item label="状态">✅ 已就绪</el-descriptions-item>
                  <el-descriptions-item label="App 定义">{{ dashboard.zapier_app_dir }}index.json</el-descriptions-item>
                  <el-descriptions-item label="API Key">
                    <el-tag :type="dashboard.has_api_key ? 'success' : 'danger'" size="small">
                      {{ dashboard.has_api_key ? '已配置' : '未配置 (设置 ZAPIER_API_KEY)' }}
                    </el-tag>
                  </el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 class="mb-2">发布步骤</h4>
                <el-steps direction="vertical" :active="-1" size="small">
                  <el-step title="安装 Zapier CLI" description="npm install -g zapier-platform-cli" />
                  <el-step title="登录 Zapier" description="cd deploy/zapier-app && zapier login" />
                  <el-step title="推送 App" description="zapier push" />
                  <el-step title="发布到市场" description="在 Zapier 平台提交审核" />
                </el-steps>
              </el-card>
            </el-col>
            <el-col :span="12">
              <el-card shadow="never">
                <template #header><span>Make.com 集成</span></template>
                <el-descriptions :column="1" border size="small">
                  <el-descriptions-item label="状态">✅ 已就绪</el-descriptions-item>
                  <el-descriptions-item label="App 定义">{{ dashboard.make_app_dir }}index.json</el-descriptions-item>
                  <el-descriptions-item label="API Key">
                    <el-tag :type="dashboard.has_api_key ? 'success' : 'danger'" size="small">
                      {{ dashboard.has_api_key ? '已配置' : '未配置' }}
                    </el-tag>
                  </el-descriptions-item>
                </el-descriptions>
                <el-divider />
                <h4 class="mb-2">发布步骤</h4>
                <el-steps direction="vertical" :active="-1" size="small">
                  <el-step title="登录 Make.com" description="在 make.com 创建开发者账户" />
                  <el-step title="导入 App 定义" :description="'导入 deploy/make-app/index.json'" />
                  <el-step title="配置 API Key" description="输入 HWT License API Key" />
                  <el-step title="发布到市场" description="在 Make 平台提交审核" />
                </el-steps>
              </el-card>
            </el-col>
          </el-row>
        </el-tab-pane>

        <!-- Tab 3: API 端点 -->
        <el-tab-pane label="API 端点" name="endpoints">
          <el-table :data="endpointList" stripe size="small">
            <el-table-column label="平台" width="80">
              <template #default="{ row }"><el-tag :type="row.platform === 'Zapier' ? 'success' : 'primary'" size="small">{{ row.platform }}</el-tag></template>
            </el-table-column>
            <el-table-column label="类型" width="80">
              <template #default="{ row }"><el-tag size="small">{{ row.type }}</el-tag></template>
            </el-table-column>
            <el-table-column label="方法" width="70">
              <template #default="{ row }"><el-tag :type="row.method === 'GET' ? 'info' : 'warning'" size="small">{{ row.method }}</el-tag></template>
            </el-table-column>
            <el-table-column prop="endpoint" label="端点" min-width="350">
              <template #default="{ row }"><code>{{ row.endpoint }}</code></template>
            </el-table-column>
            <el-table-column prop="description" label="说明" min-width="200" />
          </el-table>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { Connection, Refresh } from '@element-plus/icons-vue';
import zapierApi from '@/api/zapier';

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
  { platform: 'Zapier', type: '触发器', method: 'GET', endpoint: '/api/zapier/triggers/new_license', description: '新 License 创建' },
  { platform: 'Zapier', type: '触发器', method: 'GET', endpoint: '/api/zapier/triggers/expiring_licenses', description: 'License 即将到期' },
  { platform: 'Zapier', type: '触发器', method: 'GET', endpoint: '/api/zapier/triggers/new_customer', description: '新客户创建' },
  { platform: 'Zapier', type: '触发器', method: 'GET', endpoint: '/api/zapier/triggers/license_activated', description: 'License 激活' },
  { platform: 'Zapier', type: '动作', method: 'POST', endpoint: '/api/zapier/actions/create_license', description: '创建 License' },
  { platform: 'Zapier', type: '动作', method: 'POST', endpoint: '/api/zapier/actions/suspend_license', description: '挂起 License' },
  { platform: 'Zapier', type: '动作', method: 'POST', endpoint: '/api/zapier/actions/revoke_license', description: '吊销 License' },
  { platform: 'Zapier', type: '搜索', method: 'POST', endpoint: '/api/zapier/searches/find_license', description: '查找 License' },
  { platform: 'Zapier', type: '资源', method: 'GET', endpoint: '/api/zapier/resources/products', description: '产品列表' },
  { platform: 'Zapier', type: '资源', method: 'GET', endpoint: '/api/zapier/resources/customers', description: '客户列表' },
  { platform: 'Make', type: '触发器', method: 'GET', endpoint: '/api/make/triggers/licenses', description: '新 License' },
  { platform: 'Make', type: '触发器', method: 'GET', endpoint: '/api/make/triggers/expiring-licenses', description: '即将到期' },
  { platform: 'Make', type: '触发器', method: 'GET', endpoint: '/api/make/triggers/customers', description: '新客户' },
  { platform: 'Make', type: '动作', method: 'POST', endpoint: '/api/make/actions/create-license', description: '创建 License' },
  { platform: 'Make', type: '动作', method: 'POST', endpoint: '/api/make/actions/suspend-license', description: '挂起' },
  { platform: 'Make', type: '搜索', method: 'POST', endpoint: '/api/make/searches/find-license', description: '查找' },
]);

const categoryCount = computed(() => Object.keys(dashboard.categories).length);
const triggersCount = computed(() => 4); // 4 triggers
const actionsCount = computed(() => 3); // 3 actions

function filterTemplates() {
  if (!templateFilter.category) {
    filteredTemplates.value = templates.value;
  } else {
    filteredTemplates.value = templates.value.filter(t => t.category === templateFilter.category);
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
    ElMessage.error('加载失败');
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
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #409eff; }
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
