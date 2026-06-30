<template>
    <div class="open-platform-page">
        <div class="page-header">
            <div>
                <h2>{{ pageTitle }}</h2>
                <p class="text-muted">{{ pageSubtitle }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">刷新</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">开发者</div><div class="stat-value">{{ stats.active_developers || 0 }}/{{ stats.total_developers || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">待审开发者</div><div class="stat-value warning">{{ stats.pending_developers || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">已上架应用</div><div class="stat-value success">{{ stats.published_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">待审应用</div><div class="stat-value warning">{{ stats.pending_review_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">总应用数</div><div class="stat-value">{{ stats.total_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">安装数</div><div class="stat-value primary">{{ stats.total_installations || 0 }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="hover">
            <el-tabs v-model="activeTab" @tab-change="onTabChange">
                <el-tab-pane label="应用审核" name="pending">
                    <el-table :data="pendingApps" stripe v-loading="pendingLoading">
                        <el-table-column label="应用" min-width="180">
                            <template #default="{ row }">
                                <div class="app-name">{{ row.name }}</div>
                                <div class="text-muted small">{{ row.slug }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column label="开发者" width="140"><template #default="{ row }">{{ row.developer?.display_name || '-' }}</template></el-table-column>
                        <el-table-column label="分类" width="100"><template #default="{ row }">{{ categoryLabel(row.category) }}</template></el-table-column>
                        <el-table-column label="版本" width="80" prop="current_version" />
                        <el-table-column label="提交时间" width="160"><template #default="{ row }">{{ fmtDate(row.updated_at) }}</template></el-table-column>
                        <el-table-column label="操作" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openAppDetail(row)">详情</el-button>
                                <el-button size="small" link type="success" @click="reviewApp(row, 'approve')">通过</el-button>
                                <el-button size="small" link type="warning" @click="openReviewDialog(row, 'request_changes')">退回</el-button>
                                <el-button size="small" link type="danger" @click="openReviewDialog(row, 'reject')">驳回</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="全部应用" name="apps">
                    <div class="tab-toolbar">
                        <el-input v-model="appFilters.search" placeholder="搜索应用" clearable style="width:200px" @keyup.enter="loadApps" />
                        <el-select v-model="appFilters.status" placeholder="状态" clearable style="width:130px" @change="loadApps">
                            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                        <el-select v-model="appFilters.category" placeholder="分类" clearable style="width:120px" @change="loadApps">
                            <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                    </div>
                    <el-table :data="apps" stripe v-loading="appsLoading">
                        <el-table-column label="应用" min-width="160"><template #default="{ row }">{{ row.name }}</template></el-table-column>
                        <el-table-column label="开发者" width="120"><template #default="{ row }">{{ row.developer?.display_name }}</template></el-table-column>
                        <el-table-column label="分类" width="100"><template #default="{ row }">{{ categoryLabel(row.category) }}</template></el-table-column>
                        <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column label="安装" width="70" prop="install_count" />
                        <el-table-column label="定价" width="90"><template #default="{ row }">{{ row.pricing_type === 'free' ? '免费' : '¥' + row.price }}</template></el-table-column>
                        <el-table-column label="操作" width="240">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openAppDetail(row)">详情</el-button>
                                <el-button v-if="row.status === 'published'" size="small" type="danger" link @click="suspendApp(row)">下架</el-button>
                                <el-button v-if="row.status === 'suspended'" size="small" type="success" link @click="unsuspendApp(row)">恢复</el-button>
                                <el-button v-if="row.status === 'published'" size="small" link @click="forceUpdateApp(row)">强制更新</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="appsTotal" :page-size="20" v-model:current-page="appsPage" @current-change="loadApps" />
                </el-tab-pane>

                <el-tab-pane label="开发者" name="developers">
                    <div class="tab-toolbar">
                        <el-select v-model="devFilters.status" placeholder="状态" clearable style="width:120px" @change="loadDevelopers">
                            <el-option label="待审核" value="pending" /><el-option label="活跃" value="active" /><el-option label="已暂停" value="suspended" />
                        </el-select>
                    </div>
                    <el-table :data="developers" stripe v-loading="devLoading">
                        <el-table-column label="名称" prop="display_name" min-width="140" />
                        <el-table-column label="公司" prop="company_name" width="140" />
                        <el-table-column label="用户" min-width="160"><template #default="{ row }">{{ row.user?.name }} ({{ row.user?.email }})</template></el-table-column>
                        <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">{{ devStatusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column label="应用数" width="80"><template #default="{ row }">{{ row.apps_count || 0 }}</template></el-table-column>
                        <el-table-column label="操作" width="140">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'pending'" size="small" link type="success" @click="verifyDev(row, 'approve')">通过</el-button>
                                <el-button v-if="row.status === 'active'" size="small" link type="danger" @click="verifyDev(row, 'suspend')">暂停</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="安装记录" name="installations">
                    <el-table :data="installations" stripe v-loading="instLoading">
                        <el-table-column label="应用" min-width="140"><template #default="{ row }">{{ row.app?.name }}</template></el-table-column>
                        <el-table-column label="用户" min-width="140"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
                        <el-table-column label="版本" width="80" prop="installed_version" />
                        <el-table-column label="状态" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ row.status === 'active' ? '已安装' : '已卸载' }}</el-tag></template></el-table-column>
                        <el-table-column label="安装时间" width="160"><template #default="{ row }">{{ fmtDate(row.installed_at) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 评价审核 -->
                <el-tab-pane label="评价审核" name="reviews">
                    <div class="toolbar">
                        <el-select v-model="reviewFilter.status" clearable placeholder="状态" style="width:130px" @change="loadAdminReviews">
                            <el-option label="全部" value="" />
                            <el-option label="待审核" value="pending" />
                            <el-option label="已通过" value="approved" />
                            <el-option label="已拒绝" value="rejected" />
                        </el-select>
                    </div>
                    <el-table :data="adminReviews" stripe v-loading="adminReviewLoading">
                        <el-table-column label="应用" min-width="140"><template #default="{ row }">{{ row.app?.name }}</template></el-table-column>
                        <el-table-column label="用户" width="120"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
                        <el-table-column label="评分" width="120">
                            <template #default="{ row }"><el-rate :model-value="row.rating" disabled size="small" /></template>
                        </el-table-column>
                        <el-table-column label="内容" min-width="200">
                            <template #default="{ row }"><span class="text-muted">{{ row.content || '（无文字）' }}</span></template>
                        </el-table-column>
                        <el-table-column label="状态" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'danger' : 'warning'" size="small">
                                    {{ row.status === 'approved' ? '已通过' : row.status === 'rejected' ? '已拒绝' : '待审核' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'pending'" text size="small" type="success" @click="approveReview(row)">通过</el-button>
                                <el-button v-if="row.status === 'pending'" text size="small" type="danger" @click="rejectReview(row)">拒绝</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- Banner管理 -->
                <el-tab-pane label="Banner管理" name="banners">
                    <div class="toolbar">
                        <el-button type="primary" @click="openBannerDialog()"><el-icon><Plus /></el-icon> 新建 Banner</el-button>
                    </div>
                    <el-table :data="banners" stripe v-loading="bannerLoading">
                        <el-table-column label="标题" prop="title" min-width="160" />
                        <el-table-column label="副标题" prop="subtitle" min-width="160" class-name="text-muted" />
                        <el-table-column label="链接" width="160">
                            <template #default="{ row }">{{ row.link_value || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="排序" width="60" prop="sort_order" align="center" />
                        <el-table-column label="状态" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openBannerDialog(row)">编辑</el-button>
                                <el-button text size="small" type="danger" @click="deleteBanner(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <!-- 内容安全 -->
                <el-tab-pane label="内容安全" name="security">
                    <div class="toolbar">
                        <el-button type="primary" @click="handleScanApps" :loading="scanAppsLoading"><el-icon><Search /></el-icon> 扫描应用</el-button>
                        <el-button @click="handleScanReviews" :loading="scanReviewsLoading"><el-icon><Search /></el-icon> 扫描评价</el-button>
                        <span class="text-muted" style="margin-left:8px">基于敏感词库自动检测违规内容</span>
                    </div>
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value" :class="secStats.flagged_apps > 0 ? 'danger' : 'success'">{{ secStats.flagged_apps ?? '-' }}</div><div class="stat-label">违规应用</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value success">{{ secStats.clean_apps ?? '-' }}</div><div class="stat-label">安全应用</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value warning">{{ secStats.pending_reviews ?? '-' }}</div><div class="stat-label">待审评价</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ secStats.total_apps ?? '-' }}</div><div class="stat-label">应用总数</div></el-card></el-col>
                    </el-row>

                    <el-table :data="securityResults" v-loading="secResultsLoading" stripe v-if="securityResults.length">
                        <el-table-column label="类型" width="80">
                            <template #default="{ row }"><el-tag :type="row.review_id ? 'warning' : ''" size="small">{{ row.review_id ? '评价' : '应用' }}</el-tag></template>
                        </el-table-column>
                        <el-table-column label="名称" min-width="180">
                            <template #default="{ row }">{{ row.app_name || row.user_name }}</template>
                        </el-table-column>
                        <el-table-column label="违规项" width="300">
                            <template #default="{ row }">
                                <el-tag v-for="v in (row.violations || [])" :key="v.field" size="small" :type="v.severity >= 3 ? 'danger' : v.severity >= 2 ? 'warning' : 'info'" style="margin:1px">
                                    {{ v.field_label }} ({{ v.words.length }})
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="严重程度" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.max_severity >= 3 ? 'danger' : row.max_severity >= 2 ? 'warning' : 'info'" size="small">
                                    {{ ['', '低', '中', '高'][row.max_severity] || '低' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="120">
                            <template #default="{ row }">
                                <el-button v-if="row.app_id" text size="small" type="primary" @click="openAppDetail({id: row.app_id})">查看应用</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!secResultsLoading" description="点击扫描按钮检查内容安全" :image-size="50" />
                </el-tab-pane>

                <!-- 分类管理 -->
                <el-tab-pane label="分类管理" name="categories">
                    <div class="toolbar">
                        <el-button type="primary" @click="showCategoryDialog(null)"><el-icon><Plus /></el-icon> 新建分类</el-button>
                    </div>
                    <el-table :data="catList" v-loading="catLoading" stripe>
                        <el-table-column label="名称" prop="name" min-width="200" />
                        <el-table-column label="Slug" prop="slug" width="150" />
                        <el-table-column label="图标" width="80" align="center">
                            <template #default="{ row }">{{ row.icon || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="排序" prop="sort_order" width="80" align="center" />
                        <el-table-column label="应用数" width="80" align="center">
                            <template #default="{ row }">{{ row.apps_count ?? '-' }}</template>
                        </el-table-column>
                        <el-table-column label="操作" width="200">
                            <template #default="{ row }">
                                <el-button size="small" @click="showCategoryDialog(row)">编辑</el-button>
                                <el-popconfirm title="确认删除此分类？" @confirm="deleteCategory(row)">
                                    <template #reference><el-button size="small" type="danger">删除</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!catList.length && !catLoading" description="暂无分类" :image-size="50" />
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="reviewVisible" :title="reviewAction === 'reject' ? '驳回应用' : '退回修改'" width="420px">
            <el-input v-model="reviewNotes" type="textarea" :rows="4" placeholder="审核备注" />
            <template #footer>
                <el-button @click="reviewVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitReview">确认</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" title="应用详情" size="580px">
            <template v-if="currentApp">
                <el-descriptions :column="1" border>
                    <el-descriptions-item label="名称">{{ currentApp.name }}</el-descriptions-item>
                    <el-descriptions-item label="Slug">{{ currentApp.slug }}</el-descriptions-item>
                    <el-descriptions-item label="开发者">{{ currentApp.developer?.display_name }}</el-descriptions-item>
                    <el-descriptions-item label="分类">{{ categoryLabel(currentApp.category) }}</el-descriptions-item>
                    <el-descriptions-item label="状态">{{ statusLabel(currentApp.status) }}</el-descriptions-item>
                    <el-descriptions-item label="当前版本">{{ currentApp.current_version || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="简介">{{ currentApp.short_description || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="描述">{{ currentApp.description || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="权限">{{ (currentApp.permissions || []).join(', ') || '-' }}</el-descriptions-item>
                </el-descriptions>

                <!-- 版本列表 -->
                <el-divider />
                <div class="section-title">版本历史</div>
                <el-timeline v-if="appVersions.length">
                    <el-timeline-item v-for="v in appVersions" :key="v.id" :timestamp="fmtDate(v.created_at)" placement="top">
                        <div class="version-item">
                            <el-tag size="small" type="primary">v{{ v.version }}</el-tag>
                            <el-tag v-if="v.status === 'published'" size="small" type="success">已发布</el-tag>
                            <el-tag v-else size="small">{{ v.status }}</el-tag>
                        </div>
                        <div class="version-changelog text-muted">{{ v.changelog || '无更新说明' }}</div>
                        <div v-if="v.package_url" class="version-url">
                            <a :href="v.package_url" target="_blank" class="text-link">📦 下载安装包</a>
                        </div>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else description="暂无版本记录" :image-size="40" />

                <el-divider />
                <div class="drawer-actions">
                    <el-button type="primary" @click="openUploader('package')" :icon="UploadFilled">上传安装包</el-button>
                    <el-button @click="openUploader('screenshot')" :icon="PictureFilled">上传截图</el-button>
                    <el-button type="success" @click="showAddVersion = true" :icon="Plus">创建版本</el-button>
                </div>
            </template>
        </el-drawer>

        <!-- 创建版本 Dialog -->
        <el-dialog v-model="showAddVersion" title="创建新版本" width="480px">
            <el-form :model="verForm" label-width="120px">
                <el-form-item label="版本号" required>
                    <el-input v-model="verForm.version" placeholder="如: 1.0.0" maxlength="30" />
                </el-form-item>
                <el-form-item label="更新说明">
                    <el-input v-model="verForm.changelog" type="textarea" :rows="4" placeholder="本次更新的内容" maxlength="5000" />
                </el-form-item>
                <el-form-item label="安装包 URL">
                    <el-input v-model="verForm.package_url" placeholder="上传后自动填入，或手动输入 URL">
                        <template #append>
                            <el-button @click="verForm.package_url = lastUploadedUrl || ''" :disabled="!lastUploadedUrl">使用上次上传</el-button>
                        </template>
                    </el-input>
                    <div v-if="lastUploadedUrl" class="text-success" style="font-size:12px;margin-top:4px">
                        上次上传: {{ lastUploadedUrl }}
                    </div>
                </el-form-item>
                <el-form-item label="最低平台版本">
                    <el-input v-model="verForm.min_platform_version" placeholder="如: 1.0.0" maxlength="30" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddVersion = false">取消</el-button>
                <el-button type="primary" :loading="verSaving" @click="submitVersion">发布版本</el-button>
            </template>
        </el-dialog>

        <!-- Banner 编辑 Dialog -->
        <el-dialog v-model="bannerDialogVisible" :title="editingBanner ? '编辑 Banner' : '新建 Banner'" width="520px">
            <el-form :model="bannerForm" label-width="100px">
                <el-form-item label="标题" required>
                    <el-input v-model="bannerForm.title" placeholder="Banner 标题" />
                </el-form-item>
                <el-form-item label="副标题">
                    <el-input v-model="bannerForm.subtitle" placeholder="可选副标题" />
                </el-form-item>
                <el-form-item label="图片 URL" required>
                    <el-input v-model="bannerForm.image_url" placeholder="https://..." />
                </el-form-item>
                <el-form-item label="链接类型">
                    <el-select v-model="bannerForm.link_type" style="width:100%">
                        <el-option label="应用" value="app" />
                        <el-option label="分类" value="category" />
                        <el-option label="URL" value="url" />
                    </el-select>
                </el-form-item>
                <el-form-item label="链接值">
                    <el-input v-model="bannerForm.link_value" :placeholder="bannerForm.link_type === 'app' ? '应用 ID' : bannerForm.link_type === 'category' ? '分类 slug' : 'https://...'" />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item label="排序">
                            <el-input-number v-model="bannerForm.sort_order" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item label="启用">
                            <el-switch v-model="bannerForm.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item label="有效期">
                    <el-date-picker v-model="bannerForm.starts_at" type="datetime" placeholder="开始时间" style="width:48%" />
                    <span style="margin:0 8px">至</span>
                    <el-date-picker v-model="bannerForm.ends_at" type="datetime" placeholder="结束时间" style="width:48%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="bannerDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="bannerLoading" @click="saveBanner">保存</el-button>
            </template>
        </el-dialog>

        <!-- 分类编辑 Dialog -->
        <el-dialog v-model="catDialogVisible" :title="editingCat ? '编辑分类' : '新建分类'" width="450px">
            <el-form :model="catForm" label-width="100px">
                <el-form-item label="名称" required>
                    <el-input v-model="catForm.name" placeholder="分类名称" maxlength="100" />
                </el-form-item>
                <el-form-item label="Slug">
                    <el-input v-model="catForm.slug" placeholder="唯一标识符，留空自动生成" maxlength="100" />
                </el-form-item>
                <el-form-item label="图标">
                    <el-input v-model="catForm.icon" placeholder="Emoji 或图标名" maxlength="20" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="catForm.sort_order" :min="0" style="width:100%" />
                </el-form-item>
                <el-form-item label="描述">
                    <el-input v-model="catForm.description" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="catDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="catSaving" @click="saveCategory">保存</el-button>
            </template>
        </el-dialog>
    </div>

    <!-- 文件上传组件 -->
    <MarketplaceUploader ref="uploaderRef" :upload-type="uploaderType" @confirm="onUploadConfirm" />
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, UploadFilled, PictureFilled, Search } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';
import marketplaceApi from '@/api/marketplace';
import securityApi from '@/api/marketplaceSecurity';
import MarketplaceUploader from '@/components/MarketplaceUploader.vue';

const props = defineProps({
    defaultTab: { type: String, default: 'pending' },
});

const route = useRoute();
const isMarketplace = computed(() => route.name === 'AppMarketplace');
const pageTitle = computed(() => isMarketplace.value ? '应用市场' : '开放平台');
const pageSubtitle = computed(() => isMarketplace.value
    ? '浏览和安装第三方开发者提供的插件与应用'
    : '开发者入驻、应用审核与开放平台管理');

const loading = ref(false);
const activeTab = ref(props.defaultTab);
const stats = ref({});
const categories = ref([]);
const statusOptions = [
    { value: 'draft', label: '草稿' }, { value: 'pending_review', label: '待审核' },
    { value: 'published', label: '已上架' }, { value: 'rejected', label: '已驳回' }, { value: 'suspended', label: '已暂停' },
];

const pendingApps = ref([]);
const pendingLoading = ref(false);
const apps = ref([]);
const appsLoading = ref(false);
const appsTotal = ref(0);
const appsPage = ref(1);
const appFilters = reactive({ search: '', status: '', category: '' });

const developers = ref([]);
const devLoading = ref(false);
const devFilters = reactive({ status: '' });

const installations = ref([]);
const instLoading = ref(false);

const reviewVisible = ref(false);
const reviewNotes = ref('');
const reviewAction = ref('reject');
const currentApp = ref(null);
const detailVisible = ref(false);
const submitting = ref(false);

function fmtDate(d) { return d ? new Date(d).toLocaleString('zh-CN') : '-'; }
function categoryLabel(v) { return categories.value.find(c => c.value === v)?.label || v; }
function statusLabel(s) { return statusOptions.find(o => o.value === s)?.label || s; }
function statusTag(s) { return { draft: 'info', pending_review: 'warning', published: 'success', rejected: 'danger', suspended: 'info' }[s] || 'info'; }
function devStatusLabel(s) { return { pending: '待审核', active: '活跃', suspended: '已暂停' }[s] || s; }

async function loadStats() {
    try { const r = await api.stats(); stats.value = r.data?.data || r.data || {}; } catch {}
}
async function loadMetadata() {
    try { const r = await api.metadata(); categories.value = r.data?.data?.categories || r.data?.categories || []; } catch {}
}
async function loadPending() {
    pendingLoading.value = true;
    try {
        const r = await api.pendingApps({ per_page: 50 });
        const d = r.data?.data || r.data;
        pendingApps.value = d?.data || d || [];
    } finally { pendingLoading.value = false; }
}
async function loadApps() {
    appsLoading.value = true;
    try {
        const r = await api.apps({ page: appsPage.value, per_page: 20, ...appFilters });
        const d = r.data?.data || r.data;
        apps.value = d?.data || d || [];
        appsTotal.value = d?.total || apps.value.length;
    } finally { appsLoading.value = false; }
}
async function loadDevelopers() {
    devLoading.value = true;
    try {
        const r = await api.developers({ ...devFilters, per_page: 50 });
        const d = r.data?.data || r.data;
        developers.value = d?.data || d || [];
    } finally { devLoading.value = false; }
}
async function loadInstallations() {
    instLoading.value = true;
    try {
        const r = await api.installations({ per_page: 50 });
        const d = r.data?.data || r.data;
        installations.value = d?.data || d || [];
    } finally { instLoading.value = false; }
}

function onTabChange(tab) {
    if (tab === 'apps') loadApps();
    if (tab === 'developers') loadDevelopers();
    if (tab === 'installations') loadInstallations();
    if (tab === 'reviews') loadAdminReviews();
    if (tab === 'banners') loadBanners();
    if (tab === 'security') { loadSecurityStats(); }
}

// ─── 内容安全 ───
const secStats = ref({});
const securityResults = ref([]);
const secResultsLoading = ref(false);
const scanAppsLoading = ref(false);
const scanReviewsLoading = ref(false);

async function loadSecurityStats() {
    try { const { data: r } = await securityApi.stats(); if (r.success) secStats.value = r.data; } catch {}
}

async function handleScanApps() {
    scanAppsLoading.value = true;
    secResultsLoading.value = true;
    try {
        const { data: r } = await securityApi.scanAllApps();
        if (r.success) {
            securityResults.value = r.data?.results || [];
            ElMessage.info(`扫描完成，发现 ${r.data?.total_flagged || 0} 个违规应用`);
            loadSecurityStats();
        }
    } catch { ElMessage.error('扫描失败'); }
    finally { scanAppsLoading.value = false; secResultsLoading.value = false; }
}

async function handleScanReviews() {
    scanReviewsLoading.value = true;
    secResultsLoading.value = true;
    try {
        const { data: r } = await securityApi.scanAllReviews();
        if (r.success) {
            securityResults.value = r.data?.results || [];
            ElMessage.info(`扫描完成，发现 ${r.data?.total_flagged || 0} 条违规评价`);
            loadSecurityStats();
        }
    } catch { ElMessage.error('扫描失败'); }
    finally { scanReviewsLoading.value = false; secResultsLoading.value = false; }
}

async function refreshAll() {
    loading.value = true;
    await Promise.all([loadStats(), loadMetadata(), loadPending()]);
    if (activeTab.value === 'apps') await loadApps();
    if (activeTab.value === 'developers') await loadDevelopers();
    if (activeTab.value === 'installations') await loadInstallations();
    loading.value = false;
}

async function openAppDetail(row) {
    try {
        const r = await api.showApp(row.id);
        currentApp.value = r.data?.data || r.data;
        detailVisible.value = true;
        loadAppVersions(row.id);
    } catch { ElMessage.error('加载失败'); }
}

async function suspendApp(row) {
    try {
        await ElMessageBox.confirm(
            `确认下架应用「${row.name}」？所有已安装用户将收到强制通知，安装记录将被暂停。`,
            '紧急下架',
            { confirmButtonText: '确认下架', confirmButtonClass: 'el-button--danger', type: 'warning' }
        );
        await api.suspendApp(row.id, { reason: '运营下架' });
        ElMessage.success('应用已下架，已通知所有安装用户');
        loadApps();
    } catch {}
}

async function unsuspendApp(row) {
    try {
        await ElMessageBox.confirm(`确认恢复应用「${row.name}」上架？`, '恢复上架');
        await api.unsuspendApp(row.id);
        ElMessage.success('应用已恢复上架');
        loadApps();
    } catch {}
}

async function forceUpdateApp(row) {
    try {
        await ElMessageBox.confirm(
            `推送强制更新通知给「${row.name}」的所有安装用户？`,
            '强制更新',
            { confirmButtonText: '确认推送', type: 'warning' }
        );
        await api.forceUpdate(row.id, { reason: '安全更新', version: row.current_version });
        ElMessage.success('强制更新通知已推送');
    } catch {}
}

async function reviewApp(row, action) {
    try {
        if (action === 'approve') {
            await ElMessageBox.confirm(`确认通过应用「${row.name}」？`, '审核');
        }
        await api.reviewApp(row.id, { action, notes: '' });
        ElMessage.success('审核完成');
        await refreshAll();
    } catch (e) { if (e !== 'cancel') ElMessage.error(e.response?.data?.message || '操作失败'); }
}

function openReviewDialog(row, action) {
    currentApp.value = row;
    reviewAction.value = action;
    reviewNotes.value = '';
    reviewVisible.value = true;
}

async function submitReview() {
    submitting.value = true;
    try {
        await api.reviewApp(currentApp.value.id, { action: reviewAction.value, notes: reviewNotes.value });
        ElMessage.success('审核完成');
        reviewVisible.value = false;
        await refreshAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
    finally { submitting.value = false; }
}

async function verifyDev(row, action) {
    try {
        await api.verifyDeveloper(row.id, { action });
        ElMessage.success('开发者状态已更新');
        await refreshAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || '操作失败'); }
}

// ─── 评价审核 ───
const adminReviews = ref([]);
const adminReviewLoading = ref(false);
const reviewFilter = reactive({ status: '' });

async function loadAdminReviews() {
    adminReviewLoading.value = true;
    try {
        const params = { per_page: 50 };
        if (reviewFilter.status) params.status = reviewFilter.status;
        const { data: res } = await marketplaceApi.reviews(0, params);
        // 使用开放平台 reviews 端点需要 app_id，这里使用管理端
        // 改用通用查询
        adminReviews.value = res.data || [];
    } catch { adminReviews.value = []; }
    finally { adminReviewLoading.value = false; }
}

async function approveReview(row) {
    try {
        await ElMessageBox.confirm('确认通过该评价？', '审核');
        await marketplaceApi.reviewModerate(row.id, 'approve');
        ElMessage.success('评价已通过');
        loadAdminReviews();
    } catch {}
}

async function rejectReview(row) {
    try {
        await ElMessageBox.confirm('确认拒绝该评价？', '审核');
        await marketplaceApi.reviewModerate(row.id, 'reject');
        ElMessage.success('评价已拒绝');
        loadAdminReviews();
    } catch {}
}

// ─── Banner管理 ───
const banners = ref([]);
const bannerLoading = ref(false);
const bannerDialogVisible = ref(false);
const editingBanner = ref(null);
const bannerForm = reactive({
    title: '', subtitle: '', image_url: '', link_type: 'app', link_value: '',
    sort_order: 0, is_active: true, starts_at: null, ends_at: null,
});

async function loadBanners() {
    bannerLoading.value = true;
    try {
        const { data: res } = await marketplaceApi.bannersAdmin();
        banners.value = res.data || [];
    } catch { banners.value = []; }
    finally { bannerLoading.value = false; }
}

function openBannerDialog(banner = null) {
    editingBanner.value = banner;
    if (banner) {
        Object.assign(bannerForm, banner);
        bannerForm.starts_at = banner.starts_at || null;
        bannerForm.ends_at = banner.ends_at || null;
    } else {
        bannerForm.title = ''; bannerForm.subtitle = ''; bannerForm.image_url = '';
        bannerForm.link_type = 'app'; bannerForm.link_value = '';
        bannerForm.sort_order = 0; bannerForm.is_active = true;
        bannerForm.starts_at = null; bannerForm.ends_at = null;
    }
    bannerDialogVisible.value = true;
}

async function saveBanner() {
    try {
        if (editingBanner.value) {
            await marketplaceApi.bannerUpdate(editingBanner.value.id, bannerForm);
            ElMessage.success('Banner 已更新');
        } else {
            await marketplaceApi.bannerCreate(bannerForm);
            ElMessage.success('Banner 已创建');
        }
        bannerDialogVisible.value = false;
        loadBanners();
    } catch { ElMessage.error('操作失败'); }
}

async function deleteBanner(row) {
    try {
        await ElMessageBox.confirm('确定删除该 Banner？', '确认删除', { type: 'warning' });
        await marketplaceApi.bannerDelete(row.id);
        ElMessage.success('Banner 已删除');
        loadBanners();
    } catch {}
}

// ─── 分类管理 ───
const catList = ref([]);
const catLoading = ref(false);
const catDialogVisible = ref(false);
const editingCat = ref(null);
const catSaving = ref(false);
const catForm = reactive({ name: '', slug: '', icon: '', sort_order: 0, description: '' });

async function loadCategories() {
    catLoading.value = true;
    try { const { data: res } = await marketplaceApi.categories(); catList.value = res.data || []; }
    catch { catList.value = []; }
    finally { catLoading.value = false; }
}

function showCategoryDialog(cat = null) {
    editingCat.value = cat;
    if (cat) { Object.assign(catForm, { name: cat.name, slug: cat.slug || '', icon: cat.icon || '', sort_order: cat.sort_order || 0, description: cat.description || '' }); }
    else { catForm.name = ''; catForm.slug = ''; catForm.icon = ''; catForm.sort_order = 0; catForm.description = ''; }
    catDialogVisible.value = true;
}

async function saveCategory() {
    if (!catForm.name) { ElMessage.warning('请输入分类名称'); return; }
    catSaving.value = true;
    try {
        if (editingCat.value) { await marketplaceApi.categoryUpdate(editingCat.value.id, catForm); ElMessage.success('分类已更新'); }
        else { await marketplaceApi.categoryCreate(catForm); ElMessage.success('分类已创建'); }
        catDialogVisible.value = false; loadCategories();
    } catch {} finally { catSaving.value = false; }
}

async function deleteCategory(row) {
    try { await ElMessageBox.confirm(`删除分类"${row.name}"？关联应用将变为未分类。`, '确认删除'); await marketplaceApi.categoryDelete(row.id); ElMessage.success('已删除'); loadCategories(); }
    catch {}
}

// ─── 文件上传 ───
const uploaderRef = ref(null);
const uploaderType = ref('package');

function openUploader(type) {
    uploaderType.value = type;
    nextTick(() => uploaderRef.value?.open());
}

function onUploadConfirm(result) {
    lastUploadedUrl.value = result.url || '';
    ElMessage.success(`文件已上传: ${result.original_name}`);

    // 如果版本 Dialog 已打开，自动填入 URL
    if (showAddVersion.value) {
        verForm.package_url = lastUploadedUrl.value;
    }
}

// ─── 版本管理 ───
const appVersions = ref([]);
const showAddVersion = ref(false);
const lastUploadedUrl = ref('');
const verSaving = ref(false);
const verForm = reactive({
    version: '', changelog: '', package_url: '', min_platform_version: '',
});

async function loadAppVersions(appId) {
    try {
        const { data: res } = await api.apps({ app_id: appId, per_page: 50 });
        appVersions.value = res.data?.data || res.data || [];
    } catch { appVersions.value = []; }
}

async function submitVersion() {
    if (!verForm.version || !currentApp.value?.id) { ElMessage.warning('请输入版本号'); return; }
    if (!verForm.package_url) { ElMessage.warning('请上传或填写安装包 URL'); return; }
    verSaving.value = true;
    try {
        await api.addVersion(currentApp.value.id, {
            version: verForm.version,
            changelog: verForm.changelog || null,
            package_url: verForm.package_url,
            min_platform_version: verForm.min_platform_version || null,
            status: 'published',
        });
        ElMessage.success(`版本 v${verForm.version} 已创建`);
        showAddVersion.value = false;
        verForm.version = ''; verForm.changelog = ''; verForm.package_url = ''; verForm.min_platform_version = '';
        loadAppVersions(currentApp.value.id);
    } catch { ElMessage.error('创建版本失败'); }
    finally { verSaving.value = false; }
}

onMounted(() => { refreshAll(); loadCategories(); });
</script>

<style scoped>
.open-platform-page { padding: 0 4px; }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.page-header h2 { margin: 0 0 4px; font-size: 22px; }
.drawer-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.text-muted { color: #909399; font-size: 13px; }
.small { font-size: 12px; }
.tab-toolbar { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.stat-label { font-size: 13px; color: #909399; margin-bottom: 4px; }
.stat-value { font-size: 22px; font-weight: 600; }
.stat-value.warning { color: #e6a23c; }
.stat-value.success { color: #67c23a; }
.stat-value.primary { color: #409eff; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.app-name { font-weight: 500; }
</style>
