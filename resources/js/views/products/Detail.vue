<template>
    <div class="product-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/products' }">{{ t('product_detail_page.breadcrumb_products') }}</el-breadcrumb-item>
                <el-breadcrumb-item>{{ t('product_detail_page.breadcrumb_detail') }}</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div v-if="product" class="detail-content">
            <el-tabs type="border-card" class="detail-tabs">
                <el-tab-pane :label="t('product_detail_page.tab_basic')" name="basic">
                    <el-card shadow="never" class="info-card">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.basic_info') }}</span>
                                <div class="header-actions">
                                    <el-button size="small" @click="openEditDialog">{{ t('actions.edit') }}</el-button>
                                    <el-button v-if="product.is_active" size="small" type="warning" @click="toggleActive(false)">{{ t('products_page.deactivate') }}</el-button>
                                    <el-button v-else size="small" type="success" @click="toggleActive(true)">{{ t('products_page.activate') }}</el-button>
                                </div>
                            </div>
                        </template>
                        <el-descriptions :column="3" border>
                            <el-descriptions-item :label="t('product_detail_page.field_product_id')"><code>{{ product.id }}</code></el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_name')" :span="2">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <el-avatar v-if="product.image_url" :size="48" shape="square" :src="product.image_url" />
                                    <span>{{ product.name }}</span>
                                </div>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_slug')"><code>{{ product.slug }}</code></el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_version')">{{ product.version || '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_sellable')">
                                <el-tag :type="product.is_sellable ? 'success' : 'info'" size="small">{{ product.is_sellable ? t('product_detail_page.yes') : t('product_detail_page.no') }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_featured')">
                                <el-tag :type="product.is_featured ? 'warning' : 'info'" size="small">{{ product.is_featured ? t('product_detail_page.yes') : t('product_detail_page.no') }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_base_price')">{{ product.base_price ? '¥' + product.base_price : '-' }}</el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_tags')" :span="2">
                                <template v-if="product.tags?.length">
                                    <el-tag v-for="tag in product.tags" :key="tag" size="small" effect="plain" style="margin:2px 4px 2px 0">{{ tag }}</el-tag>
                                </template>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_status')">
                                <el-tag :type="product.is_active ? 'success' : 'info'" size="small">{{ product.is_active ? t('product_detail_page.status_listed') : t('product_detail_page.status_delisted') }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_licenses_count')"><el-tag type="primary" effect="plain" size="small">{{ product.licenses_count || 0 }}</el-tag></el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_sales_count')">{{ product.sales_count ?? 0 }}</el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_description')" :span="3">{{ product.description || t('product_detail_page.no_description') }}</el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_category')" :span="3">
                                <el-tag v-if="product.category" size="small" effect="plain">{{ product.category.name }}</el-tag>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('products_page.field_modules')" :span="3">
                                <template v-if="product.modules?.length">
                                    <el-tag v-for="mod in product.modules" :key="mod" size="small" effect="plain" style="margin:2px 4px 2px 0">{{ mod }}</el-tag>
                                </template>
                                <span v-else>{{ t('product_detail_page.no_modules') }}</span>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_created_at')">{{ formatDate(product.created_at) }}</el-descriptions-item>
                            <el-descriptions-item :label="t('product_detail_page.field_updated_at')">{{ formatDate(product.updated_at) }}</el-descriptions-item>
                        </el-descriptions>
                    </el-card>

                    <el-card shadow="never" class="section-card" style="margin-top:16px">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.feature_flags_title', { n: assignedFeatures.length }) }}</span>
                                <el-button size="small" type="primary" @click="showFeatureDialog = true"><el-icon><Setting /></el-icon> {{ t('product_detail_page.manage') }}</el-button>
                            </div>
                        </template>
                        <div v-if="assignedFeatures.length > 0" class="feature-list">
                            <el-tag v-for="f in assignedFeatures" :key="f.id" size="default" effect="plain" type="primary" style="margin:4px 8px 4px 0">
                                <el-icon style="margin-right:4px"><Flag /></el-icon>{{ f.name }} <code style="margin-left:4px;font-size:11px">{{ f.key }}</code>
                            </el-tag>
                        </div>
                        <div v-else class="empty-state"><el-empty :image-size="60" :description="t('product_detail_page.no_feature_flags')" /></div>
                    </el-card>

                    <el-card shadow="never" class="section-card" style="margin-top:16px">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.recent_licenses') }}</span>
                                <el-button size="small" type="primary" @click="$router.push('/licenses?product_id='+product.id)">{{ t('product_detail_page.view_all') }}</el-button>
                            </div>
                        </template>
                        <el-table :data="recentLicenses" stripe size="small">
                            <el-table-column :label="t('licenses_page.license_key')" min-width="220">
                                <template #default="{ row }"><el-link type="primary" @click="$router.push('/licenses/'+row.id)"><code>{{ (row.license_key||'').substring(0,20) }}...</code></el-link></template>
                            </el-table-column>
                            <el-table-column :label="t('licenses_page.col_customer')" width="160">
                                <template #default="{ row }">{{ row.customer?.user?.name || '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t('licenses_page.col_status')" width="90">
                                <template #default="{ row }"><el-tag :type="licenseStatusType(row.status)" size="small">{{ licenseStatusLabel(row.status) }}</el-tag></template>
                            </el-table-column>
                            <el-table-column :label="t('licenses_page.col_expires_at')" width="170">
                                <template #default="{ row }">{{ formatDate(row.expires_at) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('licenses_page.col_created_at')" width="170">
                                <template #default="{ row }">{{ formatDate(row.created_at) }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane :label="t('product_detail_page.tab_sku')" name="sku">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.sku_list_title', { n: skus.length }) }}</span>
                                <el-button size="small" type="primary" @click="showSkuDialog = true; skuForm = { sku_code: '', name: '', price: null, stock: -1, is_active: true, billing_cycle: 'one_time' }">{{ t('product_detail_page.create_sku') }}</el-button>
                            </div>
                        </template>
                        <el-table :data="skus" stripe v-loading="skusLoading">
                            <el-table-column :label="t('product_detail_page.col_sku_code')" prop="sku_code" min-width="140" />
                            <el-table-column :label="t('product_detail_page.col_name')" prop="name" min-width="160" />
                            <el-table-column :label="t('product_detail_page.col_price')" width="100">
                                <template #default="{ row }">¥{{ row.price ?? '-' }}</template>
                            </el-table-column>
                            <el-table-column :label="t('product_detail_page.col_billing_cycle')" width="100">
                                <template #default="{ row }">{{ billingCycleLabel(row.billing_cycle) }}</template>
                            </el-table-column>
                            <el-table-column :label="t('product_detail_page.col_stock')" width="70" prop="stock">
                                <template #default="{ row }">{{ row.stock === -1 ? t('product_detail_page.stock_unlimited') : row.stock }}</template>
                            </el-table-column>
                            <el-table-column :label="t('product_detail_page.col_sold')" width="60" prop="sold_count" />
                            <el-table-column :label="t('product_detail_page.field_status')" width="70">
                                <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? t('product_detail_page.sku_status_active') : t('product_detail_page.sku_status_inactive') }}</el-tag></template>
                            </el-table-column>
                            <el-table-column :label="t('product_detail_page.col_actions')" width="120" fixed="right">
                                <template #default="{ row }">
                                    <el-button text size="small" type="primary" @click="editSku(row)">{{ t('actions.edit') }}</el-button>
                                    <el-button text size="small" type="danger" @click="deleteSku(row)">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>

                    <el-dialog v-model="showSkuDialog" :title="skuEditingId ? t('product_detail_page.edit_sku_title') : t('product_detail_page.create_sku_title')" width="500px" :close-on-click-modal="false">
                        <el-form :model="skuForm" label-width="100px">
                            <el-form-item :label="t('product_detail_page.col_sku_code')"><el-input v-model="skuForm.sku_code" :placeholder="t('product_detail_page.sku_code_ph')" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_name')"><el-input v-model="skuForm.name" :placeholder="t('product_detail_page.sku_name_ph')" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_price')"><el-input-number v-model="skuForm.price" :precision="2" :min="0" style="width:200px"><template #prefix>¥</template></el-input-number></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_billing_cycle')">
                                <el-select v-model="skuForm.billing_cycle" style="width:200px">
                                    <el-option v-for="opt in billingCycleOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                </el-select>
                            </el-form-item>
                            <el-form-item :label="t('product_detail_page.col_stock')"><el-input-number v-model="skuForm.stock" :min="-1" style="width:200px" /> <span style="color:#909399;font-size:12px;margin-left:8px">{{ t('product_detail_page.stock_unlimited_hint') }}</span></el-form-item>
                            <el-form-item :label="t('product_detail_page.field_enabled')"><el-switch v-model="skuForm.is_active" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showSkuDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" :loading="skuSubmitting" @click="submitSku">{{ t('actions.save') }}</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>

                <el-tab-pane :label="t('product_detail_page.tab_specs')" name="specs">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.specs_title') }}</span>
                                <el-button size="small" type="primary" @click="addSpecGroup">{{ t('product_detail_page.add_spec_group') }}</el-button>
                            </div>
                        </template>
                        <div v-if="specGroups.length > 0">
                            <el-card v-for="(group, gi) in specGroups" :key="gi" shadow="never" style="margin-bottom:12px">
                                <template #header>
                                    <div class="card-header">
                                        <el-input v-model="group.name" :placeholder="t('product_detail_page.spec_group_name_ph')" style="width:200px" />
                                        <div>
                                            <el-button size="small" @click="addSpecValue(gi)">{{ t('product_detail_page.add_spec_param') }}</el-button>
                                            <el-button size="small" type="danger" plain @click="specGroups.splice(gi,1)">{{ t('product_detail_page.delete_group') }}</el-button>
                                        </div>
                                    </div>
                                </template>
                                <div v-for="(sv, si) in group.values" :key="si" style="display:flex;gap:8px;margin-bottom:8px">
                                    <el-input v-model="sv.name" :placeholder="t('product_detail_page.param_name_ph')" style="width:200px" />
                                    <el-input v-model="sv.value" :placeholder="t('product_detail_page.param_value_ph')" style="width:300px" />
                                    <el-button size="small" type="danger" plain @click="group.values.splice(si,1)">{{ t('actions.delete') }}</el-button>
                                </div>
                            </el-card>
                            <el-button type="primary" :loading="specSubmitting" @click="saveSpecs">{{ t('product_detail_page.save_specs') }}</el-button>
                        </div>
                        <div v-else><el-empty :image-size="60" :description="t('product_detail_page.no_specs')" /></div>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane :label="t('product_detail_page.tab_seo')" name="seo">
                    <el-card shadow="never">
                        <template #header><span>{{ t('product_detail_page.seo_metadata') }}</span></template>
                        <el-form :model="seoForm" label-width="140px">
                            <el-form-item :label="t('product_detail_page.meta_title')"><el-input v-model="seoForm.meta_title" :placeholder="t('product_detail_page.meta_title_ph')" maxlength="160" show-word-limit /></el-form-item>
                            <el-form-item :label="t('product_detail_page.meta_description')"><el-input v-model="seoForm.meta_description" type="textarea" :rows="3" :placeholder="t('product_detail_page.meta_description_ph')" maxlength="500" show-word-limit /></el-form-item>
                            <el-form-item :label="t('product_detail_page.meta_keywords')"><el-input v-model="seoForm.meta_keywords" :placeholder="t('product_detail_page.meta_keywords_ph')" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.canonical_url')"><el-input v-model="seoForm.canonical_url" :placeholder="t('product_detail_page.canonical_url_ph')" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.og_title')"><el-input v-model="seoForm.og_title" :placeholder="t('product_detail_page.og_title_ph')" maxlength="160" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.og_description')"><el-input v-model="seoForm.og_description" type="textarea" :rows="2" :placeholder="t('product_detail_page.og_description_ph')" /></el-form-item>
                            <el-form-item><el-button type="primary" :loading="seoSubmitting" @click="saveSeo">{{ t('product_detail_page.save_seo') }}</el-button></el-form-item>
                        </el-form>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane :label="t('product_detail_page.tab_translations')" name="translations">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.translations_title') }}</span>
                                <el-button size="small" type="primary" @click="addTranslation">{{ t('product_detail_page.add_translation') }}</el-button>
                            </div>
                        </template>
                        <div v-if="translations.length > 0">
                            <el-table :data="translations" stripe>
                                <el-table-column :label="t('product_detail_page.col_language')" width="100">
                                    <template #default="{ row }">{{ localeLabel(row.locale) }}</template>
                                </el-table-column>
                                <el-table-column :label="t('product_detail_page.col_name')" prop="name" min-width="160" />
                                <el-table-column :label="t('products_page.field_description')" prop="description" min-width="200" />
                                <el-table-column :label="t('product_detail_page.col_actions')" width="80">
                                    <template #default="{ $index }"><el-button text size="small" type="danger" @click="translations.splice($index,1)">{{ t('actions.delete') }}</el-button></template>
                                </el-table-column>
                            </el-table>
                            <el-button type="primary" :loading="transSubmitting" @click="saveTranslations" style="margin-top:12px">{{ t('product_detail_page.save_translations') }}</el-button>
                        </div>
                        <div v-else><el-empty :image-size="60" :description="t('product_detail_page.no_translations')" /></div>
                    </el-card>
                    <el-dialog v-model="showTransDialog" :title="t('product_detail_page.add_translation_title')" width="500px">
                        <el-form :model="transForm" label-width="80px">
                            <el-form-item :label="t('product_detail_page.col_language')"><el-select v-model="transForm.locale" style="width:100%">
                                <el-option v-for="opt in localeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_name')"><el-input v-model="transForm.name" /></el-form-item>
                            <el-form-item :label="t('products_page.field_description')"><el-input v-model="transForm.description" type="textarea" :rows="3" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showTransDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" @click="confirmAddTranslation">{{ t('product_detail_page.add_btn') }}</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>
                <el-tab-pane :label="t('product_detail_page.tab_demo')" name="demo">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>{{ t('product_detail_page.demo_settings_title') }}</span>
                                <el-button size="small" type="primary" @click="openAddDemoDialog">{{ t('product_detail_page.add_demo_platform') }}</el-button>
                            </div>
                        </template>

                        <el-form label-width="120px" style="margin-bottom:20px">
                            <el-form-item :label="t('product_detail_page.demo_enabled')">
                                <el-switch v-model="demoSettings.enabled" @change="saveDemoSettings" />
                                <span style="margin-left:10px;color:#909399;font-size:12px">{{ t('product_detail_page.demo_enabled_hint') }}</span>
                            </el-form-item>
                            <el-form-item :label="t('product_detail_page.demo_images')">
                                <div style="width:100%">
                                    <div v-for="(img, idx) in demoSettings.images" :key="idx" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                                        <el-input v-model="img.label" :placeholder="t('product_detail_page.demo_image_label_ph')" style="width:160px" @blur="saveDemoSettings" />
                                        <el-input v-model="img.url" placeholder="https://..." style="width:320px" @blur="saveDemoSettings" />
                                        <el-button size="small" @click="uploadDemoImage(idx)">{{ t('actions.upload') }}</el-button>
                                        <el-button size="small" type="danger" plain @click="removeDemoImage(idx)">{{ t('actions.delete') }}</el-button>
                                        <template v-if="img.url">
                                            <el-image :src="img.url" fit="cover" style="width:48px;height:48px;border-radius:4px;border:1px solid #e5e7eb" />
                                        </template>
                                    </div>
                                    <el-button size="small" type="primary" plain @click="demoSettings.images.push({label:'',url:''})">
                                        + {{ t('product_detail_page.add_image') }}
                                    </el-button>
                                </div>
                            </el-form-item>
                        </el-form>

                        <el-table :data="demoList" stripe v-loading="demoLoading">
                            <el-table-column :label="t('product_detail_page.col_sort_order')" width="60" prop="sort_order" />
                            <el-table-column :label="t('product_detail_page.col_demo_platform')" min-width="140" prop="platform" />
                            <el-table-column :label="t('product_detail_page.col_demo_site')" min-width="240" prop="site_url">
                                <template #default="{ row }">
                                    <a :href="row.site_url" target="_blank" style="color:#0f172a">{{ row.site_url || '-' }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t('product_detail_page.col_demo_account')" width="140" prop="account" />
                            <el-table-column :label="t('product_detail_page.col_demo_password')" width="140" prop="password" />
                            <el-table-column :label="t('product_detail_page.col_actions')" width="140" fixed="right">
                                <template #default="{ row }">
                                    <el-button text size="small" type="primary" @click="openEditDemoDialog(row)">{{ t('actions.edit') }}</el-button>
                                    <el-button text size="small" type="danger" @click="deleteDemo(row)">{{ t('actions.delete') }}</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="demoList.length === 0 && !demoLoading" style="text-align:center;padding:40px;color:#909399">
                            {{ t('product_detail_page.no_demo_platforms') }}
                        </div>
                    </el-card>

                    <el-dialog v-model="showDemoDialog" :title="demoEditingId ? t('product_detail_page.edit_demo_title') : t('product_detail_page.add_demo_title')" width="500px" :close-on-click-modal="false">
                        <el-form :model="demoForm" label-width="100px">
                            <el-form-item :label="t('product_detail_page.col_demo_platform')"><el-input v-model="demoForm.platform" :placeholder="t('product_detail_page.demo_platform_ph')" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_demo_site')"><el-input v-model="demoForm.site_url" placeholder="https://..." /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_demo_account')"><el-input v-model="demoForm.account" placeholder="demo@example.com" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_demo_password')"><el-input v-model="demoForm.password" placeholder="demo123" /></el-form-item>
                            <el-form-item :label="t('product_detail_page.col_sort_order')"><el-input-number v-model="demoForm.sort_order" :min="0" style="width:100px" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showDemoDialog = false">{{ t('actions.cancel') }}</el-button>
                            <el-button type="primary" :loading="demoSubmitting" @click="submitDemo">{{ t('actions.save') }}</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>
            </el-tabs>
        </div>

        <el-dialog
            v-model="dialogVisible"
            :title="t('products_page.edit_title')"
            width="600px"
            :close-on-click-modal="false"
        >
            <el-form
                ref="formRef"
                :model="form"
                :rules="formRules"
                label-width="100px"
                label-position="right"
            >
                <el-form-item :label="t('products_page.field_name')" prop="name">
                    <el-input v-model="form.name" />
                </el-form-item>
                <el-form-item :label="t('products_page.field_slug')" prop="slug">
                    <el-input v-model="form.slug" />
                </el-form-item>
                <el-form-item :label="t('products_page.field_version')" prop="version">
                    <el-input v-model="form.version" style="width: 200px" />
                </el-form-item>
                <el-form-item :label="t('products_page.field_description')" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item :label="t('products_page.field_category')" prop="category_id">
                    <el-select v-model="form.category_id" clearable :placeholder="t('products_page.category_ph')" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('products_page.field_main_image')">
                    <div class="image-upload-wrapper">
                        <template v-if="form.image_url">
                            <div class="image-preview">
                                <el-image :src="form.image_url" fit="cover" style="width: 120px; height: 120px; border-radius: 6px;" />
                                <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.image_url = ''">
                                    <el-icon><Close /></el-icon>
                                </el-button>
                            </div>
                        </template>
                        <el-upload :show-file-list="false" :before-upload="handleMainImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                            <el-button type="primary" plain>
                                <el-icon><Upload /></el-icon> {{ t('products_page.upload_main_image') }}
                            </el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item :label="t('products_page.field_carousel')">
                    <div class="images-upload-wrapper">
                        <div class="images-list" v-if="form.images && form.images.length">
                            <div v-for="(img, idx) in form.images" :key="idx" class="image-preview">
                                <el-image :src="img" fit="cover" style="width: 80px; height: 80px; border-radius: 4px;" />
                                <el-button class="image-remove-btn" size="small" type="danger" circle @click="form.images.splice(idx, 1)">
                                    <el-icon><Close /></el-icon>
                                </el-button>
                            </div>
                        </div>
                        <el-upload :show-file-list="false" :before-upload="handleCarouselImageUpload" accept="image/jpeg,image/png,image/gif,image/webp">
                            <el-button type="primary" plain size="small">
                                <el-icon><Plus /></el-icon> {{ t('products_page.add_image') }}
                            </el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item :label="t('products_page.field_modules')" prop="modules">
                    <el-select
                        v-model="form.modules"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        :placeholder="t('products_page.tag_input_ph')"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="t('products_page.field_featured')">
                    <el-switch v-model="form.is_featured" />
                </el-form-item>
                <el-form-item :label="t('products_page.field_active')">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">{{ t('actions.save') }}</el-button>
            </template>
        </el-dialog>

        <el-dialog
            v-model="showFeatureDialog"
            :title="t('product_detail_page.manage_feature_flags')"
            width="550px"
            :close-on-click-modal="false"
        >
            <div v-loading="featuresLoading">
                <el-empty v-if="availableFeatures.length === 0" :image-size="60" :description="t('product_detail_page.no_available_features')" />
                <el-checkbox-group v-else v-model="selectedFeatureIds" class="feature-checkbox-group">
                    <el-checkbox
                        v-for="f in availableFeatures"
                        :key="f.id"
                        :label="f.id"
                        class="feature-checkbox"
                    >
                        <div class="feature-item">
                            <span class="feature-name">{{ f.name }}</span>
                            <code class="feature-key">{{ f.key }}</code>
                            <span v-if="f.description" class="feature-desc">{{ f.description }}</span>
                        </div>
                    </el-checkbox>
                </el-checkbox-group>
            </div>
            <template #footer>
                <el-button @click="showFeatureDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" :loading="featureSubmitting" @click="submitFeatures">
                    {{ t('actions.save') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Setting, Flag, Upload, Close, Plus } from '@element-plus/icons-vue';
import productApi from '@/api/product';
import productDemoApi from '@/api/productDemo';
import categoryApi from '@/api/productCategory';

const { t, locale } = useI18n();
const route = useRoute();
const router = useRouter();
const productId = Number(route.params.id);

const loading = ref(false);
const product = ref(null);
const recentLicenses = ref([]);

const billingCycleOptions = computed(() => [
    { label: t('shop_page.cycle_onetime'), value: 'one_time' },
    { label: t('shop_page.cycle_monthly'), value: 'monthly' },
    { label: t('shop_page.cycle_yearly'), value: 'yearly' },
    { label: t('shop_page.cycle_lifetime'), value: 'lifetime' },
]);

const localeOptions = computed(() => [
    { label: t('product_detail_page.locale_en'), value: 'en' },
    { label: t('product_detail_page.locale_zh'), value: 'zh' },
    { label: t('product_detail_page.locale_ja'), value: 'ja' },
    { label: t('product_detail_page.locale_zh_tw'), value: 'zh-TW' },
]);

function billingCycleLabel(cycle) {
    const map = {
        one_time: t('shop_page.cycle_onetime'),
        monthly: t('shop_page.cycle_monthly'),
        yearly: t('shop_page.cycle_yearly'),
        lifetime: t('shop_page.cycle_lifetime'),
    };
    return map[cycle] || cycle;
}

function localeLabel(loc) {
    const map = {
        en: t('product_detail_page.locale_en'),
        zh: t('product_detail_page.locale_zh'),
        ja: t('product_detail_page.locale_ja'),
        'zh-TW': t('product_detail_page.locale_zh_tw'),
    };
    return map[loc] || loc;
}

const dialogVisible = ref(false);
const submitting = ref(false);
const formRef = ref(null);
const form = reactive({
    name: '', slug: '', version: '', description: '', modules: [], is_active: true, is_featured: false,
    image_url: '', images: [], category_id: null,
});
const formRules = computed(() => ({
    name: [{ required: true, message: t('products_page.name_required'), trigger: 'blur' }],
    slug: [{ required: true, message: t('products_page.slug_required'), trigger: 'blur' }],
}));

const categories = ref([]);
async function loadCategories() {
    try {
        const res = await categoryApi.options();
        categories.value = res.data?.data || res.data || [];
    } catch { categories.value = []; }
}

const showFeatureDialog = ref(false);
const featuresLoading = ref(false);
const featureSubmitting = ref(false);
const assignedFeatures = ref([]);
const availableFeatures = ref([]);
const selectedFeatureIds = ref([]);

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const loc = locale.value === 'en' ? 'en-US' : 'zh-CN';
    return new Date(dateStr).toLocaleString(loc, {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function licenseStatusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'info', blacklisted: 'danger' };
    return map[status] || 'info';
}

function licenseStatusLabel(status) {
    const map = {
        active: t('licenses_page.st_active'),
        expired: t('licenses_page.st_expired'),
        suspended: t('licenses_page.st_suspended'),
        revoked: t('licenses_page.st_revoked'),
        frozen: t('licenses_page.st_frozen'),
        blacklisted: t('licenses_page.st_blacklisted'),
    };
    return map[status] || status;
}

async function loadDetail() {
    loading.value = true;
    try {
        const { data: res } = await productApi.show(productId);
        if (res.success) {
            product.value = res.data.product;
            recentLicenses.value = res.data.recent_licenses || [];
            assignedFeatures.value = res.data.product.feature_flags || [];
        }
    } catch {
        ElMessage.error(t('product_detail_page.load_detail_failed'));
    } finally {
        loading.value = false;
    }
}

async function loadFeatures() {
    featuresLoading.value = true;
    try {
        const { data: res } = await productApi.features(productId);
        if (res.success) {
            assignedFeatures.value = res.data.assigned || [];
            availableFeatures.value = res.data.available || [];
            selectedFeatureIds.value = assignedFeatures.value.map(f => f.id);
        }
    } catch {
        // ignore
    } finally {
        featuresLoading.value = false;
    }
}

function openEditDialog() {
    if (!product.value) return;
    form.name = product.value.name;
    form.slug = product.value.slug;
    form.version = product.value.version || '';
    form.description = product.value.description || '';
    form.modules = product.value.modules ? [...product.value.modules] : [];
    form.is_active = Boolean(product.value.is_active);
    form.is_featured = Boolean(product.value.is_featured);
    form.image_url = product.value.image_url || '';
    form.images = product.value.images ? [...product.value.images] : [];
    form.category_id = product.value.category_id || null;
    dialogVisible.value = true;
}

async function submitForm() {
    const valid = await formRef.value.validate().catch(() => false);
    if (!valid) return;

    submitting.value = true;
    try {
        await productApi.update(productId, { ...form, is_active: form.is_active ? 1 : 0 });
        ElMessage.success(t('products_page.update_ok'));
        dialogVisible.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

async function handleMainImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            form.image_url = res.data.url;
        } else {
            ElMessage.error(res.message || t('products_page.upload_fail'));
        }
    } catch {
        ElMessage.error(t('products_page.upload_image_fail'));
    }
    return false;
}

