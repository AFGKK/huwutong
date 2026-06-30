<template>
  <div class="csm-page">
    <el-card shadow="never" class="mb-4">
      <el-row justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">客户成功仪表盘 (CSM)</span>
          <span class="text-gray-400 text-sm ml-4">{{ dashboard?.total_customers || 0 }} 客户</span>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button size="small" @click="batchCalculateHealth" :loading="batchCalcLoading">批量计算健康分</el-button>
          <el-button size="small" @click="createReminders" :loading="reminderLoading">生成续费提醒</el-button>
          <el-button size="small" type="primary" @click="loadAll">刷新</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 健康分布卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #67c23a">{{ healthCount('healthy') }}</div>
          <div class="stat-label">健康客户</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #e6a23c">{{ healthCount('attention') }}</div>
          <div class="stat-label">需关注</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #f56c6c">{{ healthCount('at_risk') }}</div>
          <div class="stat-label">高风险</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #909399">{{ healthCount('churned') }}</div>
          <div class="stat-label">已流失</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 任务概览 -->
    <el-row :gutter="16" class="mb-4" v-if="dashboard?.task_stats">
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#409eff">{{ dashboard.task_stats.total_open || 0 }}</div><div class="stat-label">待办任务</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#f56c6c">{{ dashboard.task_stats.overdue || 0 }}</div><div class="stat-label">逾期任务</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#e6a23c">{{ dashboard.task_stats.high_priority || 0 }}</div><div class="stat-label">高优先级</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#67c23a">{{ dashboard.task_stats.completed_today || 0 }}</div><div class="stat-label">今日完成</div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <!-- 概览 -->
      <el-tab-pane label="概览" name="overview">
        <el-row :gutter="16">
          <el-col :span="14">
            <el-card shadow="never" class="mb-4">
              <template #header><span>健康评分趋势</span></template>
              <CsmHealthTrendChart :points="healthTrend" :loading="trendLoading" />
            </el-card>
          </el-col>
          <el-col :span="10">
            <el-card shadow="never">
              <template #header><span>续费预测灯号</span></template>
              <div class="risk-lights" v-if="renewalCalendar.summary">
                <div class="risk-light green"><span class="dot"></span><span>绿灯 {{ renewalCalendar.summary.green || 0 }}</span><small>健康/低风险</small></div>
                <div class="risk-light yellow"><span class="dot"></span><span>黄灯 {{ renewalCalendar.summary.yellow || 0 }}</span><small>需关注</small></div>
                <div class="risk-light red"><span class="dot"></span><span>红灯 {{ renewalCalendar.summary.red || 0 }}</span><small>高风险/临近到期</small></div>
              </div>
              <el-empty v-else description="暂无续费数据" :image-size="60" />
            </el-card>
          </el-col>
        </el-row>
        <el-card shadow="never">
          <template #header><span>最近活动</span></template>
          <el-timeline v-if="timelinePreview.length">
            <el-timeline-item v-for="(item, idx) in timelinePreview" :key="idx" :timestamp="formatTime(item.occurred_at)" placement="top">
              <el-tag size="small" class="mr-1">{{ timelineTypeLabel(item.type) }}</el-tag>
              <strong>{{ item.title }}</strong>
              <span class="text-gray-400 text-sm ml-1">{{ item.customer_name }}</span>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无活动" :image-size="60" />
        </el-card>
      </el-tab-pane>

      <!-- 客户列表 -->
      <el-tab-pane label="客户健康列表" name="customers">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-input v-model="filters.search" placeholder="搜索客户名称/邮箱" size="small" style="width:260px" clearable @clear="doSearch" @keyup.enter="doSearch" />
            <el-select v-model="filters.health_level" placeholder="健康等级" size="small" style="width:140px" clearable @change="doSearch">
              <el-option label="健康" value="healthy" />
              <el-option label="关注" value="attention" />
              <el-option label="风险" value="at_risk" />
              <el-option label="流失" value="churned" />
            </el-select>
            <el-select v-model="filters.churn_risk" placeholder="流失风险" size="small" style="width:140px" clearable @change="doSearch">
              <el-option label="低风险" value="low" />
              <el-option label="中风险" value="medium" />
              <el-option label="高风险" value="high" />
            </el-select>
          </div>
          <el-table :data="customers" v-loading="loading" stripe>
            <el-table-column label="客户" min-width="160">
              <template #default="{ row }">{{ row.user_name || row.user?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column label="邮箱" min-width="180" prop="user_email" />
            <el-table-column label="健康分" width="100" sortable="custom" prop="health_score">
              <template #default="{ row }">
                <el-tag v-if="row.health_score != null" :type="healthTagType(row.health_level)" effect="dark" size="small">
                  {{ row.health_score }}
                </el-tag>
                <span v-else class="text-gray-400">—</span>
              </template>
            </el-table-column>
            <el-table-column label="健康等级" width="110">
              <template #default="{ row }">
                <el-tag v-if="row.health_level" :type="healthTagType(row.health_level)" effect="plain" size="small">
                  {{ healthLevelLabel(row.health_level) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="流失风险" width="100">
              <template #default="{ row }">
                <el-tag v-if="row.churn_risk" :type="row.churn_risk === 'high' ? 'danger' : (row.churn_risk === 'medium' ? 'warning' : 'success')" size="small" effect="plain">
                  {{ row.churn_risk }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="最近活跃" width="150" prop="last_login_at">
              <template #default="{ row }">{{ formatTime(row.last_login_at) }}</template>
            </el-table-column>
            <el-table-column label="评分时间" width="150" prop="calculated_at">
              <template #default="{ row }">{{ formatTime(row.calculated_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="180" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" type="primary" @click="openCustomerDetail(row)">详情</el-button>
                <el-button text size="small" @click="calculateSingle(row)">重新评分</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination" v-if="total > 0">
            <el-pagination v-model:current-page="page" v-model:page-size="perPage" :total="total"
              :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next, jumper"
              @size-change="loadCustomers" @current-change="loadCustomers" />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 任务 -->
      <el-tab-pane label="跟进任务" name="tasks">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showTaskForm = true">
              <el-icon><Plus /></el-icon> 新建任务
            </el-button>
            <el-select v-model="taskFilter.status" placeholder="状态" size="small" style="width:120px" clearable @change="loadTasks">
              <el-option v-for="(l, k) in taskStatuses" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="taskFilter.priority" placeholder="优先级" size="small" style="width:120px" clearable @change="loadTasks">
              <el-option v-for="(l, k) in taskPriorities" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="tasks" v-loading="taskLoading" stripe>
            <el-table-column label="标题" min-width="180" prop="title" />
            <el-table-column label="客户" width="150">
              <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column label="负责人" width="120" prop="assignee.name" />
            <el-table-column label="优先级" width="90">
              <template #default="{ row }">
                <el-tag :type="priorityTagType(row.priority)" size="small" effect="plain">{{ taskPriorities[row.priority] || row.priority }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="状态" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : (row.status === 'cancelled' ? 'info' : 'warning')" size="small">
                  {{ taskStatuses[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="分类" width="90">
              <template #default="{ row }">{{ taskCategories[row.category] || row.category }}</template>
            </el-table-column>
            <el-table-column label="截止日期" width="120" prop="due_at">
              <template #default="{ row }">{{ formatTime(row.due_at) }}</template>
            </el-table-column>
            <el-table-column label="操作" width="150" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'open' || row.status === 'in_progress'" text size="small" type="success" @click="completeTask(row)">完成</el-button>
                <el-button text size="small" @click="editTask(row)">编辑</el-button>
                <el-button text size="small" type="danger" @click="handleDeleteTask(row)">删除</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="pagination" v-if="taskTotal > 0">
            <el-pagination v-model:current-page="taskPage" v-model:page-size="taskPerPage" :total="taskTotal"
              layout="total, sizes, prev, pager, next" :page-sizes="[10, 20, 50]"
              @size-change="loadTasks" @current-change="loadTasks" />
          </div>
        </el-card>
      </el-tab-pane>

      <!-- 续费日历 -->
      <el-tab-pane label="续费日历" name="renewal-calendar">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-date-picker v-model="calendarMonth" type="month" value-format="YYYY-MM" placeholder="选择月份" @change="loadRenewalCalendar" />
          </div>
          <el-table :data="renewalCalendar.events || []" stripe>
            <el-table-column label="日期" width="110" prop="date" />
            <el-table-column label="灯号" width="70" align="center">
              <template #default="{ row }"><span class="traffic-light" :class="row.risk_level"></span></template>
            </el-table-column>
            <el-table-column label="类型" width="100">
              <template #default="{ row }">{{ row.type === 'subscription_renewal' ? '订阅续费' : 'License到期' }}</template>
            </el-table-column>
            <el-table-column label="客户" min-width="140" prop="customer_name" />
            <el-table-column label="产品" min-width="120" prop="product_name" />
            <el-table-column label="金额" width="100">
              <template #default="{ row }">{{ row.amount != null ? `${row.amount} ${row.currency || ''}` : '-' }}</template>
            </el-table-column>
            <el-table-column label="自动续费" width="90">
              <template #default="{ row }">{{ row.auto_renew != null ? (row.auto_renew ? '是' : '否') : '-' }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 沟通记录 -->
      <el-tab-pane label="沟通记录" name="communications">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showCommForm = true"><el-icon><Plus /></el-icon> 记录沟通</el-button>
          </div>
          <el-table :data="communications" v-loading="commLoading" stripe>
            <el-table-column label="时间" width="160"><template #default="{ row }">{{ formatTime(row.contacted_at) }}</template></el-table-column>
            <el-table-column label="客户" width="140"><template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template></el-table-column>
            <el-table-column label="类型" width="90"><template #default="{ row }">{{ commTypeLabel(row.type) }}</template></el-table-column>
            <el-table-column label="主题" prop="subject" min-width="140" />
            <el-table-column label="内容" prop="content" min-width="200" show-overflow-tooltip />
            <el-table-column label="记录人" width="100"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 活动时间线 -->
      <el-tab-pane label="活动时间线" name="timeline">
        <el-card shadow="never">
          <el-timeline v-if="timeline.length">
            <el-timeline-item v-for="(item, idx) in timeline" :key="idx" :timestamp="formatTime(item.occurred_at)" placement="top" :type="timelineItemType(item.type)">
              <div class="timeline-card">
                <el-tag size="small">{{ timelineTypeLabel(item.type) }}</el-tag>
                <strong class="ml-1">{{ item.title }}</strong>
                <div class="text-gray-400 text-sm">{{ item.customer_name }} · {{ item.actor || '' }}</div>
                <div v-if="item.description" class="text-sm mt-1">{{ item.description }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else description="暂无活动记录" />
        </el-card>
      </el-tab-pane>

      <!-- 即将续费 -->
      <el-tab-pane label="即将续费" name="renewals">
        <el-table :data="dashboard?.upcoming_renewals || []" stripe>
          <el-table-column label="灯号" width="70" align="center">
            <template #default="{ row }"><span class="traffic-light" :class="renewalRiskForRow(row)"></span></template>
          </el-table-column>
          <el-table-column label="客户" min-width="150">
            <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
          </el-table-column>
          <el-table-column label="产品" width="150" prop="product?.name" />
          <el-table-column label="套餐" width="120" prop="plan" />
          <el-table-column label="金额" width="100">
            <template #default="{ row }">{{ row.price }} {{ row.currency }}</template>
          </el-table-column>
          <el-table-column label="下次扣款" width="150" prop="next_billing_at">
            <template #default="{ row }">{{ formatTime(row.next_billing_at) }}</template>
          </el-table-column>
          <el-table-column label="自动续费" width="90">
            <template #default="{ row }">{{ row.auto_renew ? '是' : '否' }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 即将过期License -->
      <el-tab-pane label="即将过期License" name="expiring">
        <el-table :data="dashboard?.expiring_licenses || []" stripe>
          <el-table-column label="客户" min-width="150">
            <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
          </el-table-column>
          <el-table-column label="产品" width="150" prop="product?.name" />
          <el-table-column label="License Key" width="200" prop="license_key" />
          <el-table-column label="过期时间" width="150" prop="expires_at">
            <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 客户详情 Dialog -->
    <el-dialog v-model="detailVisible" title="客户CSM详情" width="700px" top="5vh">
      <template v-if="detailLoading"><div class="text-center py-4">加载中...</div></template>
      <template v-else-if="customerDetail">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" :style="{ color: customerDetail.health_score ? healthColor(customerDetail.health_score.health_level) : '#909399' }">
                {{ customerDetail.health_score?.health_score ?? '—' }}
              </div>
              <div class="stat-label">健康分</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" style="color: #e6a23c">{{ customerDetail.churn_prediction?.churn_risk || '无数据' }}</div>
              <div class="stat-label">流失风险</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" style="color: #409eff">{{ customerDetail.customer?.subscriptions?.length || 0 }}</div>
              <div class="stat-label">订阅数</div>
            </el-card>
          </el-col>
        </el-row>

        <el-descriptions :column="2" border size="small">
          <el-descriptions-item label="客户">{{ customerDetail.customer?.user?.name || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item label="邮箱">{{ customerDetail.customer?.user?.email || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item label="等级">{{ customerDetail.customer?.level || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item label="最近登录">{{ formatTime(customerDetail.customer?.user?.last_login_at) }}</el-descriptions-item>
        </el-descriptions>

        <!-- 健康评分因素 -->
        <div v-if="customerDetail.health_score?.factors" class="mt-3">
          <h5 class="section-title">评分因素</h5>
          <div v-for="(factor, key) in customerDetail.health_score.factors" :key="key" class="factor-row">
            <span class="factor-label">{{ factorLabels[key] || key }}:</span>
            <el-progress :percentage="factor.score" :color="progressColor(factor.score)" :stroke-width="16" />
            <span class="factor-desc">{{ factor.description }}</span>
          </div>
        </div>

        <!-- 健康历史趋势 -->
        <div v-if="customerDetail.health_history?.length" class="mt-3">
          <h5 class="section-title">健康评分趋势</h5>
          <CsmHealthTrendChart :points="customerHealthTrendPoints" :loading="false" />
        </div>

        <!-- 沟通记录 -->
        <div v-if="customerDetail.communications?.length" class="mt-3">
          <h5 class="section-title">沟通记录</h5>
          <div v-for="c in customerDetail.communications" :key="c.id" class="comm-item">
            <el-tag size="small">{{ commTypeLabel(c.type) }}</el-tag>
            <span class="ml-1">{{ c.subject || c.content?.slice(0, 40) }}</span>
            <span class="text-gray-400 text-xs ml-1">{{ formatTime(c.contacted_at) }}</span>
          </div>
        </div>

        <!-- 健康历史 -->
        <div v-if="customerDetail.health_history?.length" class="mt-3">
          <h5 class="section-title">健康评分历史</h5>
          <el-table :data="customerDetail.health_history" size="small" stripe>
            <el-table-column label="分数" width="80" prop="health_score" />
            <el-table-column label="等级" width="100">
              <template #default="{ row }">
                <el-tag :type="healthTagType(row.health_level)" size="small" effect="plain">{{ healthLevelLabel(row.health_level) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column label="时间" width="160" prop="calculated_at">
              <template #default="{ row }">{{ formatTime(row.calculated_at) }}</template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 跟进任务 -->
        <div class="mt-3">
          <h5 class="section-title">跟进任务 ({{ customerDetail.tasks?.length || 0 }})</h5>
          <div v-for="t in (customerDetail.tasks || [])" :key="t.id" class="task-item">
            <el-tag :type="t.status === 'completed' ? 'success' : 'warning'" size="small">{{ taskStatuses[t.status] || t.status }}</el-tag>
            <span class="ml-1">{{ t.title }}</span>
            <span class="text-gray-400 text-xs ml-1">{{ t.assignee?.name || '未分配' }}</span>
          </div>
          <div v-if="!customerDetail.tasks?.length" class="text-gray-400 text-sm">暂无任务</div>
        </div>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">关闭</el-button>
        <el-button type="primary" @click="recalcCustomerDetail">重新评分</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑任务 Dialog -->
    <el-dialog v-model="showTaskForm" :title="taskEditId ? '编辑任务' : '新建任务'" width="550px">
      <el-form :model="taskForm" label-width="100px" size="small">
        <el-form-item label="客户" required>
          <el-select v-model="taskForm.customer_id" filterable style="width:100%" placeholder="选择客户">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.user?.name || c.user_name || `客户 #${c.id}`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="标题" required>
          <el-input v-model="taskForm.title" maxlength="200" />
        </el-form-item>
        <el-form-item label="描述">
          <el-input v-model="taskForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item label="优先级">
              <el-select v-model="taskForm.priority" style="width:100%">
                <el-option v-for="(l, k) in taskPriorities" :key="k" :label="l" :value="k" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="分类">
              <el-select v-model="taskForm.category" style="width:100%">
                <el-option v-for="(l, k) in taskCategories" :key="k" :label="l" :value="k" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item label="截止日期">
              <el-date-picker v-model="taskForm.due_at" type="date" value-format="YYYY-MM-DD" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item label="负责人" required>
          <el-select v-model="taskForm.assigned_to" filterable style="width:100%" placeholder="选择负责人">
            <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTaskForm = false">取消</el-button>
        <el-button type="primary" :loading="savingTask" @click="submitTask">{{ taskEditId ? '更新' : '创建' }}</el-button>
      </template>
    </el-dialog>

    <!-- 记录沟通 Dialog -->
    <el-dialog v-model="showCommForm" title="记录客户沟通" width="520px">
      <el-form :model="commForm" label-width="80px" size="small">
        <el-form-item label="客户" required>
          <el-select v-model="commForm.customer_id" filterable style="width:100%">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.user?.name || c.user_name || `客户 #${c.id}`" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="类型" required>
          <el-select v-model="commForm.type" style="width:100%">
            <el-option label="电话" value="call" /><el-option label="邮件" value="email" />
            <el-option label="会议" value="meeting" /><el-option label="备注" value="note" /><el-option label="在线聊天" value="chat" />
          </el-select>
        </el-form-item>
        <el-form-item label="主题"><el-input v-model="commForm.subject" /></el-form-item>
        <el-form-item label="内容"><el-input v-model="commForm.content" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCommForm = false">取消</el-button>
        <el-button type="primary" :loading="savingComm" @click="submitCommunication">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import csmApi from '../../api/csm';
import apiClient from '../../api/client';
import CsmHealthTrendChart from '@/components/CsmHealthTrendChart.vue';

const loading = ref(false);
const batchCalcLoading = ref(false);
const reminderLoading = ref(false);
const activeTab = ref('overview');
const dashboard = ref({});
const healthTrend = ref([]);
const trendLoading = ref(false);
const renewalCalendar = ref({ events: [], summary: {} });
const calendarMonth = ref(new Date().toISOString().slice(0, 7));
const timeline = ref([]);
const timelinePreview = computed(() => timeline.value.slice(0, 8));
const communications = ref([]);
const commLoading = ref(false);
const showCommForm = ref(false);
const savingComm = ref(false);
const commForm = reactive({ customer_id: '', type: 'call', subject: '', content: '' });
const customers = ref([]);
const total = ref(0);
const page = ref(1);
const perPage = ref(20);

const filters = reactive({ search: '', health_level: '', churn_risk: '' });

// Tasks
const tasks = ref([]);
const taskTotal = ref(0);
const taskPage = ref(1);
const taskPerPage = ref(20);
const taskLoading = ref(false);
const showTaskForm = ref(false);
const taskEditId = ref(null);
const savingTask = ref(false);
const taskFilter = reactive({ status: '', priority: '' });
const taskForm = reactive({ customer_id: '', title: '', description: '', priority: 'normal', category: 'checkin', assigned_to: '', due_at: '' });

// Detail
const detailVisible = ref(false);
const detailLoading = ref(false);
const customerDetail = ref(null);
const detailCustomerId = ref(null);

// Options
const customerOptions = ref([]);
const userOptions = ref([]);

const taskStatuses = { open: '待处理', in_progress: '进行中', completed: '已完成', cancelled: '已取消' };
const taskPriorities = { low: '低', normal: '普通', high: '高', urgent: '紧急' };
const taskCategories = { renewal: '续费', onboarding: '上手', support: '支持', review: '回顾', checkin: '回访', custom: '自定义' };
const factorLabels = {
  subscription_status: '订阅状态',
  license_activation: 'License活跃率',
  churn_prediction: '流失风险',
  payment_status: '支付状况',
  support_tickets: '工单支持',
  recent_activity: '近期活跃度',
};

const customerHealthTrendPoints = computed(() => {
  const history = customerDetail.value?.health_history || [];
  return [...history].reverse().map(h => ({
    date: h.calculated_at?.slice(0, 10) || '',
    avg_score: h.health_score,
  }));
});

function commTypeLabel(t) {
  return { call: '电话', email: '邮件', meeting: '会议', note: '备注', chat: '聊天' }[t] || t;
}
function timelineTypeLabel(t) {
  return { communication: '沟通', task: '任务', health_score: '健康评分' }[t] || t;
}
function timelineItemType(t) {
  return { communication: 'primary', task: 'success', health_score: 'warning' }[t] || '';
}
function renewalRiskForRow(row) {
  const days = row.next_billing_at ? Math.ceil((new Date(row.next_billing_at) - Date.now()) / 86400000) : 99;
  if (days <= 7) return 'red';
  if (days <= 14) return 'yellow';
  return 'green';
}

function healthCount(level) {
  return dashboard.value?.health_distribution?.[level] || 0;
}
function healthTagType(level) {
  return { healthy: 'success', attention: 'warning', at_risk: 'danger', churned: 'info' }[level] || 'info';
}
function healthLevelLabel(level) {
  return { healthy: '健康', attention: '关注', at_risk: '风险', churned: '流失' }[level] || level;
}
function healthColor(level) {
  return { healthy: '#67c23a', attention: '#e6a23c', at_risk: '#f56c6c', churned: '#909399' }[level] || '#909399';
}
function priorityTagType(p) {
  return { low: 'info', normal: '', high: 'warning', urgent: 'danger' }[p] || '';
}
function progressColor(score) {
  return score >= 80 ? '#67c23a' : score >= 50 ? '#e6a23c' : '#f56c6c';
}
function formatTime(t) {
  if (!t) return '-';
  return new Date(t).toLocaleDateString('zh-CN') + ' ' + new Date(t).toLocaleTimeString('zh-CN', { hour: '2-digit', minute: '2-digit' });
}

async function loadDashboard() {
  try {
    const { data: res } = await csmApi.getDashboard();
    if (res.success) dashboard.value = res.data;
  } catch { /* ignore */ }
}

async function loadHealthTrend() {
  trendLoading.value = true;
  try {
    const { data: res } = await csmApi.getHealthTrend({ days: 90 });
    if (res.success) healthTrend.value = res.data?.points || [];
  } catch { healthTrend.value = []; }
  finally { trendLoading.value = false; }
}

async function loadRenewalCalendar() {
  try {
    const { data: res } = await csmApi.getRenewalCalendar({ year_month: calendarMonth.value });
    if (res.success) renewalCalendar.value = res.data || { events: [], summary: {} };
  } catch { renewalCalendar.value = { events: [], summary: {} }; }
}

async function loadTimeline() {
  try {
    const { data: res } = await csmApi.getActivityTimeline({ limit: 30 });
    if (res.success) timeline.value = res.data || [];
  } catch { timeline.value = []; }
}

async function loadCommunications() {
  commLoading.value = true;
  try {
    const { data: res } = await csmApi.getCommunications({ per_page: 50 });
    if (res.success) communications.value = res.data?.data || res.data || [];
  } catch { communications.value = []; }
  finally { commLoading.value = false; }
}

async function submitCommunication() {
  if (!commForm.customer_id || !commForm.type) {
    ElMessage.warning('请填写客户和类型'); return;
  }
  savingComm.value = true;
  try {
    const { data: res } = await csmApi.createCommunication({ ...commForm });
    if (res.success) {
      ElMessage.success('沟通记录已保存');
      showCommForm.value = false;
      commForm.customer_id = ''; commForm.subject = ''; commForm.content = '';
      loadCommunications();
      loadTimeline();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '保存失败'); }
  finally { savingComm.value = false; }
}

async function loadCustomers() {
  loading.value = true;
  try {
    const { data: res } = await csmApi.getCustomers({
      page: page.value, per_page: perPage.value,
      health_level: filters.health_level || undefined,
      search: filters.search || undefined,
      churn_risk: filters.churn_risk || undefined,
    });
    if (res.success) {
      customers.value = res.data?.data || [];
      total.value = res.data?.total || 0;
    }
  } catch { customers.value = []; }
  finally { loading.value = false; }
}

async function loadTasks() {
  taskLoading.value = true;
  try {
    const { data: res } = await csmApi.getTasks({
      page: taskPage.value, per_page: taskPerPage.value,
      status: taskFilter.status || undefined,
      priority: taskFilter.priority || undefined,
    });
    if (res.success) {
      tasks.value = res.data?.data || [];
      taskTotal.value = res.data?.total || 0;
    }
  } catch { tasks.value = []; }
  finally { taskLoading.value = false; }
}

async function loadCustomerOptions() {
  try {
    const { data: res } = await csmApi.getCustomers({ per_page: 200 });
    if (res.success) customerOptions.value = res.data?.data || [];
  } catch { /* ignore */ }
}

async function loadUserOptions() {
  try {
    const { data: res } = await apiClient.get('/admin/users/with-roles');
    if (res.success) userOptions.value = res.data || [];
  } catch { /* ignore */ }
}

function doSearch() { page.value = 1; loadCustomers(); }

async function openCustomerDetail(row) {
  detailCustomerId.value = row.id;
  detailVisible.value = true;
  detailLoading.value = true;
  customerDetail.value = null;
  try {
    const { data: res } = await csmApi.getCustomerDetail(row.id);
    if (res.success) customerDetail.value = res.data;
  } catch { ElMessage.error('加载失败'); }
  finally { detailLoading.value = false; }
}

async function recalcCustomerDetail() {
  if (!detailCustomerId.value) return;
  try {
    await csmApi.calculateHealth(detailCustomerId.value);
    ElMessage.success('健康评分已更新');
    openCustomerDetail({ id: detailCustomerId.value });
  } catch { ElMessage.error('评分失败'); }
}

async function calculateSingle(row) {
  try {
    await csmApi.calculateHealth(row.id);
    ElMessage.success('评分已更新');
    loadCustomers();
  } catch { ElMessage.error('评分失败'); }
}

async function batchCalculateHealth() {
  batchCalcLoading.value = true;
  try {
    const { data: res } = await csmApi.batchCalculateHealth();
    if (res.success) {
      ElMessage.success(res.message || '批量评分完成');
      loadDashboard();
      loadCustomers();
    }
  } catch { ElMessage.error('批量评分失败'); }
  finally { batchCalcLoading.value = false; }
}

async function createReminders() {
  reminderLoading.value = true;
  try {
    const { data: res } = await csmApi.createRenewalReminders();
    if (res.success) ElMessage.success(res.message || '续费提醒已生成');
  } catch { ElMessage.error('生成失败'); }
  finally { reminderLoading.value = false; }
}

function resetTaskForm() {
  taskEditId.value = null;
  taskForm.customer_id = '';
  taskForm.title = '';
  taskForm.description = '';
  taskForm.priority = 'normal';
  taskForm.category = 'checkin';
  taskForm.assigned_to = '';
  taskForm.due_at = '';
}

function editTask(row) {
  taskEditId.value = row.id;
  taskForm.customer_id = row.customer_id;
  taskForm.title = row.title;
  taskForm.description = row.description || '';
  taskForm.priority = row.priority;
  taskForm.category = row.category || 'checkin';
  taskForm.assigned_to = row.assigned_to;
  taskForm.due_at = row.due_at || '';
  showTaskForm.value = true;
}

async function submitTask() {
  if (!taskForm.title.trim() || !taskForm.customer_id || !taskForm.assigned_to) {
    ElMessage.warning('请填写必填字段'); return;
  }
  savingTask.value = true;
  try {
    const payload = { ...taskForm };
    const res = taskEditId.value
      ? await csmApi.updateTask(taskEditId.value, payload)
      : await csmApi.createTask(payload);
    if (res.data.success) {
      ElMessage.success(taskEditId.value ? '已更新' : '已创建');
      showTaskForm.value = false;
      resetTaskForm();
      loadTasks();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
  finally { savingTask.value = false; }
}

async function completeTask(row) {
  try {
    await csmApi.updateTask(row.id, { status: 'completed' });
    ElMessage.success('任务已完成');
    loadTasks();
  } catch { ElMessage.error('操作失败'); }
}

async function handleDeleteTask(row) {
  try {
    await ElMessageBox.confirm(`确认删除任务「${row.title}」？`, '提示', { type: 'warning' });
    await csmApi.deleteTask(row.id);
    ElMessage.success('已删除');
    loadTasks();
  } catch (e) { if (e !== 'cancel') ElMessage.error('删除失败'); }
}

async function loadAll() {
  await Promise.all([loadDashboard(), loadHealthTrend(), loadRenewalCalendar(), loadTimeline(), loadCustomers()]);
}

watch(activeTab, (v) => {
  if (v === 'tasks') { loadTasks(); loadUserOptions(); loadCustomerOptions(); }
  if (v === 'customers') loadCustomers();
  if (v === 'communications') { loadCommunications(); loadCustomerOptions(); }
  if (v === 'timeline') loadTimeline();
  if (v === 'renewal-calendar') loadRenewalCalendar();
});

onMounted(() => {
  loadAll();
  loadCustomerOptions();
});
</script>

<style scoped>
.csm-page { padding: 20px; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.ml-1 { margin-left: 4px; }
.ml-2 { margin-left: 8px; }
.text-right { text-align: right; }
.text-lg { font-size: 16px; font-weight: 600; }
.text-sm { font-size: 13px; }
.text-xs { font-size: 12px; }
.text-gray-400 { color: #909399; }
.text-center { text-align: center; }
.py-4 { padding: 16px 0; }
.stat-card { text-align: center; }
.stat-card .stat-value { font-size: 28px; font-weight: 700; }
.stat-card .stat-label { font-size: 12px; color: #909399; margin-top: 4px; }
.stat-card.mini .stat-value { font-size: 22px; }
.tab-toolbar { margin-bottom: 12px; display: flex; gap: 8px; align-items: center; }
.pagination { display: flex; justify-content: flex-end; margin-top: 16px; }
.section-title { font-size: 14px; font-weight: 600; margin: 0 0 8px; }
.factor-row { margin-bottom: 8px; }
.factor-label { display: inline-block; width: 120px; font-size: 12px; color: #606266; }
.factor-desc { font-size: 11px; color: #909399; margin-top: 2px; }
.task-item { margin-bottom: 4px; }
.comm-item { margin-bottom: 6px; font-size: 13px; }
.traffic-light { display: inline-block; width: 14px; height: 14px; border-radius: 50%; }
.traffic-light.green { background: #67c23a; box-shadow: 0 0 6px #67c23a; }
.traffic-light.yellow { background: #e6a23c; box-shadow: 0 0 6px #e6a23c; }
.traffic-light.red { background: #f56c6c; box-shadow: 0 0 6px #f56c6c; }
.risk-lights { display: flex; flex-direction: column; gap: 12px; padding: 8px 0; }
.risk-light { display: flex; align-items: center; gap: 10px; font-size: 15px; font-weight: 600; }
.risk-light small { font-weight: 400; color: #909399; margin-left: auto; }
.risk-light .dot { width: 16px; height: 16px; border-radius: 50%; }
.risk-light.green .dot { background: #67c23a; }
.risk-light.yellow .dot { background: #e6a23c; }
.risk-light.red .dot { background: #f56c6c; }
.mr-1 { margin-right: 4px; }
.timeline-card { padding: 4px 0; }
</style>
