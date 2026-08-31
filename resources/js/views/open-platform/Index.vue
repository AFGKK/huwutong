<template>
    <div class="open-platform-page">
        <div class="page-header">
            <div>
                <h2>{{ pageTitle }}</h2>
                <p class="text-muted">{{ pageSubtitle }}</p>
            </div>
            <el-button @click="refreshAll" :loading="loading" :icon="Refresh">{{ t('open_platform_page.refresh') }}</el-button>
        </div>

        <el-row :gutter="16" class="mb-4">
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.developers') }}</div><div class="stat-value">{{ stats.active_developers || 0 }}/{{ stats.total_developers || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.pending_developers') }}</div><div class="stat-value warning">{{ stats.pending_developers || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.published_apps') }}</div><div class="stat-value success">{{ stats.published_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.pending_review_apps') }}</div><div class="stat-value warning">{{ stats.pending_review_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.total_apps') }}</div><div class="stat-value">{{ stats.total_apps || 0 }}</div></el-card></el-col>
            <el-col :xs="12" :sm="8" :md="4"><el-card shadow="hover"><div class="stat-label">{{ t('open_platform_page.stats.installations') }}</div><div class="stat-value primary">{{ stats.total_installations || 0 }}</div></el-card></el-col>
        </el-row>

        <el-card shadow="hover">
            <el-tabs v-model="activeTab" @tab-change="onTabChange">
                <el-tab-pane :label="t('open_platform_page.tabs.pending')" name="pending">
                    <el-table :data="pendingApps" stripe v-loading="pendingLoading">
                        <el-table-column :label="t('open_platform_page.cols.app')" min-width="180">
                            <template #default="{ row }">
                                <div class="app-name">{{ row.name }}</div>
                                <div class="text-muted small">{{ row.slug }}</div>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.developer')" width="140"><template #default="{ row }">{{ row.developer?.display_name || '-' }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.category')" width="100"><template #default="{ row }">{{ categoryLabel(row.category) }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.version')" width="80" prop="current_version" />
                        <el-table-column :label="t('open_platform_page.cols.submitted_at')" width="160"><template #default="{ row }">{{ fmtDate(row.updated_at) }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="220" fixed="right">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openAppDetail(row)">{{ t('open_platform_page.btn.detail') }}</el-button>
                                <el-button size="small" link type="success" @click="reviewApp(row, 'approve')">{{ t('open_platform_page.btn.approve') }}</el-button>
                                <el-button size="small" link type="warning" @click="openReviewDialog(row, 'request_changes')">{{ t('open_platform_page.btn.request_changes') }}</el-button>
                                <el-button size="small" link type="danger" @click="openReviewDialog(row, 'reject')">{{ t('open_platform_page.btn.reject') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.apps')" name="apps">
                    <div class="tab-toolbar">
                        <el-input v-model="appFilters.search" :placeholder="t('open_platform_page.filters.search_apps')" clearable style="width:200px" @keyup.enter="loadApps" />
                        <el-select v-model="appFilters.status" :placeholder="t('open_platform_page.filters.status')" clearable style="width:130px" @change="loadApps">
                            <el-option v-for="s in statusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                        <el-select v-model="appFilters.category" :placeholder="t('open_platform_page.filters.category')" clearable style="width:120px" @change="loadApps">
                            <el-option v-for="c in categories" :key="c.value" :label="c.label" :value="c.value" />
                        </el-select>
                    </div>
                    <el-table :data="apps" stripe v-loading="appsLoading">
                        <el-table-column :label="t('open_platform_page.cols.app')" min-width="160"><template #default="{ row }">{{ row.name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.developer')" width="120"><template #default="{ row }">{{ row.developer?.display_name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.category')" width="100"><template #default="{ row }">{{ categoryLabel(row.category) }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.status')" width="100"><template #default="{ row }"><el-tag :type="statusTag(row.status)" size="small">{{ statusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.installs')" width="70" prop="install_count" />
                        <el-table-column :label="t('open_platform_page.cols.pricing')" width="90"><template #default="{ row }">{{ row.pricing_type === 'free' ? t('open_platform_page.pricing.free') : '¥' + row.price }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="240">
                            <template #default="{ row }">
                                <el-button size="small" link @click="openAppDetail(row)">{{ t('open_platform_page.btn.detail') }}</el-button>
                                <el-button v-if="row.status === 'published'" size="small" type="danger" link @click="suspendApp(row)">{{ t('open_platform_page.btn.suspend') }}</el-button>
                                <el-button v-if="row.status === 'suspended'" size="small" type="success" link @click="unsuspendApp(row)">{{ t('open_platform_page.btn.unsuspend') }}</el-button>
                                <el-button v-if="row.status === 'published'" size="small" link @click="forceUpdateApp(row)">{{ t('open_platform_page.btn.force_update') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination class="mt-3" layout="total, prev, pager, next" :total="appsTotal" :page-size="20" v-model:current-page="appsPage" @current-change="loadApps" />
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.developers')" name="developers">
                    <div class="tab-toolbar">
                        <el-select v-model="devFilters.status" :placeholder="t('open_platform_page.filters.status')" clearable style="width:120px" @change="loadDevelopers">
                            <el-option v-for="s in devStatusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                    </div>
                    <el-table :data="developers" stripe v-loading="devLoading">
                        <el-table-column :label="t('open_platform_page.cols.name')" prop="display_name" min-width="140" />
                        <el-table-column :label="t('open_platform_page.cols.company')" prop="company_name" width="140" />
                        <el-table-column :label="t('open_platform_page.cols.user')" min-width="160"><template #default="{ row }">{{ row.user?.name }} ({{ row.user?.email }})</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.status')" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'info'" size="small">{{ devStatusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.app_count')" width="80"><template #default="{ row }">{{ row.apps_count || 0 }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="140">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'pending'" size="small" link type="success" @click="verifyDev(row, 'approve')">{{ t('open_platform_page.btn.approve') }}</el-button>
                                <el-button v-if="row.status === 'active'" size="small" link type="danger" @click="verifyDev(row, 'suspend')">{{ t('open_platform_page.btn.verify_suspend') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.installations')" name="installations">
                    <el-table :data="installations" stripe v-loading="instLoading">
                        <el-table-column :label="t('open_platform_page.cols.app')" min-width="140"><template #default="{ row }">{{ row.app?.name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.user')" min-width="140"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.version')" width="80" prop="installed_version" />
                        <el-table-column :label="t('open_platform_page.cols.status')" width="90"><template #default="{ row }"><el-tag :type="row.status === 'active' ? 'success' : 'info'" size="small">{{ installStatusLabel(row.status) }}</el-tag></template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.installed_at')" width="160"><template #default="{ row }">{{ fmtDate(row.installed_at) }}</template></el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.reviews')" name="reviews">
                    <div class="toolbar">
                        <el-select v-model="reviewFilter.status" clearable :placeholder="t('open_platform_page.filters.status')" style="width:130px" @change="loadAdminReviews">
                            <el-option v-for="s in reviewStatusOptions" :key="s.value" :label="s.label" :value="s.value" />
                        </el-select>
                    </div>
                    <el-table :data="adminReviews" stripe v-loading="adminReviewLoading">
                        <el-table-column :label="t('open_platform_page.cols.app')" min-width="140"><template #default="{ row }">{{ row.app?.name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.user')" width="120"><template #default="{ row }">{{ row.user?.name }}</template></el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.rating')" width="120">
                            <template #default="{ row }"><el-rate :model-value="row.rating" disabled size="small" /></template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.content')" min-width="200">
                            <template #default="{ row }"><span class="text-muted">{{ row.content || t('open_platform_page.content.no_text') }}</span></template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.status')" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.status === 'approved' ? 'success' : row.status === 'rejected' ? 'danger' : 'warning'" size="small">
                                    {{ reviewStatusLabel(row.status) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button v-if="row.status === 'pending'" text size="small" type="success" @click="approveReview(row)">{{ t('open_platform_page.btn.approve') }}</el-button>
                                <el-button v-if="row.status === 'pending'" text size="small" type="danger" @click="rejectReview(row)">{{ t('open_platform_page.btn.reject_review') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.banners')" name="banners">
                    <div class="toolbar">
                        <el-button type="primary" @click="openBannerDialog()"><el-icon><Plus /></el-icon> {{ t('open_platform_page.btn.create_banner') }}</el-button>
                    </div>
                    <el-table :data="banners" stripe v-loading="bannerLoading">
                        <el-table-column :label="t('open_platform_page.cols.title')" prop="title" min-width="160" />
                        <el-table-column :label="t('open_platform_page.cols.subtitle')" prop="subtitle" min-width="160" class-name="text-muted" />
                        <el-table-column :label="t('open_platform_page.cols.link')" width="160">
                            <template #default="{ row }">{{ row.link_value || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.sort_order')" width="60" prop="sort_order" align="center" />
                        <el-table-column :label="t('open_platform_page.cols.status')" width="80">
                            <template #default="{ row }">
                                <el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('open_platform_page.status.enabled') : t('open_platform_page.status.disabled') }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="160" fixed="right">
                            <template #default="{ row }">
                                <el-button text size="small" type="primary" @click="openBannerDialog(row)">{{ t('actions.edit') }}</el-button>
                                <el-button text size="small" type="danger" @click="deleteBanner(row)">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.security')" name="security">
                    <div class="toolbar">
                        <el-button type="primary" @click="handleScanApps" :loading="scanAppsLoading"><el-icon><Search /></el-icon> {{ t('open_platform_page.btn.scan_apps') }}</el-button>
                        <el-button @click="handleScanReviews" :loading="scanReviewsLoading"><el-icon><Search /></el-icon> {{ t('open_platform_page.btn.scan_reviews') }}</el-button>
                        <span class="text-muted" style="margin-left:8px">{{ t('open_platform_page.security.hint') }}</span>
                    </div>
                    <el-row :gutter="16" class="mb-4">
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value" :class="secStats.flagged_apps > 0 ? 'danger' : 'success'">{{ secStats.flagged_apps ?? '-' }}</div><div class="stat-label">{{ t('open_platform_page.security.flagged_apps') }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value success">{{ secStats.clean_apps ?? '-' }}</div><div class="stat-label">{{ t('open_platform_page.security.clean_apps') }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value warning">{{ secStats.pending_reviews ?? '-' }}</div><div class="stat-label">{{ t('open_platform_page.security.pending_reviews') }}</div></el-card></el-col>
                        <el-col :span="6"><el-card shadow="never"><div class="stat-value">{{ secStats.total_apps ?? '-' }}</div><div class="stat-label">{{ t('open_platform_page.security.total_apps') }}</div></el-card></el-col>
                    </el-row>

                    <el-table :data="securityResults" v-loading="secResultsLoading" stripe v-if="securityResults.length">
                        <el-table-column :label="t('open_platform_page.cols.type')" width="80">
                            <template #default="{ row }"><el-tag :type="row.review_id ? 'warning' : ''" size="small">{{ row.review_id ? t('open_platform_page.entity_type.review') : t('open_platform_page.entity_type.app') }}</el-tag></template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.name')" min-width="180">
                            <template #default="{ row }">{{ row.app_name || row.user_name }}</template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.violations')" width="300">
                            <template #default="{ row }">
                                <el-tag v-for="v in (row.violations || [])" :key="v.field" size="small" :type="v.severity >= 3 ? 'danger' : v.severity >= 2 ? 'warning' : 'info'" style="margin:1px">
                                    {{ v.field_label }} ({{ v.words.length }})
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.severity')" width="100">
                            <template #default="{ row }">
                                <el-tag :type="row.max_severity >= 3 ? 'danger' : row.max_severity >= 2 ? 'warning' : 'info'" size="small">
                                    {{ severityLabel(row.max_severity) }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="120">
                            <template #default="{ row }">
                                <el-button v-if="row.app_id" text size="small" type="primary" @click="openAppDetail({id: row.app_id})">{{ t('open_platform_page.btn.view_app') }}</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-else-if="!secResultsLoading" :description="t('open_platform_page.security.empty')" :image-size="50" />
                </el-tab-pane>

                <el-tab-pane :label="t('open_platform_page.tabs.categories')" name="categories">
                    <div class="toolbar">
                        <el-button type="primary" @click="showCategoryDialog(null)"><el-icon><Plus /></el-icon> {{ t('open_platform_page.btn.create_category') }}</el-button>
                    </div>
                    <el-table :data="catList" v-loading="catLoading" stripe>
                        <el-table-column :label="t('open_platform_page.cols.name')" prop="name" min-width="200" />
                        <el-table-column :label="t('open_platform_page.cols.slug')" prop="slug" width="150" />
                        <el-table-column :label="t('open_platform_page.cols.icon')" width="80" align="center">
                            <template #default="{ row }">{{ row.icon || '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.sort_order')" prop="sort_order" width="80" align="center" />
                        <el-table-column :label="t('open_platform_page.cols.app_count')" width="80" align="center">
                            <template #default="{ row }">{{ row.apps_count ?? '-' }}</template>
                        </el-table-column>
                        <el-table-column :label="t('open_platform_page.cols.actions')" width="200">
                            <template #default="{ row }">
                                <el-button size="small" @click="showCategoryDialog(row)">{{ t('actions.edit') }}</el-button>
                                <el-popconfirm :title="t('open_platform_page.category.delete_confirm')" @confirm="deleteCategory(row)">
                                    <template #reference><el-button size="small" type="danger">{{ t('actions.delete') }}</el-button></template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!catList.length && !catLoading" :description="t('open_platform_page.category.empty')" :image-size="50" />
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <el-dialog v-model="reviewVisible" :title="reviewAction === 'reject' ? t('open_platform_page.review_dialog.reject_title') : t('open_platform_page.review_dialog.request_changes_title')" width="420px">
            <el-input v-model="reviewNotes" type="textarea" :rows="4" :placeholder="t('open_platform_page.review_dialog.notes_ph')" />
            <template #footer>
                <el-button @click="reviewVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitReview">{{ t('actions.confirm') }}</el-button>
            </template>
        </el-dialog>

        <el-drawer v-model="detailVisible" :title="t('open_platform_page.detail.title')" size="580px">
            <template v-if="currentApp">
                <el-descriptions :column="1" border>
                    <el-descriptions-item :label="t('open_platform_page.cols.name')">{{ currentApp.name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.slug')">{{ currentApp.slug }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.developer')">{{ currentApp.developer?.display_name }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.category')">{{ categoryLabel(currentApp.category) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.status')">{{ statusLabel(currentApp.status) }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.current_version')">{{ currentApp.current_version || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.short_description')">{{ currentApp.short_description || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.description')">{{ currentApp.description || '-' }}</el-descriptions-item>
                    <el-descriptions-item :label="t('open_platform_page.cols.permissions')">{{ (currentApp.permissions || []).join(', ') || '-' }}</el-descriptions-item>
                </el-descriptions>

                <el-divider />
                <div class="section-title">{{ t('open_platform_page.version.history') }}</div>
                <el-timeline v-if="appVersions.length">
                    <el-timeline-item v-for="v in appVersions" :key="v.id" :timestamp="fmtDate(v.created_at)" placement="top">
                        <div class="version-item">
                            <el-tag size="small" type="primary">v{{ v.version }}</el-tag>
                            <el-tag v-if="v.status === 'published'" size="small" type="success">{{ t('open_platform_page.version.published') }}</el-tag>
                            <el-tag v-else size="small">{{ statusLabel(v.status) }}</el-tag>
                        </div>
                        <div class="version-changelog text-muted">{{ v.changelog || t('open_platform_page.version.no_changelog') }}</div>
                        <div v-if="v.package_url" class="version-url">
                            <a :href="v.package_url" target="_blank" class="text-link">📦 {{ t('open_platform_page.version.download_package') }}</a>
                        </div>
                    </el-timeline-item>
                </el-timeline>
                <el-empty v-else :description="t('open_platform_page.version.no_versions')" :image-size="40" />

                <el-divider />
                <div class="drawer-actions">
                    <el-button type="primary" @click="openUploader('package')" :icon="UploadFilled">{{ t('open_platform_page.btn.upload_package') }}</el-button>
                    <el-button @click="openUploader('screenshot')" :icon="PictureFilled">{{ t('open_platform_page.btn.upload_screenshot') }}</el-button>
                    <el-button type="success" @click="showAddVersion = true" :icon="Plus">{{ t('open_platform_page.btn.create_version') }}</el-button>
                </div>
            </template>
        </el-drawer>

        <el-dialog v-model="showAddVersion" :title="t('open_platform_page.version.create_title')" width="480px">
            <el-form :model="verForm" label-width="120px">
                <el-form-item :label="t('open_platform_page.version.version_no')" required>
                    <el-input v-model="verForm.version" :placeholder="t('open_platform_page.version.version_no_ph')" maxlength="30" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.version.changelog')">
                    <el-input v-model="verForm.changelog" type="textarea" :rows="4" :placeholder="t('open_platform_page.version.changelog_ph')" maxlength="5000" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.version.package_url')">
                    <el-input v-model="verForm.package_url" :placeholder="t('open_platform_page.version.package_url_ph')">
                        <template #append>
                            <el-button @click="verForm.package_url = lastUploadedUrl || ''" :disabled="!lastUploadedUrl">{{ t('open_platform_page.btn.use_last_upload') }}</el-button>
                        </template>
                    </el-input>
                    <div v-if="lastUploadedUrl" class="text-success" style="font-size:12px;margin-top:4px">
                        {{ t('open_platform_page.version.last_upload') }}: {{ lastUploadedUrl }}
                    </div>
                </el-form-item>
                <el-form-item :label="t('open_platform_page.version.min_platform')">
                    <el-input v-model="verForm.min_platform_version" :placeholder="t('open_platform_page.version.min_platform_ph')" maxlength="30" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showAddVersion = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="verSaving" @click="submitVersion">{{ t('open_platform_page.btn.publish_version') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="bannerDialogVisible" :title="editingBanner ? t('open_platform_page.banner.edit_title') : t('open_platform_page.banner.create_title')" width="520px">
            <el-form :model="bannerForm" label-width="100px">
                <el-form-item :label="t('open_platform_page.cols.title')" required>
                    <el-input v-model="bannerForm.title" :placeholder="t('open_platform_page.banner.title_ph')" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.cols.subtitle')">
                    <el-input v-model="bannerForm.subtitle" :placeholder="t('open_platform_page.banner.subtitle_ph')" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.banner.image_url')" required>
                    <el-input v-model="bannerForm.image_url" placeholder="https://..." />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.banner.link_type')">
                    <el-select v-model="bannerForm.link_type" style="width:100%">
                        <el-option :label="t('open_platform_page.banner.link_type_app')" value="app" />
                        <el-option :label="t('open_platform_page.banner.link_type_category')" value="category" />
                        <el-option :label="t('open_platform_page.banner.link_type_url')" value="url" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('open_platform_page.banner.link_value')">
                    <el-input v-model="bannerForm.link_value" :placeholder="bannerLinkValuePlaceholder" />
                </el-form-item>
                <el-row :gutter="12">
                    <el-col :span="8">
                        <el-form-item :label="t('open_platform_page.cols.sort_order')">
                            <el-input-number v-model="bannerForm.sort_order" :min="0" style="width:100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="8">
                        <el-form-item :label="t('open_platform_page.status.enabled')">
                            <el-switch v-model="bannerForm.is_active" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('open_platform_page.banner.validity')">
                    <el-date-picker v-model="bannerForm.starts_at" type="datetime" :placeholder="t('open_platform_page.banner.starts_at')" style="width:48%" />
                    <span style="margin:0 8px">{{ t('open_platform_page.banner.to') }}</span>
                    <el-date-picker v-model="bannerForm.ends_at" type="datetime" :placeholder="t('open_platform_page.banner.ends_at')" style="width:48%" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="bannerDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="bannerLoading" @click="saveBanner">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="catDialogVisible" :title="editingCat ? t('open_platform_page.category.edit_title') : t('open_platform_page.category.create_title')" width="450px">
            <el-form :model="catForm" label-width="100px">
                <el-form-item :label="t('open_platform_page.cols.name')" required>
                    <el-input v-model="catForm.name" :placeholder="t('open_platform_page.category.name_ph')" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.cols.slug')">
                    <el-input v-model="catForm.slug" :placeholder="t('open_platform_page.category.slug_ph')" maxlength="100" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.cols.icon')">
                    <el-input v-model="catForm.icon" :placeholder="t('open_platform_page.category.icon_ph')" maxlength="20" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.cols.sort_order')">
                    <el-input-number v-model="catForm.sort_order" :min="0" style="width:100%" />
                </el-form-item>
                <el-form-item :label="t('open_platform_page.cols.description')">
                    <el-input v-model="catForm.description" type="textarea" :rows="2" maxlength="500" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="catDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="catSaving" @click="saveCategory">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>
    </div>

    <MarketplaceUploader ref="uploaderRef" :upload-type="uploaderType" @confirm="onUploadConfirm" />
</template>

<script setup>
import { ref, reactive, computed, onMounted, nextTick } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Refresh, Plus, UploadFilled, PictureFilled, Search } from '@element-plus/icons-vue';
import api from '@/api/openPlatform';
import marketplaceApi from '@/api/marketplace';
import securityApi from '@/api/marketplaceSecurity';
import MarketplaceUploader from '@/components/MarketplaceUploader.vue';

const props = defineProps({
    defaultTab: { type: String, default: 'pending' },
});

const { t, locale } = useI18n();
const route = useRoute();
const isMarketplace = computed(() => route.name === 'AppMarketplace');
const pageTitle = computed(() => isMarketplace.value ? t('open_platform_page.title_marketplace') : t('open_platform_page.title'));
const pageSubtitle = computed(() => isMarketplace.value
    ? t('open_platform_page.subtitle_marketplace')
    : t('open_platform_page.subtitle'));

const loading = ref(false);
const activeTab = ref(props.defaultTab);
const stats = ref({});
const categories = ref([]);

const statusOptions = computed(() => [
    { value: 'draft', label: t('open_platform_page.status.draft') },
    { value: 'pending_review', label: t('open_platform_page.status.pending_review') },
    { value: 'published', label: t('open_platform_page.status.published') },
    { value: 'rejected', label: t('open_platform_page.status.rejected') },
    { value: 'suspended', label: t('open_platform_page.status.suspended') },
]);

const devStatusOptions = computed(() => [
    { value: 'pending', label: t('open_platform_page.status.pending') },
    { value: 'active', label: t('open_platform_page.status.active') },
    { value: 'suspended', label: t('open_platform_page.status.suspended') },
]);

const reviewStatusOptions = computed(() => [
    { value: '', label: t('open_platform_page.status.all') },
    { value: 'pending', label: t('open_platform_page.status.pending') },
    { value: 'approved', label: t('open_platform_page.status.approved') },
    { value: 'rejected', label: t('open_platform_page.status.review_rejected') },
]);

const devStatusLabels = computed(() => ({
    pending: t('open_platform_page.status.pending'),
    active: t('open_platform_page.status.active'),
    suspended: t('open_platform_page.status.suspended'),
}));

const installStatusLabels = computed(() => ({
    active: t('open_platform_page.status.installed'),
    uninstalled: t('open_platform_page.status.uninstalled'),
}));

const reviewStatusLabels = computed(() => ({
    approved: t('open_platform_page.status.approved'),
    rejected: t('open_platform_page.status.review_rejected'),
    pending: t('open_platform_page.status.pending'),
}));

const severityLabels = computed(() => ({
    1: t('open_platform_page.severity.low'),
    2: t('open_platform_page.severity.medium'),
    3: t('open_platform_page.severity.high'),
}));

const bannerLinkValuePlaceholder = computed(() => {
    if (bannerForm.link_type === 'app') return t('open_platform_page.banner.link_value_app_ph');
    if (bannerForm.link_type === 'category') return t('open_platform_page.banner.link_value_category_ph');
    return t('open_platform_page.banner.link_value_url_ph');
});

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

function fmtDate(d) {
    if (!d) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(d).toLocaleString(loc);
}

function categoryLabel(v) { return categories.value.find(c => c.value === v)?.label || v; }
function statusLabel(s) { return statusOptions.value.find(o => o.value === s)?.label || s; }
function statusTag(s) { return { draft: 'info', pending_review: 'warning', published: 'success', rejected: 'danger', suspended: 'info' }[s] || 'info'; }
function devStatusLabel(s) { return devStatusLabels.value[s] || s; }
function installStatusLabel(s) { return installStatusLabels.value[s] || s; }
function reviewStatusLabel(s) { return reviewStatusLabels.value[s] || s; }
function severityLabel(n) { return severityLabels.value[n] || severityLabels.value[1]; }

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
            ElMessage.info(t('open_platform_page.messages.scan_apps_done', { n: r.data?.total_flagged || 0 }));
            loadSecurityStats();
        }
    } catch { ElMessage.error(t('open_platform_page.messages.scan_failed')); }
    finally { scanAppsLoading.value = false; secResultsLoading.value = false; }
}

async function handleScanReviews() {
    scanReviewsLoading.value = true;
    secResultsLoading.value = true;
    try {
        const { data: r } = await securityApi.scanAllReviews();
        if (r.success) {
            securityResults.value = r.data?.results || [];
            ElMessage.info(t('open_platform_page.messages.scan_reviews_done', { n: r.data?.total_flagged || 0 }));
            loadSecurityStats();
        }
    } catch { ElMessage.error(t('open_platform_page.messages.scan_failed')); }
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
    } catch { ElMessage.error(t('open_platform_page.messages.load_failed')); }
}

async function suspendApp(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.suspend', { name: row.name }),
            t('open_platform_page.confirm.suspend_title'),
            { confirmButtonText: t('open_platform_page.confirm.suspend_btn'), confirmButtonClass: 'el-button--danger', type: 'warning' }
        );
        await api.suspendApp(row.id, { reason: t('open_platform_page.reason_ops_takedown') });
        ElMessage.success(t('open_platform_page.messages.app_suspended'));
        loadApps();
    } catch {}
}

async function unsuspendApp(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.unsuspend', { name: row.name }),
            t('open_platform_page.confirm.unsuspend_title')
        );
        await api.unsuspendApp(row.id);
        ElMessage.success(t('open_platform_page.messages.app_unsuspended'));
        loadApps();
    } catch {}
}

async function forceUpdateApp(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.force_update', { name: row.name }),
            t('open_platform_page.confirm.force_update_title'),
            { confirmButtonText: t('open_platform_page.confirm.force_update_btn'), type: 'warning' }
        );
        await api.forceUpdate(row.id, { reason: t('open_platform_page.reason_security_update'), version: row.current_version });
        ElMessage.success(t('open_platform_page.messages.force_update_sent'));
    } catch {}
}

async function reviewApp(row, action) {
    try {
        if (action === 'approve') {
            await ElMessageBox.confirm(
                t('open_platform_page.confirm.approve_app', { name: row.name }),
                t('open_platform_page.confirm.review_title')
            );
        }
        await api.reviewApp(row.id, { action, notes: '' });
        ElMessage.success(t('open_platform_page.messages.review_done'));
        await refreshAll();
    } catch (e) { if (e !== 'cancel') ElMessage.error(e.response?.data?.message || t('open_platform_page.messages.operation_failed')); }
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
        ElMessage.success(t('open_platform_page.messages.review_done'));
        reviewVisible.value = false;
        await refreshAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || t('open_platform_page.messages.operation_failed')); }
    finally { submitting.value = false; }
}

async function verifyDev(row, action) {
    try {
        await api.verifyDeveloper(row.id, { action });
        ElMessage.success(t('open_platform_page.messages.dev_status_updated'));
        await refreshAll();
    } catch (e) { ElMessage.error(e.response?.data?.message || t('open_platform_page.messages.operation_failed')); }
}

const adminReviews = ref([]);
const adminReviewLoading = ref(false);
const reviewFilter = reactive({ status: '' });

async function loadAdminReviews() {
    adminReviewLoading.value = true;
    try {
        const params = { per_page: 50 };
        if (reviewFilter.status) params.status = reviewFilter.status;
        const { data: res } = await marketplaceApi.reviews(0, params);
        adminReviews.value = res.data || [];
    } catch { adminReviews.value = []; }
    finally { adminReviewLoading.value = false; }
}

async function approveReview(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.approve_review'),
            t('open_platform_page.confirm.review_title')
        );
        await marketplaceApi.reviewModerate(row.id, 'approve');
        ElMessage.success(t('open_platform_page.messages.review_approved'));
        loadAdminReviews();
    } catch {}
}

async function rejectReview(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.reject_review'),
            t('open_platform_page.confirm.review_title')
        );
        await marketplaceApi.reviewModerate(row.id, 'reject');
        ElMessage.success(t('open_platform_page.messages.review_rejected'));
        loadAdminReviews();
    } catch {}
}

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
            ElMessage.success(t('open_platform_page.messages.banner_updated'));
        } else {
            await marketplaceApi.bannerCreate(bannerForm);
            ElMessage.success(t('open_platform_page.messages.banner_created'));
        }
        bannerDialogVisible.value = false;
        loadBanners();
    } catch { ElMessage.error(t('open_platform_page.messages.operation_failed')); }
}