async function handleCarouselImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            if (!form.images) form.images = [];
            form.images.push(res.data.url);
        } else {
            ElMessage.error(res.message || t('products_page.upload_fail'));
        }
    } catch {
        ElMessage.error(t('products_page.upload_image_fail'));
    }
    return false;
}

async function toggleActive(active) {
    const action = active ? t('products_page.activate') : t('products_page.deactivate');
    try {
        await ElMessageBox.confirm(
            t('product_detail_page.toggle_confirm', { action }),
            t('products_page.confirm_title'),
            {
                confirmButtonText: t('actions.confirm'),
                cancelButtonText: t('actions.cancel'),
                type: active ? 'info' : 'warning',
            },
        );
        await productApi.update(productId, { is_active: active ? 1 : 0 });
        ElMessage.success(t('product_detail_page.toggle_ok', { action }));
        loadDetail();
    } catch { /* cancelled */ }
}

async function submitFeatures() {
    featureSubmitting.value = true;
    try {
        await productApi.assignFeatures(productId, selectedFeatureIds.value);
        ElMessage.success(t('product_detail_page.feature_flags_updated'));
        showFeatureDialog.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        featureSubmitting.value = false;
    }
}

const skus = ref([]);
const skusLoading = ref(false);
const showSkuDialog = ref(false);
const skuSubmitting = ref(false);
const skuEditingId = ref(null);
const skuForm = reactive({ sku_code: '', name: '', price: null, stock: -1, is_active: true, billing_cycle: 'one_time' });

