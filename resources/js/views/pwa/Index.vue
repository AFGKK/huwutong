<template>
  <div class="pwa-page">
    <div class="page-header">
      <h2>
        <el-icon style="vertical-align:middle;margin-right:8px"><Monitor /></el-icon>
        PWA 移动端管理
      </h2>
      <div class="header-actions">
        <el-tag v-if="dashboard.enabled" type="success" effect="dark" size="small">已启用</el-tag>
        <el-tag v-else type="info" effect="dark" size="small">已禁用</el-tag>
        <el-button @click="loadDashboard" :loading="loading" size="small">
          <el-icon><Refresh /></el-icon> 刷新
        </el-button>
      </div>
    </div>

    <el-alert
      title="PWA 渐进式 Web 应用 — 比 Flutter App 更快触达用户，离线可用，可添加到主屏幕"
      type="info" show-icon :closable="false" class="mb-4"
    />

    <!-- 状态卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.service_worker?.registered ? 'text-success' : 'text-danger'">
            {{ dashboard.service_worker?.registered ? '✅' : '❌' }}
          </div>
          <div class="stat-label">Service Worker</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" :class="dashboard.manifest?.exists ? 'text-success' : 'text-danger'">
            {{ dashboard.manifest?.exists ? '✅' : '❌' }}
          </div>
          <div class="stat-label">Manifest</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.push_notifications?.subscribers || 0 }}</div>
          <div class="stat-label">推送订阅数</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value">{{ dashboard.caching?.strategy }}</div>
          <div class="stat-label">缓存策略</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 主 Tabs -->
    <el-card shadow="hover">
      <el-tabs v-model="activeTab">
        <!-- Tab 1: 概览 -->
        <el-tab-pane label="概览" name="overview">
          <el-descriptions :column="2" border>
            <el-descriptions-item label="应用名称">{{ dashboard.manifest?.name }}</el-descriptions-item>
            <el-descriptions-item label="简称">{{ dashboard.manifest?.short_name }}</el-descriptions-item>
            <el-descriptions-item label="主题色">
              <span :style="{ color: dashboard.manifest?.theme_color }">{{ dashboard.manifest?.theme_color }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="显示模式">{{ dashboard.manifest?.display }}</el-descriptions-item>
            <el-descriptions-item label="SW 版本">{{ dashboard.service_worker?.cache_version }}</el-descriptions-item>
            <el-descriptions-item label="SW 作用域">{{ dashboard.service_worker?.scope }}</el-descriptions-item>
            <el-descriptions-item label="API 缓存 TTL">{{ dashboard.caching?.api_cache_ttl }}s</el-descriptions-item>
            <el-descriptions-item label="离线回退页">{{ dashboard.offline?.fallback_page }}</el-descriptions-item>
          </el-descriptions>
        </el-tab-pane>

        <!-- Tab 2: 推送通知 -->
        <el-tab-pane label="推送通知" name="push">
          <div class="section-toolbar">
            <span class="text-gray mb-2">VAPID 状态: {{ dashboard.push_notifications?.configured ? '✅ 已配置' : '❌ 未配置 (需设置 PWA_VAPID_PUBLIC_KEY)' }}</span>
          </div>

          <el-form :model="pushForm" label-width="80px" class="mb-4" @submit.prevent="sendPush">
            <el-form-item label="标题">
              <el-input v-model="pushForm.title" placeholder="通知标题" maxlength="100" />
            </el-form-item>
            <el-form-item label="内容">
              <el-input v-model="pushForm.body" type="textarea" :rows="3" placeholder="通知正文" maxlength="500" />
            </el-form-item>
            <el-form-item label="链接">
              <el-input v-model="pushForm.url" placeholder="/build/notifications" />
            </el-form-item>
            <el-form-item>
              <el-button type="primary" native-type="submit" :loading="pushSending">
                <el-icon><Bell /></el-icon> 发送推送通知
              </el-button>
              <span class="ml-2 text-gray">共 {{ dashboard.push_notifications?.subscribers }} 个订阅用户</span>
            </el-form-item>
          </el-form>

          <!-- 订阅列表 -->
          <h4 class="mb-2">订阅列表</h4>
          <el-table :data="subscriptions" stripe size="small" v-loading="subLoading">
            <el-table-column prop="endpoint_prefix" label="EndPoint" min-width="250" />
            <el-table-column prop="user_agent" label="User Agent" min-width="200" show-overflow-tooltip />
            <el-table-column prop="subscribed_at" label="订阅时间" width="180" />
          </el-table>
        </el-tab-pane>

        <!-- Tab 3: 缓存管理 -->
        <el-tab-pane label="缓存管理" name="cache">
          <el-alert
            title="清除浏览器缓存会导致 Service Worker 重新下载所有资源"
            type="warning" show-icon :closable="false" class="mb-3"
          />
          <el-button type="danger" @click="handleClearCache" :loading="clearing">
            <el-icon><Delete /></el-icon> 清除所有缓存
          </el-button>
          <el-divider />
          <el-button type="warning" @click="handleUpdateWorker" :loading="updating">
            <el-icon><Refresh /></el-icon> 更新 Service Worker 版本
          </el-button>
          <p class="text-gray mt-2" style="font-size:0.85em">
            当前 SW 版本: {{ dashboard.stats?.last_sw_update }} | 
            估算缓存大小: {{ formatBytes(dashboard.stats?.estimated_cache_size || 0) }}
          </p>
        </el-tab-pane>

        <!-- Tab 4: 部署指引 -->
        <el-tab-pane label="部署指引" name="guide">
          <el-steps direction="vertical" :active="-1">
            <el-step title="确认配置文件" description="检查 config/pwa.php 中的设置是否满足需求" />
            <el-step title="配置 VAPID 密钥" description="设置 PWA_VAPID_PUBLIC_KEY 和 PWA_VAPID_PRIVATE_KEY 到 .env（推送通知需要）" />
            <el-step title="生成 PWA 图标" description="在 public/build/assets/ 下放置 pwa-icon-192.png / pwa-icon-512.png / pwa-badge.png" />
            <el-step title="验证 Manifest" description="访问 /manifest.json 确认 JSON 格式正确" />
            <el-step title="注册 Service Worker" description="访问任意页面，查看控制台确认 SW 注册成功" />
            <el-step title="测试离线" description="DevTools → Network → Offline，刷新页面验证离线回退" />
            <el-step title="Lighthouse 审计" description="使用 Chrome Lighthouse 生成 PWA 合规报告" />
          </el-steps>
        </el-tab-pane>
      </el-tabs>
    </el-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Monitor, Refresh, Bell, Delete } from '@element-plus/icons-vue';
