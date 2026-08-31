<template>
  <div class="csm-page">
    <el-card shadow="never" class="mb-4">
      <el-row justify="space-between" align="middle">
        <el-col :span="12">
          <span class="text-lg font-medium">{{ t('csm_page.title') }}</span>
          <span class="text-gray-400 text-sm ml-4">{{ t('csm_page.customers_count', { count: dashboard?.total_customers || 0 }) }}</span>
        </el-col>
        <el-col :span="12" class="text-right">
          <el-button size="small" @click="batchCalculateHealth" :loading="batchCalcLoading">{{ t('csm_page.batch_calc_health') }}</el-button>
          <el-button size="small" @click="createReminders" :loading="reminderLoading">{{ t('csm_page.create_renewal_reminders') }}</el-button>
          <el-button size="small" type="primary" @click="loadAll">{{ t('csm_page.refresh') }}</el-button>
        </el-col>
      </el-row>
    </el-card>

    <!-- 健康分布卡片 -->
    <el-row :gutter="16" class="mb-4">
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #67c23a">{{ healthCount('healthy') }}</div>
          <div class="stat-label">{{ t('csm_page.stat_healthy') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #e6a23c">{{ healthCount('attention') }}</div>
          <div class="stat-label">{{ t('csm_page.stat_attention') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #f56c6c">{{ healthCount('at_risk') }}</div>
          <div class="stat-label">{{ t('csm_page.stat_at_risk') }}</div>
        </el-card>
      </el-col>
      <el-col :span="6">
        <el-card shadow="hover" class="stat-card">
          <div class="stat-value" style="color: #909399">{{ healthCount('churned') }}</div>
          <div class="stat-label">{{ t('csm_page.stat_churned') }}</div>
        </el-card>
      </el-col>
    </el-row>

    <!-- 任务概览 -->
    <el-row :gutter="16" class="mb-4" v-if="dashboard?.task_stats">
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#0f172a">{{ dashboard.task_stats.total_open || 0 }}</div><div class="stat-label">{{ t('csm_page.stat_open_tasks') }}</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#f56c6c">{{ dashboard.task_stats.overdue || 0 }}</div><div class="stat-label">{{ t('csm_page.stat_overdue_tasks') }}</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#e6a23c">{{ dashboard.task_stats.high_priority || 0 }}</div><div class="stat-label">{{ t('csm_page.stat_high_priority') }}</div></el-card></el-col>
      <el-col :span="6"><el-card shadow="hover" class="stat-card"><div class="stat-value" style="color:#67c23a">{{ dashboard.task_stats.completed_today || 0 }}</div><div class="stat-label">{{ t('csm_page.stat_completed_today') }}</div></el-card></el-col>
    </el-row>

    <el-tabs v-model="activeTab">
      <!-- 概览 -->
      <el-tab-pane :label="t('csm_page.tab_overview')" name="overview">
        <el-row :gutter="16">
          <el-col :span="14">
            <el-card shadow="never" class="mb-4">
              <template #header><span>{{ t('csm_page.health_trend_title') }}</span></template>
              <CsmHealthTrendChart :points="healthTrend" :loading="trendLoading" />
            </el-card>
          </el-col>
          <el-col :span="10">
            <el-card shadow="never">
              <template #header><span>{{ t('csm_page.renewal_forecast_lights') }}</span></template>
              <div class="risk-lights" v-if="renewalCalendar.summary">
                <div class="risk-light green"><span class="dot"></span><span>{{ t('csm_page.green_light', { count: renewalCalendar.summary.green || 0 }) }}</span><small>{{ t('csm_page.green_light_desc') }}</small></div>
                <div class="risk-light yellow"><span class="dot"></span><span>{{ t('csm_page.yellow_light', { count: renewalCalendar.summary.yellow || 0 }) }}</span><small>{{ t('csm_page.yellow_light_desc') }}</small></div>
                <div class="risk-light red"><span class="dot"></span><span>{{ t('csm_page.red_light', { count: renewalCalendar.summary.red || 0 }) }}</span><small>{{ t('csm_page.red_light_desc') }}</small></div>
              </div>
              <el-empty v-else :description="t('csm_page.no_renewal_data')" :image-size="60" />
            </el-card>
          </el-col>
        </el-row>
        <el-card shadow="never">
          <template #header><span>{{ t('csm_page.recent_activity') }}</span></template>
          <el-timeline v-if="timelinePreview.length">
            <el-timeline-item v-for="(item, idx) in timelinePreview" :key="idx" :timestamp="formatTime(item.occurred_at)" placement="top">
              <el-tag size="small" class="mr-1">{{ timelineTypeLabel(item.type) }}</el-tag>
              <strong>{{ item.title }}</strong>
              <span class="text-gray-400 text-sm ml-1">{{ item.customer_name }}</span>
            </el-timeline-item>
          </el-timeline>
          <el-empty v-else :description="t('csm_page.no_activity')" :image-size="60" />
        </el-card>
      </el-tab-pane>

      <!-- 客户列表 -->
      <el-tab-pane :label="t('csm_page.tab_customers')" name="customers">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-input v-model="filters.search" :placeholder="t('csm_page.search_customer_ph')" size="small" style="width:260px" clearable @clear="doSearch" @keyup.enter="doSearch" />
            <el-select v-model="filters.health_level" :placeholder="t('csm_page.health_level_ph')" size="small" style="width:140px" clearable @change="doSearch">
              <el-option v-for="opt in healthLevelOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
            <el-select v-model="filters.churn_risk" :placeholder="t('csm_page.churn_risk_ph')" size="small" style="width:140px" clearable @change="doSearch">
              <el-option v-for="opt in churnRiskOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
            </el-select>
          </div>
          <el-table :data="customers" v-loading="loading" stripe>
            <el-table-column :label="t('csm_page.col_customer')" min-width="160">
              <template #default="{ row }">{{ row.user_name || row.user?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_email')" min-width="180" prop="user_email" />
            <el-table-column :label="t('csm_page.col_health_score')" width="100" sortable="custom" prop="health_score">
              <template #default="{ row }">
                <el-tag v-if="row.health_score != null" :type="healthTagType(row.health_level)" effect="dark" size="small">
                  {{ row.health_score }}
                </el-tag>
                <span v-else class="text-gray-400">—</span>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_health_level')" width="110">
              <template #default="{ row }">
                <el-tag v-if="row.health_level" :type="healthTagType(row.health_level)" effect="plain" size="small">
                  {{ healthLevelLabel(row.health_level) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_churn_risk')" width="100">
              <template #default="{ row }">
                <el-tag v-if="row.churn_risk" :type="row.churn_risk === 'high' ? 'danger' : (row.churn_risk === 'medium' ? 'warning' : 'success')" size="small" effect="plain">
                  {{ churnRiskLabel(row.churn_risk) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_last_active')" width="150" prop="last_login_at">
              <template #default="{ row }">{{ formatTime(row.last_login_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_calculated_at')" width="150" prop="calculated_at">
              <template #default="{ row }">{{ formatTime(row.calculated_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_actions')" width="180" fixed="right">
              <template #default="{ row }">
                <el-button text size="small" type="primary" @click="openCustomerDetail(row)">{{ t('csm_page.detail') }}</el-button>
                <el-button text size="small" @click="calculateSingle(row)">{{ t('csm_page.recalculate') }}</el-button>
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
      <el-tab-pane :label="t('csm_page.tab_tasks')" name="tasks">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showTaskForm = true">
              <el-icon><Plus /></el-icon> {{ t('csm_page.create_task') }}
            </el-button>
            <el-select v-model="taskFilter.status" :placeholder="t('csm_page.status_ph')" size="small" style="width:120px" clearable @change="loadTasks">
              <el-option v-for="(l, k) in taskStatuses" :key="k" :label="l" :value="k" />
            </el-select>
            <el-select v-model="taskFilter.priority" :placeholder="t('csm_page.priority_ph')" size="small" style="width:120px" clearable @change="loadTasks">
              <el-option v-for="(l, k) in taskPriorities" :key="k" :label="l" :value="k" />
            </el-select>
          </div>
          <el-table :data="tasks" v-loading="taskLoading" stripe>
            <el-table-column :label="t('csm_page.col_title')" min-width="180" prop="title" />
            <el-table-column :label="t('csm_page.col_customer')" width="150">
              <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_assignee')" width="120" prop="assignee.name" />
            <el-table-column :label="t('csm_page.priority_ph')" width="90">
              <template #default="{ row }">
                <el-tag :type="priorityTagType(row.priority)" size="small" effect="plain">{{ taskPriorities[row.priority] || row.priority }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.status_ph')" width="100">
              <template #default="{ row }">
                <el-tag :type="row.status === 'completed' ? 'success' : (row.status === 'cancelled' ? 'info' : 'warning')" size="small">
                  {{ taskStatuses[row.status] || row.status }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_category')" width="90">
              <template #default="{ row }">{{ taskCategories[row.category] || row.category }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_due_at')" width="120" prop="due_at">
              <template #default="{ row }">{{ formatTime(row.due_at) }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_actions')" width="150" fixed="right">
              <template #default="{ row }">
                <el-button v-if="row.status === 'open' || row.status === 'in_progress'" text size="small" type="success" @click="completeTask(row)">{{ t('csm_page.complete') }}</el-button>
                <el-button text size="small" @click="editTask(row)">{{ t('actions.edit') }}</el-button>
                <el-button text size="small" type="danger" @click="handleDeleteTask(row)">{{ t('actions.delete') }}</el-button>
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
      <el-tab-pane :label="t('csm_page.tab_renewal_calendar')" name="renewal-calendar">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-date-picker v-model="calendarMonth" type="month" value-format="YYYY-MM" :placeholder="t('csm_page.select_month_ph')" @change="loadRenewalCalendar" />
          </div>
          <el-table :data="renewalCalendar.events || []" stripe>
            <el-table-column :label="t('csm_page.col_date')" width="110" prop="date" />
            <el-table-column :label="t('csm_page.col_traffic_light')" width="70" align="center">
              <template #default="{ row }"><span class="traffic-light" :class="row.risk_level"></span></template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_type')" width="100">
              <template #default="{ row }">{{ renewalTypeLabel(row.type) }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_customer')" min-width="140" prop="customer_name" />
            <el-table-column :label="t('csm_page.col_product')" min-width="120" prop="product_name" />
            <el-table-column :label="t('csm_page.col_amount')" width="100">
              <template #default="{ row }">{{ row.amount != null ? `${row.amount} ${row.currency || ''}` : '-' }}</template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_auto_renew')" width="90">
              <template #default="{ row }">{{ row.auto_renew != null ? (row.auto_renew ? t('csm_page.yes') : t('csm_page.no')) : '-' }}</template>
            </el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 沟通记录 -->
      <el-tab-pane :label="t('csm_page.tab_communications')" name="communications">
        <el-card shadow="never">
          <div class="tab-toolbar">
            <el-button size="small" type="primary" @click="showCommForm = true"><el-icon><Plus /></el-icon> {{ t('csm_page.log_communication') }}</el-button>
          </div>
          <el-table :data="communications" v-loading="commLoading" stripe>
            <el-table-column :label="t('csm_page.col_time')" width="160"><template #default="{ row }">{{ formatTime(row.contacted_at) }}</template></el-table-column>
            <el-table-column :label="t('csm_page.col_customer')" width="140"><template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template></el-table-column>
            <el-table-column :label="t('csm_page.col_type')" width="90"><template #default="{ row }">{{ commTypeLabel(row.type) }}</template></el-table-column>
            <el-table-column :label="t('csm_page.col_subject')" prop="subject" min-width="140" />
            <el-table-column :label="t('csm_page.col_content')" prop="content" min-width="200" show-overflow-tooltip />
            <el-table-column :label="t('csm_page.col_recorder')" width="100"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
          </el-table>
        </el-card>
      </el-tab-pane>

      <!-- 活动时间线 -->
      <el-tab-pane :label="t('csm_page.tab_timeline')" name="timeline">
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
          <el-empty v-else :description="t('csm_page.no_activity_records')" />
        </el-card>
      </el-tab-pane>

      <!-- 即将续费 -->
      <el-tab-pane :label="t('csm_page.tab_renewals')" name="renewals">
        <el-table :data="dashboard?.upcoming_renewals || []" stripe>
          <el-table-column :label="t('csm_page.col_traffic_light')" width="70" align="center">
            <template #default="{ row }"><span class="traffic-light" :class="renewalRiskForRow(row)"></span></template>
          </el-table-column>
          <el-table-column :label="t('csm_page.col_customer')" min-width="150">
            <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
          </el-table-column>
          <el-table-column :label="t('csm_page.col_product')" width="150" prop="product?.name" />
          <el-table-column :label="t('csm_page.col_plan')" width="120" prop="plan" />
          <el-table-column :label="t('csm_page.col_amount')" width="100">
            <template #default="{ row }">{{ row.price }} {{ row.currency }}</template>
          </el-table-column>
          <el-table-column :label="t('csm_page.col_next_billing')" width="150" prop="next_billing_at">
            <template #default="{ row }">{{ formatTime(row.next_billing_at) }}</template>
          </el-table-column>
          <el-table-column :label="t('csm_page.col_auto_renew')" width="90">
            <template #default="{ row }">{{ row.auto_renew ? t('csm_page.yes') : t('csm_page.no') }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>

      <!-- 即将过期License -->
      <el-tab-pane :label="t('csm_page.tab_expiring')" name="expiring">
        <el-table :data="dashboard?.expiring_licenses || []" stripe>
          <el-table-column :label="t('csm_page.col_customer')" min-width="150">
            <template #default="{ row }">{{ row.customer?.user?.name || 'N/A' }}</template>
          </el-table-column>
          <el-table-column :label="t('csm_page.col_product')" width="150" prop="product?.name" />
          <el-table-column :label="t('csm_page.col_license_key')" width="200" prop="license_key" />
          <el-table-column :label="t('csm_page.col_expires_at')" width="150" prop="expires_at">
            <template #default="{ row }">{{ formatTime(row.expires_at) }}</template>
          </el-table-column>
        </el-table>
      </el-tab-pane>
    </el-tabs>

    <!-- 客户详情 Dialog -->
    <el-dialog v-model="detailVisible" :title="t('csm_page.detail_dialog_title')" width="700px" top="5vh">
      <template v-if="detailLoading"><div class="text-center py-4">{{ t('actions.loading') }}</div></template>
      <template v-else-if="customerDetail">
        <el-row :gutter="16" class="mb-4">
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" :style="{ color: customerDetail.health_score ? healthColor(customerDetail.health_score.health_level) : '#909399' }">
                {{ customerDetail.health_score?.health_score ?? '—' }}
              </div>
              <div class="stat-label">{{ t('csm_page.col_health_score') }}</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" style="color: #e6a23c">{{ customerDetail.churn_prediction?.churn_risk ? churnRiskLabel(customerDetail.churn_prediction.churn_risk) : t('csm_page.no_data') }}</div>
              <div class="stat-label">{{ t('csm_page.col_churn_risk') }}</div>
            </el-card>
          </el-col>
          <el-col :span="8">
            <el-card shadow="hover" class="stat-card mini">
              <div class="stat-value" style="color: #0f172a">{{ customerDetail.customer?.subscriptions?.length || 0 }}</div>
              <div class="stat-label">{{ t('csm_page.subscription_count') }}</div>
            </el-card>
          </el-col>
        </el-row>

        <el-descriptions :column="2" border size="small">
          <el-descriptions-item :label="t('csm_page.col_customer')">{{ customerDetail.customer?.user?.name || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item :label="t('csm_page.col_email')">{{ customerDetail.customer?.user?.email || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item :label="t('csm_page.col_level')">{{ customerDetail.customer?.level || 'N/A' }}</el-descriptions-item>
          <el-descriptions-item :label="t('csm_page.col_last_login')">{{ formatTime(customerDetail.customer?.user?.last_login_at) }}</el-descriptions-item>
        </el-descriptions>

        <!-- 健康评分因素 -->
        <div v-if="customerDetail.health_score?.factors" class="mt-3">
          <h5 class="section-title">{{ t('csm_page.scoring_factors') }}</h5>
          <div v-for="(factor, key) in customerDetail.health_score.factors" :key="key" class="factor-row">
            <span class="factor-label">{{ factorLabels[key] || key }}:</span>
            <el-progress :percentage="factor.score" :color="progressColor(factor.score)" :stroke-width="16" />
            <span class="factor-desc">{{ factor.description }}</span>
          </div>
        </div>

        <!-- 健康历史趋势 -->
        <div v-if="customerDetail.health_history?.length" class="mt-3">
          <h5 class="section-title">{{ t('csm_page.health_trend_title') }}</h5>
          <CsmHealthTrendChart :points="customerHealthTrendPoints" :loading="false" />
        </div>

        <!-- 沟通记录 -->
        <div v-if="customerDetail.communications?.length" class="mt-3">
          <h5 class="section-title">{{ t('csm_page.tab_communications') }}</h5>
          <div v-for="c in customerDetail.communications" :key="c.id" class="comm-item">
            <el-tag size="small">{{ commTypeLabel(c.type) }}</el-tag>
            <span class="ml-1">{{ c.subject || c.content?.slice(0, 40) }}</span>
            <span class="text-gray-400 text-xs ml-1">{{ formatTime(c.contacted_at) }}</span>
          </div>
        </div>

        <!-- 健康历史 -->
        <div v-if="customerDetail.health_history?.length" class="mt-3">
          <h5 class="section-title">{{ t('csm_page.health_score_history') }}</h5>
          <el-table :data="customerDetail.health_history" size="small" stripe>
            <el-table-column :label="t('csm_page.col_score')" width="80" prop="health_score" />
            <el-table-column :label="t('csm_page.col_health_level')" width="100">
              <template #default="{ row }">
                <el-tag :type="healthTagType(row.health_level)" size="small" effect="plain">{{ healthLevelLabel(row.health_level) }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column :label="t('csm_page.col_time')" width="160" prop="calculated_at">
              <template #default="{ row }">{{ formatTime(row.calculated_at) }}</template>
            </el-table-column>
          </el-table>
        </div>

        <!-- 跟进任务 -->
        <div class="mt-3">
          <h5 class="section-title">{{ t('csm_page.follow_up_tasks', { count: customerDetail.tasks?.length || 0 }) }}</h5>
          <div v-for="task in (customerDetail.tasks || [])" :key="task.id" class="task-item">
            <el-tag :type="task.status === 'completed' ? 'success' : 'warning'" size="small">{{ taskStatuses[task.status] || task.status }}</el-tag>
            <span class="ml-1">{{ task.title }}</span>
            <span class="text-gray-400 text-xs ml-1">{{ task.assignee?.name || t('csm_page.unassigned') }}</span>
          </div>
          <div v-if="!customerDetail.tasks?.length" class="text-gray-400 text-sm">{{ t('csm_page.no_tasks') }}</div>
        </div>
      </template>
      <template #footer>
        <el-button @click="detailVisible = false">{{ t('actions.close') }}</el-button>
        <el-button type="primary" @click="recalcCustomerDetail">{{ t('csm_page.recalculate') }}</el-button>
      </template>
    </el-dialog>

    <!-- 新建/编辑任务 Dialog -->
    <el-dialog v-model="showTaskForm" :title="taskEditId ? t('csm_page.edit_task_title') : t('csm_page.create_task_title')" width="550px">
      <el-form :model="taskForm" label-width="100px" size="small">
        <el-form-item :label="t('csm_page.form_customer')" required>
          <el-select v-model="taskForm.customer_id" filterable style="width:100%" :placeholder="t('csm_page.select_customer_ph')">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.user?.name || c.user_name || t('csm_page.customer_fallback', { id: c.id })" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('csm_page.form_title')" required>
          <el-input v-model="taskForm.title" maxlength="200" />
        </el-form-item>
        <el-form-item :label="t('csm_page.form_description')">
          <el-input v-model="taskForm.description" type="textarea" :rows="2" />
        </el-form-item>
        <el-row :gutter="16">
          <el-col :span="8">
            <el-form-item :label="t('csm_page.form_priority')">
              <el-select v-model="taskForm.priority" style="width:100%">
                <el-option v-for="(l, k) in taskPriorities" :key="k" :label="l" :value="k" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('csm_page.form_category')">
              <el-select v-model="taskForm.category" style="width:100%">
                <el-option v-for="(l, k) in taskCategories" :key="k" :label="l" :value="k" />
              </el-select>
            </el-form-item>
          </el-col>
          <el-col :span="8">
            <el-form-item :label="t('csm_page.form_due_at')">
              <el-date-picker v-model="taskForm.due_at" type="date" value-format="YYYY-MM-DD" style="width:100%" />
            </el-form-item>
          </el-col>
        </el-row>
        <el-form-item :label="t('csm_page.form_assignee')" required>
          <el-select v-model="taskForm.assigned_to" filterable style="width:100%" :placeholder="t('csm_page.select_assignee_ph')">
            <el-option v-for="u in userOptions" :key="u.id" :label="u.name" :value="u.id" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showTaskForm = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingTask" @click="submitTask">{{ taskEditId ? t('actions.update') : t('actions.create') }}</el-button>
      </template>
    </el-dialog>

    <!-- 记录沟通 Dialog -->
    <el-dialog v-model="showCommForm" :title="t('csm_page.log_comm_dialog_title')" width="520px">
      <el-form :model="commForm" label-width="80px" size="small">
        <el-form-item :label="t('csm_page.form_customer')" required>
          <el-select v-model="commForm.customer_id" filterable style="width:100%" :placeholder="t('csm_page.select_customer_ph')">
            <el-option v-for="c in customerOptions" :key="c.id" :label="c.user?.name || c.user_name || t('csm_page.customer_fallback', { id: c.id })" :value="c.id" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('csm_page.form_type')" required>
          <el-select v-model="commForm.type" style="width:100%">
            <el-option v-for="opt in commTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('csm_page.col_subject')"><el-input v-model="commForm.subject" /></el-form-item>
        <el-form-item :label="t('csm_page.col_content')"><el-input v-model="commForm.content" type="textarea" :rows="3" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="showCommForm = false">{{ t('actions.cancel') }}</el-button>
        <el-button type="primary" :loading="savingComm" @click="submitCommunication">{{ t('actions.save') }}</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Plus } from '@element-plus/icons-vue';
import csmApi from '@/api/csm';
import permissionApi from '@/api/permission';
import CsmHealthTrendChart from '@/components/CsmHealthTrendChart.vue';

const { t, locale } = useI18n();

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

const HEALTH_LEVEL_KEYS = ['healthy', 'attention', 'at_risk', 'churned'];
const CHURN_RISK_KEYS = ['low', 'medium', 'high'];
const TASK_STATUS_KEYS = ['open', 'in_progress', 'completed', 'cancelled'];
const TASK_PRIORITY_KEYS = ['low', 'normal', 'high', 'urgent'];
const TASK_CATEGORY_KEYS = ['renewal', 'onboarding', 'support', 'review', 'checkin', 'custom'];
const FACTOR_KEYS = ['subscription_status', 'license_activation', 'churn_prediction', 'payment_status', 'support_tickets', 'recent_activity'];
const COMM_TYPE_KEYS = ['call', 'email', 'meeting', 'note', 'chat'];
const TIMELINE_TYPE_KEYS = ['communication', 'task', 'health_score'];

const healthLevelOptions = computed(() =>
  HEALTH_LEVEL_KEYS.map((value) => ({ value, label: t(`csm_page.health_level.${value}`) })),
);
const churnRiskOptions = computed(() =>
  CHURN_RISK_KEYS.map((value) => ({ value, label: t(`csm_page.churn_risk.${value}`) })),
);
const taskStatuses = computed(() =>
  Object.fromEntries(TASK_STATUS_KEYS.map((k) => [k, t(`csm_page.task_status.${k}`)])),
);
const taskPriorities = computed(() =>
  Object.fromEntries(TASK_PRIORITY_KEYS.map((k) => [k, t(`csm_page.task_priority.${k}`)])),
);
const taskCategories = computed(() =>
  Object.fromEntries(TASK_CATEGORY_KEYS.map((k) => [k, t(`csm_page.task_category.${k}`)])),
);
const factorLabels = computed(() =>
  Object.fromEntries(FACTOR_KEYS.map((k) => [k, t(`csm_page.factor.${k}`)])),
);
const commTypeOptions = computed(() =>
  COMM_TYPE_KEYS.map((value) => ({ value, label: t(`csm_page.comm_type.${value}`) })),
);

const customerHealthTrendPoints = computed(() => {
  const history = customerDetail.value?.health_history || [];
  return [...history].reverse().map(h => ({
    date: h.calculated_at?.slice(0, 10) || '',
    avg_score: h.health_score,
  }));
});

function commTypeLabel(type) {
  return t(`csm_page.comm_type.${type}`) || type;
}
function timelineTypeLabel(type) {
  return t(`csm_page.timeline_type.${type}`) || type;
}
function timelineItemType(type) {
  return { communication: 'primary', task: 'success', health_score: 'warning' }[type] || '';
}
function renewalTypeLabel(type) {
  if (type === 'subscription_renewal') return t('csm_page.renewal_type.subscription_renewal');
  return t('csm_page.renewal_type.license_expiry');
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
  return t(`csm_page.health_level.${level}`) || level;
}
function churnRiskLabel(risk) {
  return t(`csm_page.churn_risk.${risk}`) || risk;
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
function formatTime(value) {
  if (!value) return '-';
  const loc = locale.value === 'zh_CN' ? 'zh-CN' : 'en-US';
  return new Date(value).toLocaleDateString(loc) + ' ' + new Date(value).toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
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
    ElMessage.warning(t('csm_page.messages.fill_customer_and_type')); return;
  }
  savingComm.value = true;
  try {
    const { data: res } = await csmApi.createCommunication({ ...commForm });
    if (res.success) {
      ElMessage.success(t('csm_page.messages.comm_saved'));
      showCommForm.value = false;
      commForm.customer_id = ''; commForm.subject = ''; commForm.content = '';
      loadCommunications();
      loadTimeline();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('csm_page.messages.save_failed')); }
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
    const { data: res } = await permissionApi.tenantUsers();
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
  } catch { ElMessage.error(t('messages.load_failed')); }
  finally { detailLoading.value = false; }
}

async function recalcCustomerDetail() {
  if (!detailCustomerId.value) return;
  try {
    await csmApi.calculateHealth(detailCustomerId.value);
    ElMessage.success(t('csm_page.messages.health_score_updated'));
    openCustomerDetail({ id: detailCustomerId.value });
  } catch { ElMessage.error(t('csm_page.messages.score_failed')); }
}

async function calculateSingle(row) {
  try {
    await csmApi.calculateHealth(row.id);
    ElMessage.success(t('csm_page.messages.score_updated'));
    loadCustomers();
  } catch { ElMessage.error(t('csm_page.messages.score_failed')); }
}

async function batchCalculateHealth() {
  batchCalcLoading.value = true;
  try {
    const { data: res } = await csmApi.batchCalculateHealth();
    if (res.success) {
      ElMessage.success(res.message || t('csm_page.messages.batch_score_done'));
      loadDashboard();
      loadCustomers();
    }
  } catch { ElMessage.error(t('csm_page.messages.batch_score_failed')); }
  finally { batchCalcLoading.value = false; }
}

async function createReminders() {
  reminderLoading.value = true;
  try {
    const { data: res } = await csmApi.createRenewalReminders();
    if (res.success) ElMessage.success(res.message || t('csm_page.messages.renewal_reminders_created'));
  } catch { ElMessage.error(t('csm_page.messages.create_failed')); }
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
    ElMessage.warning(t('csm_page.messages.fill_required_fields')); return;
  }
  savingTask.value = true;
  try {
    const payload = { ...taskForm };
    const res = taskEditId.value
      ? await csmApi.updateTask(taskEditId.value, payload)
      : await csmApi.createTask(payload);
    if (res.data.success) {
      ElMessage.success(taskEditId.value ? t('csm_page.messages.updated') : t('csm_page.messages.created'));
      showTaskForm.value = false;
      resetTaskForm();
      loadTasks();
    }
  } catch (e) { ElMessage.error(e.response?.data?.message || t('messages.failed')); }
  finally { savingTask.value = false; }
}

async function completeTask(row) {
  try {
    await csmApi.updateTask(row.id, { status: 'completed' });
    ElMessage.success(t('csm_page.messages.task_completed'));
    loadTasks();
  } catch { ElMessage.error(t('messages.failed')); }
}

async function handleDeleteTask(row) {
  try {
    await ElMessageBox.confirm(t('csm_page.confirm.delete_task', { title: row.title }), t('actions.confirm'), { type: 'warning' });
    await csmApi.deleteTask(row.id);
    ElMessage.success(t('csm_page.messages.deleted'));
    loadTasks();
  } catch (e) { if (e !== 'cancel') ElMessage.error(t('csm_page.messages.delete_failed')); }
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