async function loadSkus() {
    skusLoading.value = true;
    try {
        const { data: res } = await productApi.getSkus(productId);
        skus.value = res?.data || res || [];
    } catch { skus.value = []; }
    finally { skusLoading.value = false; }
}

function editSku(row) {
    skuEditingId.value = row.id;
    Object.assign(skuForm, { sku_code: row.sku_code, name: row.name, price: row.price, stock: row.stock, is_active: row.is_active, billing_cycle: row.billing_cycle });
    showSkuDialog.value = true;
}

async function submitSku() {
    skuSubmitting.value = true;
    try {
        if (skuEditingId.value) {
            await productApi.updateSku(skuEditingId.value, { ...skuForm });
            ElMessage.success(t('product_detail_page.sku_updated'));
        } else {
            await productApi.createSku(productId, { ...skuForm });
            ElMessage.success(t('product_detail_page.sku_created'));
        }
        showSkuDialog.value = false;
        skuEditingId.value = null;
        loadSkus();
    } catch { ElMessage.error(t('messages.failed')); }
    finally { skuSubmitting.value = false; }
}

async function deleteSku(row) {
    try {
        await ElMessageBox.confirm(
            t('product_detail_page.delete_sku_confirm'),
            t('product_detail_page.confirm_title'),
            { type: 'warning', confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel') },
        );
        await productApi.deleteSku(row.id);
        ElMessage.success(t('product_detail_page.sku_deleted'));
        loadSkus();
    } catch { /* cancelled */ }
}

const specGroups = ref([]);
const specSubmitting = ref(false);

function addSpecGroup() {
    specGroups.value.push({ name: '', values: [{ name: '', value: '' }] });
}
function addSpecValue(gi) {
    specGroups.value[gi].values.push({ name: '', value: '' });
}
async function saveSpecs() {
    specSubmitting.value = true;
    try {
        await productApi.saveSpecs(productId, specGroups.value);
        ElMessage.success(t('product_detail_page.specs_saved'));
    } catch { ElMessage.error(t('product_detail_page.save_failed')); }
    finally { specSubmitting.value = false; }
}

const seoForm = reactive({ meta_title: '', meta_description: '', meta_keywords: '', canonical_url: '', og_title: '', og_description: '' });
const seoSubmitting = ref(false);

async function loadSeo() {
    try {
        const { data: res } = await productApi.getSeo(productId);
        if (res?.success && res.data) Object.assign(seoForm, res.data);
    } catch { /* ignore */ }
}

const demoList = ref([]);
const demoLoading = ref(false);
const showDemoDialog = ref(false);
const demoEditingId = ref(null);
const demoSubmitting = ref(false);
const demoSettings = ref({ enabled: false, images: [] });
const demoForm = reactive({ platform: '', site_url: '', account: '', password: '', sort_order: 0 });

async function loadDemos() {
    demoLoading.value = true;
    try {
        const res = await productDemoApi.list(productId);
        if (res.data.success) {
            demoList.value = res.data.data.demos || [];
            demoSettings.value = {
                enabled: res.data.data.demo_enabled ?? false,
                images: res.data.data.demo_images || [],
            };
        }
    } catch (e) { console.error(e); }
    demoLoading.value = false;
}

function openAddDemoDialog() {
    demoEditingId.value = null;
    Object.assign(demoForm, { platform: '', site_url: '', account: '', password: '', sort_order: 0 });
    showDemoDialog.value = true;
}

function openEditDemoDialog(row) {
    demoEditingId.value = row.id;
    Object.assign(demoForm, {
        platform: row.platform,
        site_url: row.site_url || '',
        account: row.account || '',
        password: row.password || '',
        sort_order: row.sort_order ?? 0,
    });
    showDemoDialog.value = true;
}

async function submitDemo() {
    demoSubmitting.value = true;
    const wasEditing = Boolean(demoEditingId.value);
    try {
        if (demoEditingId.value) {
            await productDemoApi.update(demoEditingId.value, demoForm);
        } else {
            await productDemoApi.create(productId, demoForm);
        }
        showDemoDialog.value = false;
        await loadDemos();
        ElMessage.success(wasEditing ? t('product_detail_page.demo_updated') : t('product_detail_page.demo_added'));
    } catch (e) { console.error(e); }
    demoSubmitting.value = false;
}

async function deleteDemo(row) {
    try {
        await ElMessageBox.confirm(
            t('product_detail_page.delete_demo_confirm'),
            t('product_detail_page.prompt_title'),
            { type: 'warning', confirmButtonText: t('actions.confirm'), cancelButtonText: t('actions.cancel') },
        );
        await productDemoApi.delete(row.id);
        await loadDemos();
        ElMessage.success(t('product_detail_page.deleted_ok'));
    } catch (e) { if (e !== 'cancel') console.error(e); }
}

async function saveDemoSettings() {
    try {
        await productDemoApi.saveSettings(productId, {
            demo_enabled: demoSettings.value.enabled,
            demo_images: demoSettings.value.images,
        });
        ElMessage.success(t('product_detail_page.demo_settings_saved'));
    } catch (e) { console.error(e); }
}

function removeDemoImage(idx) {
    demoSettings.value.images.splice(idx, 1);
    saveDemoSettings();
}

function uploadDemoImage(idx) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const { data: res } = await productApi.uploadImage(formData);
            if (res.success) {
                demoSettings.value.images[idx].url = res.data.url;
                await saveDemoSettings();
                ElMessage.success(t('product_detail_page.image_uploaded'));
            }
        } catch (e) { console.error(e); }
    };
    input.click();
}