async function deleteBanner(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.delete_banner'),
            t('open_platform_page.confirm.delete_banner_title'),
            { type: 'warning' }
        );
        await marketplaceApi.bannerDelete(row.id);
        ElMessage.success(t('open_platform_page.messages.banner_deleted'));
        loadBanners();
    } catch {}
}

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
    if (!catForm.name) { ElMessage.warning(t('open_platform_page.messages.category_name_required')); return; }
    catSaving.value = true;
    try {
        if (editingCat.value) { await marketplaceApi.categoryUpdate(editingCat.value.id, catForm); ElMessage.success(t('open_platform_page.messages.category_updated')); }
        else { await marketplaceApi.categoryCreate(catForm); ElMessage.success(t('open_platform_page.messages.category_created')); }
        catDialogVisible.value = false; loadCategories();
    } catch {} finally { catSaving.value = false; }
}

async function deleteCategory(row) {
    try {
        await ElMessageBox.confirm(
            t('open_platform_page.confirm.delete_category', { name: row.name }),
            t('open_platform_page.confirm.delete_banner_title')
        );
        await marketplaceApi.categoryDelete(row.id);
        ElMessage.success(t('open_platform_page.messages.category_deleted'));
        loadCategories();
    } catch {}
}

const uploaderRef = ref(null);
const uploaderType = ref('package');

