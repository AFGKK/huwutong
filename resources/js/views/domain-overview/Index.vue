<template>
  <div class="domain-overview-page">
    <el-page-header content="域名管理总览" @back="$router.back()" />

    <!-- 平台域名配置 -->
    <el-card shadow="never" class="mb-4">
      <template #header><span>平台域名配置</span></template>
      <el-form :inline="true" :model="platformForm" size="small" label-width="140px">
        <el-form-item label="权威域名"><el-input v-model="platformForm.canonical_domain" style="width:320px" /></el-form-item>
        <el-form-item label="CDN 域名"><el-input v-model="platformForm.cdn_url" style="width:320px" /></el-form-item>
        <el-form-item label="CDN 加速"><el-switch v-model="platformForm.cdn_enabled" /></el-form-item>
        <el-form-item><el-button type="primary" @click="savePlatform">保存</el-button></el-form-item>
      </el-form>
    </el-card>

    <!-- 统计卡片（可点击跳转） -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="4"><el-card shadow="hover" @click="$router.push('/domains')">
        <div class="stat-value">{{ stats.custom_domains?.total ?? 0 }}</div>
        <div class="stat-label">自定义域名 →</div>
      </el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" @click="$router.push('/domains')">
        <div class="stat-value text-success">{{ stats.custom_domains?.active ?? 0 }}</div>
        <div class="stat-label">已生效 →</div>
      </el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" @click="$router.push('/domains')">
        <div class="stat-value text-warning">{{ stats.custom_domains?.pending ?? 0 }}</div>
        <div class="stat-label">待验证 →</div>
      </el-card></el-col>
      <el-col :span="4"><el-card shadow="hover" @click="$router.push('/domain-whitelist')">
        <div class="stat-value text-danger">{{ stats.custom_domains?.failed ?? 0 }}</div>
        <div class="stat-label">异常 →</div>
      </el-card></el-col>
      <el-col :span="4"><el-card shadow="never">
        <div class="stat-value text-primary">{{ stats.tenants?.with_domain ?? 0 }}/{{ stats.tenants?.total ?? 0 }}</div>
        <div class="stat-label">租户已配域名</div>
      </el-card></el-col>
      <el-col :span="4"><el-card shadow="never">
        <div class="stat-value">{{ stats.ssl?.issued ?? 0 }}</div>
        <div class="stat-label">SSL<el-tag v-if="stats.ssl?.expiring_soon>0" type="warning" size="small" style="margin-left:4px">{{stats.ssl.expiring_soon}}将过期</el-tag></div>
      </el-card></el-col>
    </el-row>

    <!-- 域名列表 + 批量操作 -->
    <el-card shadow="never" class="mb-4">
      <template #header>
        <span>域名列表</span>
        <div style="float:right;display:flex;gap:8px">
          <el-button v-if="selectedIds.length>0" type="warning" size="small" @click="batchRenewSsl">续期 SSL ({{selectedIds.length}})</el-button>
          <el-select v-model="filterStatus" clearable placeholder="过滤" size="small" style="width:120px" @change="page=1;loadDomainList()">
            <el-option label="全部" value="" /><el-option label="已生效" value="active" /><el-option label="已验证" value="verified" /><el-option label="待验证" value="pending" /><el-option label="失败" value="failed" />
          </el-select>
          <el-input v-model="searchKeyword" placeholder="搜索域名" size="small" style="width:180px" clearable @keyup.enter="page=1;loadDomainList()" />
          <el-button size="small" @click="page=1;loadDomainList()">搜索</el-button>
        </div>
      </template>
      <el-table :data="domainList" v-loading="loadingList" stripe @selection-change="selectedIds = $event.map(r=>r.id)">
        <el-table-column type="selection" width="40" />
        <el-table-column prop="domain" label="域名" min-width="180">
          <template #default="{row}"><a :href="`//${row.domain}`" target="_blank" class="domain-link">{{row.domain}}</a></template>
        </el-table-column>
        <el-table-column prop="tenant.name" label="租户" width="110" />
        <el-table-column label="DNS" width="65" align="center">
          <template #default="{row}"><el-tag :type="row.dns_resolved?'success':'danger'" size="small">{{row.dns_resolved?'✓':'✗'}}</el-tag></template>
        </el-table-column>
        <el-table-column label="SSL" width="100" align="center">
          <template #default="{row}">
            <el-tag v-if="row.ssl_status==='issued'&&row.ssl_days_left>0" :type="row.ssl_days_left<30?'warning':'success'" size="small">{{row.ssl_days_left}}天</el-tag>
            <el-tag v-else-if="row.ssl_status==='renewing'" type="warning" size="small">续期中</el-tag>
            <span v-else class="text-muted">-</span>
          </template>
        </el-table-column>
        <el-table-column label="健康" width="80" align="center">
          <template #default="{row}">
            <el-tag v-if="row.health==='healthy'" type="success" size="small">正常</el-tag>
            <el-tag v-else-if="row.health==='ssl_expiring_soon'" type="warning" size="small">SSL将过期</el-tag>
            <el-tag v-else-if="row.health==='dns_error'" type="danger" size="small">DNS异常</el-tag>
            <el-tag v-else type="info" size="small">{{row.health}}</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{row}">
            <el-button v-if="row.ssl_status==='issued'" text size="small" @click="renewSsl(row)">续期SSL</el-button>
            <el-button text size="small" @click="$router.push('/domains')">详情</el-button>
          </template>
        </el-table-column>
      </el-table>
      <div class="mt-3 flex justify-end">
        <el-pagination v-model:current-page="page" :page-size="20" :total="totalDomains" layout="prev,pager,next" small background @current-change="loadDomainList" />
      </div>
    </el-card>

    <!-- 待办事项 + 最近绑定 -->
    <el-row :gutter="16">
      <el-col :span="14">
        <el-card shadow="never">
          <template #header><span>待办事项</span></template>
          <div v-if="todoList.length===0" class="text-muted" style="padding:20px;text-align:center">全部正常</div>
          <div v-for="item in todoList" :key="item.type" class="todo-item" style="cursor:pointer" @click="item.route && $router.push(item.route)">
            <el-tag :type="item.severity" size="small" style="margin-right:8px">{{item.label}}</el-tag>
            <span>{{item.message}}</span>
            <el-tag type="info" size="small" style="margin-left:auto">{{item.count}}</el-tag>
            <el-icon v-if="item.route" style="margin-left:4px"><ArrowRight /></el-icon>
          </div>
        </el-card>
      </el-col>
      <el-col :span="10">
        <el-card shadow="never">
          <template #header><span>最近绑定</span></template>
          <el-table :data="stats.custom_domains?.recent" stripe empty-text="暂无" size="small">
            <el-table-column prop="domain" label="域名" min-width="140" />
            <el-table-column prop="tenant.name" label="租户" width="100" />
            <el-table-column prop="created_at" label="时间" width="130" />
          </el-table>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { ArrowRight } from '@element-plus/icons-vue'