async function loadSpecs() {
    try {
        const { data: res } = await productApi.getSpecs(productId);
        if (res?.success && res.data) specGroups.value = res.data;
    } catch { /* ignore */ }
}
async function saveSeo() {
    seoSubmitting.value = true;
    try {
        await productApi.saveSeo(productId, { ...seoForm });
        ElMessage.success(t('product_detail_page.seo_saved'));
    } catch { ElMessage.error(t('product_detail_page.save_failed')); }
    finally { seoSubmitting.value = false; }
}

const translations = ref([]);
const transSubmitting = ref(false);
const showTransDialog = ref(false);
const transForm = reactive({ locale: 'en', name: '', description: '' });

function addTranslation() { showTransDialog.value = true; }
function confirmAddTranslation() {
    translations.value.push({ ...transForm });
    showTransDialog.value = false;
    transForm.locale = 'en'; transForm.name = ''; transForm.description = '';
}
async function saveTranslations() {
    transSubmitting.value = true;
    try {
        await productApi.saveTranslations(productId, translations.value);
        ElMessage.success(t('product_detail_page.translations_saved'));
    } catch { ElMessage.error(t('product_detail_page.save_failed')); }
    finally { transSubmitting.value = false; }
}

onMounted(() => {
    loadDetail();
    loadCategories();
    loadSkus();
    loadSeo();
    loadSpecs();
    loadDemos();
});
</script>