function openUploader(type) {
    uploaderType.value = type;
    nextTick(() => uploaderRef.value?.open());
}

function onUploadConfirm(result) {
    lastUploadedUrl.value = result.url || '';
    ElMessage.success(t('open_platform_page.messages.file_uploaded', { name: result.original_name }));

    if (showAddVersion.value) {
        verForm.package_url = lastUploadedUrl.value;
    }
}

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
    if (!verForm.version || !currentApp.value?.id) { ElMessage.warning(t('open_platform_page.messages.version_required')); return; }
    if (!verForm.package_url) { ElMessage.warning(t('open_platform_page.messages.package_url_required')); return; }
    verSaving.value = true;
    try {
        await api.addVersion(currentApp.value.id, {
            version: verForm.version,
            changelog: verForm.changelog || null,
            package_url: verForm.package_url,
            min_platform_version: verForm.min_platform_version || null,
            status: 'published',
        });
        ElMessage.success(t('open_platform_page.messages.version_created', { version: verForm.version }));
        showAddVersion.value = false;
        verForm.version = ''; verForm.changelog = ''; verForm.package_url = ''; verForm.min_platform_version = '';
        loadAppVersions(currentApp.value.id);
    } catch { ElMessage.error(t('open_platform_page.messages.version_create_failed')); }
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
.stat-value.primary { color: #0f172a; }
.mb-4 { margin-bottom: 16px; }
.mt-3 { margin-top: 12px; }
.app-name { font-weight: 500; }
</style>