import pwaApi from '@/api/pwa';

const loading = ref(false);
const activeTab = ref('overview');
const dashboard = ref({});
const subscriptions = ref([]);
const subLoading = ref(false);
const pushSending = ref(false);
const clearing = ref(false);
const updating = ref(false);

const pushForm = ref({ title: '', body: '', url: '' });

function formatBytes(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(1024));
  return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
}

async function loadDashboard() {
  loading.value = true;
  try {
    const { data } = await pwaApi.getDashboard();
    if (data.success) dashboard.value = data.data;
  } catch {
    ElMessage.error('加载失败');
  } finally {
    loading.value = false;
  }
}

async function loadSubscriptions() {
  subLoading.value = true;
  try {
    const { data } = await pwaApi.getSubscriptions();
    if (data.success) subscriptions.value = data.data;
  } finally {
    subLoading.value = false;
  }
}

async function sendPush() {
  if (!pushForm.value.title || !pushForm.value.body) {
    ElMessage.warning('请输入标题和内容');
    return;
  }

  pushSending.value = true;
  try {
    const { data } = await pwaApi.sendNotification(pushForm.value);
    if (data.success) {
      ElMessage.success(data.message);
      pushForm.value = { title: '', body: '', url: '' };
    }
  } catch {
    ElMessage.error('发送失败');
  } finally {
    pushSending.value = false;
  }
}

async function handleClearCache() {
  try {
    await ElMessageBox.confirm('确定要清除所有缓存吗？', '确认', {
      type: 'warning', confirmButtonText: '确定', cancelButtonText: '取消',
    });
    clearing.value = true;
    const { data } = await pwaApi.clearCache();
    if (data.success) ElMessage.success(data.message);
  } catch {
    // cancelled
  } finally {
    clearing.value = false;
  }
}

async function handleUpdateWorker() {
  updating.value = true;
  try {
    const { data } = await pwaApi.updateWorker();
    if (data.success) ElMessage.success(data.message);
    await loadDashboard();
  } catch {
    ElMessage.error('更新失败');
  } finally {
    updating.value = false;
  }
}

onMounted(async () => {
  await loadDashboard();
  if (activeTab.value === 'push') await loadSubscriptions();
});
</script>

<style scoped>
.pwa-page { padding: 0; }
.page-header {
  display: flex; justify-content: space-between;
  align-items: center; margin-bottom: 16px;
}
.page-header h2 { margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; }
.mb-4 { margin-bottom: 16px; }
.mb-3 { margin-bottom: 12px; }
.mb-2 { margin-bottom: 8px; }
.mt-2 { margin-top: 8px; }
.ml-2 { margin-left: 8px; }
.text-gray { color: #909399; }
.text-success { color: #67c23a; }
.text-danger { color: #f56c6c; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 1.8em; font-weight: 700; color: #409eff; }
.stat-card .stat-label { font-size: 0.85em; color: #909399; margin-top: 4px; }
.section-toolbar { margin-bottom: 12px; }
</style>