<style scoped>
.product-detail-page { padding: 20px; }

.page-breadcrumb { margin-bottom: 20px; }

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
}
.header-actions { display: flex; gap: 8px; }

.info-card { margin-bottom: 20px; }
.section-card { margin-bottom: 20px; }

.text-muted { color: var(--el-text-color-placeholder); }

code {
    font-family: 'SF Mono', 'Fira Code', monospace;
    font-size: 12px;
    background: var(--el-fill-color-light);
    padding: 2px 6px;
    border-radius: 3px;
}

.feature-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
}

.empty-state {
    padding: 20px 0;
}

.feature-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.feature-checkbox {
    margin-right: 0 !important;
    padding: 8px 12px;
    border: 1px solid var(--el-border-color-light);
    border-radius: 6px;
    transition: all 0.2s;
}
.feature-checkbox:hover {
    border-color: var(--el-color-primary);
    background: var(--el-color-primary-light-9);
}
.feature-item {
    display: flex;
    flex-direction: column;
    margin-left: 8px;
}
.feature-name {
    font-weight: 500;
    font-size: 14px;
}
.feature-key {
    font-size: 11px;
    margin-top: 2px;
}
.feature-desc {
    font-size: 12px;
    color: var(--el-text-color-secondary);
    margin-top: 2px;
}

.image-upload-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
}
.images-upload-wrapper {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.images-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.image-preview {
    position: relative;
    display: inline-block;
}
.image-remove-btn {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    padding: 0;
}

@media (max-width: 768px) {
    .product-detail-page { padding: 12px; }
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    .header-actions {
        width: 100%;
        flex-wrap: wrap;
    }
    .product-detail-page :deep(.el-descriptions) {
        --el-descriptions-item-bordered-label-background: var(--el-fill-color-light);
    }
    .product-detail-page :deep(.el-descriptions__body .el-descriptions__table) {
        display: block;
    }
    .product-detail-page :deep(.el-descriptions__cell) {
        display: block;
        width: 100% !important;
    }
    .product-detail-page :deep(.el-table) {
        display: block;
        overflow-x: auto;
    }
    .product-detail-page :deep(.el-tabs__nav-scroll) {
        overflow-x: auto;
    }
}
</style>