import request from '@/utils/request'

const loadingList = ref(false)
const platformForm = reactive({ canonical_domain: '', cdn_url: '', cdn_enabled: false })
const stats = ref({})
const domainList = ref([])
const page = ref(1)
const totalDomains = ref(0)
const filterStatus = ref('')
const searchKeyword = ref('')
const selectedIds = ref([])

const todoList = computed(() => {
  const s = stats.value; const items = []
  if (s.custom_domains?.pending > 0) items.push({ type:'pending', label:'待验证', message:'域名待完成 DNS 验证', count:s.custom_domains.pending, severity:'warning', route:'/domains' })
  if (s.custom_domains?.failed > 0) items.push({ type:'failed', label:'异常', message:'域名验证失败需处理', count:s.custom_domains.failed, severity:'danger', route:'/domains' })
  if (s.custom_domains?.expired > 0) items.push({ type:'expired', label:'过期', message:'域名绑定已过期', count:s.custom_domains.expired, severity:'danger', route:'/domains' })
  if (s.ssl?.expiring_soon > 0) items.push({ type:'ssl', label:'SSL', message:'证书即将过期', count:s.ssl.expiring_soon, severity:'warning' })
  if (s.ssl?.failed > 0) items.push({ type:'ssl-fail', label:'SSL', message:'证书签发失败', count:s.ssl.failed, severity:'danger' })
  if (s.tenants?.without_domain > 0) items.push({ type:'tenant', label:'租户', message:'租户未配域名', count:s.tenants.without_domain, severity:'info' })
  return items
})

async function loadOverview() {
  try {
    const res = await request.get('/domain-overview/')
    stats.value = res.data ?? {}
    const p = res.data?.platform ?? {}
    platformForm.canonical_domain = p.canonical_domain || ''
    platformForm.cdn_url = p.cdn_url || ''
    platformForm.cdn_enabled = !!p.cdn_enabled
  } catch {}
}

async function loadDomainList() {
  loadingList.value = true; selectedIds.value = []
  try {
    const params = { page:page.value, per_page:20 }
    if (filterStatus.value) params.status = filterStatus.value
    if (searchKeyword.value) params.search = searchKeyword.value
    const res = await request.get('/domain-overview/domains', { params })
    domainList.value = res.data?.data ?? []
    totalDomains.value = res.data?.total ?? 0
  } catch { domainList.value=[]; totalDomains.value=0 }
  finally { loadingList.value = false }
}

async function savePlatform() {
  try { await request.put('/domain-overview/platform', platformForm); ElMessage.success('已保存') } catch {}
}

async function renewSsl(row) {
  try { await request.post(`/domain-overview/domains/${row.id}/renew-ssl`); ElMessage.success('SSL 续期已提交') } catch {}
}

async function batchRenewSsl() {
  try {
    const res = await request.post('/domain-overview/domains/batch-renew-ssl', { domain_ids: selectedIds.value })
    ElMessage.success(res.message || '批量续期已提交')
    loadDomainList()
  } catch {}
}

onMounted(() => { loadOverview(); loadDomainList() })
</script>

<style scoped>
.domain-overview-page { padding: 16px; }
.mb-4 { margin-bottom: 16px; }
.stat-value { font-size: 26px; font-weight: 700; line-height: 1.2; }
.stat-label { font-size: 13px; color: #909399; margin-top: 4px; display:flex;align-items:center; }
.text-success { color: #67c23a; } .text-warning { color: #e6a23c; }
.text-danger { color: #f56c6c; } .text-primary { color: #409eff; }
.text-muted { color: #c0c4cc; }
.todo-item { display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f0f0f0; }
.todo-item:last-child { border-bottom: none; }
.todo-item:hover { background:#f5f7fa; }
.domain-link { color: #409eff; text-decoration: none; }
.domain-link:hover { text-decoration: underline; }
.mt-3 { margin-top: 12px; }
</style>
