<template>
    <div class="product-detail-page" v-loading="loading">
        <div class="page-breadcrumb">
            <el-breadcrumb>
                <el-breadcrumb-item :to="{ path: '/products' }">产品管理</el-breadcrumb-item>
                <el-breadcrumb-item>产品详情</el-breadcrumb-item>
            </el-breadcrumb>
        </div>

        <div v-if="product" class="detail-content">
            <el-tabs type="border-card" class="detail-tabs">
                <el-tab-pane label="基本信息" name="basic">
                    <el-card shadow="never" class="info-card">
                        <template #header>
                            <div class="card-header">
                                <span>基本信息</span>
                                <div class="header-actions">
                                    <el-button size="small" @click="openEditDialog">编辑</el-button>
                                    <el-button v-if="product.is_active" size="small" type="warning" @click="toggleActive(false)">下架</el-button>
                                    <el-button v-else size="small" type="success" @click="toggleActive(true)">上架</el-button>
                                </div>
                            </div>
                        </template>
                        <el-descriptions :column="3" border>
                            <el-descriptions-item label="产品 ID"><code>{{ product.id }}</code></el-descriptions-item>
                            <el-descriptions-item label="产品名称" :span="2">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <el-avatar v-if="product.image_url" :size="48" shape="square" :src="product.image_url" />
                                    <span>{{ product.name }}</span>
                                </div>
                            </el-descriptions-item>
                            <el-descriptions-item label="编码"><code>{{ product.slug }}</code></el-descriptions-item>
                            <el-descriptions-item label="版本">{{ product.version || '-' }}</el-descriptions-item>
                            <el-descriptions-item label="可售卖">
                                <el-tag :type="product.is_sellable ? 'success' : 'info'" size="small">{{ product.is_sellable ? '是' : '否' }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="精选产品">
                                <el-tag :type="product.is_featured ? 'warning' : 'info'" size="small">{{ product.is_featured ? '是' : '否' }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="基础价格">{{ product.base_price ? '¥' + product.base_price : '-' }}</el-descriptions-item>
                            <el-descriptions-item label="标签" :span="2">
                                <template v-if="product.tags?.length">
                                    <el-tag v-for="t in product.tags" :key="t" size="small" effect="plain" style="margin:2px 4px 2px 0">{{ t }}</el-tag>
                                </template>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item label="状态">
                                <el-tag :type="product.is_active ? 'success' : 'info'" size="small">{{ product.is_active ? '上架' : '下架' }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item label="License 数"><el-tag type="primary" effect="plain" size="small">{{ product.licenses_count || 0 }}</el-tag></el-descriptions-item>
                            <el-descriptions-item label="累计销量">{{ product.sales_count ?? 0 }}</el-descriptions-item>
                            <el-descriptions-item label="描述" :span="3">{{ product.description || '暂无描述' }}</el-descriptions-item>
                            <el-descriptions-item label="分类" :span="3">
                                <el-tag v-if="product.category" size="small" effect="plain">{{ product.category.name }}</el-tag>
                                <span v-else>-</span>
                            </el-descriptions-item>
                            <el-descriptions-item label="模块" :span="3">
                                <template v-if="product.modules?.length">
                                    <el-tag v-for="mod in product.modules" :key="mod" size="small" effect="plain" style="margin:2px 4px 2px 0">{{ mod }}</el-tag>
                                </template>
                                <span v-else>无模块配置</span>
                            </el-descriptions-item>
                            <el-descriptions-item label="创建时间">{{ formatDate(product.created_at) }}</el-descriptions-item>
                            <el-descriptions-item label="更新时间">{{ formatDate(product.updated_at) }}</el-descriptions-item>
                        </el-descriptions>
                    </el-card>

                    <el-card shadow="never" class="section-card" style="margin-top:16px">
                        <template #header>
                            <div class="card-header">
                                <span>Feature Flags ({{ assignedFeatures.length }})</span>
                                <el-button size="small" type="primary" @click="showFeatureDialog = true"><el-icon><Setting /></el-icon> 管理</el-button>
                            </div>
                        </template>
                        <div v-if="assignedFeatures.length > 0" class="feature-list">
                            <el-tag v-for="f in assignedFeatures" :key="f.id" size="default" effect="plain" type="primary" style="margin:4px 8px 4px 0">
                                <el-icon style="margin-right:4px"><Flag /></el-icon>{{ f.name }} <code style="margin-left:4px;font-size:11px">{{ f.key }}</code>
                            </el-tag>
                        </div>
                        <div v-else class="empty-state"><el-empty :image-size="60" description="尚未分配 Feature Flag" /></div>
                    </el-card>

                    <el-card shadow="never" class="section-card" style="margin-top:16px">
                        <template #header>
                            <div class="card-header">
                                <span>最近 License</span>
                                <el-button size="small" type="primary" @click="$router.push('/licenses?product_id='+product.id)">查看全部</el-button>
                            </div>
                        </template>
                        <el-table :data="recentLicenses" stripe size="small">
                            <el-table-column label="License Key" min-width="220">
                                <template #default="{ row }"><el-link type="primary" @click="$router.push('/licenses/'+row.id)"><code>{{ (row.license_key||'').substring(0,20) }}...</code></el-link></template>
                            </el-table-column>
                            <el-table-column label="客户" width="160">
                                <template #default="{ row }">{{ row.customer?.user?.name || '-' }}</template>
                            </el-table-column>
                            <el-table-column label="状态" width="90">
                                <template #default="{ row }"><el-tag :type="licenseStatusType(row.status)" size="small">{{ licenseStatusLabel(row.status) }}</el-tag></template>
                            </el-table-column>
                            <el-table-column label="过期时间" width="170">{{ formatDate(row.expires_at) }}</el-table-column>
                            <el-table-column label="创建时间" width="170">{{ formatDate(row.created_at) }}</el-table-column>
                        </el-table>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane label="SKU 管理" name="sku">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>SKU 列表 ({{ skus.length }})</span>
                                <el-button size="small" type="primary" @click="showSkuDialog = true; skuForm = { sku_code: '', name: '', price: null, stock: -1, is_active: true, billing_cycle: 'one_time' }">新建 SKU</el-button>
                            </div>
                        </template>
                        <el-table :data="skus" stripe v-loading="skusLoading">
                            <el-table-column label="SKU 编码" prop="sku_code" min-width="140" />
                            <el-table-column label="名称" prop="name" min-width="160" />
                            <el-table-column label="价格" width="100">
                                <template #default="{ row }">¥{{ row.price ?? '-' }}</template>
                            </el-table-column>
                            <el-table-column label="计费周期" width="100">
                                <template #default="{ row }">{{ {one_time:'一次性',monthly:'月度',yearly:'年度',lifetime:'终身'}[row.billing_cycle] || row.billing_cycle }}</template>
                            </el-table-column>
                            <el-table-column label="库存" width="70" prop="stock">
                                <template #default="{ row }">{{ row.stock === -1 ? '不限' : row.stock }}</template>
                            </el-table-column>
                            <el-table-column label="已售" width="60" prop="sold_count" />
                            <el-table-column label="状态" width="70">
                                <template #default="{ row }"><el-tag :type="row.is_active ? 'success' : 'info'" size="small">{{ row.is_active ? '启用' : '停用' }}</el-tag></template>
                            </el-table-column>
                            <el-table-column label="操作" width="120" fixed="right">
                                <template #default="{ row }">
                                    <el-button text size="small" type="primary" @click="editSku(row)">编辑</el-button>
                                    <el-button text size="small" type="danger" @click="deleteSku(row)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-card>

                    <el-dialog v-model="showSkuDialog" :title="skuEditingId ? '编辑 SKU' : '新建 SKU'" width="500px" :close-on-click-modal="false">
                        <el-form :model="skuForm" label-width="100px">
                            <el-form-item label="SKU 编码"><el-input v-model="skuForm.sku_code" placeholder="如：pro-monthly" /></el-form-item>
                            <el-form-item label="名称"><el-input v-model="skuForm.name" placeholder="如：专业版-月度" /></el-form-item>
                            <el-form-item label="价格"><el-input-number v-model="skuForm.price" :precision="2" :min="0" style="width:200px"><template #prefix>¥</template></el-input-number></el-form-item>
                            <el-form-item label="计费周期">
                                <el-select v-model="skuForm.billing_cycle" style="width:200px">
                                    <el-option label="一次性" value="one_time" />
                                    <el-option label="月度" value="monthly" />
                                    <el-option label="年度" value="yearly" />
                                    <el-option label="终身" value="lifetime" />
                                </el-select>
                            </el-form-item>
                            <el-form-item label="库存"><el-input-number v-model="skuForm.stock" :min="-1" style="width:200px" /> <span style="color:#909399;font-size:12px;margin-left:8px">-1 表示不限</span></el-form-item>
                            <el-form-item label="启用"><el-switch v-model="skuForm.is_active" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showSkuDialog = false">取消</el-button>
                            <el-button type="primary" :loading="skuSubmitting" @click="submitSku">保存</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>

                <el-tab-pane label="规格参数" name="specs">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>规格参数</span>
                                <el-button size="small" type="primary" @click="addSpecGroup">添加规格组</el-button>
                            </div>
                        </template>
                        <div v-if="specGroups.length > 0">
                            <el-card v-for="(group, gi) in specGroups" :key="gi" shadow="never" style="margin-bottom:12px">
                                <template #header>
                                    <div class="card-header">
                                        <el-input v-model="group.name" placeholder="规格组名称" style="width:200px" />
                                        <div>
                                            <el-button size="small" @click="addSpecValue(gi)">添加参数</el-button>
                                            <el-button size="small" type="danger" plain @click="specGroups.splice(gi,1)">删除组</el-button>
                                        </div>
                                    </div>
                                </template>
                                <div v-for="(sv, si) in group.values" :key="si" style="display:flex;gap:8px;margin-bottom:8px">
                                    <el-input v-model="sv.name" placeholder="参数名" style="width:200px" />
                                    <el-input v-model="sv.value" placeholder="参数值" style="width:300px" />
                                    <el-button size="small" type="danger" plain @click="group.values.splice(si,1)">删除</el-button>
                                </div>
                            </el-card>
                            <el-button type="primary" :loading="specSubmitting" @click="saveSpecs">保存规格配置</el-button>
                        </div>
                        <div v-else><el-empty :image-size="60" description="暂未配置规格参数" /></div>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane label="SEO" name="seo">
                    <el-card shadow="never">
                        <template #header><span>SEO 元数据</span></template>
                        <el-form :model="seoForm" label-width="140px">
                            <el-form-item label="Meta 标题"><el-input v-model="seoForm.meta_title" placeholder="搜索引擎显示的标题" maxlength="160" show-word-limit /></el-form-item>
                            <el-form-item label="Meta 描述"><el-input v-model="seoForm.meta_description" type="textarea" :rows="3" placeholder="搜索引擎显示的描述" maxlength="500" show-word-limit /></el-form-item>
                            <el-form-item label="Meta 关键词"><el-input v-model="seoForm.meta_keywords" placeholder="逗号分隔" /></el-form-item>
                            <el-form-item label="规范 URL (Canonical)"><el-input v-model="seoForm.canonical_url" placeholder="https://..." /></el-form-item>
                            <el-form-item label="OG 标题"><el-input v-model="seoForm.og_title" placeholder="社交分享标题" maxlength="160" /></el-form-item>
                            <el-form-item label="OG 描述"><el-input v-model="seoForm.og_description" type="textarea" :rows="2" placeholder="社交分享描述" /></el-form-item>
                            <el-form-item><el-button type="primary" :loading="seoSubmitting" @click="saveSeo">保存 SEO 设置</el-button></el-form-item>
                        </el-form>
                    </el-card>
                </el-tab-pane>

                <el-tab-pane label="多语言" name="translations">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>多语言翻译</span>
                                <el-button size="small" type="primary" @click="addTranslation">添加翻译</el-button>
                            </div>
                        </template>
                        <div v-if="translations.length > 0">
                            <el-table :data="translations" stripe>
                                <el-table-column label="语言" width="100">
                                    <template #default="{ row }">{{ {en:'English',zh:'中文',ja:'日本語','zh-TW':'繁体中文'}[row.locale] || row.locale }}</template>
                                </el-table-column>
                                <el-table-column label="名称" prop="name" min-width="160" />
                                <el-table-column label="描述" prop="description" min-width="200" />
                                <el-table-column label="操作" width="80">
                                    <template #default="{ row, $index }"><el-button text size="small" type="danger" @click="translations.splice($index,1)">删除</el-button></template>
                                </el-table-column>
                            </el-table>
                            <el-button type="primary" :loading="transSubmitting" @click="saveTranslations" style="margin-top:12px">保存翻译</el-button>
                        </div>
                        <div v-else><el-empty :image-size="60" description="暂未添加翻译" /></div>
                    </el-card>
                    <el-dialog v-model="showTransDialog" title="添加翻译" width="500px">
                        <el-form :model="transForm" label-width="80px">
                            <el-form-item label="语言"><el-select v-model="transForm.locale" style="width:100%">
                                <el-option label="English" value="en" /><el-option label="中文" value="zh" /><el-option label="日本語" value="ja" /><el-option label="繁体中文" value="zh-TW" />
                            </el-select></el-form-item>
                            <el-form-item label="名称"><el-input v-model="transForm.name" /></el-form-item>
                            <el-form-item label="描述"><el-input v-model="transForm.description" type="textarea" :rows="3" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showTransDialog = false">取消</el-button>
                            <el-button type="primary" @click="confirmAddTranslation">添加</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>
                <el-tab-pane label="产品演示" name="demo">
                    <el-card shadow="never">
                        <template #header>
                            <div class="card-header">
                                <span>产品演示设置</span>
                                <el-button size="small" type="primary" @click="openAddDemoDialog">添加演示平台</el-button>
                            </div>
                        </template>

                        <el-form label-width="120px" style="margin-bottom:20px">
                            <el-form-item label="启用演示">
                                <el-switch v-model="demoSettings.enabled" @change="saveDemoSettings" />
                                <span style="margin-left:10px;color:#909399;font-size:12px">开启后前端产品详情页将显示「演示」按钮</span>
                            </el-form-item>
                            <el-form-item label="演示图片">
                                <div style="width:100%">
                                    <div v-for="(img, idx) in demoSettings.images" :key="idx" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                                        <el-input v-model="img.label" placeholder="图片标签，如：H5移动端演示" style="width:160px" @blur="saveDemoSettings" />
                                        <el-input v-model="img.url" placeholder="https://..." style="width:320px" @blur="saveDemoSettings" />
                                        <el-button size="small" @click="uploadDemoImage(idx)">上传</el-button>
                                        <el-button size="small" type="danger" plain @click="removeDemoImage(idx)">删除</el-button>
                                        <template v-if="img.url">
                                            <el-image :src="img.url" fit="cover" style="width:48px;height:48px;border-radius:4px;border:1px solid #e5e7eb" />
                                        </template>
                                    </div>
                                    <el-button size="small" type="primary" plain @click="demoSettings.images.push({label:'',url:''})">
                                        + 添加图片
                                    </el-button>
                                </div>
                            </el-form-item>
                        </el-form>

                        <el-table :data="demoList" stripe v-loading="demoLoading">
                            <el-table-column label="排序" width="60" prop="sort_order" />
                            <el-table-column label="演示平台" min-width="140" prop="platform" />
                            <el-table-column label="演示站点" min-width="240" prop="site_url">
                                <template #default="{ row }">
                                    <a :href="row.site_url" target="_blank" style="color:#409eff">{{ row.site_url || '-' }}</a>
                                </template>
                            </el-table-column>
                            <el-table-column label="演示账号" width="140" prop="account" />
                            <el-table-column label="演示密码" width="140" prop="password" />
                            <el-table-column label="操作" width="140" fixed="right">
                                <template #default="{ row }">
                                    <el-button text size="small" type="primary" @click="openEditDemoDialog(row)">编辑</el-button>
                                    <el-button text size="small" type="danger" @click="deleteDemo(row)">删除</el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <div v-if="demoList.length === 0 && !demoLoading" style="text-align:center;padding:40px;color:#909399">
                            暂未添加演示平台，点击上方「添加演示平台」按钮添加
                        </div>
                    </el-card>

                    <el-dialog v-model="showDemoDialog" :title="demoEditingId ? '编辑演示平台' : '添加演示平台'" width="500px" :close-on-click-modal="false">
                        <el-form :model="demoForm" label-width="100px">
                            <el-form-item label="演示平台"><el-input v-model="demoForm.platform" placeholder="如：管理后台、PC端前台、H5端前台" /></el-form-item>
                            <el-form-item label="演示站点"><el-input v-model="demoForm.site_url" placeholder="https://..." /></el-form-item>
                            <el-form-item label="演示账号"><el-input v-model="demoForm.account" placeholder="demo@example.com" /></el-form-item>
                            <el-form-item label="演示密码"><el-input v-model="demoForm.password" placeholder="demo123" /></el-form-item>
                            <el-form-item label="排序"><el-input-number v-model="demoForm.sort_order" :min="0" style="width:100px" /></el-form-item>
                        </el-form>
                        <template #footer>
                            <el-button @click="showDemoDialog = false">取消</el-button>
                            <el-button type="primary" :loading="demoSubmitting" @click="submitDemo">保存</el-button>
                        </template>
                    </el-dialog>
                </el-tab-pane>
            </el-tabs>
        </div>

        <!-- 编辑 Dialog -->
        <el-dialog
            v-model="dialogVisible"
            title="编辑产品"
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
                <el-form-item label="产品名称" prop="name">
                    <el-input v-model="form.name" />
                </el-form-item>
                <el-form-item label="编码" prop="slug">
                    <el-input v-model="form.slug" />
                </el-form-item>
                <el-form-item label="版本" prop="version">
                    <el-input v-model="form.version" style="width: 200px" />
                </el-form-item>
                <el-form-item label="描述" prop="description">
                    <el-input v-model="form.description" type="textarea" :rows="3" />
                </el-form-item>
                <el-form-item label="分类" prop="category_id">
                    <el-select v-model="form.category_id" clearable placeholder="选择产品分类" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="主图">
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
                                <el-icon><Upload /></el-icon> 上传主图
                            </el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item label="轮播图">
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
                                <el-icon><Plus /></el-icon> 添加轮播图
                            </el-button>
                        </el-upload>
                    </div>
                </el-form-item>
                <el-form-item label="模块" prop="modules">
                    <el-select
                        v-model="form.modules"
                        multiple
                        allow-create
                        filterable
                        default-first-option
                        placeholder="输入后回车添加"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item label="精选产品">
                    <el-switch v-model="form.is_featured" />
                </el-form-item>
                <el-form-item label="上架">
                    <el-switch v-model="form.is_active" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="submitting" @click="submitForm">保存</el-button>
            </template>
        </el-dialog>

        <!-- Feature Flags 管理 Dialog -->
        <el-dialog
            v-model="showFeatureDialog"
            title="管理 Feature Flags"
            width="550px"
            :close-on-click-modal="false"
        >
            <div v-loading="featuresLoading">
                <el-empty v-if="availableFeatures.length === 0" :image-size="60" description="暂无可用 Feature Flag" />
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
                <el-button @click="showFeatureDialog = false">取消</el-button>
                <el-button type="primary" :loading="featureSubmitting" @click="submitFeatures">
                    保存
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup>
import { ref, computed, reactive, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Setting, Flag, Upload, Close, Plus } from '@element-plus/icons-vue';
import productApi from '@/api/product';
import productDemoApi from '@/api/productDemo';
import categoryApi from '@/api/productCategory';

const route = useRoute();
const router = useRouter();
const productId = Number(route.params.id);

const loading = ref(false);
const product = ref(null);
const recentLicenses = ref([]);

// 所有图片预览列表
const allImages = computed(() => {
    if (!product.value) return [];
    const list = [];
    if (product.value.image_url) list.push(product.value.image_url);
    if (product.value.images && product.value.images.length) {
        list.push(...product.value.images);
    }
    return list;
});

// Edit dialog
const dialogVisible = ref(false);
const submitting = ref(false);
const formRef = ref(null);
const form = reactive({
    name: '', slug: '', version: '', description: '', modules: [], is_active: true, is_featured: false,
    image_url: '', images: [], category_id: null,
});
const formRules = {
    name: [{ required: true, message: '请输入产品名称', trigger: 'blur' }],
    slug: [{ required: true, message: '请输入产品编码', trigger: 'blur' }],
};

// Product categories
const categories = ref([]);
async function loadCategories() {
    try {
        const res = await categoryApi.options();
        categories.value = res.data?.data || res.data || [];
    } catch { categories.value = []; }
}

// Feature flags
const showFeatureDialog = ref(false);
const featuresLoading = ref(false);
const featureSubmitting = ref(false);
const assignedFeatures = ref([]);
const availableFeatures = ref([]);
const selectedFeatureIds = ref([]);

function formatDate(dateStr) {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleString('zh-CN', {
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit',
    });
}

function licenseStatusType(status) {
    const map = { active: 'success', expired: 'danger', suspended: 'warning', revoked: 'info', blacklisted: 'danger' };
    return map[status] || 'info';
}

function licenseStatusLabel(status) {
    const map = { active: '启用', expired: '过期', suspended: '暂停', revoked: '撤销', frozen: '冻结', blacklisted: '黑名单' };
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
        ElMessage.error('获取产品详情失败');
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

// 编辑
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
        ElMessage.success('产品更新成功');
        dialogVisible.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        submitting.value = false;
    }
}

// 图片上传
async function handleMainImageUpload(file) {
    const fd = new FormData();
    fd.append('file', file);
    try {
        const { data: res } = await productApi.uploadImage(fd);
        if (res.success) {
            form.image_url = res.data.url;
        } else {
            ElMessage.error(res.message || '上传失败');
        }
    } catch {
        ElMessage.error('图片上传失败');
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
            ElMessage.error(res.message || '上传失败');
        }
    } catch {
        ElMessage.error('图片上传失败');
    }
    return false;
}

// 上架/下架
async function toggleActive(active) {
    const action = active ? '上架' : '下架';
    try {
        await ElMessageBox.confirm(`确定要${action}该产品吗？`, '确认操作', {
            confirmButtonText: '确定', cancelButtonText: '取消', type: active ? 'info' : 'warning',
        });
        await productApi.update(productId, { is_active: active ? 1 : 0 });
        ElMessage.success(`${action}成功`);
        loadDetail();
    } catch { /* cancelled */ }
}

// Feature Flags
async function submitFeatures() {
    featureSubmitting.value = true;
    try {
        await productApi.assignFeatures(productId, selectedFeatureIds.value);
        ElMessage.success('Feature Flags 更新成功');
        showFeatureDialog.value = false;
        loadDetail();
    } catch {
        // handled by interceptor
    } finally {
        featureSubmitting.value = false;
    }
}

// ── SKU 管理 ──
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
            ElMessage.success('SKU 已更新');
        } else {
            await productApi.createSku(productId, { ...skuForm });
            ElMessage.success('SKU 已创建');
        }
        showSkuDialog.value = false;
        skuEditingId.value = null;
        loadSkus();
    } catch { ElMessage.error('操作失败'); }
    finally { skuSubmitting.value = false; }
}

