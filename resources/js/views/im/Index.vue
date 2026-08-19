<template>
    <div class="im-center">
        <div class="page-header">
            <h2>{{ t('im_page.title') }}</h2>
        </div>

        <el-tabs v-model="activeTab" type="border-card" class="im-tabs" @tab-change="onTabChange">
            <!-- IM 管理后台 -->
            <el-tab-pane :label="t('im_page.tabs.im_admin')" name="im-admin">
                <div class="tab-content" v-if="adminTabVisited">
                    <!-- 二级 tabs -->
                    <el-tabs v-model="adminActiveTab" type="border-card" class="im-admin-tabs">
                        <!-- ADMIN-005: dashboard -->
                        <el-tab-pane :label="t('im_admin_page.tabs.dashboard')" name="dashboard">
                            <div class="tab-content" v-loading="adminLoadingDash">
                                <el-row :gutter="16" class="stat-cards">
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.total_users }}</div><div class="stat-label">{{ t('im_admin_page.stats.users') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.total_groups }}</div><div class="stat-label">{{ t('im_admin_page.stats.groups') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.total_messages }}</div><div class="stat-label">{{ t('im_admin_page.stats.total_messages') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.today_messages }}</div><div class="stat-label">{{ t('im_admin_page.stats.today_messages') }}</div></div></el-card></el-col>
                                </el-row>
                                <el-row :gutter="16" style="margin-top:16px">
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.week_messages }}</div><div class="stat-label">{{ t('im_admin_page.stats.week_messages') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.active_users_7d }}</div><div class="stat-label">{{ t('im_admin_page.stats.active_users_7d') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value">{{ adminDash.total_conversations }}</div><div class="stat-label">{{ t('im_admin_page.stats.total_conversations') }}</div></div></el-card></el-col>
                                    <el-col :span="6"><el-card shadow="never"><div class="stat-item"><div class="stat-value" style="color:#e6a23c">{{ adminDash.pending_reports }}</div><div class="stat-label">{{ t('im_admin_page.stats.pending_reports') }}</div></div></el-card></el-col>
                                </el-row>
                                <el-card shadow="never" style="margin-top:16px">
                                    <template #header><span>{{ t('im_admin_page.trend_title') }}</span></template>
                                    <div class="trend-chart">
                                        <div v-for="item in adminDash.message_trend" :key="item.date" class="trend-bar-wrap">
                                            <div class="trend-bar" :style="{ height: adminTrendHeight(item.count) + 'px' }"></div>
                                            <div class="trend-label">{{ item.date }}</div>
                                            <div class="trend-value">{{ item.count }}</div>
                                        </div>
                                    </div>
                                </el-card>
                            </div>
                        </el-tab-pane>

                        <!-- ADMIN-002: users -->
                        <el-tab-pane :label="t('im_admin_page.tabs.users')" name="users">
                            <div class="tab-content">
                                <div class="toolbar">
                                    <el-input v-model="adminUserQuery" :placeholder="t('im_admin_page.search_user_ph')" size="small" clearable style="width:260px" @keydown.enter="loadAdminUsers" />
                                    <el-button size="small" type="primary" @click="loadAdminUsers">{{ t('actions.search') }}</el-button>
                                </div>
                                <el-table :data="adminUsers" v-loading="adminLoadingUsers" stripe size="small" style="width:100%">
                                    <el-table-column prop="id" :label="t('im_admin_page.cols.id')" width="60" />
                                    <el-table-column prop="name" :label="t('im_admin_page.cols.username')" min-width="120" />
                                    <el-table-column prop="email" :label="t('im_admin_page.cols.email')" min-width="180" />
                                    <el-table-column :label="t('im_admin_page.cols.msg_count')" width="80"><template #default="{row}">{{ row.total_msgs || 0 }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.conv_count')" width="80"><template #default="{row}">{{ row.total_convs || 0 }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.status')" width="80">
                                        <template #default="{row}"><el-tag :type="row.status === 'active' ? 'success' : 'danger'" size="small">{{ row.status }}</el-tag></template>
                                    </el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.registered_at')" width="160"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.ops')" width="140">
                                        <template #default="{row}">
                                            <el-button size="small" text @click="showAdminUserDetail(row.id)">{{ t('im_admin_page.detail') }}</el-button>
                                            <el-button v-if="row.status === 'active'" size="small" text type="danger" @click="banAdminUser(row)">{{ t('im_admin_page.ban') }}</el-button>
                                            <el-button v-else size="small" text type="success" @click="unbanAdminUser(row)">{{ t('im_admin_page.unban') }}</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-pagination v-if="adminUserTotal > 20" background layout="prev,pager,next" :total="adminUserTotal" :page-size="20" @current-change="page => { adminUserPage = page; loadAdminUsers() }" style="margin-top:12px;justify-content:center" />
                            </div>
                        </el-tab-pane>

                        <!-- ADMIN-003: groups -->
                        <el-tab-pane :label="t('im_admin_page.tabs.groups')" name="groups">
                            <div class="tab-content">
                                <div class="toolbar">
                                    <el-input v-model="adminGroupQuery" :placeholder="t('im_admin_page.search_group_ph')" size="small" clearable style="width:260px" @keydown.enter="loadAdminGroups" />
                                    <el-button size="small" type="primary" @click="loadAdminGroups">{{ t('actions.search') }}</el-button>
                                </div>
                                <el-table :data="adminGroups" v-loading="adminLoadingGroups" stripe size="small" style="width:100%">
                                    <el-table-column prop="id" :label="t('im_admin_page.cols.id')" width="60" />
                                    <el-table-column prop="name" :label="t('im_admin_page.cols.group_name')" min-width="160" />
                                    <el-table-column :label="t('im_admin_page.cols.creator')" width="120"><template #default="{row}">{{ row.creator?.name || '-' }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.member_count')" width="80"><template #default="{row}">{{ row.member_count || 0 }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.created_at')" width="160"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.ops')" width="120">
                                        <template #default="{row}">
                                            <el-button size="small" text @click="showAdminGroupDetail(row.id)">{{ t('im_admin_page.detail') }}</el-button>
                                            <el-popconfirm :title="t('im_admin_page.dismiss_confirm')" @confirm="dismissAdminGroup(row.id)">
                                                <template #reference><el-button size="small" text type="danger">{{ t('im_admin_page.dismiss') }}</el-button></template>
                                            </el-popconfirm>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-pagination v-if="adminGroupTotal > 20" background layout="prev,pager,next" :total="adminGroupTotal" :page-size="20" @current-change="page => { adminGroupPage = page; loadAdminGroups() }" style="margin-top:12px;justify-content:center" />
                            </div>
                        </el-tab-pane>

                        <!-- ADMIN-004: message audit -->
                        <el-tab-pane :label="t('im_admin_page.tabs.audit')" name="audit">
                            <div class="tab-content">
                                <div class="toolbar" style="flex-wrap:wrap;gap:6px">
                                    <el-input v-model="adminAuditQuery" :placeholder="t('im_admin_page.keyword_ph')" size="small" clearable style="width:160px" />
                                    <el-select v-model="adminAuditType" :placeholder="t('im_admin_page.msg_type')" size="small" clearable style="width:130px">
                                        <el-option v-for="opt in adminAuditTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                    <el-date-picker v-model="adminAuditDateRange" type="daterange" size="small" :range-separator="t('im_admin_page.date_sep')" :start-placeholder="t('im_admin_page.date_start')" :end-placeholder="t('im_admin_page.date_end')" value-format="YYYY-MM-DD" style="width:220px" />
                                    <el-button size="small" type="primary" @click="loadAdminAudit">{{ t('actions.search') }}</el-button>
                                </div>
                                <el-table :data="adminAuditMsgs" v-loading="adminLoadingAudit" stripe size="small" style="width:100%">
                                    <el-table-column prop="id" :label="t('im_admin_page.cols.id')" width="60" />
                                    <el-table-column :label="t('im_admin_page.cols.sender')" width="100"><template #default="{row}">{{ row.sender?.name || '-' }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.conversation')" width="140"><template #default="{row}">{{ row.conversation?.name || '-' }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.type')" width="70"><template #default="{row}"><el-tag size="small">{{ row.message_type }}</el-tag></template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.content')" min-width="200">
                                        <template #default="{row}">
                                            <div class="audit-content">{{ row.content?.substring(0, 100) }}{{ row.content?.length > 100 ? '...' : '' }}</div>
                                        </template>
                                    </el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.time')" width="150"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.ops')" width="80">
                                        <template #default="{row}">
                                            <el-popconfirm :title="t('im_admin_page.delete_msg_confirm')" @confirm="deleteAdminAuditMsg(row.id)">
                                                <template #reference><el-button size="small" text type="danger">{{ t('actions.delete') }}</el-button></template>
                                            </el-popconfirm>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-pagination v-if="adminAuditTotal > 20" background layout="prev,pager,next" :total="adminAuditTotal" :page-size="20" @current-change="page => { adminAuditPage = page; loadAdminAudit() }" style="margin-top:12px;justify-content:center" />
                            </div>
                        </el-tab-pane>

                        <!-- ADMIN-007: reports -->
                        <el-tab-pane :label="t('im_admin_page.tabs.reports')" name="reports">
                            <div class="tab-content">
                                <div class="toolbar">
                                    <el-select v-model="adminReportStatus" size="small" clearable style="width:150px">
                                        <el-option v-for="opt in adminReportStatusOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                    <el-button size="small" type="primary" @click="loadAdminReports">{{ t('im_admin_page.refresh') }}</el-button>
                                </div>
                                <el-table :data="adminReports" v-loading="adminLoadingReports" stripe size="small" style="width:100%">
                                    <el-table-column prop="id" :label="t('im_admin_page.cols.id')" width="60" />
                                    <el-table-column :label="t('im_admin_page.cols.reporter')" width="100"><template #default="{row}">{{ row.reporter?.name || '-' }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.reported')" width="140"><template #default="{row}">{{ row.reportable?.user?.name || row.reportable?.name || row.reportable?.sender?.name || '-' }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.reason')" min-width="180"><template #default="{row}">{{ row.reason }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.status')" width="90">
                                        <template #default="{row}"><el-tag :type="row.status === 'resolved' ? 'success' : 'warning'" size="small">{{ row.status }}</el-tag></template>
                                    </el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.time')" width="150"><template #default="{row}">{{ row.created_at }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.ops')" width="120">
                                        <template #default="{row}">
                                            <el-button v-if="row.status !== 'resolved'" size="small" type="primary" text @click="resolveAdminReport(row.id)">{{ t('im_admin_page.resolve') }}</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <el-pagination v-if="adminReportTotal > 20" background layout="prev,pager,next" :total="adminReportTotal" :page-size="20" @current-change="page => { adminReportPage = page; loadAdminReports() }" style="margin-top:12px;justify-content:center" />
                            </div>
                        </el-tab-pane>

                        <!-- OA category management -->
                        <el-tab-pane :label="t('im_admin_page.tabs.oa_cats')" name="oaCats">
                            <div class="tab-content">
                                <div class="toolbar">
                                    <el-button size="small" type="primary" @click="openAdminNewOaCat">{{ t('im_admin_page.new_category') }}</el-button>
                                    <el-button size="small" @click="loadAdminOaCats">{{ t('im_admin_page.refresh') }}</el-button>
                                </div>
                                <el-table :data="adminOaCats" v-loading="adminLoadingOaCats" stripe size="small" style="width:100%">
                                    <el-table-column :label="t('im_admin_page.cols.icon')" width="60"><template #default="{row}">{{ row.icon || '📌' }}</template></el-table-column>
                                    <el-table-column prop="name" :label="t('im_admin_page.cols.category_name')" width="120" />
                                    <el-table-column prop="sort_order" :label="t('im_admin_page.cols.sort')" width="70" />
                                    <el-table-column :label="t('im_admin_page.cols.status')" width="70">
                                        <template #default="{row}"><el-tag :type="row.is_active ? 'success' : 'danger'" size="small">{{ row.is_active ? t('actions.enable') : t('actions.disable') }}</el-tag></template>
                                    </el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.oa_count')" width="80"><template #default="{row}">{{ row.accounts_count || 0 }}</template></el-table-column>
                                    <el-table-column :label="t('im_admin_page.cols.ops')" width="140">
                                        <template #default="{row}">
                                            <el-button size="small" text @click="editAdminOaCat(row)">{{ t('actions.edit') }}</el-button>
                                            <el-button size="small" text type="danger" @click="deleteAdminOaCat(row)">{{ t('actions.delete') }}</el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                            </div>
                        </el-tab-pane>

                        <!-- Conversations -->
                        <el-tab-pane :label="t('im_admin_page.tabs.conversations')" name="conversations">
                            <div style="margin-bottom:10px;display:flex;gap:6px;align-items:center">
                                <el-input v-model="adminConvSearch" :placeholder="t('im_admin_page.search_conv_ph')" size="small" clearable prefix-icon="Search" style="width:260px" @keydown.enter="loadAdminConversations(1)" />
                                <el-select v-model="adminConvType" size="small" style="width:100px" @change="loadAdminConversations(1)">
                                    <el-option v-for="opt in adminConvTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </div>
                            <el-table :data="adminConvs" v-loading="adminLoadingConvs" stripe size="small" @row-click="showAdminConvDetail">
                                <el-table-column prop="id" :label="t('im_admin_page.cols.id')" width="60" />
                                <el-table-column :label="t('im_admin_page.cols.type')" width="70">
                                    <template #default="{row}"><el-tag :type="row.type === 'group' ? 'success' : 'info'" size="small">{{ adminConvTypeLabel(row.type) }}</el-tag></template>
                                </el-table-column>
                                <el-table-column :label="t('im_admin_page.cols.name')" min-width="160">
                                    <template #default="{row}">{{ row.name || row.participants?.map(p => p.user?.name).filter(Boolean).join(', ') || '-' }}</template>
                                </el-table-column>
                                <el-table-column :label="t('im_admin_page.cols.member_count')" width="70" prop="participants_count" />
                                <el-table-column :label="t('im_admin_page.cols.msg_count')" width="70" prop="messages_count" />
                                <el-table-column :label="t('im_admin_page.cols.last_active')" width="140">
                                    <template #default="{row}">{{ formatTime(row.updated_at) }}</template>
                                </el-table-column>
                                <el-table-column :label="t('im_admin_page.cols.ops')" width="80" fixed="right">
                                    <template #default="{row}">
                                        <el-button size="small" text type="danger" @click.stop="deleteAdminConv(row)">{{ t('actions.delete') }}</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                            <div v-if="adminConvTotal > 20" style="margin-top:10px;text-align:center">
                                <el-pagination background layout="prev,pager,next" :total="adminConvTotal" :page-size="20" :current-page="adminConvPage" @current-change="loadAdminConversations" />
                            </div>
                        </el-tab-pane>
                    </el-tabs>

                    <!-- user detail dialog -->
                    <el-dialog v-model="adminShowUserDetailDialog" :title="t('im_admin_page.user_detail_title')" width="500px">
                        <div v-if="adminUserDetail" class="user-detail">
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.id') }}</span><span>{{ adminUserDetail.user?.id }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.username') }}</span><span>{{ adminUserDetail.user?.name }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.email') }}</span><span>{{ adminUserDetail.user?.email }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.status') }}</span><el-tag :type="adminUserDetail.user?.status === 'active' ? 'success' : 'danger'" size="small">{{ adminUserDetail.user?.status }}</el-tag></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.online') }}</span><el-tag :type="adminUserDetail.online === 'online' ? 'success' : 'info'" size="small">{{ adminUserDetail.online }}</el-tag></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.friend_count') }}</span><span>{{ adminUserDetail.friend_count || 0 }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.msg_count') }}</span><span>{{ adminUserDetail.total_msgs || 0 }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.conv_count') }}</span><span>{{ adminUserDetail.total_convs || 0 }}</span></div>
                            <div class="detail-row"><span class="detail-label">{{ t('im_admin_page.labels.last_active') }}</span><span>{{ adminUserDetail.last_active || '-' }}</span></div>
                        </div>
                    </el-dialog>

                    <!-- OA category dialog -->
                    <el-dialog v-model="adminShowOaCatDialog" :title="adminOaCatEditId ? t('im_admin_page.edit_category') : t('im_admin_page.create_category')" width="380px">
                        <el-form label-width="70px">
                            <el-form-item :label="t('im_admin_page.labels.name')">
                                <el-input v-model="adminOaCatForm.name" :placeholder="t('im_admin_page.name_ph')" maxlength="50" />
                            </el-form-item>
                            <el-form-item :label="t('im_admin_page.labels.icon')">
                                <el-input v-model="adminOaCatForm.icon" :placeholder="t('im_admin_page.icon_ph')" maxlength="10" />
                            </el-form-item>
                            <el-form-item :label="t('im_admin_page.labels.sort')">
                                <el-input-number v-model="adminOaCatForm.sort_order" :min="0" size="small" />
                            </el-form-item>
                            <el-form-item :label="t('im_admin_page.labels.enabled')">
                                <el-switch v-model="adminOaCatForm.is_active" />
                            </el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button size="small" @click="adminShowOaCatDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button size="small" type="primary" :loading="adminSavingOaCat" @click="saveAdminOaCat">{{ t('actions.save') }}</el-button>
                        </template>
                    </el-dialog>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('im_page.tabs.ai_chat')" name="ai-chat">
                <div class="tab-content">
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.total_conversations') }}</div>
                                    <div class="stat-value">{{ aiStats.total_conversations }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.total_messages') }}</div>
                                    <div class="stat-value">{{ aiStats.total_messages }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.satisfaction') }}</div>
                                    <div class="stat-value" :class="satisfactionClass">{{ aiStats.satisfaction_rate }}%</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.helpful') }}</div>
                                    <div class="stat-value" style="color:#67c23a">{{ aiStats.helpful_count }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.unhelpful') }}</div>
                                    <div class="stat-value" style="color:#f56c6c">{{ aiStats.unhelpful_count }}</div>
                                </div>
                            </el-card>
                        </el-col>
                        <el-col :span="4">
                            <el-card shadow="never">
                                <div class="stat-item">
                                    <div class="stat-label">{{ t('im_page.stats.rag_documents') }}</div>
                                    <div class="stat-value" style="color:#0f172a">{{ ragStats.total_documents }}</div>
                                </div>
                            </el-card>
                        </el-col>
                    </el-row>

                    <el-tabs v-model="aiSubTab" class="ai-sub-tabs">
                        <el-tab-pane :label="t('im_page.ai_sub_tabs.chat_test')" name="chat">
                            <el-row :gutter="16">
                                <el-col :span="14">
                                    <el-card shadow="never" class="chat-card">
                                        <template #header>
                                            <div class="card-header">
                                                <span>{{ t('im_page.chat.title') }}</span>
                                                <el-button text size="small" @click="resetAiChat">{{ t('im_page.chat.clear') }}</el-button>
                                            </div>
                                        </template>
                                        <div class="chat-messages" ref="chatContainer">
                                            <div v-if="aiMessages.length === 0" class="chat-empty">
                                                <el-empty :image-size="60" :description="t('im_page.chat.empty_desc')" />
                                            </div>
                                            <div v-for="(msg, idx) in aiMessages" :key="idx" class="chat-msg" :class="msg.role">
                                                <el-avatar :size="32" :icon="msg.role === 'user' ? UserFilled : MagicStick" :style="{ background: msg.role === 'user' ? '#0f172a' : '#67c23a' }" />
                                                <div class="msg-content">
                                                    <div class="msg-text">{{ msg.content }}</div>
                                                    <div v-if="msg.sources?.length" class="msg-sources">
                                                        <el-tag v-for="(s, si) in msg.sources" :key="si" size="small" effect="plain" style="margin:2px">
                                                            {{ s.title || t('im_page.chat.source_fallback') }} ({{ (s.score * 100).toFixed(0) }}%)
                                                        </el-tag>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="chat-input">
                                            <el-input v-model="aiChatInput" :placeholder="t('im_page.chat.input_ph')" @keyup.enter="sendAiChat" :disabled="sendingAiChat">
                                                <template #append>
                                                    <el-button :loading="sendingAiChat" @click="sendAiChat" type="primary">
                                                        <el-icon><Promotion /></el-icon>
                                                    </el-button>
                                                </template>
                                            </el-input>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="10">
                                    <el-card shadow="never">
                                        <template #header>
                                            <div class="card-header"><span>{{ t('im_page.chat.supported_intents') }}</span></div>
                                        </template>
                                        <div v-loading="loadingIntents" class="intent-list">
                                            <div v-for="intent in intents" :key="intent.name" class="intent-item">
                                                <div class="intent-name">
                                                    <el-tag size="small" type="primary" effect="plain">{{ intent.name }}</el-tag>
                                                </div>
                                                <div class="intent-desc">{{ intent.description }}</div>
                                                <div v-if="intent.examples" class="intent-examples">
                                                    <span v-for="(ex, ei) in intent.examples" :key="ei" class="example-tag">{{ ex }}</span>
                                                </div>
                                            </div>
                                            <el-empty v-if="intents.length === 0 && !loadingIntents" :image-size="50" :description="t('im_page.chat.no_intents')" />
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </el-tab-pane>

                        <el-tab-pane :label="t('im_page.ai_sub_tabs.rag')" name="rag">
                            <el-row :gutter="16">
                                <el-col :span="16">
                                    <el-card shadow="never">
                                        <template #header>
                                            <div class="card-header">
                                                <span>{{ t('im_page.rag.index_status') }}</span>
                                                <el-button type="primary" size="small" @click="rebuildRagIndex" :loading="rebuildingRag">
                                                    <el-icon><Refresh /></el-icon> {{ t('im_page.rag.rebuild_index') }}
                                                </el-button>
                                            </div>
                                        </template>
                                        <div class="rag-stats">
                                            <div class="rag-stat-row">
                                                <span class="rag-stat-label">{{ t('im_page.rag.total_documents') }}</span>
                                                <span class="rag-stat-value">{{ ragStats.total_documents }}</span>
                                            </div>
                                            <div class="rag-stat-row">
                                                <span class="rag-stat-label">{{ t('im_page.rag.total_conversations') }}</span>
                                                <span class="rag-stat-value">{{ ragStats.total_conversations }}</span>
                                            </div>
                                            <div class="rag-stat-row">
                                                <span class="rag-stat-label">{{ t('im_page.rag.total_messages') }}</span>
                                                <span class="rag-stat-value">{{ ragStats.total_messages }}</span>
                                            </div>
                                            <el-divider />
                                            <h4 class="subsection-title">{{ t('im_page.rag.source_distribution') }}</h4>
                                            <div class="source-distribution">
                                                <div v-for="(count, source) in ragStats.documents_by_source" :key="source" class="source-row">
                                                    <el-tag size="small" effect="plain">{{ source }}</el-tag>
                                                    <el-progress :percentage="ragSourcePercent(source)" :stroke-width="16" :text-inside="true" :format="() => formatRagDocCount(count)" />
                                                </div>
                                                <el-empty v-if="!Object.keys(ragStats.documents_by_source || {}).length" :image-size="50" :description="t('im_page.rag.no_documents')" />
                                            </div>
                                        </div>
                                    </el-card>
                                </el-col>
                                <el-col :span="8">
                                    <el-card shadow="never">
                                        <template #header>
                                            <div class="card-header"><span>{{ t('im_page.rag.search_test') }}</span></div>
                                        </template>
                                        <div class="rag-test">
                                            <el-input v-model="ragQuery" :placeholder="t('im_page.rag.query_ph')" @keyup.enter="searchRag">
                                                <template #append>
                                                    <el-button :loading="searchingRag" @click="searchRag"><el-icon><Search /></el-icon></el-button>
                                                </template>
                                            </el-input>
                                            <div class="rag-results" v-if="ragResults.length">
                                                <div v-for="(r, idx) in ragResults" :key="idx" class="rag-result-item">
                                                    <div class="result-score">
                                                        <el-tag :type="r.score > 0.7 ? 'success' : r.score > 0.4 ? 'warning' : 'info'" size="small">{{ (r.score * 100).toFixed(0) }}%</el-tag>
                                                    </div>
                                                    <div class="result-content">
                                                        <div class="result-title">{{ r.title || t('im_page.rag.untitled') }}</div>
                                                        <div class="result-snippet">{{ r.snippet || r.content?.substring(0, 150) }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <el-empty v-if="ragSearched && !ragResults.length" :image-size="50" :description="t('im_page.rag.no_results')" />
                                        </div>
                                    </el-card>
                                </el-col>
                            </el-row>
                        </el-tab-pane>

                    </el-tabs>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('im_page.tabs.integration')" name="integration">
                <div class="tab-content">
                    <el-row :gutter="20">
                        <el-col :span="8">
                            <el-card shadow="never" class="integration-card" @click="$router.push('/im-integration')">
                                <div class="integration-icon">📢</div>
                                <div class="integration-name">{{ t('im_page.integration.im_notify') }}</div>
                                <div class="integration-desc">{{ t('im_page.integration.im_notify_desc') }}</div>
                                <el-tag size="small" type="info">{{ t('im_page.integration.view_details') }}</el-tag>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never" class="integration-card is-static">
                                <div class="integration-icon">🌐</div>
                                <div class="integration-name">{{ t('im_page.integration.websocket') }}</div>
                                <div class="integration-desc">{{ t('im_page.integration.websocket_guide_desc') }}</div>
                                <el-tag size="small" type="info">{{ t('im_page.integration.websocket_guide') }}</el-tag>
                            </el-card>
                        </el-col>
                        <el-col :span="8">
                            <el-card shadow="never" class="integration-card" @click="$router.push('/tickets')">
                                <div class="integration-icon">🎫</div>
                                <div class="integration-name">{{ t('im_page.integration.tickets') }}</div>
                                <div class="integration-desc">{{ t('im_page.integration.tickets_desc') }}</div>
                                <el-tag size="small" type="info">{{ t('im_page.integration.view_tickets') }}</el-tag>
                            </el-card>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('im_page.tabs.settings')" name="settings">
                <div class="tab-content">
                    <el-row :gutter="16">
                        <el-col :span="12">
                            <el-card shadow="never">
                                <template #header><span>{{ t('im_page.settings.asr') }}</span></template>
                                <el-form label-width="180px" size="small">
                                    <el-form-item :label="t('im_page.settings.asr_provider')">
                                        <el-select v-model="asrConfig.provider" style="width:100%">
                                            <el-option
                                                v-for="opt in asrProviderOptions"
                                                :key="opt.value"
                                                :label="opt.label"
                                                :value="opt.value"
                                            />
                                        </el-select>
                                    </el-form-item>
                                    <el-form-item v-if="asrConfig.provider === 'openai'" :label="t('im_page.settings.openai_key')">
                                        <el-input v-model="asrConfig.openai_key" type="password" :placeholder="t('im_page.settings.openai_key_ph')" />
                                    </el-form-item>
                                    <template v-if="asrConfig.provider === 'aliyun'">
                                        <el-form-item :label="t('im_page.settings.app_key')">
                                            <el-input v-model="asrConfig.aliyun_app_key" :placeholder="t('im_page.settings.app_key_ph')" />
                                        </el-form-item>
                                        <el-form-item :label="t('im_page.settings.access_key_id')">
                                            <el-input v-model="asrConfig.aliyun_access_key" :placeholder="t('im_page.settings.access_key_id_ph')" />
                                        </el-form-item>
                                        <el-form-item :label="t('im_page.settings.access_key_secret')">
                                            <el-input v-model="asrConfig.aliyun_access_secret" type="password" :placeholder="t('im_page.settings.access_key_secret_ph')" />
                                        </el-form-item>
                                    </template>
                                    <template v-if="asrConfig.provider === 'tencent'">
                                        <el-form-item :label="t('im_page.settings.secret_id')">
                                            <el-input v-model="asrConfig.tencent_secret_id" :placeholder="t('im_page.settings.secret_id_ph')" />
                                        </el-form-item>
                                        <el-form-item :label="t('im_page.settings.secret_key')">
                                            <el-input v-model="asrConfig.tencent_secret_key" type="password" :placeholder="t('im_page.settings.secret_key_ph')" />
                                        </el-form-item>
                                    </template>
                                    <el-form-item>
                                        <el-button type="primary" @click="saveAsrConfig" :loading="savingAsr">{{ t('im_page.settings.save_asr') }}</el-button>
                                    </el-form-item>
                                </el-form>
                            </el-card>
                            <div style="margin-top:12px">
                                <el-button @click="$router.push('/settings')">{{ t('im_page.settings.system_settings') }}</el-button>
                            </div>
                        </el-col>
                    </el-row>
                </div>
            </el-tab-pane>

            <el-tab-pane :label="t('im_page.tabs.faq')" name="faq">
                <div class="tab-content">
                    <div class="tab-summary">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-500">{{ t('im_page.faq.summary') }}</p>
                            <div class="flex gap-2">
                                <el-button @click="loadFaqs" :loading="faqLoading">
                                    <el-icon><Refresh /></el-icon> {{ t('im_page.faq.refresh') }}
                                </el-button>
                                <el-button type="success" @click="openFaqAddDialog">
                                    {{ t('im_page.faq.add') }}
                                </el-button>
                                <el-button type="primary" @click="openFaqAdmin">
                                    {{ t('im_page.faq.open_admin') }}
                                </el-button>
                            </div>
                        </div>
                        <el-table :data="faqList" v-loading="faqLoading" stripe size="small">
                            <el-table-column label="#" width="50">
                                <template #default="{ $index }">{{ $index + 1 }}</template>
                            </el-table-column>
                            <el-table-column prop="icon" :label="t('im_page.faq.col_icon')" width="60" />
                            <el-table-column prop="question" :label="t('im_page.faq.col_question')" min-width="200" show-overflow-tooltip />
                            <el-table-column prop="answer" :label="t('im_page.faq.col_answer')" min-width="240" show-overflow-tooltip />
                            <el-table-column :label="t('im_page.faq.col_status')" width="80">
                                <template #default="{ row }">
                                    <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('im_page.faq.active') : t('im_page.faq.inactive') }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('im_page.faq.col_sort')" width="120" fixed="right">
                                <template #default="{ row, $index }">
                                    <el-button text size="small" :disabled="$index === 0" @click="moveFaq(row, -1)">↑</el-button>
                                    <el-button text size="small" :disabled="$index === faqList.length - 1" @click="moveFaq(row, 1)">↓</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </div>
                </div>

                <el-dialog v-model="faqDialog.visible" :title="t('im_page.faq.add_title')" width="500px" :close-on-click-modal="false">
                    <el-form :model="faqDialog.form" label-position="top" @submit.prevent="submitFaq">
                        <el-form-item :label="t('im_page.faq.col_question')" required>
                            <el-input v-model="faqDialog.form.question" :placeholder="t('im_page.faq.question_ph')" maxlength="200" />
                        </el-form-item>
                        <el-form-item :label="t('im_page.faq.col_answer')">
                            <el-input v-model="faqDialog.form.answer" type="textarea" :rows="3" :placeholder="t('im_page.faq.answer_ph')" maxlength="500" />
                        </el-form-item>
                        <el-form-item :label="t('im_page.faq.icon_label')">
                            <el-input v-model="faqDialog.form.icon" :placeholder="t('im_page.faq.icon_ph')" maxlength="10" />
                        </el-form-item>
                        <el-form-item>
                            <el-switch v-model="faqDialog.form.is_active" :active-text="t('actions.enable')" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="faqDialog.visible = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" @click="submitFaq" :loading="faqDialog.submitting">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Promotion, Search, Refresh, UserFilled, MagicStick } from '@element-plus/icons-vue';
import apiClient from '@/utils/request';
import { getAdminFaqs, createFaq, reorderFaqs } from '@/api/chatFaq';

const { t } = useI18n();
const route = useRoute();
const VALID_IM_TABS = ['im-admin', 'ai-chat', 'integration', 'settings', 'faq'];
const REMAPPED_IM_TABS = { agentWorkspace: 'ai-chat', liveChat: 'ai-chat' };
const activeTab = ref('ai-chat');

function resolveImTab(raw) {
    const tab = String(raw || '');
    if (REMAPPED_IM_TABS[tab]) return REMAPPED_IM_TABS[tab];
    return VALID_IM_TABS.includes(tab) ? tab : 'ai-chat';
}

// ==============================
// IM 管理后台 state (prefixed with admin_)
// ==============================
const adminTabVisited = ref(false);
const adminActiveTab = ref('dashboard');

// dashboard
const adminLoadingDash = ref(false);
const adminDash = reactive({
    total_users: 0, total_groups: 0, total_conversations: 0,
    total_messages: 0, today_messages: 0, week_messages: 0,
    active_users_7d: 0, pending_reports: 0, message_trend: [],
});
function adminTrendHeight(count) { return Math.min(Math.max(count, 4), 120); }

async function loadAdminDash() {
    adminLoadingDash.value = true;
    try {
        const res = await apiClient.get('/im-admin/dashboard');
        const d = res.data?.data || {};
        Object.assign(adminDash, d);
    } catch { /* ignore */ }
    finally { adminLoadingDash.value = false; }
}

// users
const adminUsers = ref([]);
const adminLoadingUsers = ref(false);
const adminUserQuery = ref('');
const adminUserPage = ref(1);
const adminUserTotal = ref(0);
const adminShowUserDetailDialog = ref(false);
const adminUserDetail = ref(null);

async function loadAdminUsers() {
    adminLoadingUsers.value = true;
    try {
        const res = await apiClient.get('/im-admin/users', { params: { q: adminUserQuery.value, per_page: 20, page: adminUserPage.value } });
        adminUsers.value = res.data?.data || [];
        adminUserTotal.value = res.data?.meta?.total || 0;
    } catch { adminUsers.value = []; }
    finally { adminLoadingUsers.value = false; }
}
async function showAdminUserDetail(id) {
    try {
        const res = await apiClient.get('/im-admin/users/' + id);
        adminUserDetail.value = res.data?.data || null;
        adminShowUserDetailDialog.value = true;
    } catch { ElMessage.error(t('im_admin_page.msg_user_detail_fail')); }
}
async function banAdminUser(row) {
    try {
        await ElMessageBox.confirm(t('im_admin_page.ban_confirm', { name: row.name }), t('actions.confirm'), { type: 'warning' });
        await apiClient.post('/im-admin/users/' + row.id + '/ban');
        ElMessage.success(t('im_admin_page.msg_banned'));
        loadAdminUsers();
    } catch { /* cancelled */ }
}
async function unbanAdminUser(row) {
    try {
        await apiClient.post('/im-admin/users/' + row.id + '/unban');
        ElMessage.success(t('im_admin_page.msg_unbanned'));
        loadAdminUsers();
    } catch { ElMessage.error(t('messages.failed')); }
}

// groups
const adminGroups = ref([]);
const adminLoadingGroups = ref(false);
const adminGroupQuery = ref('');
const adminGroupPage = ref(1);
const adminGroupTotal = ref(0);

async function loadAdminGroups() {
    adminLoadingGroups.value = true;
    try {
        const res = await apiClient.get('/im-admin/groups', { params: { q: adminGroupQuery.value, per_page: 20, page: adminGroupPage.value } });
        adminGroups.value = res.data?.data || [];
        adminGroupTotal.value = res.data?.meta?.total || 0;
    } catch { adminGroups.value = []; }
    finally { adminLoadingGroups.value = false; }
}
async function showAdminGroupDetail(id) {
    try {
        const res = await apiClient.get('/im-admin/groups/' + id);
        const d = res.data?.data;
        const msg = t('im_admin_page.group_detail_msg', {
            name: d.group.name,
            creator: d.group.creator?.name || '-',
            members: d.member_count,
            messages: d.total_messages,
        });
        ElMessageBox.alert(msg, t('im_admin_page.group_detail_title'));
    } catch { /* ignore */ }
}
async function dismissAdminGroup(id) {
    try {
        await apiClient.delete('/im-admin/groups/' + id);
        ElMessage.success(t('im_admin_page.msg_group_dismissed'));
        loadAdminGroups();
    } catch { ElMessage.error(t('messages.failed')); }
}

// message audit
const adminAuditMsgs = ref([]);
const adminLoadingAudit = ref(false);
const adminAuditQuery = ref('');
const adminAuditType = ref('');
const adminAuditDateRange = ref(null);
const adminAuditPage = ref(1);
const adminAuditTotal = ref(0);

async function loadAdminAudit() {
    adminLoadingAudit.value = true;
    try {
        const params = { per_page: 20, page: adminAuditPage.value };
        if (adminAuditQuery.value) params.q = adminAuditQuery.value;
        if (adminAuditType.value) params.message_type = adminAuditType.value;
        if (adminAuditDateRange.value && adminAuditDateRange.value[0]) params.date_from = adminAuditDateRange.value[0];
        if (adminAuditDateRange.value && adminAuditDateRange.value[1]) params.date_to = adminAuditDateRange.value[1];
        const res = await apiClient.get('/im-admin/messages', { params });
        adminAuditMsgs.value = res.data?.data || [];
        adminAuditTotal.value = res.data?.meta?.total || 0;
    } catch { adminAuditMsgs.value = []; }
    finally { adminLoadingAudit.value = false; }
}
async function deleteAdminAuditMsg(id) {
    try {
        await apiClient.delete('/im-admin/messages/' + id);
        ElMessage.success(t('im_admin_page.msg_msg_deleted'));
        loadAdminAudit();
    } catch { ElMessage.error(t('im_admin_page.msg_delete_fail')); }
}

// reports
const adminReports = ref([]);
const adminLoadingReports = ref(false);
const adminReportStatus = ref('');
const adminReportPage = ref(1);
const adminReportTotal = ref(0);

async function loadAdminReports() {
    adminLoadingReports.value = true;
    try {
        const params = { per_page: 20, page: adminReportPage.value };
        if (adminReportStatus.value) params.status = adminReportStatus.value;
        const res = await apiClient.get('/im-admin/reports', { params });
        adminReports.value = res.data?.data || [];
        adminReportTotal.value = res.data?.meta?.total || 0;
    } catch { adminReports.value = []; }
    finally { adminLoadingReports.value = false; }
}
async function resolveAdminReport(id) {
    try {
        await ElMessageBox.prompt(t('im_admin_page.resolve_prompt_note'), t('im_admin_page.resolve_prompt_title'), {
            inputType: 'textarea',
            inputPlaceholder: t('im_admin_page.resolve_prompt_ph'),
            confirmButtonText: t('im_admin_page.resolve'),
            cancelButtonText: t('actions.cancel'),
        })
            .then(async ({ value }) => {
                await apiClient.post('/im-admin/reports/' + id + '/resolve', { note: value || '' });
                ElMessage.success(t('im_admin_page.msg_report_resolved'));
                loadAdminReports();
            });
    } catch { /* cancelled */ }
}

// OA categories
const adminOaCats = ref([]);
const adminLoadingOaCats = ref(false);
const adminShowOaCatDialog = ref(false);
const adminOaCatEditId = ref(null);
const adminSavingOaCat = ref(false);
const adminOaCatForm = reactive({ name: '', icon: '', sort_order: 0, is_active: true });

async function loadAdminOaCats() {
    adminLoadingOaCats.value = true;
    try { const r = await apiClient.get('/official-accounts/admin/categories'); adminOaCats.value = r.data?.data || []; }
    catch { adminOaCats.value = []; }
    finally { adminLoadingOaCats.value = false; }
}
function openAdminNewOaCat() {
    adminOaCatEditId.value = null; adminOaCatForm.name = ''; adminOaCatForm.icon = ''; adminOaCatForm.sort_order = 0; adminOaCatForm.is_active = true;
    adminShowOaCatDialog.value = true;
}
function editAdminOaCat(row) {
    adminOaCatEditId.value = row.id; adminOaCatForm.name = row.name; adminOaCatForm.icon = row.icon || ''; adminOaCatForm.sort_order = row.sort_order ?? 0; adminOaCatForm.is_active = row.is_active !== false;
    adminShowOaCatDialog.value = true;
}
async function saveAdminOaCat() {
    if (!adminOaCatForm.name.trim()) { ElMessage.warning(t('im_admin_page.msg_name_required')); return; }
    adminSavingOaCat.value = true;
    try {
        const payload = { name: adminOaCatForm.name.trim(), icon: adminOaCatForm.icon || undefined, sort_order: adminOaCatForm.sort_order, is_active: adminOaCatForm.is_active };
        if (adminOaCatEditId.value) { await apiClient.put('/official-accounts/admin/categories/' + adminOaCatEditId.value, payload); ElMessage.success(t('im_admin_page.msg_updated')); }
        else { await apiClient.post('/official-accounts/admin/categories', payload); ElMessage.success(t('im_admin_page.msg_created')); }
        adminShowOaCatDialog.value = false; await loadAdminOaCats();
    } catch (e) { ElMessage.error(e.response?.data?.message || t('im_admin_page.msg_save_fail')); }
    finally { adminSavingOaCat.value = false; }
}
async function deleteAdminOaCat(row) {
    try {
        await ElMessageBox.confirm(t('im_admin_page.delete_category_confirm', { name: row.name }));
        await apiClient.delete('/official-accounts/admin/categories/' + row.id);
        ElMessage.success(t('im_admin_page.msg_deleted')); await loadAdminOaCats();
    } catch { /* cancelled */ }
}

// conversations
const adminConvs = ref([]);
const adminLoadingConvs = ref(false);
const adminConvSearch = ref('');
const adminConvType = ref('');
const adminConvPage = ref(1);
const adminConvTotal = ref(0);

async function loadAdminConversations(page) {
    if (page) adminConvPage.value = page;
    adminLoadingConvs.value = true;
    try {
        const params = { per_page: 20, page: adminConvPage.value };
        if (adminConvSearch.value) params.q = adminConvSearch.value;
        if (adminConvType.value) params.type = adminConvType.value;
        const r = await apiClient.get('/im-admin/conversations', { params });
        adminConvs.value = r.data?.data || [];
        adminConvTotal.value = r.data?.meta?.total || 0;
    } catch { adminConvs.value = []; }
    finally { adminLoadingConvs.value = false; }
}
async function showAdminConvDetail(conv) {
    try {
        const r = await apiClient.get('/im-admin/conversations/' + conv.id);
        const data = r.data?.data;
        if (!data) return;
        const parts = data.conversation?.participants?.map(p => p.user?.name).filter(Boolean).join(', ') || '-';
        const recentMsgs = data.recent_messages?.slice(0, 10).map(m => `[${m.sender?.name || '?'}] ${m.content?.substring(0, 50)}`).join('\n') || t('im_admin_page.conv_detail_no_msgs');
        const typeLabel = adminConvTypeLabel(conv.type);
        const html = `<div style="font-size:14px"><b>${t('im_admin_page.conv_detail_heading', { id: conv.id })}</b><br>${t('im_admin_page.conv_detail_type')}: ${typeLabel}<br>${t('im_admin_page.conv_detail_participants')}: ${parts}<br>${t('im_admin_page.conv_detail_total_msgs')}: ${conv.messages_count}<br><br><b>${t('im_admin_page.conv_detail_recent')}:</b><br>${recentMsgs}</div>`;
        ElMessageBox.alert(html, t('im_admin_page.conv_detail_title'), { dangerouslyUseHTMLString: true, confirmButtonText: t('actions.close') });
    } catch { ElMessage.error(t('messages.load_failed')); }
}
async function deleteAdminConv(conv) {
    try {
        await ElMessageBox.confirm(t('im_admin_page.delete_conv_confirm'), t('actions.confirm'), { type: 'warning' });
        await apiClient.delete('/im-admin/conversations/' + conv.id);
        ElMessage.success(t('im_admin_page.msg_deleted'));
        loadAdminConversations();
    } catch { /* cancelled */ }
}

function adminConvTypeLabel(type) {
    return type === 'group' ? t('im_admin_page.conv_types.group') : t('im_admin_page.conv_types.private');
}

// admin computed options
const adminAuditTypeOptions = computed(() => [
    { label: t('im_admin_page.all'), value: '' },
    { label: t('im_admin_page.msg_types.text'), value: 'text' },
    { label: t('im_admin_page.msg_types.image'), value: 'image' },
    { label: t('im_admin_page.msg_types.file'), value: 'file' },
    { label: t('im_admin_page.msg_types.voice'), value: 'voice' },
    { label: t('im_admin_page.msg_types.sticker'), value: 'sticker' },
    { label: t('im_admin_page.msg_types.card'), value: 'card' },
]);

const adminReportStatusOptions = computed(() => [
    { label: t('im_admin_page.all'), value: '' },
    { label: t('im_admin_page.report_status.pending'), value: 'pending' },
    { label: t('im_admin_page.report_status.resolved'), value: 'resolved' },
]);

const adminConvTypeOptions = computed(() => [
    { label: t('im_admin_page.all'), value: '' },
    { label: t('im_admin_page.conv_types.private'), value: 'private' },
    { label: t('im_admin_page.conv_types.group'), value: 'group' },
]);

// lazy-load all admin data on first visit
function loadAllAdminData() {
    loadAdminDash();
    loadAdminUsers();
    loadAdminGroups();
    loadAdminAudit();
    loadAdminReports();
    loadAdminOaCats();
    loadAdminConversations();
}

// format time helper (used by admin tab)
function formatTime(value) {
    if (!value) return '-';
    try {
        const d = new Date(value);
        if (isNaN(d.getTime())) return value;
        return d.toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch { return value; }
}

// ==============================
// Original IM state
// ==============================
const faqList = ref([]);
const faqLoading = ref(false);

const asrProviderOptions = computed(() => [
    { label: t('im_page.settings.asr_mock'), value: 'mock' },
    { label: t('im_page.settings.asr_openai'), value: 'openai' },
    { label: t('im_page.settings.asr_aliyun'), value: 'aliyun' },
    { label: t('im_page.settings.asr_tencent'), value: 'tencent' },
]);

function formatRagDocCount(count) {
    return t('im_page.rag.doc_count', { n: count });
}

const faqDialog = reactive({
    visible: false,
    submitting: false,
    form: {
        question: '',
        answer: '',
        icon: '💬',
        is_active: true,
    },
});

function openFaqAdmin() {
    window.open('/admin/chat-faqs', '_blank');
}

async function loadFaqs() {
    faqLoading.value = true;
    try {
        const { data } = await getAdminFaqs();
        faqList.value = data?.data || [];
    } catch {
        ElMessage.error(t('im_page.faq.load_fail'));
    } finally {
        faqLoading.value = false;
    }
}

async function moveFaq(row, direction) {
    const idx = faqList.value.findIndex(f => f.id === row.id);
    const swapIdx = idx + direction;
    if (idx < 0 || swapIdx < 0 || swapIdx >= faqList.value.length) return;

    const items = [...faqList.value];
    [items[idx], items[swapIdx]] = [items[swapIdx], items[idx]];
    const orders = items.map((item, i) => ({ id: item.id, sort_order: i }));

    try {
        await reorderFaqs(orders);
        faqList.value = items.map((item, i) => ({ ...item, sort_order: i }));
        ElMessage.success(t('im_page.faq.sort_ok'));
    } catch {
        ElMessage.error(t('im_page.faq.sort_fail'));
    }
}

function openFaqAddDialog() {
    faqDialog.form = { question: '', answer: '', icon: '💬', is_active: true };
    faqDialog.visible = true;
}

async function submitFaq() {
    if (!faqDialog.form.question.trim()) {
        ElMessage.warning(t('im_page.faq.question_required'));
        return;
    }
    faqDialog.submitting = true;
    try {
        const { data } = await createFaq(faqDialog.form);
        if (data.success) {
            ElMessage.success(t('im_page.faq.add_ok'));
            faqDialog.visible = false;
            await loadFaqs();
        } else {
            ElMessage.error(data.errors?.question?.[0] || data.errors?.answer?.[0] || t('im_page.faq.add_fail'));
        }
    } catch {
        ElMessage.error(t('messages.network_error'));
    } finally {
        faqDialog.submitting = false;
    }
}

const aiStats = reactive({ total_conversations: 0, total_messages: 0, satisfaction_rate: 0, helpful_count: 0, unhelpful_count: 0 });
const ragStats = reactive({ total_documents: 0, total_conversations: 0, total_messages: 0, documents_by_source: {} });

const satisfactionClass = computed(() => {
    if (aiStats.satisfaction_rate >= 80) return 'text-success';
    if (aiStats.satisfaction_rate >= 60) return 'text-warning';
    return 'text-danger';
});

const aiSubTab = ref('chat');
const aiChatInput = ref('');
const aiMessages = ref([]);
const sendingAiChat = ref(false);
const sessionId = ref('session-' + Date.now());
const intents = ref([]);
const loadingIntents = ref(false);
const chatContainer = ref(null);
const rebuildingRag = ref(false);
const ragQuery = ref('');
const ragResults = ref([]);
const searchingRag = ref(false);
const ragSearched = ref(false);

const asrConfig = reactive({
    provider: 'mock',
    openai_key: '',
    aliyun_app_key: '',
    aliyun_access_key: '',
    aliyun_access_secret: '',
    tencent_secret_id: '',
    tencent_secret_key: '',
});
const savingAsr = ref(false);

async function saveAsrConfig() {
    savingAsr.value = true;
    try {
        const settings = [
            { key: 'asr_provider', value: asrConfig.provider },
        ];
        if (asrConfig.provider === 'openai') settings.push({ key: 'asr_openai_key', value: asrConfig.openai_key });
        if (asrConfig.provider === 'aliyun') {
            settings.push({ key: 'asr_aliyun_app_key', value: asrConfig.aliyun_app_key });
            settings.push({ key: 'asr_aliyun_access_key', value: asrConfig.aliyun_access_key });
            settings.push({ key: 'asr_aliyun_access_secret', value: asrConfig.aliyun_access_secret });
        }
        if (asrConfig.provider === 'tencent') {
            settings.push({ key: 'asr_tencent_secret_id', value: asrConfig.tencent_secret_id });
            settings.push({ key: 'asr_tencent_secret_key', value: asrConfig.tencent_secret_key });
        }
        await apiClient.post('/settings', { settings });
        ElMessage.success(t('im_page.settings.asr_save_ok'));
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('im_page.save_fail'));
    } finally {
        savingAsr.value = false;
    }
}

async function loadAsrConfig() {
    try {
        const res = await apiClient.get('/settings/all');
        const items = res.data?.data || [];
        const map = {};
        items.forEach(item => { map[item.key] = item.value; });
        asrConfig.provider = map.asr_provider || 'mock';
        asrConfig.openai_key = map.asr_openai_key || '';
        asrConfig.aliyun_app_key = map.asr_aliyun_app_key || '';
        asrConfig.aliyun_access_key = map.asr_aliyun_access_key || '';
        asrConfig.aliyun_access_secret = map.asr_aliyun_access_secret || '';
        asrConfig.tencent_secret_id = map.asr_tencent_secret_id || '';
        asrConfig.tencent_secret_key = map.asr_tencent_secret_key || '';
    } catch { /* ignore */ }
}

async function loadStats() {
    try {
        const [chatRes, ragRes, intentRes] = await Promise.allSettled([
            apiClient.get('/chat/stats'),
            apiClient.get('/rag/stats'),
            apiClient.get('/chat/intents'),
        ]);
        if (chatRes.status === 'fulfilled' && chatRes.value.data?.success) {
            Object.assign(aiStats, chatRes.value.data.data || {});
        }
        if (ragRes.status === 'fulfilled') {
            Object.assign(ragStats, ragRes.value.data?.data || {});
        }
        if (intentRes.status === 'fulfilled') {
            intents.value = intentRes.value.data?.data || [];
        }
    } catch { /* ignore */ }
}

function resetAiChat() {
    sessionId.value = 'session-' + Date.now();
    aiMessages.value = [];
}

async function sendAiChat() {
    const message = aiChatInput.value.trim();
    if (!message) return;
    aiMessages.value.push({ role: 'user', content: message });
    aiChatInput.value = '';
    sendingAiChat.value = true;
    try {
        const res = await apiClient.post('/chat/send', {
            session_id: sessionId.value,
            message,
            save_conversation: false,
        });
        const data = res.data?.data || res.data || {};
        aiMessages.value.push({
            role: 'assistant',
            content: data.answer || data.reply || data.content || t('im_page.chat.no_reply'),
            sources: data.sources || [],
        });
        await nextTick();
        if (chatContainer.value) chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
    } catch {
        aiMessages.value.push({ role: 'assistant', content: t('im_page.chat.reply_failed') });
    } finally {
        sendingAiChat.value = false;
    }
}

function ragSourcePercent(source) {
    const total = ragStats.total_documents || 1;
    const count = ragStats.documents_by_source[source] || 0;
    return Math.round((count / total) * 100);
}

async function rebuildRagIndex() {
    rebuildingRag.value = true;
    try {
        await apiClient.post('/rag/rebuild');
        ElMessage.success(t('im_page.rag.rebuild_triggered'));
        const ragRes = await apiClient.get('/rag/stats');
        Object.assign(ragStats, ragRes.data?.data || {});
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('im_page.rag.rebuild_fail'));
    } finally {
        rebuildingRag.value = false;
    }
}

async function searchRag() {
    const q = ragQuery.value.trim();
    if (!q) return;
    searchingRag.value = true;
    ragSearched.value = true;
    try {
        const res = await apiClient.post('/rag/retrieve', { query: q });
        ragResults.value = res.data?.data || [];
    } catch {
        ragResults.value = [];
    } finally {
        searchingRag.value = false;
    }
}

// lazy-load admin data when im-admin tab is first visited
function onTabChange(tab) {
    if (tab === 'im-admin' && !adminTabVisited.value) {
        adminTabVisited.value = true;
        loadAllAdminData();
    }
}

onMounted(() => {
    if (route.query.tab) {
        activeTab.value = resolveImTab(route.query.tab);
    }
    loadStats();
    loadAsrConfig();
    if (activeTab.value === 'faq') {
        loadFaqs();
    }
    if (activeTab.value === 'im-admin') {
        adminTabVisited.value = true;
        loadAllAdminData();
    }
});

watch(activeTab, (tab) => {
    if (tab === 'faq' && !faqList.value.length) {
        loadFaqs();
    }
    if (tab === 'im-admin' && !adminTabVisited.value) {
        adminTabVisited.value = true;
        loadAllAdminData();
    }
});
</script>

<style scoped>
.im-center { padding: 20px; }
.page-header { margin-bottom: 20px; }
.page-header h2 { margin: 0; }
.im-tabs { min-height: 400px; }
.tab-content { padding: 10px 0; }
.tab-actions { margin-top: 20px; text-align: center; }
.stat-item { text-align: center; padding: 10px; }
.stat-value { font-size: 32px; font-weight: bold; color: #0f172a; }
.stat-label { font-size: 14px; color: #909399; margin-top: 8px; }
.integration-card { cursor: pointer; text-align: center; padding: 10px; transition: all .3s; }
.integration-card:hover { border-color: #0f172a; transform: translateY(-2px); }
.integration-card.is-static { cursor: default; }
.integration-card.is-static:hover { transform: none; border-color: inherit; }
.integration-icon { font-size: 36px; margin-bottom: 8px; }
.integration-name { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
.integration-desc { font-size: 12px; color: #909399; margin-bottom: 10px; }
.config-hint { font-size: 12px; color: #909399; margin-left: 10px; }

/* AI 子标签页 */
.ai-sub-tabs { margin-top: 16px; }
.ai-sub-tabs :deep(.el-tabs__item) { font-size: 13px; }
.chat-card .chat-messages { height: 360px; overflow-y: auto; padding: 10px; }
.chat-card .chat-msg { display: flex; gap: 10px; margin-bottom: 16px; }
.chat-card .chat-msg.assistant { flex-direction: row; }
.chat-card .chat-msg.user { flex-direction: row-reverse; }
.chat-card .msg-content { max-width: 70%; }
.chat-card .msg-text { padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.5; }
.chat-card .chat-msg.user .msg-text { background: #0f172a; color: #fff; border-bottom-right-radius: 4px; }
.chat-card .chat-msg.assistant .msg-text { background: #f0f0f0; color: #303133; border-bottom-left-radius: 4px; }
.chat-card .msg-sources { margin-top: 4px; }
.chat-card .chat-empty { text-align: center; padding: 80px 0; }
.chat-card .chat-input { border-top: 1px solid #ebeef5; padding-top: 10px; }
.text-success { color: #67c23a !important; }
.text-warning { color: #e6a23c !important; }
.text-danger { color: #f56c6c !important; }
.intent-list { max-height: 400px; overflow-y: auto; }
.intent-item { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.intent-item:last-child { border-bottom: none; }
.intent-name { margin-bottom: 4px; }
.intent-desc { font-size: 12px; color: #909399; margin: 4px 0; }
.intent-examples { display: flex; flex-wrap: wrap; gap: 4px; }
.example-tag { font-size: 11px; background: #f5f7fa; padding: 2px 8px; border-radius: 10px; color: #606266; }
.rag-stats { padding: 10px 0; }
.rag-stat-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f5f7fa; }
.rag-stat-label { font-size: 13px; color: #606266; }
.rag-stat-value { font-size: 14px; font-weight: 600; color: #303133; }
.subsection-title { font-size: 14px; font-weight: 600; margin: 12px 0 8px; }
.source-row { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
.source-row .el-progress { flex: 1; }
.rag-test { min-height: 200px; }
.rag-results { margin-top: 12px; }
.rag-result-item { display: flex; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.result-score { flex-shrink: 0; }
.result-title { font-size: 13px; font-weight: 500; margin-bottom: 2px; }
.result-snippet { font-size: 12px; color: #909399; line-height: 1.4; }
.mb-4 { margin-bottom: 16px; }
.mt-4 { margin-top: 16px; }

/* IM 管理后台 styles (merged from im-admin) */
.im-admin-tabs { min-height: 400px; }
.im-admin-tabs .tab-content { padding: 16px 0; }
.stat-cards { margin-bottom: 0; }
.stat-cards .stat-item { padding: 8px 0; }
.stat-cards .stat-value { font-size: 28px; }
.stat-cards .stat-label { font-size: 13px; }
.toolbar { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.trend-chart { display: flex; align-items: flex-end; gap: 12px; height: 140px; padding: 10px 0; justify-content: center; }
.trend-bar-wrap { display: flex; flex-direction: column; align-items: center; gap: 2px; }
.trend-bar { width: 36px; background: linear-gradient(180deg, #0f172a, #66b1ff); border-radius: 4px 4px 0 0; min-height: 4px; transition: height 0.3s; }
.trend-label { font-size: 11px; color: #909399; }
.trend-value { font-size: 12px; color: #606266; font-weight: 600; }
.audit-content { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.user-detail .detail-row { display: flex; padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
.user-detail .detail-label { width: 100px; color: #909399; flex-shrink: 0; }
</style>