async function deleteSku(row) {
    try {
        await ElMessageBox.confirm('确定删除此 SKU？', '确认', { type: 'warning' });
        await productApi.deleteSku(row.id);
        ElMessage.success('SKU 已删除');
        loadSkus();
    } catch { /* cancelled */ }
}

// ── 规格参数 ──
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
        ElMessage.success('规格配置已保存');
    } catch { ElMessage.error('保存失败'); }
    finally { specSubmitting.value = false; }
}

// ── SEO ──
const seoForm = reactive({ meta_title: '', meta_description: '', meta_keywords: '', canonical_url: '', og_title: '', og_description: '' });
const seoSubmitting = ref(false);

async function loadSeo() {
    try {
        const { data: res } = await productApi.getSeo(productId);
        if (res?.success && res.data) Object.assign(seoForm, res.data);
    } catch { /* ignore */ }
}

// ── 产品演示 ──
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
    try {
        if (demoEditingId.value) {
            await productDemoApi.update(demoEditingId.value, demoForm);
        } else {
            await productDemoApi.create(productId, demoForm);
        }
        showDemoDialog.value = false;
        await loadDemos();
        ElMessage.success(demoEditingId.value ? '演示平台已更新' : '演示平台已添加');
    } catch (e) { console.error(e); }
    demoSubmitting.value = false;
}

async function deleteDemo(row) {
    try {
        await ElMessageBox.confirm('确定删除该演示平台？', '提示', { type: 'warning' });
        await productDemoApi.delete(row.id);
        await loadDemos();
        ElMessage.success('已删除');
    } catch (e) { if (e !== 'cancel') console.error(e); }
}

async function saveDemoSettings() {
    try {
        await productDemoApi.saveSettings(productId, {
            demo_enabled: demoSettings.value.enabled,
            demo_images: demoSettings.value.images,
        });
        ElMessage.success('演示设置已保存');
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
                ElMessage.success('图片已上传');
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
        ElMessage.success('SEO 设置已保存');
    } catch { ElMessage.error('保存失败'); }
    finally { seoSubmitting.value = false; }
}

// ── 多语言 ──
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
        ElMessage.success('翻译已保存');
    } catch { ElMessage.error('保存失败'); }
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
