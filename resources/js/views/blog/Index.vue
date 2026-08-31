<template>
    <el-tabs v-model="blogMainTab" type="border-card" class="blog-changelog-tabs">
        <el-tab-pane label="Blog" name="blog">
    <div class="blog-manager-page">
        <!-- 统计 -->
        <el-row :gutter="20" class="stats-row">
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.total }}</div>
                    <div class="stat-label">{{ t('blog_page.stats_total') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.published }}</div>
                    <div class="stat-label">{{ t('blog_page.stats_published') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">{{ stats.drafts }}</div>
                    <div class="stat-label">{{ t('blog_page.stats_drafts') }}</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card shadow="hover" class="stat-card">
                    <div class="stat-value">
                        <el-tooltip :content="t('blog_page.stats_type_tooltip')" placement="top">
                            <span>{{ stats.by_type?.blog || 0 }} / {{ stats.by_type?.changelog || 0 }} / {{ stats.by_type?.release_note || 0 }}</span>
                        </el-tooltip>
                    </div>
                    <div class="stat-label">{{ t('blog_page.stats_bcr') }}</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- RSS信息 -->
        <el-card class="rss-info" style="margin-bottom: 16px">
            <div class="rss-header">
                <span class="rss-label">{{ t('blog_page.rss_label') }}</span>
                <div class="rss-links">
                    <el-tag>{{ t('blog_page.rss_all') }}</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/all</code>
                    <el-tag type="success">Blog</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/blog</code>
                    <el-tag type="warning">Changelog</el-tag>
                    <code class="rss-url">{{ baseUrl }}/api/rss/changelog</code>
                </div>
                <el-button size="small" @click="copyRssUrls">{{ t('blog_page.copy_all_urls') }}</el-button>
            </div>
        </el-card>

        <!-- 操作栏 -->
        <el-card class="search-card">
            <el-row :gutter="16">
                <el-col :span="5">
                    <el-input v-model="filters.search" :placeholder="t('blog_page.search_ph')" clearable @clear="loadList" @keyup.enter="loadList" />
                </el-col>
                <el-col :span="3">
                    <el-select v-model="filters.type" :placeholder="t('blog_page.filter_type')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="opt in typeFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-col>
                <el-col :span="3">
                    <el-select v-model="filters.status" :placeholder="t('blog_page.filter_status')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="opt in statusFilterOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                    </el-select>
                </el-col>
                <el-col :span="4">
                    <el-select v-model="filters.category_id" :placeholder="t('blog_page.filter_category')" clearable @change="loadList" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-col>
                <el-col :span="9" style="text-align: right">
                    <el-button type="primary" @click="showCreateDialog">
                        <el-icon><Plus /></el-icon> {{ t('blog_page.new_post') }}
                    </el-button>
                    <el-button @click="showCategoryDialog = true">
                        <el-icon><FolderOpened /></el-icon> {{ t('blog_page.category_manage') }}
                    </el-button>
                    <el-button @click="handleExport" :loading="exporting">
                        <el-icon><Download /></el-icon> {{ t('blog_page.export_csv') }}
                    </el-button>
                    <el-button @click="loadList">{{ t('blog_page.refresh') }}</el-button>
                </el-col>
            </el-row>
        </el-card>

        <!-- 批量操作栏 -->
        <el-card v-if="selectedIds.length > 0" class="batch-bar" style="margin-bottom:12px">
            <div class="batch-bar-inner">
                <span class="batch-info">{{ t('blog_page.selected_count', { n: selectedIds.length }) }}</span>
                <el-button size="small" @click="clearSelection">{{ t('blog_page.clear_selection') }}</el-button>
                <el-button size="small" type="primary" @click="handleBatchPublish" :loading="batchLoading">
                    <el-icon><Upload /></el-icon> {{ t('blog_page.batch_publish') }}
                </el-button>
                <el-button size="small" type="warning" @click="showBatchCategoryDialog = true" :loading="batchLoading">
                    <el-icon><FolderOpened /></el-icon> {{ t('blog_page.batch_switch_category') }}
                </el-button>
                <el-button size="small" type="danger" @click="handleBatchDelete" :loading="batchLoading">
                    <el-icon><Delete /></el-icon> {{ t('blog_page.batch_delete') }}
                </el-button>
            </div>
        </el-card>

        <!-- 文章列表 -->
        <el-card class="table-card">
            <el-table ref="tableRef" :data="list" v-loading="loading" border stripe style="width: 100%"
                @selection-change="onSelectionChange">
                <el-table-column type="selection" width="45" />
                <el-table-column prop="title" :label="t('blog_page.col_title')" min-width="250">
                    <template #default="{ row }">
                        <div class="title-cell">
                            <el-tag :type="typeTag(row.type)" size="small" class="type-badge">
                                {{ typeLabel(row.type) }}
                            </el-tag>
                            <span>{{ row.title }}</span>
                            <el-tag v-if="row.is_featured" type="warning" size="small" effect="dark">{{ t('blog_page.featured') }}</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="author" :label="t('blog_page.col_author')" width="120" />
                <el-table-column :label="t('blog_page.col_category')" width="110">
                    <template #default="{ row }">
                        <el-tag v-if="row.category" size="small" effect="plain" :color="row.category.color || ''">
                            {{ row.category.name }}
                        </el-tag>
                        <span v-else class="text-muted">-</span>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_version')" width="90">
                    <template #default="{ row }">{{ row.version || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_status')" width="90">
                    <template #default="{ row }">
                        <el-tag :type="row.is_published ? 'success' : 'info'" size="small">
                            {{ row.is_published ? t('blog_page.status.published') : t('blog_page.status.draft') }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_published_at')" width="170">
                    <template #default="{ row }">{{ row.published_at || '-' }}</template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_created_at')" width="170">
                    <template #default="{ row }">{{ row.created_at }}</template>
                </el-table-column>
                <el-table-column :label="t('blog_page.col_actions')" width="260" fixed="right">
                    <template #default="{ row }">
                        <el-button size="small" type="primary" @click="showEditDialog(row)">{{ t('actions.edit') }}</el-button>
                        <el-button
                            size="small"
                            :type="row.is_published ? 'warning' : 'success'"
                            @click="handleTogglePublish(row)"
                        >
                            {{ row.is_published ? t('blog_page.unpublish') : t('blog_page.publish') }}
                        </el-button>
                        <el-button
                            size="small"
                            :type="row.is_featured ? 'warning' : 'default'"
                            @click="handleToggleFeatured(row)"
                        >
                            {{ row.is_featured ? t('blog_page.unfeature') : t('blog_page.featured') }}
                        </el-button>
                        <el-popconfirm :title="t('blog_page.confirm_delete_post')" @confirm="handleDelete(row)">
                            <template #reference>
                                <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
            <div class="pagination-wrap">
                <el-pagination
                    v-model:current-page="currentPage"
                    :page-size="perPage"
                    :total="total"
                    layout="total, prev, pager, next"
                    @current-change="loadList"
                />
            </div>
        </el-card>

        <!-- 编辑/新建对话框 -->
        <el-dialog
            v-model="dialogVisible"
            :title="editingId ? t('blog_page.edit_post') : t('blog_page.new_post')"
            width="920px"
            :class="['blog-dialog', isFullscreen ? 'blog-dialog-fullscreen' : '']"
            :close-on-click-modal="false"
            :fullscreen="isFullscreen"
        >
            <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_title')" prop="title">
                            <el-input v-model="form.title" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_type')" prop="type">
                            <el-select v-model="form.type" style="width: 100%">
                                <el-option v-for="opt in formTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                
                                
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_slug')">
                            <el-input v-model="form.slug" :placeholder="t('blog_page.slug_auto_ph')" maxlength="255" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_author')">
                            <el-input v-model="form.author" :placeholder="t('blog_page.author_default_ph')" maxlength="100" />
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_version')">
                            <el-input v-model="form.version" :placeholder="t('blog_page.version_ph')" maxlength="30" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="t('blog_page.form_cover')">
                            <div style="display:flex;gap:6px">
                                <el-input v-model="form.featured_image" :placeholder="t('blog_page.image_url_ph')" maxlength="500" style="flex:1" />
                                <el-button size="small" @click="uploadCoverImage" :title="t('blog_page.upload_cover')">{{ t('actions.upload') }}</el-button>
                            </div>
                        </el-form-item>
                    </el-col>
                </el-row>
                <el-form-item :label="t('blog_page.form_category')">
                    <el-select v-model="form.category_id" clearable :placeholder="t('blog_page.select_category_ph')" style="width: 100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('blog_page.form_tags')">
                    <el-select v-model="form.tags" multiple filterable allow-create default-first-option style="width: 100%">
                        <el-option v-for="tag in tagOptions" :key="tag" :label="tag" :value="tag" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('blog_page.form_excerpt')">
                    <el-input v-model="form.excerpt" type="textarea" :rows="2" maxlength="500" @input="updateSeoPreview" />
                </el-form-item>
                <!-- SEO 预览卡片 -->
                <el-collapse v-model="showSeoPreview" style="margin-bottom:16px">
                    <el-collapse-item :title="t('blog_page.seo_preview')" name="seo">
                        <div class="seo-preview-card">
                            <div class="seo-url">{{ seoPreviewUrl }}</div>
                            <div class="seo-title">{{ seoPreviewTitle || t('blog_page.seo_title_fallback') }}</div>
                            <div class="seo-desc">{{ seoPreviewDesc || t('blog_page.seo_desc_fallback') }}</div>
                        </div>
                    </el-collapse-item>
                </el-collapse>
                <el-form-item :label="t('blog_page.form_content')" prop="content">
                    <!-- Tiptap 工具栏 -->
                    <div v-if="editor" class="blog-editor-toolbar">
                        <el-button-group size="small">
                            <el-button :type="editor.isActive('bold') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBold().run()" :title="t('blog_page.tb_bold')"><b>B</b></el-button>
                            <el-button :type="editor.isActive('italic') ? 'primary' : 'default'" @click="editor.chain().focus().toggleItalic().run()" :title="t('blog_page.tb_italic')"><i>I</i></el-button>
                            <el-button :type="editor.isActive('underline') ? 'primary' : 'default'" @click="editor.chain().focus().toggleUnderline().run()" :title="t('blog_page.tb_underline')"><u>U</u></el-button>
                            <el-button :type="editor.isActive('strike') ? 'primary' : 'default'" @click="editor.chain().focus().toggleStrike().run()" :title="t('blog_page.tb_strike')"><s>S</s></el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive('heading', { level: 1 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 1 }).run()">H1</el-button>
                            <el-button :type="editor.isActive('heading', { level: 2 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</el-button>
                            <el-button :type="editor.isActive('heading', { level: 3 }) ? 'primary' : 'default'" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive('bulletList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBulletList().run()" :title="t('blog_page.tb_bullet_list')">•</el-button>
                            <el-button :type="editor.isActive('orderedList') ? 'primary' : 'default'" @click="editor.chain().focus().toggleOrderedList().run()" :title="t('blog_page.tb_ordered_list')">1.</el-button>
                            <el-button :type="editor.isActive('blockquote') ? 'primary' : 'default'" @click="editor.chain().focus().toggleBlockquote().run()" :title="t('blog_page.tb_blockquote')">❝</el-button>
                            <el-button :type="editor.isActive('code') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCode().run()" :title="t('blog_page.tb_inline_code')">`code`</el-button>
                            <el-button :type="editor.isActive('codeBlock') ? 'primary' : 'default'" @click="editor.chain().focus().toggleCodeBlock().run()" :title="t('blog_page.tb_code_block')">&lt;/&gt;</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button @click="insertImageFile" :title="t('blog_page.tb_upload_image')"></el-button>
                            <el-button @click="insertVideo" :title="t('blog_page.tb_insert_video')"></el-button>
                            <el-button @click="insertTable" :title="t('blog_page.tb_insert_table')">⊞</el-button>
                            <el-button @click="insertLink" :title="t('blog_page.tb_insert_link')"></el-button>
                            <el-button @click="editor?.chain().focus().setHorizontalRule().run()" :title="t('blog_page.tb_hr')">—</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button :type="editor.isActive({ textAlign: 'left' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('left').run()" :title="t('blog_page.tb_align_left')">⬅</el-button>
                            <el-button :type="editor.isActive({ textAlign: 'center' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('center').run()" :title="t('blog_page.tb_align_center')">⇔</el-button>
                            <el-button :type="editor.isActive({ textAlign: 'right' }) ? 'primary' : 'default'" @click="editor?.chain().focus().setTextAlign('right').run()" :title="t('blog_page.tb_align_right')">➔</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-button-group size="small">
                            <el-button @click="editor?.chain().focus().undo().run()" :title="t('blog_page.tb_undo')">↩</el-button>
                            <el-button @click="editor?.chain().focus().redo().run()" :title="t('blog_page.tb_redo')">↪</el-button>
                        </el-button-group>

                        <el-divider direction="vertical" />

                        <el-popover placement="bottom" :width="220" trigger="click" v-model:visible="showColorPicker">
                            <template #reference>
                                <el-button size="small" :title="t('blog_page.tb_text_color')" :style="{borderBottom:'3px solid '+(editor?.getAttributes('textStyle')?.color||'#999')}">A</el-button>
                            </template>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                                <div v-for="c in ['#333333','#666666','#999999','#c0c4cc','#0f172a','#67c23a','#e6a23c','#f56c6c','#b37feb','#ff85c0','#ff7a45','#a0d911']" :key="c"
                                    @click="editor?.chain().focus().setColor(c).run(); showColorPicker=false"
                                    :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}"
                                    :title="c">
                                </div>
                                <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                    <el-button size="small" text @click="editor?.chain().focus().unsetColor().run(); showColorPicker=false">{{ t('blog_page.clear_color') }}</el-button>
                                </div>
                            </div>
                        </el-popover>

                        <el-popover placement="bottom" :width="220" trigger="click" v-model:visible="showHighlightPicker">
                            <template #reference>
                                <el-button size="small" :title="t('blog_page.tb_highlight')" :style="{background:editor?.getAttributes('highlight')?.color||'transparent'}">🖊</el-button>
                            </template>
                            <div style="display:flex;flex-wrap:wrap;gap:4px;padding:4px">
                                <div v-for="c in ['#ffd8bf','#ffd6e7','#d3adf7','#bae7ff','#b7eb8f','#ffe58f','#ffffff']" :key="c"
                                    @click="editor?.chain().focus().toggleHighlight({color:c}).run(); showHighlightPicker=false"
                                    :style="{width:28,height:28,borderRadius:4,background:c,cursor:'pointer',border:c==='#ffffff'?'1px solid #dcdfe6':'none'}">
                                </div>
                                <div style="width:100%;margin-top:4px;border-top:1px solid #eee;padding-top:4px">
                                    <el-button size="small" text @click="editor?.chain().focus().toggleHighlight().run(); showHighlightPicker=false">{{ t('blog_page.clear_highlight') }}</el-button>
                                </div>
                            </div>
                        </el-popover>

                        <el-button size="small" :type="showSearch ? 'primary' : 'default'" @click="toggleSearch" :title="t('blog_page.tb_search_replace')">{{ t('blog_page.tb_search') }}</el-button>
                        <el-button size="small" @click="showTemplateDialog = true" :title="t('blog_page.tb_template')">{{ t('blog_page.tb_template') }}</el-button>
                        <el-button size="small" @click="applyQuickFormat" :title="t('blog_page.tb_format')">{{ t('blog_page.tb_format') }}</el-button>
                        <el-button size="small" @click="showTocDialog = true" :title="t('blog_page.tb_toc')">{{ t('blog_page.tb_toc') }}</el-button>
                        <el-button size="small" @click="showEmojiDialog = true" :title="t('blog_page.tb_emoji')">Emoji</el-button>

                        <el-dropdown trigger="click" @command="handleCopy">
                            <el-button size="small" :title="t('blog_page.tb_copy')">{{ t('blog_page.tb_copy') }} <el-icon><ArrowDown /></el-icon></el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="html">{{ t('blog_page.copy_html') }}</el-dropdown-item>
                                    <el-dropdown-item command="text">{{ t('blog_page.copy_text') }}</el-dropdown-item>
                                    <el-dropdown-item command="markdown">{{ t('blog_page.copy_markdown') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-button size="small" @click="clearFormatting" :title="t('blog_page.clear_format')">{{ t('blog_page.clear_format') }}</el-button>

                        <el-divider direction="vertical" />

                        <el-dropdown trigger="click" @command="handleAiAction">
                            <el-button size="small" type="primary" plain>{{ t('blog_page.ai_tools') }} <el-icon><ArrowDown /></el-icon></el-button>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item command="create">{{ t('blog_page.ai_create') }}</el-dropdown-item>
                                    <el-dropdown-item command="typo">{{ t('blog_page.ai_typo') }}</el-dropdown-item>
                                    <el-dropdown-item command="assistant">{{ t('blog_page.ai_assistant') }}</el-dropdown-item>
                                    <el-dropdown-item command="polish">{{ t('blog_page.ai_polish') }}</el-dropdown-item>
                                    <el-dropdown-item command="summary">{{ t('blog_page.ai_summary') }}</el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-divider direction="vertical" />

                        <el-button size="small" @click="showHelpDialog = true" :title="t('blog_page.shortcuts_help')">?</el-button>
                        <el-button size="small" @click="showStatsDialog = true" :title="t('blog_page.article_stats')">#</el-button>
                        <el-button size="small" @click="toggleFullscreen" :type="isFullscreen ? 'primary' : 'default'" :title="t('blog_page.fullscreen_edit')">⛶</el-button>
                        <el-button :type="showSource ? 'primary' : 'default'" size="small" @click="toggleSource" :title="t('blog_page.html_source')">&lt;/&gt; HTML</el-button>
                    </div>
                    <!-- 搜索替换面板 -->
                    <div v-if="showSearch" class="search-panel">
                        <div class="search-row">
                            <el-input v-model="searchQuery" :placeholder="t('blog_page.search_ph_short')" size="small" clearable style="width:180px" @input="doSearch" @keyup.enter="nextMatch" />
                            <el-button size="small" @click="prevMatch" :disabled="!searchMatches">▲</el-button>
                            <el-button size="small" @click="nextMatch" :disabled="!searchMatches">▼</el-button>
                            <span class="search-count" v-if="searchMatches > 0">{{ currentMatch + 1 }}/{{ searchMatches }}</span>
                            <span class="search-count no-result" v-else-if="searchQuery && searchMatches === 0">{{ t('blog_page.no_results') }}</span>
                        </div>
                        <div class="search-row">
                            <el-input v-model="searchReplace" :placeholder="t('blog_page.replace_ph')" size="small" style="width:180px" @keyup.enter="doReplace" />
                            <el-button size="small" @click="doReplace" :disabled="!searchMatches" type="primary">{{ t('blog_page.replace') }}</el-button>
                            <el-button size="small" @click="doReplaceAll" :disabled="!searchMatches">{{ t('blog_page.replace_all') }}</el-button>
                            <el-button size="small" text @click="showSearch = false">{{ t('actions.close') }}</el-button>
                        </div>
                    </div>
                    <!-- 代码块语言选择 -->
                    <div v-if="editor?.isActive('codeBlock')" class="code-lang-bar">
                        <span style="font-size:12px;color:#909399;margin-right:6px">{{ t('blog_page.lang_label') }}</span>
                        <el-select v-model="codeLang" size="small" style="width:130px" :placeholder="t('blog_page.select_lang_ph')" @change="changeCodeLang">
                            <el-option v-for="lang in codeLanguages" :key="lang" :label="lang" :value="lang" />
                        </el-select>
                        <span class="code-lang-badge" v-if="codeLang">{{ codeLang }}</span>
                        <span style="margin-left:auto;display:flex;gap:4px">
                            <el-button size="small" text type="primary" @click="copyCodeBlock" :title="t('blog_page.copy_code')">{{ t('blog_page.copy_code') }}</el-button>
                            <el-button size="small" text @click="exitCodeBlock">{{ t('blog_page.exit_code_block') }}</el-button>
                        </span>
                    </div>
                    <!-- 表格操作栏 -->
                    <div v-if="editor?.isActive('table')" class="table-toolbar">
                        <el-button-group size="small">
                            <el-button @click="addColumnBefore" :title="t('blog_page.col_insert_before')">◀ {{ t('blog_page.col_short') }}</el-button>
                            <el-button @click="addColumnAfter" :title="t('blog_page.col_insert_after')">{{ t('blog_page.col_short') }} ▶</el-button>
                            <el-button @click="addRowBefore" :title="t('blog_page.row_insert_before')">▲ {{ t('blog_page.row_short') }}</el-button>
                            <el-button @click="addRowAfter" :title="t('blog_page.row_insert_after')">{{ t('blog_page.row_short') }} ▼</el-button>
                        </el-button-group>
                        <el-divider direction="vertical" />
                        <el-button-group size="small">
                            <el-button @click="deleteColumn" :title="t('blog_page.delete_col')">✕ {{ t('blog_page.col_short') }}</el-button>
                            <el-button @click="deleteRow" :title="t('blog_page.delete_row')">✕ {{ t('blog_page.row_short') }}</el-button>
                            <el-button @click="deleteTable" :title="t('blog_page.delete_table')">✕ {{ t('blog_page.tb_insert_table') }}</el-button>
                        </el-button-group>
                        <el-divider direction="vertical" />
                        <el-button-group size="small">
                            <el-button @click="toggleHeaderCell" :title="t('blog_page.toggle_header')">{{ t('blog_page.header') }}</el-button>
                            <el-button @click="mergeOrSplit" :title="t('blog_page.merge_cells')">{{ t('blog_page.merge') }}</el-button>
                        </el-button-group>
                    </div>
                    <!-- 编辑器内容区 -->
                    <div v-if="editor && !showSource" class="blog-editor-content" @dragover="handleDragOver" @dragleave="handleDragLeave" @drop="handleDrop">
                        <editor-content :editor="editor" class="blog-prose-editor" @click="onEditorClick" @keydown="handleEditorKeydown" />
                        <!-- 链接气泡菜单 -->
                        <div v-if="showLinkBubble" class="link-bubble" :style="{ top: linkBubblePos.top + 'px', left: linkBubblePos.left + 'px' }">
                            <span class="link-bubble-url">{{ linkBubbleHref }}</span>
                            <el-button size="small" text @click="editLink(linkBubbleHref, linkBubbleText)">{{ t('actions.edit') }}</el-button>
                            <el-divider direction="vertical" />
                            <el-button size="small" text type="danger" @click="removeLink">{{ t('blog_page.remove') }}</el-button>
                        </div>
                        <div class="blog-editor-footer">
                            <span>{{ t('blog_page.editor_footer', { words: wordCount, cn: chineseChars, en: englishWords, para: paragraphCount, mins: readingTime }) }}</span>
                            <span style="display:flex;align-items:center;gap:6px">
                                <span v-if="wordGoal > 0" class="word-goal-progress">
                                    <el-progress :percentage="Math.min(100, Math.round(wordCount / wordGoal * 100))" :stroke-width="6" :width="80" />
                                </span>
                                <el-input-number v-model="wordGoal" :min="0" :max="100000" :step="100" size="small" style="width:80px" :placeholder="t('blog_page.word_goal_ph')" :title="t('blog_page.word_goal_title')" />
                                <span v-if="draftSaved" style="color:#67c23a">💾</span>
                            </span>
                        </div>
                    </div>
                    <!-- 拖拽上传提示 -->
                    <div v-if="dragOver" class="drag-overlay">
                        <div class="drag-hint">{{ t('blog_page.drag_upload_hint') }}</div>
                    </div>
                    <!-- HTML 源码编辑区 -->
                    <div v-else-if="showSource" class="blog-editor-source">
                        <el-input v-model="sourceHtml" type="textarea" :rows="16" :placeholder="t('blog_page.source_ph')" @change="applySource" />
                    </div>
                    <div v-else-if="!editor" class="blog-editor-loading" style="border:1px solid #dcdfe6;border-radius:4px;min-height:320px;display:flex;align-items:center;justify-content:center;color:#909399">
                        <el-icon class="is-loading"><Loading /></el-icon> {{ t('blog_page.loading_editor') }}
                    </div>
                </el-form-item>
                <el-form-item :label="t('blog_page.publish_settings')">
                    <el-checkbox v-model="form.is_published">{{ t('blog_page.publish_now') }}</el-checkbox>
                    <el-checkbox v-model="form.is_featured">{{ t('blog_page.mark_featured') }}</el-checkbox>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="previewBeforePublish">
                    {{ editingId ? t('blog_page.preview_and_save') : t('blog_page.preview_and_publish') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 发布预览对话框（支持桌面/手机预览） -->
        <el-dialog v-model="previewVisible" :title="t('blog_page.publish_preview') + (previewDevice === 'mobile' ? ' · ' + t('blog_page.device_mobile') : ' · ' + t('blog_page.device_desktop'))" :width="previewDevice === 'mobile' ? '420px' : '700px'" top="3vh" :close-on-click-modal="true" destroy-on-close>
            <!-- 设备切换栏 -->
            <div style="display:flex;gap:8px;margin-bottom:12px;justify-content:center">
                <el-radio-group v-model="previewDevice" size="small">
                    <el-radio-button value="desktop">{{ t('blog_page.device_desktop') }}</el-radio-button>
                    <el-radio-button value="mobile">{{ t('blog_page.device_mobile') }}</el-radio-button>
                </el-radio-group>
            </div>
            <!-- 预览内容 -->
            <div :style="previewDevice === 'mobile' ? 'max-height:60vh;overflow-y:auto;padding:16px 12px;background:#fff;border-radius:8px;border:1px solid #e4e7ed;width:100%;box-sizing:border-box' : 'max-height:65vh;overflow-y:auto;padding:0 4px'">
                <h1 style="font-size:22px;font-weight:600;margin-bottom:12px;color:#303133" v-html="form.title || t('blog_page.no_title')"></h1>
                <div class="preview-content" v-html="form.content || '<p style=color:#c0c4cc>' + t('blog_page.empty_content') + '</p>'"></div>
            </div>
            <template #footer>
                <el-button size="small" @click="previewVisible = false">{{ t('blog_page.back_to_edit') }}</el-button>
                <el-button size="small" type="primary" @click="confirmPublish" :loading="submitting">
                    {{ editingId ? t('blog_page.confirm_save') : t('blog_page.confirm_publish') }}
                </el-button>
            </template>
        </el-dialog>

        <!-- 分类管理对话框 -->
        <el-dialog v-model="showCategoryDialog" :title="t('blog_page.category_dialog_title')" width="550px">
            <div class="mb-4">
                <el-button type="primary" size="small" @click="showCategoryForm=true; catForm={name:'',slug:'',description:'',color:'',sort_order:0}" :icon="Plus">{{ t('blog_page.add_category') }}</el-button>
            </div>

            <!-- 添加/编辑分类表单 -->
            <el-form v-if="showCategoryForm" :model="catForm" label-width="80px" size="small" class="mb-4" :inline="true">
                <el-form-item :label="t('blog_page.name')">
                    <el-input v-model="catForm.name" :placeholder="t('blog_page.category_name_ph')" style="width:150px" />
                </el-form-item>
                <el-form-item :label="t('blog_page.slug')">
                    <el-input v-model="catForm.slug" :placeholder="t('blog_page.slug_ph')" style="width:120px" />
                </el-form-item>
                <el-form-item :label="t('blog_page.color')">
                    <el-color-picker v-model="catForm.color" />
                </el-form-item>
                <el-form-item :label="t('blog_page.sort_order')">
                    <el-input-number v-model="catForm.sort_order" :min="0" :max="999" style="width:100px" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="saveCategory">{{ t('actions.save') }}</el-button>
                    <el-button @click="showCategoryForm=false">{{ t('actions.cancel') }}</el-button>
                </el-form-item>
            </el-form>

            <el-table :data="categories" stripe size="small">
                <el-table-column prop="name" :label="t('blog_page.name')" width="120" />
                <el-table-column :label="t('blog_page.slug')" width="120">
                    <template #default="{ row }"><code>{{ row.slug }}</code></template>
                </el-table-column>
                <el-table-column :label="t('blog_page.color')" width="70">
                    <template #default="{ row }">
                        <el-tag v-if="row.color" :color="row.color" style="color:#fff;border:none">{{ row.color }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="posts_count" :label="t('blog_page.posts_count')" width="70" align="center" />
                <el-table-column prop="sort_order" :label="t('blog_page.sort_order')" width="60" align="center" />
                <el-table-column :label="t('blog_page.col_actions')" width="140">
                    <template #default="{ row }">
                        <el-button size="small" text type="primary" @click="editCategory(row)">{{ t('actions.edit') }}</el-button>
                        <el-popconfirm :title="t('blog_page.confirm_delete')" @confirm="removeCategory(row)">
                            <template #reference>
                                <el-button size="small" text type="danger" :disabled="row.posts_count > 0">{{ t('actions.delete') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>

        <!-- 批量切换分类对话框 -->
        <el-dialog v-model="showBatchCategoryDialog" :title="t('blog_page.batch_category_title')" width="400px">
            <el-form label-position="top">
                <el-form-item :label="t('blog_page.select_target_category')">
                    <el-select v-model="batchCategoryId" :placeholder="t('blog_page.select_category_ph')" filterable style="width:100%">
                        <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="showBatchCategoryDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button type="primary" @click="handleBatchCategory" :loading="batchLoading">{{ t('blog_page.confirm_switch') }}</el-button>
            </template>
        </el-dialog>

        <!-- 图片尺寸设置对话框（支持上传预览 + 网络图片 URL） -->
        <el-dialog v-model="showImageSizeDialog" :title="t('blog_page.image_dialog_title')" width="460px" :close-on-click-modal="false">
            <!-- 网络图片 URL 输入 -->
            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
                <span style="font-size:13px;color:#666;white-space:nowrap">{{ t('blog_page.network_image') }}</span>
                <el-input v-model="pendingMediaUrl" :placeholder="t('blog_page.image_url_paste_ph')" size="small" clearable @input="imageCustomWidth = 400" />
                <el-button size="small" @click="pickImageFile" :title="t('blog_page.upload_local')">{{ t('actions.upload') }}</el-button>
            </div>
            <el-divider style="margin:8px 0" />
            <!-- 图片预览 -->
            <div style="text-align:center;margin-bottom:12px;min-height:80px;display:flex;align-items:center;justify-content:center;background:#f5f7fa;border-radius:6px">
                <img v-if="pendingMediaUrl" :src="pendingMediaUrl" style="max-width:100%;max-height:180px;border-radius:6px;object-fit:contain" @error="onImagePreviewError" />
                <span v-else style="color:#c0c4cc;font-size:13px">{{ t('blog_page.no_preview') }}</span>
            </div>
            <!-- 尺寸预设 -->
            <div style="display:flex;gap:8px;justify-content:center;margin-bottom:12px">
                <el-button v-for="preset in imageSizePresets" :key="preset.value"
                    :type="imageCustomWidth === preset.value ? 'primary' : 'default'"
                    size="small" @click="imageCustomWidth = preset.value">
                    {{ preset.label }}{{ preset.value ? ` (${preset.value}px)` : '' }}
                </el-button>
            </div>
            <div style="display:flex;align-items:center;gap:8px;justify-content:center">
                <span style="font-size:13px;color:#666">{{ t('blog_page.custom_width') }}</span>
                <el-input-number v-model="imageCustomWidth" :min="50" :max="1200" :step="10" size="small" style="width:140px" />
                <span style="font-size:12px;color:#999">px</span>
            </div>
            <div style="font-size:12px;color:#909399;text-align:center;margin-top:8px">
                {{ t('blog_page.image_scale_hint') }}
            </div>
            <div style="display:flex;gap:8px;justify-content:center;margin-top:12px">
                <span style="font-size:13px;color:#666">{{ t('blog_page.align') }}</span>
                <el-radio-group v-model="imageCustomAlign" size="small">
                    <el-radio-button value="left">{{ t('blog_page.align_left') }}</el-radio-button>
                    <el-radio-button value="center">{{ t('blog_page.align_center') }}</el-radio-button>
                    <el-radio-button value="right">{{ t('blog_page.align_right') }}</el-radio-button>
                </el-radio-group>
            </div>
            <template #footer>
                <el-button size="small" @click="showImageSizeDialog = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" @click="confirmImageInsert" :disabled="!pendingMediaUrl">{{ t('blog_page.insert_image') }}</el-button>
            </template>
        </el-dialog>

        <!-- 文章模板对话框 -->
        <el-dialog v-model="showTemplateDialog" :title="t('blog_page.template_dialog_title')" width="600px">
            <div class="template-grid">
                <div class="template-card" @click="insertTemplate('product')">
                    <div class="template-icon">🏷️</div>
                    <div class="template-name">{{ t('blog_page.tpl_product') }}</div>
                    <div class="template-desc">{{ t('blog_page.tpl_product_desc') }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('announcement')">
                    <div class="template-icon">📢</div>
                    <div class="template-name">{{ t('blog_page.tpl_announcement') }}</div>
                    <div class="template-desc">{{ t('blog_page.tpl_announcement_desc') }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('changelog')">
                    <div class="template-icon">📋</div>
                    <div class="template-name">{{ t('blog_page.tpl_changelog') }}</div>
                    <div class="template-desc">{{ t('blog_page.tpl_changelog_desc') }}</div>
                </div>
                <div class="template-card" @click="insertTemplate('guide')">
                    <div class="template-icon">📖</div>
                    <div class="template-name">{{ t('blog_page.tpl_guide') }}</div>
                    <div class="template-desc">{{ t('blog_page.tpl_guide_desc') }}</div>
                </div>
            </div>
        </el-dialog>

        <!-- 目录导航对话框 -->
        <el-dialog v-model="showTocDialog" :title="t('blog_page.toc_dialog_title')" width="400px">
            <div v-if="tocItems.length === 0" style="padding:20px;text-align:center;color:#909399">
                {{ t('blog_page.toc_empty') }}
            </div>
            <div v-else class="toc-list">
                <div v-for="(item, idx) in tocItems" :key="idx" class="toc-item"
                    :style="{ paddingLeft: (item.level - 1) * 20 + 'px' }"
                    @click="scrollToHeading(idx)">
                    <span class="toc-level" :class="'toc-h' + item.level">H{{ item.level }}</span>
                    <span class="toc-text">{{ item.text }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- Emoji 选择器对话框 -->
        <el-dialog v-model="showEmojiDialog" :title="t('blog_page.emoji_dialog_title')" width="420px">
            <div class="emoji-grid">
                <span v-for="emoji in emojiList" :key="emoji" class="emoji-item" @click="insertEmoji(emoji)">{{ emoji }}</span>
            </div>
        </el-dialog>

        <!-- AI 结果对话框 -->
        <el-dialog v-model="showAiResult" :title="aiResultTitle" width="600px" :close-on-click-modal="true">
            <div v-loading="aiLoading">
                <div v-if="aiResult" class="ai-result-content" style="white-space:pre-wrap;font-size:14px;line-height:1.7;max-height:400px;overflow-y:auto">{{ aiResult }}</div>
                <div v-else-if="!aiLoading" style="text-align:center;padding:32px 0;color:#999">{{ t('blog_page.ai_click_to_start') }}</div>
            </div>
            <template #footer>
                <el-button v-if="aiResult && (aiAction === 'typo' || aiAction === 'polish')" size="small" type="primary" @click="applyAiFix">{{ t('blog_page.apply_to_editor') }}</el-button>
                <el-button v-if="aiResult && aiAction === 'summary'" size="small" @click="applyAiFix">{{ t('blog_page.fill_excerpt') }}</el-button>
                <el-button size="small" @click="showAiResult = false">{{ t('actions.close') }}</el-button>
            </template>
        </el-dialog>

        <!-- AI 助手对话对话框 -->
        <el-dialog v-model="showAiAssistant" :title="t('blog_page.ai_assistant_title')" width="480px" :close-on-click-modal="false">
            <div style="max-height:360px;overflow-y:auto;margin-bottom:8px">
                <div v-for="(msg, i) in aiChatMessages" :key="i" class="ai-chat-msg" :class="msg.role">
                    <div style="font-size:12px;font-weight:500;margin-bottom:2px;color:#909399">{{ msg.role === 'user' ? t('blog_page.role_user') : t('blog_page.role_ai') }}</div>
                    <div style="font-size:13px;white-space:pre-wrap">{{ msg.content }}</div>
                </div>
                <div v-if="aiChatLoading" style="text-align:center;padding:16px;color:#999">{{ t('blog_page.ai_thinking') }}</div>
            </div>
            <div style="display:flex;gap:6px">
                <el-input v-model="aiChatInput" :placeholder="t('blog_page.ai_question_ph')" size="small" style="flex:1" @keydown.enter="sendAiChat" />
                <el-button size="small" type="primary" :loading="aiChatLoading" @click="sendAiChat">{{ t('blog_page.send') }}</el-button>
            </div>
        </el-dialog>

        <!-- AI 创作对话框 -->
        <el-dialog v-model="showAiCreate" :title="t('blog_page.ai_create_title')" width="560px" :close-on-click-modal="false">
            <el-form label-position="top" size="small">
                <el-form-item :label="t('blog_page.create_topic')" required>
                    <el-input v-model="aiCreateTopic" :placeholder="t('blog_page.create_topic_ph')" :rows="2" type="textarea" />
                </el-form-item>
                <el-form-item :label="t('blog_page.article_style')">
                    <el-radio-group v-model="aiCreateStyle">
                        <el-radio value="general">{{ t('blog_page.style_general') }}</el-radio>
                        <el-radio value="professional">{{ t('blog_page.style_professional') }}</el-radio>
                        <el-radio value="popular">{{ t('blog_page.style_popular') }}</el-radio>
                        <el-radio value="news">{{ t('blog_page.style_news') }}</el-radio>
                        <el-radio value="story">{{ t('blog_page.style_story') }}</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('blog_page.word_length')">
                    <el-select v-model="aiCreateLength" style="width:140px">
                        <el-option :label="t('blog_page.length_short')" value="short" />
                        <el-option :label="t('blog_page.length_medium')" value="medium" />
                        <el-option :label="t('blog_page.length_long')" value="long" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="t('blog_page.extra_requirements')">
                    <el-input v-model="aiCreateExtra" :placeholder="t('blog_page.extra_requirements_ph')" :rows="2" type="textarea" />
                </el-form-item>
            </el-form>
            <div v-if="aiCreateResult" class="ai-create-preview" style="border:1px solid #e4e7ed;border-radius:6px;padding:12px;margin-bottom:8px;max-height:280px;overflow-y:auto;background:#fafafa">
                <div style="font-size:12px;color:#909399;margin-bottom:6px">{{ t('blog_page.generate_preview') }}</div>
                <div style="font-size:13px;line-height:1.6;white-space:pre-wrap">{{ aiCreateResult.substring(0, 500) }}{{ aiCreateResult.length > 500 ? '...' : '' }}</div>
            </div>
            <template #footer>
                <el-button size="small" @click="showAiCreate = false">{{ t('actions.cancel') }}</el-button>
                <el-button size="small" type="primary" :loading="aiCreateLoading" @click="doAiCreate">{{ aiCreateResult ? t('blog_page.regenerate') : t('blog_page.start_create') }}</el-button>
                <el-button v-if="aiCreateResult" size="small" type="success" @click="insertAiCreate">{{ t('blog_page.insert_to_editor') }}</el-button>
            </template>
        </el-dialog>

        <!-- 快捷键帮助对话框 -->
        <el-dialog v-model="showHelpDialog" :title="t('blog_page.shortcuts_dialog_title')" width="500px">
            <div class="shortcut-list">
                <div class="shortcut-item" v-for="s in shortcuts" :key="s.key">
                    <span class="shortcut-keys">
                        <kbd v-for="k in s.keys.split(' + ')" :key="k">{{ k }}</kbd>
                    </span>
                    <span class="shortcut-desc">{{ s.desc }}</span>
                </div>
            </div>
        </el-dialog>

        <!-- 文章统计对话框 -->
        <el-dialog v-model="showStatsDialog" :title="t('blog_page.stats_dialog_title')" width="420px">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.totalChars }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_total_chars') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.chineseChars }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_chinese_chars') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.englishWords }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_english_words') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.paragraphs }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_paragraphs') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.headings }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_headings') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.links }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_links') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.images }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_images') }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-num">{{ articleStats.codeBlocks }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_code_blocks') }}</div>
                </div>
                <div class="stat-item" style="grid-column:1/-1">
                    <div class="stat-num">{{ articleStats.readingTime }} {{ t('blog_page.minutes') }}</div>
                    <div class="stat-label">{{ t('blog_page.stat_reading_time') }}</div>
                </div>
            </div>
        </el-dialog>
    </div>
        </el-tab-pane>

        <!-- API Changelog Tab -->
        <el-tab-pane label="API Changelog" name="changelog">
            <div v-if="clLoaded" class="changelog-manager-page">
                <!-- 统计卡片 -->
                <el-row :gutter="20" class="stats-row">
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-value">{{ clStats.total_changelogs }}</div>
                            <div class="stat-label">{{ t(`${clP}.stats.total_changelogs`) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-value">{{ clStats.total_versions }}</div>
                            <div class="stat-label">{{ t(`${clP}.stats.total_versions`) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-value">{{ clStats.latest_version || '-' }}</div>
                            <div class="stat-label">{{ t(`${clP}.stats.latest_version`) }}</div>
                        </el-card>
                    </el-col>
                    <el-col :span="6">
                        <el-card shadow="hover" class="stat-card">
                            <div class="stat-value">{{ clStats.total_snapshots }}</div>
                            <div class="stat-label">{{ t(`${clP}.stats.total_snapshots`) }}</div>
                        </el-card>
                    </el-col>
                </el-row>

                <el-tabs v-model="clActiveTab" type="border-card">
                    <!-- Tab1: Changelog 列表 -->
                    <el-tab-pane :label="t(`${clP}.tabs.list`)" name="list">
                        <div class="cl-toolbar">
                            <el-row :gutter="12">
                                <el-col :span="5">
                                    <el-input v-model="clFilters.search" :placeholder="t(`${clP}.filters.search_ph`)" clearable @clear="clLoadList" @keyup.enter="clLoadList" />
                                </el-col>
                                <el-col :span="3">
                                    <el-input v-model="clFilters.version" :placeholder="t(`${clP}.filters.version_ph`)" clearable @clear="clLoadList" @keyup.enter="clLoadList" />
                                </el-col>
                                <el-col :span="3">
                                    <el-select v-model="clFilters.type" :placeholder="t(`${clP}.filters.type_ph`)" clearable @change="clLoadList" style="width:100%">
                                        <el-option v-for="opt in clTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                                    </el-select>
                                </el-col>
                                <el-col :span="5">
                                    <el-date-picker
                                        v-model="clDateRange"
                                        type="daterange"
                                        :range-separator="t(`${clP}.filters.date_range_sep`)"
                                        :start-placeholder="t(`${clP}.filters.start_date_ph`)"
                                        :end-placeholder="t(`${clP}.filters.end_date_ph`)"
                                        @change="clOnDateRangeChange"
                                        style="width:100%"
                                    />
                                </el-col>
                                <el-col :span="8" style="text-align:right">
                                    <el-button type="primary" @click="clShowCreateDialog">
                                        <el-icon><Plus /></el-icon> {{ t(`${clP}.create_changelog`) }}
                                    </el-button>
                                    <el-button @click="clLoadList">{{ t(`${clP}.refresh`) }}</el-button>
                                </el-col>
                            </el-row>
                        </div>

                        <el-table :data="clList" v-loading="clLoading" border stripe style="width:100%;margin-top:12px">
                            <el-table-column prop="version" :label="t(`${clP}.columns.version`)" width="100">
                                <template #default="{ row }">
                                    <el-tag type="primary" size="small">v{{ row.version }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="title" :label="t(`${clP}.columns.title`)" min-width="200" show-overflow-tooltip />
                            <el-table-column prop="type" :label="t(`${clP}.columns.type`)" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="clTypeTag(row.type)" size="small">{{ clTypeLabel(row.type) }}</el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column prop="release_date" :label="t(`${clP}.columns.release_date`)" width="110">
                                <template #default="{ row }">{{ row.release_date }}</template>
                            </el-table-column>
                            <el-table-column prop="source" :label="t(`${clP}.columns.source`)" width="90">
                                <template #default="{ row }">
                                    <el-tag :type="row.source === 'auto_detect' ? 'success' : 'info'" size="small">
                                        {{ clSourceLabel(row.source) }}
                                    </el-tag>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${clP}.columns.migration_guide`)" width="80" align="center">
                                <template #default="{ row }">
                                    <el-tag v-if="row.migration_guide" type="warning" size="small">{{ t(`${clP}.has_guide`) }}</el-tag>
                                    <span v-else class="cl-no-guide">-</span>
                                </template>
                            </el-table-column>
                            <el-table-column :label="t(`${clP}.columns.actions`)" width="200" fixed="right">
                                <template #default="{ row }">
                                    <el-button size="small" @click="clShowDetail(row)">{{ t(`${clP}.detail_btn`) }}</el-button>
                                    <el-button size="small" @click="clShowEditDialog(row)">{{ t('actions.edit') }}</el-button>
                                    <el-popconfirm :title="t('messages.confirm_delete')" @confirm="clHandleDelete(row.id)">
                                        <template #reference>
                                            <el-button size="small" type="danger">{{ t('actions.delete') }}</el-button>
                                        </template>
                                    </el-popconfirm>
                                </template>
                            </el-table-column>
                        </el-table>

                        <div class="cl-pagination-row">
                            <el-pagination
                                v-model:current-page="clCurrentPage"
                                v-model:page-size="clPageSize"
                                :total="clTotal"
                                :page-sizes="[10, 20, 50]"
                                layout="total, sizes, prev, pager, next"
                                @size-change="clLoadList"
                                @current-change="clLoadList"
                            />
                        </div>
                    </el-tab-pane>

                    <!-- Tab2: 自动生成 -->
                    <el-tab-pane :label="t(`${clP}.tabs.auto`)" name="auto">
                        <el-card>
                            <template #header>
                                <div class="cl-auto-header">
                                    <span>{{ t(`${clP}.auto.title`) }}</span>
                                    <el-button type="primary" :loading="clAutoLoading" @click="clHandleAutoGenerate">
                                        <el-icon><Refresh /></el-icon> {{ t(`${clP}.auto.detect_now`) }}
                                    </el-button>
                                </div>
                            </template>

                            <el-form :model="clAutoForm" label-width="120px">
                                <el-form-item :label="t(`${clP}.api_version`)">
                                    <el-select v-model="clAutoForm.api_version_id" :placeholder="t(`${clP}.select_api_version`)" style="width:300px">
                                        <el-option v-for="v in clApiVersions" :key="v.id" :label="v.version + ' - ' + (v.name || '')" :value="v.id" />
                                    </el-select>
                                </el-form-item>
                            </el-form>

                            <el-divider />

                            <div class="cl-history-section">
                                <h4>{{ t(`${clP}.auto.detect_history`) }}</h4>
                                <el-table :data="clDetectHistory" border stripe v-loading="clHistoryLoading" style="width:100%">
                                    <el-table-column prop="version" :label="t(`${clP}.columns.snapshot_version`)" width="180" />
                                    <el-table-column prop="snapshot_at" :label="t(`${clP}.columns.created_at`)" width="180" />
                                    <el-table-column prop="endpoint_count" :label="t(`${clP}.columns.endpoint_count`)" width="100" />
                                </el-table>
                            </div>
                        </el-card>

                        <el-card style="margin-top:16px">
                            <template #header>
                                <span>{{ t(`${clP}.snapshot.title`) }}</span>
                            </template>
                            <el-form :model="clSnapshotForm" label-width="120px">
                                <el-form-item :label="t(`${clP}.api_version`)">
                                    <el-select v-model="clSnapshotForm.api_version_id" :placeholder="t(`${clP}.select_api_version`)" style="width:300px">
                                        <el-option v-for="v in clApiVersions" :key="v.id" :label="v.version + ' - ' + (v.name || '')" :value="v.id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item :label="t(`${clP}.snapshot.label`)">
                                    <el-input v-model="clSnapshotForm.version_label" :placeholder="t(`${clP}.snapshot.label_ph`)" style="width:300px" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" :loading="clSnapshotLoading" @click="clHandleCreateSnapshot">{{ t(`${clP}.snapshot.create_btn`) }}</el-button>
                                </el-form-item>
                            </el-form>
                        </el-card>
                    </el-tab-pane>

                    <!-- Tab3: 迁移指南 -->
                    <el-tab-pane :label="t(`${clP}.tabs.migration`)" name="migration">
                        <el-card>
                            <template #header>
                                <span>{{ t(`${clP}.migration.title`) }}</span>
                            </template>

                            <el-form :model="clMigrationForm" label-width="120px">
                                <el-form-item :label="t(`${clP}.migration.from_version`)">
                                    <el-input v-model="clMigrationForm.from_version" :placeholder="t(`${clP}.migration.from_version_ph`)" style="width:200px" />
                                </el-form-item>
                                <el-form-item :label="t(`${clP}.migration.to_version`)">
                                    <el-input v-model="clMigrationForm.to_version" :placeholder="t(`${clP}.migration.to_version_ph`)" style="width:200px" />
                                </el-form-item>
                                <el-form-item>
                                    <el-button type="primary" :loading="clMigrationLoading" @click="clHandleGenerateMigration">
                                        {{ t(`${clP}.migration.generate_btn`) }}
                                    </el-button>
                                </el-form-item>
                            </el-form>

                            <el-divider v-if="clMigrationResult" />

                            <div v-if="clMigrationResult" class="cl-migration-result">
                                <el-alert
                                    :title="t(`${clP}.migration.version_range_fmt`, { from: clMigrationResult.from_version, to: clMigrationResult.to_version })"
                                    type="info"
                                    :description="t(`${clP}.migration.summary_fmt`, { count: clMigrationResult.changelog_count, breaking: clMigrationResult.breaking_changes.length })"
                                    show-icon
                                    style="margin-bottom:16px"
                                />

                                <h4>{{ t(`${clP}.migration.breaking_changes`) }}</h4>
                                <el-table :data="clMigrationResult.breaking_changes" border stripe style="width:100%;margin-bottom:16px">
                                    <el-table-column prop="type" :label="t(`${clP}.columns.type`)" width="100">
                                        <template #default="{ row }">
                                            <el-tag :type="row.type === 'removed' ? 'danger' : 'warning'" size="small">
                                                {{ clBreakingChangeLabel(row.type) }}
                                            </el-tag>
                                        </template>
                                    </el-table-column>
                                    <el-table-column prop="endpoint" :label="t(`${clP}.columns.endpoint`)" min-width="250" />
                                    <el-table-column prop="version" :label="t(`${clP}.columns.version`)" width="80" />
                                    <el-table-column prop="summary" :label="t(`${clP}.columns.summary`)" min-width="200" show-overflow-tooltip />
                                </el-table>

                                <h4>{{ t(`${clP}.migration.steps`) }}</h4>
                                <el-timeline>
                                    <el-timeline-item
                                        v-for="(step, idx) in clMigrationResult.migration_steps"
                                        :key="idx"
                                        :type="step.includes('✅') ? 'success' : 'primary'"
                                    >
                                        {{ step }}
                                    </el-timeline-item>
                                </el-timeline>

                                <el-card v-if="clMigrationResult.recommended_upgrade_path" shadow="never" style="margin-top:12px">
                                    <template #header><span>{{ t(`${clP}.migration.upgrade_path`) }}</span></template>
                                    <p>{{ clMigrationResult.recommended_upgrade_path }}</p>
                                </el-card>
                            </div>
                        </el-card>
                    </el-tab-pane>

                    <!-- Tab4: 版本视图 -->
                    <el-tab-pane :label="t(`${clP}.tabs.versions`)" name="versions">
                        <el-timeline v-if="clVersionData.length > 0">
                            <el-timeline-item
                                v-for="ver in clVersionData"
                                :key="ver.version"
                                :timestamp="ver.latest_release"
                                placement="top"
                            >
                                <el-card shadow="hover">
                                    <h3>v{{ ver.version }}</h3>
                                    <p class="cl-version-count">{{ t(`${clP}.version.changelog_count`, { count: ver.total }) }}</p>
                                    <ul class="cl-changelog-summary-list">
                                        <li v-for="log in ver.changelogs.slice(0, 5)" :key="log.id">
                                            <el-tag :type="clTypeTag(log.type)" size="small">{{ clTypeLabel(log.type) }}</el-tag>
                                            {{ log.title }}
                                        </li>
                                    </ul>
                                    <el-button v-if="ver.changelogs.length > 5" size="small" text type="primary" @click="clShowVersionDetail(ver)">
                                        {{ t(`${clP}.version.view_all`, { count: ver.changelogs.length }) }}
                                    </el-button>
                                </el-card>
                            </el-timeline-item>
                        </el-timeline>
                        <el-empty v-else :description="t(`${clP}.version.empty`)" />
                    </el-tab-pane>
                </el-tabs>

                <!-- 创建/编辑 Dialog -->
                <el-dialog v-model="clDialogVisible" :title="clDialogTitle" width="700px">
                    <el-form :model="clForm" :rules="clFormRules" ref="clFormRef" label-width="110px">
                        <el-form-item :label="t(`${clP}.form.version`)" prop="version">
                            <el-input v-model="clForm.version" :placeholder="t(`${clP}.form.version_ph`)" />
                        </el-form-item>
                        <el-form-item :label="t(`${clP}.form.title`)" prop="title">
                            <el-input v-model="clForm.title" :placeholder="t(`${clP}.form.title_ph`)" />
                        </el-form-item>
                        <el-form-item :label="t(`${clP}.form.type`)" prop="type">
                            <el-select v-model="clForm.type" style="width:200px">
                                <el-option v-for="opt in clTypeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                            </el-select>
                        </el-form-item>
                        <el-form-item :label="t(`${clP}.form.release_date`)">
                            <el-date-picker v-model="clForm.release_date" type="date" style="width:200px" />
                        </el-form-item>
                        <el-form-item :label="t(`${clP}.form.description`)" prop="description">
                            <el-input v-model="clForm.description" type="textarea" :rows="6" :placeholder="t(`${clP}.form.description_ph`)" />
                        </el-form-item>
                        <el-form-item :label="t(`${clP}.form.migration_guide`)">
                            <el-input v-model="clForm.migration_guide" type="textarea" :rows="4" :placeholder="t(`${clP}.form.migration_guide_ph`)" />
                        </el-form-item>
                    </el-form>
                    <template #footer>
                        <el-button @click="clDialogVisible = false">{{ t('actions.cancel') }}</el-button>
                        <el-button type="primary" :loading="clSaving" @click="clHandleSave">{{ t('actions.save') }}</el-button>
                    </template>
                </el-dialog>

                <!-- 详情 Dialog -->
                <el-dialog v-model="clDetailVisible" :title="t(`${clP}.dialog.detail_title`)" width="700px">
                    <template v-if="clDetailData">
                        <el-descriptions :column="2" border>
                            <el-descriptions-item :label="t(`${clP}.columns.version`)" :span="1">
                                <el-tag type="primary">v{{ clDetailData.version }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t(`${clP}.columns.type`)" :span="1">
                                <el-tag :type="clTypeTag(clDetailData.type)" size="small">{{ clTypeLabel(clDetailData.type) }}</el-tag>
                            </el-descriptions-item>
                            <el-descriptions-item :label="t(`${clP}.columns.release_date`)" :span="1">{{ clDetailData.release_date }}</el-descriptions-item>
                            <el-descriptions-item :label="t(`${clP}.columns.source`)" :span="1">
                                {{ clSourceLabel(clDetailData.source, true) }}
                            </el-descriptions-item>
                            <el-descriptions-item :label="t(`${clP}.columns.title`)" :span="2">{{ clDetailData.title }}</el-descriptions-item>
                        </el-descriptions>

                        <el-divider />
                        <h4>{{ t(`${clP}.form.section_description`) }}</h4>
                        <div class="cl-markdown-content" v-html="clRenderedDescription"></div>

                        <div v-if="clDetailData.migration_guide">
                            <el-divider />
                            <h4>{{ t(`${clP}.form.section_migration_guide`) }}</h4>
                            <div class="cl-markdown-content" v-html="clRenderedMigrationGuide"></div>
                        </div>
                    </template>
                </el-dialog>
            </div>
            <div v-else style="text-align:center;padding:60px;color:#909399">
                <el-icon class="is-loading" style="font-size:24px"><Loading /></el-icon>
                <p style="margin-top:12px">{{ t('blog_page.loading_editor') }}</p>
            </div>
        </el-tab-pane>
    </el-tabs>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, FolderOpened, Download, Upload, Delete, Loading, ArrowDown, Refresh } from '@element-plus/icons-vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import ImageExt from '@tiptap/extension-image'
import LinkExt from '@tiptap/extension-link'
import Placeholder from '@tiptap/extension-placeholder'
import { CodeBlockLowlight } from '@tiptap/extension-code-block-lowlight'
import { common, createLowlight } from 'lowlight'
import { Table } from '@tiptap/extension-table'
import { TableRow } from '@tiptap/extension-table-row'
import { TableCell } from '@tiptap/extension-table-cell'
import { TableHeader } from '@tiptap/extension-table-header'
import { TextAlign } from '@tiptap/extension-text-align'
import { Color } from '@tiptap/extension-color'
import { TextStyle } from '@tiptap/extension-text-style'
import { Highlight } from '@tiptap/extension-highlight'
import apiClient from '@/api/client'

const lowlight = createLowlight(common)
import {
    getBlogList, getBlogStats, getBlogDetail,
    createBlog, updateBlog, deleteBlog,
    togglePublish, toggleFeatured,
    getSubscriptionStats, getSubscriptionList,
    getCategories, createCategory, updateCategory, deleteCategory,
    batchDeletePosts, batchPublishPosts, batchCategoryPosts, exportBlogCsv,
} from '@/api/blog'
import changelogApi from '@/api/changelog'
import { getApiVersions } from '@/api/apiDocs'

const { t } = useI18n()

const blogMainTab = ref('blog')
const clLoaded = ref(false)

const baseUrl = computed(() => window.location.origin)
const loading = ref(false)
const submitting = ref(false)
const stats = ref({ total: 0, published: 0, drafts: 0, by_type: { blog: 0, changelog: 0, release_note: 0 } })
const subStats = ref({ total: 0, verified: 0, unverified: 0, unsubscribed: 0, by_type: {} })
const list = ref([])
const currentPage = ref(1)
const perPage = ref(15)
const total = ref(0)
const dialogVisible = ref(false)
const editingId = ref(null)
const formRef = ref(null)


const typeFilterOptions = computed(() => [
    { label: t('blog_page.type.blog'), value: 'blog' },
    { label: t('blog_page.type.changelog'), value: 'changelog' },
    { label: t('blog_page.type.release_note'), value: 'release_note' },
])
const statusFilterOptions = computed(() => [
    { label: t('blog_page.status.published'), value: 'published' },
    { label: t('blog_page.status.draft'), value: 'draft' },
])
const formTypeOptions = computed(() => [
    { label: t('blog_page.type.blog_article'), value: 'blog' },
    { label: t('blog_page.type.changelog'), value: 'changelog' },
    { label: t('blog_page.type.release_note'), value: 'release_note' },
])
const aiLengthOptions = computed(() => [
    { label: t('blog_page.length_short'), value: 'short' },
    { label: t('blog_page.length_medium'), value: 'medium' },
    { label: t('blog_page.length_long'), value: 'long' },
])

const filters = ref({ search: '', type: '', status: '', category_id: '' })
const form = ref({ title: '', type: 'blog', category_id: null, content: '', slug: '', excerpt: '', featured_image: '', author: '', tags: [], version: '', is_published: false, is_featured: false })
const rules = computed(() => ({
    title: [{ required: true, message: t('blog_page.rules.title_required'), trigger: 'blur' }],
    type: [{ required: true, message: t('blog_page.rules.type_required'), trigger: 'change' }],
    content: [{ required: true, message: t('blog_page.rules.content_required'), trigger: 'blur' }],
}))

// ─── Tiptap 编辑器 ───
const showSource = ref(false)
const sourceHtml = ref('')
const showColorPicker = ref(false)
const showHighlightPicker = ref(false)
const previewDevice = ref('desktop') // 'desktop' | 'mobile'
const previewVisible = ref(false)
const showLinkDialog = ref(false)
const linkUrl = ref('')
const linkText = ref('')
const linkEditMode = ref(false) // false=insert, true=edit

// ─── 链接气泡菜单 ───
const showLinkBubble = ref(false)
const linkBubblePos = ref({ top: 0, left: 0 })
const linkBubbleHref = ref('')
const linkBubbleText = ref('')

// ─── 图片尺寸设置 ───
const showImageSizeDialog = ref(false)
const pendingMediaUrl = ref('')
const imageCustomWidth = ref(400)
const imageCustomAlign = ref('left')
const imageSizePresets = computed(() => [
    { label: t('blog_page.size_small'), value: 250 },
    { label: t('blog_page.size_medium'), value: 400 },
    { label: t('blog_page.size_large'), value: 600 },
    { label: t('blog_page.size_original'), value: 0 },
])

// ─── 搜索替换 ───
const showSearch = ref(false)
const searchQuery = ref('')
const searchReplace = ref('')
const searchMatches = ref(0)
const currentMatch = ref(-1)

// ─── 文章模板 ───
const showTemplateDialog = ref(false)
const templates = computed(() => ({
    product: {
        title: t('blog_page.templates.product_title'),
        html: '<h2>Product name</h2><p style="font-size:15px;color:#666">One-line product summary highlighting key benefits.</p><div style="display:flex;gap:12px;margin:16px 0"><div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center"><div style="font-size:28px;font-weight:700;color:#f56c6c">Feature one</div><div style="font-size:13px;color:#666;margin-top:4px">Description</div></div><div style="flex:1;background:#f5f7fa;border-radius:8px;padding:16px;text-align:center"><div style="font-size:28px;font-weight:700;color:#f56c6c">Feature two</div><div style="font-size:13px;color:#666;margin-top:4px">Description</div></div></div><p>Detailed product overview, use cases, and customer stories.</p>'
    },
    announcement: {
        title: t('blog_page.templates.announcement_title'),
        html: '<h2>v2.0.0 release</h2><div style="background:#f0f7ff;border:1px solid #0f172a;border-radius:8px;padding:16px;margin:16px 0"><div style="display:flex;gap:20px;flex-wrap:wrap"><div><strong>Release date:</strong> TBD</div><div><strong>Version:</strong> v2.0.0</div></div></div><h3>New features</h3><ul><li>Feature one: description</li><li>Feature two: description</li></ul><h3>Improvements</h3><ul><li>Improvement one</li><li>Improvement two</li></ul><h3>Bug fixes</h3><ul><li>Fixed...</li></ul>'
    },
    changelog: {
        title: t('blog_page.templates.changelog_title'),
        html: '<h2>Changelog</h2><p>This release includes:</p><h3>Added</h3><ul><li>New feature...</li></ul><h3>Changed</h3><ul><li>Change...</li></ul><h3>Fixed</h3><ul><li>Fix...</li></ul><h3>Improved</h3><ul><li>Performance...</li></ul>'
    },
    guide: {
        title: t('blog_page.templates.guide_title'),
        html: '<h2>Guide: feature name</h2><p>This guide helps you get started quickly.</p><h3>Step 1: Prepare</h3><ul><li>Sign in to your account</li><li>Check version requirements</li></ul><h3>Step 2: Steps</h3><ol><li>Open the feature page</li><li>Fill in required fields</li><li>Confirm and submit</li></ol><div style="background:#f0f9eb;border:1px solid #67c23a;border-radius:8px;padding:12px;margin:16px 0"><strong>Tip:</strong> Contact support if you need help.</div>'
    }
}))

// ─── Emoji ───
const showEmojiDialog = ref(false)
const emojiList = ['😀','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','😉','😌','😍','🥰','😘','😗','😋','😛','😜','🤪','😝','🤑','👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','💪','✌️','🤟','🤘','👌','❤️','🧡','💛','💚','💙','💜','🖤','🤍','💔','💖','💗','💫','⭐','🌟','✨','🔥','💯','🎉','🎊','🎈','🎁','🎀','🏆','🥇','🥈','🥉','🏅','💎','🔔','📣','💬','🗨️','💡','😎','🤩','🥳','🤗','🤔','🤭','🤫','🤨','😐','😑','😶','😏','😒','🙄','😬','🤥','😢','😭','😤','😠','😡','🤬','😱','😨','😰','😥','😓','🤒','🤕','🥺','😳','🤯']

// ─── 目录导航 ───
const showTocDialog = ref(false)
const tocItems = computed(() => {
    const html = editor.value?.getHTML() || form.value.content || ''
    const regex = /<h([1-3])(?:\s[^>]*)?>(.*?)<\/h[1-3]>/gi
    const items = []
    let match
    while ((match = regex.exec(html)) !== null) {
        const level = parseInt(match[1])
        const text = match[2].replace(/<[^>]*>/g, '')
        items.push({ level, text })
    }
    return items
})
function scrollToHeading(idx) {
    const item = tocItems.value[idx]
    if (!item || !editor.value) return
    const docText = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const pos = docText.indexOf(item.text)
    if (pos >= 0) {
        editor.value.chain().focus().setTextSelection({ from: pos, to: pos + item.text.length }).run()
    }
    showTocDialog.value = false
}

// ─── 表格操作 ───
function insertTable() {
    if (!editor.value) return
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()
}
function addColumnBefore() { editor.value?.chain().focus().addColumnBefore().run() }
function addColumnAfter() { editor.value?.chain().focus().addColumnAfter().run() }
function addRowBefore() { editor.value?.chain().focus().addRowBefore().run() }
function addRowAfter() { editor.value?.chain().focus().addRowAfter().run() }
function deleteColumn() { editor.value?.chain().focus().deleteColumn().run() }
function deleteRow() { editor.value?.chain().focus().deleteRow().run() }
function deleteTable() { editor.value?.chain().focus().deleteTable().run() }
function toggleHeaderCell() { editor.value?.chain().focus().toggleHeaderCell().run() }
function mergeOrSplit() {
    if (editor.value?.isActive('table')) {
        editor.value.chain().focus().mergeOrSplit().run()
    }
}

// ─── 代码块语言 ───
const codeLang = ref('')
const codeLanguages = ['javascript','typescript','php','python','html','css','sql','bash','json','xml','yaml','markdown','go','rust','java','csharp','ruby']

function changeCodeLang(lang) {
    if (!editor.value) return
    editor.value.chain().focus().updateAttributes('codeBlock', { language: lang }).run()
}

function exitCodeBlock() {
    if (!editor.value) return
    editor.value.chain().focus().toggleCodeBlock().run()
}

// ─── 代码块复制（互物号风格：智能定位当前代码块 → 一键复制） ───
function copyCodeBlock() {
    if (!editor.value) return
    // 智能定位：从光标所在位置向上查找代码块节点
    const pos = editor.value.state.selection.$from
    for (let i = pos.depth; i > 0; i--) {
        const node = pos.node(i)
        if (node.type.name === 'codeBlock') {
            navigator.clipboard.writeText(node.textContent).then(() => {
                ElMessage.success(t('blog_page.messages.code_copied'))
            }).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
            return
        }
    }
    // 兜底：如果选区不在代码块内，尝试获取当前选中文本
    const selectedText = editor.value.state.doc.textBetween(
        editor.value.state.selection.from,
        editor.value.state.selection.to
    )
    if (selectedText) {
        navigator.clipboard.writeText(selectedText).then(() => {
            ElMessage.success(t('blog_page.messages.code_copied'))
        }).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
    } else {
        ElMessage.warning(t('blog_page.messages.code_block_not_found'))
    }
}

// ─── AI 工具 ───
const showAiResult = ref(false)
const showAiAssistant = ref(false)
const showAiCreate = ref(false)
const aiResultTitle = ref('')
const aiResult = ref('')
const aiLoading = ref(false)
const aiAction = ref('')
const aiChatMessages = ref([])
const aiChatInput = ref('')
const aiChatLoading = ref(false)
const aiCreateTopic = ref('')
const aiCreateStyle = ref('general')
const aiCreateLength = ref('medium')
const aiCreateExtra = ref('')
const aiCreateResult = ref('')
const aiCreateLoading = ref(false)

// ─── 快捷键帮助 ───
const showHelpDialog = ref(false)
const draftSaved = ref(false)
const isFullscreen = ref(false)
const showStatsDialog = ref(false)
const wordGoal = ref(0)
const showSeoPreview = ref([])
let draftSaveTimer = null

// SEO 预览
const seoPreviewUrl = computed(() => {
    const base = window.location.origin
    const slug = form.value.slug || '{slug}'
    return base + '/blog/' + slug
})
const seoPreviewTitle = computed(() => {
    const t = form.value.title || ''
    return t.length > 60 ? t.substring(0, 57) + '...' : t
})
const seoPreviewDesc = computed(() => {
    const d = form.value.excerpt || ''
    return d.length > 160 ? d.substring(0, 157) + '...' : d
})
function updateSeoPreview() {
    // 触发 computed 更新
}

function copyContent() {
    const text = getArticleText() || getArticleHtml()
    navigator.clipboard.writeText(text).then(() => ElMessage.success(t('blog_page.messages.content_copied'))).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
}

function handleCopy(cmd) {
    if (cmd === 'html') {
        const html = getArticleHtml()
        navigator.clipboard.writeText(html).then(() => ElMessage.success(t('blog_page.messages.html_copied'))).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
    } else if (cmd === 'text') {
        const text = getArticleText()
        navigator.clipboard.writeText(text).then(() => ElMessage.success(t('blog_page.messages.text_copied'))).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
    } else if (cmd === 'markdown') {
        copyAsMarkdown()
    }
}

function copyAsMarkdown() {
    if (!editor.value) return
    const html = editor.value.getHTML()
    // 简单 HTML 转 Markdown
    let md = html
        .replace(/<h1[^>]*>(.*?)<\/h1>/gi, '# $1\n\n')
        .replace(/<h2[^>]*>(.*?)<\/h2>/gi, '## $1\n\n')
        .replace(/<h3[^>]*>(.*?)<\/h3>/gi, '### $1\n\n')
        .replace(/<strong>(.*?)<\/strong>/gi, '**$1**')
        .replace(/<b>(.*?)<\/b>/gi, '**$1**')
        .replace(/<em>(.*?)<\/em>/gi, '*$1*')
        .replace(/<i>(.*?)<\/i>/gi, '*$1*')
        .replace(/<code>(.*?)<\/code>/gi, '`$1`')
        .replace(/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/gi, '[$2]($1)')
        .replace(/<img[^>]*src="([^"]*)"[^>]*>/gi, '![image]($1)')
        .replace(/<li>(.*?)<\/li>/gi, '- $1\n')
        .replace(/<\/ul>/gi, '\n')
        .replace(/<\/ol>/gi, '\n')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<p[^>]*>(.*?)<\/p>/gi, '$1\n\n')
        .replace(/<blockquote[^>]*>(.*?)<\/blockquote>/gi, '> $1\n\n')
        .replace(/<pre[^>]*>(.*?)<\/pre>/gis, '```\n$1\n```\n\n')
        .replace(/<[^>]*>/g, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim()
    navigator.clipboard.writeText(md).then(() => ElMessage.success(t('blog_page.messages.markdown_copied'))).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
}
const shortcuts = computed(() => [
    { keys: 'Ctrl + B', desc: t('blog_page.shortcuts.bold') },
    { keys: 'Ctrl + I', desc: t('blog_page.shortcuts.italic') },
    { keys: 'Ctrl + U', desc: t('blog_page.shortcuts.underline') },
    { keys: 'Ctrl + Z', desc: t('blog_page.shortcuts.undo') },
    { keys: 'Ctrl + Shift + Z', desc: t('blog_page.shortcuts.redo') },
    { keys: 'Ctrl + K', desc: t('blog_page.shortcuts.insert_link') },
    { keys: 'Ctrl + S', desc: t('blog_page.shortcuts.save_publish') },
    { keys: 'Ctrl + Shift + P', desc: t('blog_page.shortcuts.preview') },
    { keys: 'Ctrl + F', desc: t('blog_page.shortcuts.search_replace') },
    { keys: 'Enter', desc: t('blog_page.shortcuts.enter_newline') },
    { keys: 'Tab', desc: t('blog_page.shortcuts.tab_indent') },
    { keys: 'Shift + Tab', desc: t('blog_page.shortcuts.shift_tab_outdent') },
])

const editor = useEditor({
    content: '',
    extensions: [
        StarterKit.configure({
            codeBlock: false,
            history: {
                depth: 100,
            },
        }),
        CodeBlockLowlight.configure({ lowlight }),
        Table.configure({
            resizable: true,
        }),
        TableRow,
        TableCell,
        TableHeader,
        Underline,
        ImageExt.configure({
            inline: true,
        }),
        LinkExt.configure({
            openOnClick: false,
            HTMLAttributes: {
                rel: 'noopener noreferrer',
                target: '_blank',
            },
        }),
        Placeholder.configure({
            placeholder: () => t('blog_page.editor_placeholder'),
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        TextStyle,
        Color,
        Highlight.configure({ multicolor: true }),
    ],
    editorProps: {
        transformPastedHTML(html) {
            // 移除 Word 等外部编辑器带来的垃圾样式
            return html
                .replace(/<meta[^>]*>/gi, '')
                .replace(/<style[^>]*>[^<]*<\/style>/gi, '')
                .replace(/<!--[^>]*-->/g, '')
                .replace(/<o:[^>]*>[^<]*<\/o:[^>]*>/gi, '')
                .replace(/class="[^"]*"/gi, '')
                // 不再清除所有 style，保留颜色/对齐等有效样式
                .replace(/lang="[^"]*"/gi, '')
                .replace(/<span[^>]*>/gi, '<span>')
                .replace(/<\/span>/g, '')
        },
    },
    onUpdate: ({ editor }) => {
        form.value.content = editor.getHTML()
    },
})

const wordCount = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return text.replace(/\s/g, '').length
})

const chineseChars = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return (text.match(/[\u4e00-\u9fff]/g) || []).length
})

const englishWords = computed(() => {
    if (!editor.value) return 0
    const text = editor.value.getText()
    return text.replace(/[\u4e00-\u9fff]/g, ' ').trim().split(/\s+/).filter(Boolean).length
})

const paragraphCount = computed(() => {
    if (!editor.value) return 0
    const html = editor.value.getHTML()
    return (html.match(/<p[^>]*>/g) || []).length
})

const readingTime = computed(() => {
    const wc = wordCount.value
    if (wc === 0) return 0
    return Math.max(1, Math.ceil(wc / 400))
})

function toggleSource() {
    if (!showSource.value) {
        // 切换到源码模式：将编辑器内容同步到 sourceHtml
        sourceHtml.value = editor.value?.getHTML() || form.value.content || ''
    } else {
        // 切回可视化模式：应用源码
        applySource()
    }
    showSource.value = !showSource.value
}

function applySource() {
    if (editor.value && sourceHtml.value !== undefined) {
        editor.value.commands.setContent(sourceHtml.value || '')
    }
}

// ─── 文件上传 ───
async function uploadFile(file) {
    const formData = new FormData()
    formData.append('files[]', file)
    try {
        const { data: res } = await apiClient.post('/files/upload/simple', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
        return res?.data?.files?.[0]?.url || null
    } catch {
        ElMessage.error(t('blog_page.messages.upload_failed'))
        return null
    }
}

// ─── 封面上传 ───
function uploadCoverImage() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url) {
            form.value.featured_image = url
            ElMessage.success(t('blog_page.messages.cover_uploaded'))
        }
    }
    input.click()
}

// ─── 图片上传（如互物号：打开对话框 → 上传或粘贴 URL → 设置尺寸 → 插入） ───
function insertImageFile() {
    pendingMediaUrl.value = ''
    imageCustomWidth.value = 400
    imageCustomAlign.value = 'left'
    showImageSizeDialog.value = true
}

function pickImageFile() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'image/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url) {
            pendingMediaUrl.value = url
        }
    }
    input.click()
}

function confirmImageInsert() {
    if (!pendingMediaUrl.value || !editor.value) return
    const w = imageCustomWidth.value > 0 ? ` width="${imageCustomWidth.value}"` : ''
    const style = `max-width:100%;height:auto`
    const alignStyle = imageCustomAlign.value === 'center' ? 'display:block;margin:12px auto;' : 
        imageCustomAlign.value === 'right' ? 'float:right;margin:12px 0 12px 16px;' :
        'margin:12px 0;'
    editor.value.chain().focus().insertContent(`<img src="${pendingMediaUrl.value}"${w} style="${style};${alignStyle}" />`).run()
    showImageSizeDialog.value = false
    pendingMediaUrl.value = ''
}

function onImagePreviewError() {
    // 预览加载失败时静默处理，图片输入框仍可编辑
}

// ─── 视频插入 ───
function insertVideo() {
    const input = document.createElement('input')
    input.type = 'file'
    input.accept = 'video/*'
    input.onchange = async () => {
        const file = input.files?.[0]
        if (!file) return
        const url = await uploadFile(file)
        if (url && editor.value) {
            editor.value.chain().focus().insertContent(`<video controls src="${url}" style="max-width:100%;border-radius:6px"></video>`).run()
        }
    }
    input.click()
}

// ─── 搜索替换 ───
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}
function toggleSearch() {
    showSearch.value = !showSearch.value
    if (!showSearch.value) {
        searchQuery.value = ''
        searchReplace.value = ''
        searchMatches.value = 0
        currentMatch.value = -1
    }
}
function doSearch() {
    if (!searchQuery.value || !editor.value) {
        searchMatches.value = 0
        currentMatch.value = -1
        return
    }
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi')
    const matches = text.match(regex)
    searchMatches.value = matches ? matches.length : 0
    currentMatch.value = searchMatches.value > 0 ? 0 : -1
}
function focusOnMatch() {
    if (!searchQuery.value || !editor.value || searchMatches.value === 0) return
    const query = searchQuery.value
    const text = editor.value.view.state.doc.textBetween(0, editor.value.view.state.doc.content.size)
    const regex = new RegExp(escapeRegex(query), 'gi')
    let match, count = 0
    while ((match = regex.exec(text)) !== null) {
        if (count === currentMatch.value) {
            const from = match.index
            const to = from + query.length
            editor.value.chain().focus().setTextSelection({ from, to }).run()
            break
        }
        count++
    }
}
function nextMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value + 1) % searchMatches.value; focusOnMatch() } }
function prevMatch() { if (searchMatches.value > 0) { currentMatch.value = (currentMatch.value - 1 + searchMatches.value) % searchMatches.value; focusOnMatch() } }
function doReplace() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return
    const html = editor.value.getHTML()
    const regex = new RegExp(escapeRegex(searchQuery.value), 'i')
    const match = html.match(regex)
    if (match) {
        editor.value.commands.setContent(html.replace(regex, searchReplace.value))
        doSearch()
        ElMessage.success(t('blog_page.messages.replaced_one'))
    }
}
function doReplaceAll() {
    if (!editor.value || searchMatches.value === 0 || !searchReplace.value) return
    const html = editor.value.getHTML()
    const regex = new RegExp(escapeRegex(searchQuery.value), 'gi')
    const count = (html.match(regex) || []).length
    editor.value.commands.setContent(html.replace(regex, searchReplace.value))
    showSearch.value = false
    searchQuery.value = ''
    searchReplace.value = ''
    searchMatches.value = 0
    ElMessage.success(t('blog_page.messages.replaced_all', { n: count }))
}

// ─── 文章模板 ───
function insertTemplate(name) {
    if (!editor.value) return
    const tpl = templates.value[name]
    if (!tpl) return
    editor.value.chain().focus().insertContent(tpl.html).run()
    showTemplateDialog.value = false
    ElMessage.success(t('blog_page.messages.template_inserted', { name: tpl.title }))
}

// ─── 清除格式 ───
function clearFormatting() {
    if (!editor.value) return
    editor.value.chain().focus().clearNodes().unsetAllMarks().run()
    ElMessage.success(t('blog_page.messages.format_cleared'))
}

// ─── 全屏编辑 ───
function toggleFullscreen() {
    isFullscreen.value = !isFullscreen.value
}

// ─── 文章统计 ───
const articleStats = computed(() => ({
    totalChars: wordCount.value,
    chineseChars: chineseChars.value,
    englishWords: englishWords.value,
    paragraphs: paragraphCount.value,
    headings: (editor.value?.getHTML() || '').match(/<h[1-3][^>]*>/g)?.length || 0,
    links: (editor.value?.getHTML() || '').match(/<a [^>]*>/g)?.length || 0,
    images: (editor.value?.getHTML() || '').match(/<img [^>]*>/g)?.length || 0,
    codeBlocks: (editor.value?.getHTML() || '').match(/<pre[^>]*>/g)?.length || 0,
    readingTime: readingTime.value,
}))

// ─── 拖拽上传 ───
const dragOver = ref(false)

function handleDragOver(e) {
    e.preventDefault()
    dragOver.value = true
}
function handleDragLeave() {
    dragOver.value = false
}
function handleDrop(e) {
    e.preventDefault()
    dragOver.value = false
    const files = e.dataTransfer?.files
    if (!files?.length) return
    const file = files[0]
    if (!file.type.startsWith('image/')) {
        ElMessage.warning(t('blog_page.messages.images_only'))
        return
    }
    uploadFile(file).then(url => {
        if (url) {
            pendingMediaUrl.value = url
            imageCustomWidth.value = 400
            showImageSizeDialog.value = true
        }
    })
}

// ─── 一键排版 ───
function applyQuickFormat() {
    if (!editor.value) return
    const html = editor.value.getHTML()
    let cleaned = html
        .replace(/<p[^>]*>/gi, '<p style="margin:8px 0;line-height:1.8;font-size:15px">')
        .replace(/<h1[^>]*>/gi, '<h1 style="font-size:24px;font-weight:700;margin:16px 0 8px">')
        .replace(/<h2[^>]*>/gi, '<h2 style="font-size:20px;font-weight:600;margin:14px 0 6px">')
        .replace(/<h3[^>]*>/gi, '<h3 style="font-size:17px;font-weight:600;margin:12px 0 6px">')
        .replace(/<img /gi, '<img style="max-width:100%;height:auto;border-radius:6px;margin:12px 0" ')
    editor.value.commands.setContent(cleaned)
    ElMessage.success(t('blog_page.messages.quick_format_done'))
}

// ─── Emoji ───
function insertEmoji(emoji) {
    if (!editor.value) return
    editor.value.chain().focus().insertContent(emoji).run()
    showEmojiDialog.value = false
}

// ─── AI 工具 ───
function getArticleText() {
    return editor.value?.getText() || form.value.content?.replace(/<[^>]*>/g, '') || ''
}
function getArticleHtml() {
    return editor.value?.getHTML() || form.value.content || ''
}
function handleAiAction(cmd) {
    if (cmd === 'copy') {
        const text = getArticleText() || getArticleHtml()
        navigator.clipboard.writeText(text).then(() => ElMessage.success(t('blog_page.messages.content_copied'))).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
        return
    }
    if (cmd === 'assistant') { showAiAssistant.value = true; return }
    if (cmd === 'create') {
        aiCreateTopic.value = form.value.title || ''
        aiCreateResult.value = ''
        showAiCreate.value = true
        return
    }
    aiLoading.value = true; aiResult.value = ''; showAiResult.value = true
    const text = getArticleText()
    if (cmd === 'typo') {
        aiAction.value = 'typo'
        aiResultTitle.value = t('blog_page.ai_titles.typo')
        callLlm(t('blog_page.ai_prompts.typo') + text)
    } else if (cmd === 'polish') {
        aiAction.value = 'polish'
        aiResultTitle.value = t('blog_page.ai_titles.polish')
        callLlm(t('blog_page.ai_prompts.polish') + text)
    } else if (cmd === 'summary') {
        aiAction.value = 'summary'
        aiResultTitle.value = t('blog_page.ai_titles.summary')
        callLlm(t('blog_page.ai_prompts.summary') + text)
    }
}
async function callLlm(prompt) {
    try {
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiResult.value = res.data?.data?.reply || t('blog_page.messages.no_ai_result')
    } catch (e) {
        aiResult.value = t('blog_page.messages.ai_unavailable') + '\n' + (e.response?.data?.message || '')
    } finally { aiLoading.value = false }
}
function applyAiFix() {
    if (!aiResult.value) return
    if (aiAction.value === 'polish') {
        editor.value?.commands.setContent('<p>' + aiResult.value.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>')
        ElMessage.success(t('blog_page.messages.polish_applied'))
    } else if (aiAction.value === 'summary') {
        form.value.excerpt = aiResult.value
        ElMessage.success(t('blog_page.messages.excerpt_filled'))
    } else {
        ElMessage.success(t('blog_page.messages.apply_manually'))
    }
    showAiResult.value = false
}
async function sendAiChat() {
    if (!aiChatInput.value.trim()) return
    const q = aiChatInput.value.trim()
    aiChatMessages.value.push({ role: 'user', content: q })
    aiChatInput.value = ''
    aiChatLoading.value = true
    try {
        const context = t('blog_page.ai_prompts.chat_context') + getArticleText().substring(0, 2000) + '\n\n'
        const res = await apiClient.post('/user-chat/ai-conversation', { message: context + t('blog_page.ai_prompts.chat_question') + q })
        const reply = res.data?.data?.reply || t('blog_page.messages.ai_reply_failed')
        aiChatMessages.value.push({ role: 'assistant', content: reply })
    } catch {
        aiChatMessages.value.push({ role: 'assistant', content: t('blog_page.messages.ai_chat_unavailable') })
    } finally { aiChatLoading.value = false }
}
async function doAiCreate() {
    if (!aiCreateTopic.value.trim()) { ElMessage.warning(t('blog_page.messages.enter_create_topic')); return }
    aiCreateLoading.value = true
    aiCreateResult.value = ''
    try {
        const styleKey = aiCreateStyle.value || 'general'
        const lengthKey = aiCreateLength.value || 'medium'
        let prompt = t('blog_page.ai_prompts.create_prefix', {
            topic: aiCreateTopic.value,
            style: t('blog_page.ai_prompts.create_style.' + styleKey),
            length: t('blog_page.ai_prompts.create_length.' + lengthKey),
        })
        if (aiCreateExtra.value.trim()) prompt += t('blog_page.ai_prompts.create_extra', { extra: aiCreateExtra.value.trim() })
        prompt += t('blog_page.ai_prompts.create_suffix')
        const res = await apiClient.post('/user-chat/ai-conversation', { message: prompt })
        aiCreateResult.value = res.data?.data?.reply || t('blog_page.messages.generate_failed')
        if (!form.value.title && aiCreateTopic.value) form.value.title = aiCreateTopic.value
    } catch (e) {
        aiCreateResult.value = t('blog_page.messages.ai_unavailable') + (e.response?.data?.message || '')
    } finally { aiCreateLoading.value = false }
}
function insertAiCreate() {
    if (!aiCreateResult.value || !editor.value) return
    const html = aiCreateResult.value
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h2>$1</h2>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^\- (.+)$/gm, '<li>$1</li>')
        .replace(/\n\n/g, '</p><p>')
        .replace(/\n/g, '<br>')
    editor.value.commands.setContent('<p>' + html + '</p>')
    ElMessage.success(t('blog_page.messages.ai_create_inserted'))
    showAiCreate.value = false
}

// ─── 编辑器点击(链接气泡菜单) ───
function onEditorClick(event) {
    const target = event.target
    if (!editor.value) return
    
    // 检查点击的是否是链接
    if (target && target.tagName === 'A') {
        event.preventDefault()
        linkBubbleHref.value = target.getAttribute('href') || ''
        linkBubbleText.value = target.textContent || ''
        const rect = target.getBoundingClientRect()
        const editorRect = event.currentTarget?.getBoundingClientRect()
        if (editorRect) {
            linkBubblePos.value = {
                top: rect.bottom - editorRect.top + 4,
                left: rect.left - editorRect.left,
            }
        }
        showLinkBubble.value = true
        return
    }
    
    // 检查代码块右上角点击（📋复制）
    const pre = target?.closest?.('pre')
    if (pre) {
        const rect = pre.getBoundingClientRect()
        const x = event.clientX - rect.left
        const y = event.clientY - rect.top
        if (x > rect.width - 50 && y < 30) {
            const code = pre.textContent || ''
            navigator.clipboard.writeText(code).then(() => {
                ElMessage.success(t('blog_page.messages.code_copied'))
            }).catch(() => ElMessage.error(t('blog_page.messages.copy_failed')))
            return
        }
    }
    
    // 点击其他区域隐藏气泡
    showLinkBubble.value = false
}

// ─── Ctrl+S 保存快捷键 ───
function handleEditorKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault()
        previewBeforePublish()
    }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'p' || e.key === 'P')) {
        e.preventDefault()
        previewVisible.value = true
    }
}

// ─── slug 自动生成 ───
watch(() => form.value.title, (title) => {
    if (!form.value.slug && title) {
        // 从标题生成简单 slug：取前 30 个字符，转小写，空格转中划线
        let slug = title.toLowerCase()
            .replace(/[^\w\u4e00-\u9fff\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .substring(0, 50)
        // 如果全是非 ASCII（如中文），用时间戳
        if (!/[a-z0-9]/.test(slug)) {
            slug = 'post-' + Date.now().toString(36)
        }
        form.value.slug = slug
    }
})

// ─── 预览并发布 ───
// ─── 自动保存草稿 ───
const DRAFT_KEY = 'blog_editor_draft'
let draftTimer = null

function saveDraft() {
    if (!form.value.title && !form.value.content) return
    const draft = {
        title: form.value.title,
        type: form.value.type,
        content: editor.value?.getHTML() || form.value.content,
        excerpt: form.value.excerpt,
        tags: form.value.tags,
        featured_image: form.value.featured_image,
        savedAt: new Date().toISOString(),
    }
    try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(draft))
        draftSaved.value = true
        if (draftSaveTimer) clearTimeout(draftSaveTimer)
        draftSaveTimer = setTimeout(() => { draftSaved.value = false }, 3000)
    } catch { /* ignore quota errors */ }
}

function restoreDraft() {
    try {
        const raw = localStorage.getItem(DRAFT_KEY)
        if (!raw) return
        const draft = JSON.parse(raw)
        if (!draft.title && !draft.content) return
        // 检查是否过期（超过 24 小时）
        const savedAt = new Date(draft.savedAt)
        if (Date.now() - savedAt.getTime() > 24 * 60 * 60 * 1000) {
            localStorage.removeItem(DRAFT_KEY)
            return
        }
        form.value.title = draft.title || ''
        form.value.type = draft.type || 'blog'
        form.value.content = draft.content || ''
        form.value.excerpt = draft.excerpt || ''
        form.value.tags = draft.tags || []
        form.value.featured_image = draft.featured_image || ''
        // 编辑器内容在挂载后设置
        setTimeout(() => {
            if (editor.value && form.value.content) {
                editor.value.commands.setContent(form.value.content)
            }
        }, 200)
        ElMessage.info(t('blog_page.messages.draft_restored'))
    } catch { /* ignore parse errors */ }
}

function clearDraft() {
    try {
        localStorage.removeItem(DRAFT_KEY)
    } catch {}
}

function previewBeforePublish() {
    if (editor.value) {
        form.value.content = editor.value.getHTML()
    }
    // 验证必填字段
    if (!form.value.title) { ElMessage.warning(t('blog_page.messages.enter_title')); return }
    if (!form.value.content) { ElMessage.warning(t('blog_page.messages.enter_content')); return }
    previewVisible.value = true
}

async function confirmPublish() {
    clearDraft()
    await submitForm()
    previewVisible.value = false
}

function insertLink() {
    linkEditMode.value = false
    linkText.value = ''
    linkUrl.value = ''
    showLinkDialog.value = true
}

function doInsertLink() {
    if (!linkUrl.value || !editor.value) return
    const selected = editor.value.state.selection.content().size > 0
    if (selected) {
        editor.value.chain().focus().setLink({ href: linkUrl.value }).run()
    } else {
        editor.value.chain().focus().insertContent(`<a href="${linkUrl.value}" target="_blank" rel="noopener noreferrer">${linkText.value || linkUrl.value}</a>`).run()
    }
    showLinkDialog.value = false
}

function editLink(href, text) {
    linkEditMode.value = true
    linkUrl.value = href
    linkText.value = text
    showLinkDialog.value = true
}

function removeLink() {
    if (!editor.value) return
    editor.value.chain().focus().unsetLink().run()
    showLinkBubble.value = false
}

// 监听对话框关闭，销毁编辑器释放资源
watch(dialogVisible, (val) => {
    if (val) {
        // 打开对话框时，启动自动保存定时器（每 30 秒）
        draftTimer = setInterval(saveDraft, 30000)
    } else {
        // 关闭对话框时，清除定时器
        if (draftTimer) {
            clearInterval(draftTimer)
            draftTimer = null
        }
        showSource.value = false
        previewVisible.value = false
        showLinkBubble.value = false
    }
})

// 监听编辑内容变化，初始化编辑器内容
watch(() => form.value.content, (newContent) => {
    if (editor.value && newContent && editor.value.isEmpty && form.value.content) {
        editor.value.commands.setContent(form.value.content)
    }
})

const tagOptions = computed(() => [
    t('blog_page.tags.product_update'),
    t('blog_page.tags.feature_release'),
    t('blog_page.tags.bug_fix'),
    t('blog_page.tags.security_update'),
    t('blog_page.tags.performance'),
    t('blog_page.tags.new_version'),
    t('blog_page.tags.developer'),
    t('blog_page.tags.tech_blog'),
    t('blog_page.tags.tips'),
    t('blog_page.tags.best_practice'),
])
const categories = ref([])
const showCategoryDialog = ref(false)
const showCategoryForm = ref(false)
const catForm = reactive({ name: '', slug: '', description: '', color: '#0f172a', sort_order: 0 })
const editingCatId = ref(null)

async function loadCategories() {
    try {
        const { data: res } = await getCategories()
        categories.value = res.data || []
    } catch { categories.value = [] }
}

async function saveCategory() {
    if (!catForm.name) { ElMessage.warning(t('blog_page.messages.enter_category_name')); return }
    try {
        if (editingCatId.value) {
            await updateCategory(editingCatId.value, catForm)
            ElMessage.success(t('blog_page.messages.category_updated'))
        } else {
            await createCategory(catForm)
            ElMessage.success(t('blog_page.messages.category_created'))
        }
        showCategoryForm.value = false
        editingCatId.value = null
        await loadCategories()
    } catch { ElMessage.error(t('messages.failed')) }
}

function editCategory(cat) {
    editingCatId.value = cat.id
    Object.assign(catForm, { name: cat.name, slug: cat.slug, description: cat.description || '', color: cat.color || '#0f172a', sort_order: cat.sort_order || 0 })
    showCategoryForm.value = true
}

async function removeCategory(cat) {
    try {
        await deleteCategory(cat.id)
        ElMessage.success(t('blog_page.messages.category_deleted'))
        await loadCategories()
    } catch { ElMessage.error(t('blog_page.messages.delete_fail')) }
}

// ─── 批量操作 ───
const selectedIds = ref([])
const tableRef = ref(null)
const batchLoading = ref(false)
const showBatchCategoryDialog = ref(false)
const batchCategoryId = ref(null)
const exporting = ref(false)

function onSelectionChange(rows) {
    selectedIds.value = rows.map(r => r.id)
}

function clearSelection() {
    tableRef.value?.clearSelection()
    selectedIds.value = []
}

async function handleBatchPublish() {
    if (selectedIds.value.length === 0) return
    try {
        await ElMessageBox.confirm(t('blog_page.confirm_msgs.batch_publish', { n: selectedIds.value.length }), t('blog_page.confirm_titles.batch_publish'))
        batchLoading.value = true
        const { data: res } = await batchPublishPosts(selectedIds.value)
        ElMessage.success(res?.message || t('blog_page.messages.publish_ok'))
        clearSelection()
        loadList()
        loadStats()
    } catch { /* cancelled */ }
    finally { batchLoading.value = false }
}

async function handleBatchDelete() {
    if (selectedIds.value.length === 0) return
    try {
        await ElMessageBox.confirm(t('blog_page.confirm_msgs.batch_delete', { n: selectedIds.value.length }), t('blog_page.confirm_titles.batch_delete'), { type: 'warning' })
        batchLoading.value = true
        const { data: res } = await batchDeletePosts(selectedIds.value)
        ElMessage.success(res?.message || t('blog_page.messages.delete_ok'))
        clearSelection()
        loadList()
        loadStats()
    } catch { /* cancelled */ }
    finally { batchLoading.value = false }
}

async function handleBatchCategory() {
    if (!batchCategoryId.value) { ElMessage.warning(t('blog_page.messages.select_category')); return }
    batchLoading.value = true
    try {
        const { data: res } = await batchCategoryPosts(selectedIds.value, batchCategoryId.value)
        ElMessage.success(res?.message || t('blog_page.messages.category_switch_ok'))
        showBatchCategoryDialog.value = false
        clearSelection()
        loadList()
    } catch { ElMessage.error(t('messages.failed')) }
    finally { batchLoading.value = false }
}

async function handleExport() {
    exporting.value = true
    try {
        const res = await exportBlogCsv({ ...filters.value })
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8' })
        const url = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = url
        a.download = 'blog-export-' + new Date().toISOString().slice(0, 10) + '.csv'
        a.click()
        URL.revokeObjectURL(url)
        ElMessage.success(t('blog_page.messages.export_ok'))
    } catch { ElMessage.error(t('blog_page.messages.export_fail')) }
    finally { exporting.value = false }
}

const typeTags = { blog: 'primary', changelog: 'success', release_note: 'warning' };
function typeLabel(typeKey) { return t('blog_page.type.' + typeKey) || typeKey }
function typeTag(typeKey) { return typeTags[typeKey] || '' }

async function loadStats() {
    try {
        const res = await getBlogStats()
        stats.value = res.data?.data || res.data || res
    } catch { /* ignore */ }
}

async function loadList() {
    loading.value = true
    try {
        const params = { page: currentPage.value, per_page: perPage.value, ...filters.value }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await getBlogList(params)
        list.value = res.data?.data?.data || res.data?.data || res.data || []
        total.value = res.data?.data?.total || res.data?.total || res.total || 0
    } catch { ElMessage.error(t('blog_page.messages.load_fail')) }
    finally { loading.value = false }
}

function showCreateDialog() {
    editingId.value = null
    form.value = { title: '', type: 'blog', category_id: null, content: '', slug: '', excerpt: '', featured_image: '', author: '', tags: [], version: '', is_published: false, is_featured: false }
    // 清空编辑器
    if (editor.value) {
        editor.value.commands.setContent('')
    }
    dialogVisible.value = true
    // 恢复自动保存的草稿
    setTimeout(() => restoreDraft(), 300)
}

async function showEditDialog(row) {
    editingId.value = row.id
    try {
        const res = await getBlogDetail(row.id)
        const data = res.data?.data || res.data || res
        form.value = {
            title: data.title,
            type: data.type,
            content: data.content || '',
            slug: data.slug || '',
            excerpt: data.excerpt || '',
            featured_image: data.featured_image || '',
            author: data.author || '',
            tags: data.tags || [],
            version: data.version || '',
            is_published: data.is_published,
            is_featured: data.is_featured,
        }
        dialogVisible.value = true
        // 编辑器内容在下一个 tick 设置，确保编辑器已挂载
        setTimeout(() => {
            if (editor.value && form.value.content) {
                editor.value.commands.setContent(form.value.content)
            }
        }, 100)
    } catch { ElMessage.error(t('blog_page.messages.load_detail_fail')) }
}

async function submitForm() {
    // 确保编辑器内容已同步到 form
    if (editor.value) {
        form.value.content = editor.value.getHTML()
    }
    const valid = await formRef.value.validate().catch(() => false)
    if (!valid) return
    submitting.value = true
    try {
        if (editingId.value) {
            await updateBlog(editingId.value, form.value)
            ElMessage.success(t('blog_page.messages.save_ok'))
        } else {
            await createBlog(form.value)
            ElMessage.success(t('blog_page.messages.publish_ok'))
        }
        dialogVisible.value = false
        previewVisible.value = false
        loadList()
        loadStats()
    } catch (e) {
        ElMessage.error(e.response?.data?.message || t('messages.failed'))
    } finally {
        submitting.value = false
    }
}

async function handleTogglePublish(row) {
    try {
        await togglePublish(row.id)
        ElMessage.success(row.is_published ? t('blog_page.messages.unpublished') : t('blog_page.messages.published_ok'))
        loadList()
        loadStats()
    } catch { ElMessage.error(t('messages.failed')) }
}

async function handleToggleFeatured(row) {
    try {
        await toggleFeatured(row.id)
        ElMessage.success(row.is_featured ? t('blog_page.messages.unfeatured') : t('blog_page.messages.featured_ok'))
        loadList()
    } catch { ElMessage.error(t('messages.failed')) }
}

async function handleDelete(row) {
    try {
        await deleteBlog(row.id)
        ElMessage.success(t('blog_page.messages.deleted'))
        loadList()
        loadStats()
    } catch { ElMessage.error(t('blog_page.messages.delete_fail')) }
}

function copyRssUrls() {
    const urls = ['/api/rss/all', '/api/rss/blog', '/api/rss/changelog']
        .map(p => baseUrl.value + p)
        .join('\n')
    navigator.clipboard.writeText(urls)
    ElMessage.success(t('blog_page.messages.rss_copied'))
}

onMounted(() => {
    loadStats()
    loadList()
    loadSubStats()
    loadCategories()
})

// 编辑器销毁清理
onBeforeUnmount(() => {
    if (editor.value) {
        editor.value.destroy()
    }
})

async function loadSubStats() {
    try {
        const { data } = await getSubscriptionStats()
        if (data?.data) subStats.value = data.data
    } catch { /* */ }
}

// ═══════════════════════════════════════════
//  API Changelog Tab (cl_ prefixed)
// ═══════════════════════════════════════════

const clP = 'changelog_page'

const clActiveTab = ref('list')
const clLoading = ref(false)
const clSaving = ref(false)
const clAutoLoading = ref(false)
const clSnapshotLoading = ref(false)
const clHistoryLoading = ref(false)
const clMigrationLoading = ref(false)

const clList = ref([])
const clTotal = ref(0)
const clCurrentPage = ref(1)
const clPageSize = ref(20)
const clStats = ref({})
const clApiVersions = ref([])
const clDetectHistory = ref([])

const clTypeKeys = ['release', 'beta', 'hotfix', 'security']

const clTypeOptions = computed(() => clTypeKeys.map((value) => ({
    value,
    label: t(`${clP}.types.${value}`),
})))

const clTypeLabels = computed(() => Object.fromEntries(
    clTypeKeys.map((key) => [key, t(`${clP}.types.${key}`)]),
))

const clBreakingChangeLabels = computed(() => ({
    removed: t(`${clP}.migration.change_removed`),
    deprecated: t(`${clP}.migration.change_deprecated`),
}))

const clDialogTitle = computed(() => (
    clIsEditing.value ? t(`${clP}.dialog.edit_title`) : t(`${clP}.dialog.create_title`)
))

const clFormRules = computed(() => ({
    version: [{ required: true, message: t(`${clP}.validation.version_required`), trigger: 'blur' }],
    title: [{ required: true, message: t(`${clP}.validation.title_required`), trigger: 'blur' }],
}))

const clFilters = reactive({
    search: '',
    version: '',
    type: '',
    start_date: '',
    end_date: '',
})
const clDateRange = ref(null)

const clDialogVisible = ref(false)
const clDetailVisible = ref(false)
const clIsEditing = ref(false)
const clEditId = ref(null)
const clDetailData = ref(null)

const clFormRef = ref(null)
const clForm = reactive({
    version: '',
    title: '',
    type: 'release',
    release_date: null,
    description: '',
    migration_guide: '',
})

const clAutoForm = reactive({ api_version_id: '' })
const clSnapshotForm = reactive({ api_version_id: '', version_label: '' })
const clMigrationForm = reactive({ from_version: '', to_version: '' })
const clMigrationResult = ref(null)

const clVersionData = ref([])

const clRenderedDescription = computed(() => {
    return clDetailData.value ? clRenderMarkdown(clDetailData.value.description || '') : ''
})

const clRenderedMigrationGuide = computed(() => {
    return clDetailData.value ? clRenderMarkdown(clDetailData.value.migration_guide || '') : ''
})

function clTypeTag(type) {
    const map = { release: 'primary', beta: 'warning', hotfix: 'danger', security: 'info' }
    return map[type] || 'info'
}

function clTypeLabel(type) {
    return clTypeLabels.value[type] || type
}

function clSourceLabel(source, detailed = false) {
    if (source === 'auto_detect') {
        return detailed ? t(`${clP}.source.auto_detect`) : t(`${clP}.source.auto`)
    }
    return detailed ? t(`${clP}.source.manual_create`) : t(`${clP}.source.manual`)
}

function clBreakingChangeLabel(type) {
    return clBreakingChangeLabels.value[type] || type
}

function clRenderMarkdown(text) {
    if (!text) return ''
    return text
        .replace(/### (.+)/g, '<h5>$1</h5>')
        .replace(/## (.+)/g, '<h4>$1</h4>')
        .replace(/# (.+)/g, '<h3>$1</h3>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\n- /g, '<br>• ')
        .replace(/\n/g, '<br>')
        .replace(/`(.+?)`/g, '<code>$1</code>')
}

async function clLoadList() {
    clLoading.value = true
    try {
        const params = {
            page: clCurrentPage.value,
            per_page: clPageSize.value,
            ...clFilters,
        }
        Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
        const res = await changelogApi.list(params)
        clList.value = res.data?.data ?? []
        clTotal.value = res.data?.total ?? 0
    } catch (e) {
        console.error('Failed to load changelog list', e)
    } finally {
        clLoading.value = false
    }
}

async function clLoadStats() {
    try {
        const res = await changelogApi.stats()
        clStats.value = res.data ?? {}
    } catch (e) {
        console.error('Failed to load stats', e)
    }
}

async function clLoadApiVersions() {
    try {
        const res = await getApiVersions()
        clApiVersions.value = res.data ?? []
    } catch (e) {
        console.error('Failed to load API versions', e)
    }
}

async function clLoadDetectHistory() {
    clHistoryLoading.value = true
    try {
        const res = await changelogApi.autoDetectHistory()
        clDetectHistory.value = res.data ?? []
    } catch (e) {
        console.error('Failed to load detect history', e)
    } finally {
        clHistoryLoading.value = false
    }
}

async function clLoadVersionData() {
    try {
        const res = await changelogApi.publicByVersion()
        clVersionData.value = res.data ?? []
    } catch (e) {
        console.error('Failed to load version data', e)
    }
}

function clOnDateRangeChange(range) {
    if (range) {
        clFilters.start_date = range[0]
        clFilters.end_date = range[1]
    } else {
        clFilters.start_date = ''
        clFilters.end_date = ''
    }
    clLoadList()
}

function clShowCreateDialog() {
    clIsEditing.value = false
    clEditId.value = null
    clForm.version = ''
    clForm.title = ''
    clForm.type = 'release'
    clForm.release_date = null
    clForm.description = ''
    clForm.migration_guide = ''
    clDialogVisible.value = true
}

function clShowEditDialog(row) {
    clIsEditing.value = true
    clEditId.value = row.id
    clForm.version = row.version
    clForm.title = row.title
    clForm.type = row.type || 'release'
    clForm.release_date = row.release_date
    clForm.description = row.description || ''
    clForm.migration_guide = row.migration_guide || ''
    clDialogVisible.value = true
}

async function clShowDetail(row) {
    try {
        const res = await changelogApi.show(row.id)
        clDetailData.value = res.data
        clDetailVisible.value = true
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.load_detail_failed`))
    }
}

function clShowVersionDetail(ver) {
    clFilters.version = ver.version
    clActiveTab.value = 'list'
    clLoadList()
}

async function clHandleSave() {
    const valid = await clFormRef.value?.validate().catch(() => false)
    if (!valid) return
    clSaving.value = true
    try {
        const data = {
            version: clForm.version,
            title: clForm.title,
            type: clForm.type,
            release_date: clForm.release_date,
            description: clForm.description,
            migration_guide: clForm.migration_guide,
        }
        if (clIsEditing.value) {
            await changelogApi.update(clEditId.value, data)
            ElMessage.success(t(`${clP}.messages.update_success`))
        } else {
            await changelogApi.create(data)
            ElMessage.success(t(`${clP}.messages.create_success`))
        }
        clDialogVisible.value = false
        clLoadList()
        clLoadStats()
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.save_failed`))
    } finally {
        clSaving.value = false
    }
}

async function clHandleDelete(id) {
    try {
        await changelogApi.delete(id)
        ElMessage.success(t(`${clP}.messages.delete_success`))
        clLoadList()
        clLoadStats()
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.delete_failed`))
    }
}

async function clHandleAutoGenerate() {
    if (!clAutoForm.api_version_id) {
        ElMessage.warning(t(`${clP}.messages.select_api_version`))
        return
    }
    clAutoLoading.value = true
    try {
        const res = await changelogApi.autoGenerate(clAutoForm.api_version_id)
        ElMessage.success(res.data?.message || t(`${clP}.messages.detect_complete`))
        clLoadDetectHistory()
        clLoadList()
        clLoadStats()
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.detect_failed`))
    } finally {
        clAutoLoading.value = false
    }
}

async function clHandleCreateSnapshot() {
    if (!clSnapshotForm.api_version_id) {
        ElMessage.warning(t(`${clP}.messages.select_api_version`))
        return
    }
    clSnapshotLoading.value = true
    try {
        const res = await changelogApi.createSnapshot(
            clSnapshotForm.api_version_id,
            clSnapshotForm.version_label || null
        )
        ElMessage.success(t(`${clP}.messages.snapshot_success`, { count: res.data?.endpoints_snapshotted || 0 }))
        clLoadDetectHistory()
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.snapshot_failed`))
    } finally {
        clSnapshotLoading.value = false
    }
}

async function clHandleGenerateMigration() {
    if (!clMigrationForm.from_version || !clMigrationForm.to_version) {
        ElMessage.warning(t(`${clP}.messages.fill_versions`))
        return
    }
    clMigrationLoading.value = true
    try {
        const res = await changelogApi.migrationGuide(
            clMigrationForm.from_version,
            clMigrationForm.to_version
        )
        clMigrationResult.value = res.data
    } catch (e) {
        ElMessage.error(t(`${clP}.messages.migration_failed`))
    } finally {
        clMigrationLoading.value = false
    }
}

async function clLoadAll() {
    clLoadList()
    clLoadStats()
    clLoadApiVersions()
    clLoadDetectHistory()
    clLoadVersionData()
}

// Lazy load changelog tab on first access
watch(blogMainTab, (val) => {
    if (val === 'changelog' && !clLoaded.value) {
        clLoaded.value = true
        clLoadAll()
    }
})
</script>

<style scoped>
.blog-manager-page { padding: 20px; }
.stats-row { margin-bottom: 20px; }
.stat-card { text-align: center; margin-bottom: 10px; }
.stat-card .stat-value { font-size: 24px; font-weight: bold; color: #303133; }
.stat-card .stat-label { font-size: 13px; color: #909399; margin-top: 4px; }
.rss-info .rss-header { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.rss-label { font-weight: bold; white-space: nowrap; }
.rss-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; flex: 1; }
.rss-url { font-size: 12px; background: #f5f7fa; padding: 2px 6px; border-radius: 3px; }
.search-card { margin-bottom: 16px; }
.table-card { margin-bottom: 20px; }
.pagination-wrap { margin-top: 16px; display: flex; justify-content: flex-end; }
.title-cell { display: flex; align-items: center; gap: 8px; }
.type-badge { flex-shrink: 0; }

/* Tiptap 编辑器样式 */
.blog-editor-toolbar {
    width: 100%;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 2px;
    padding: 4px 8px;
    background: #fafafa;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-radius: 4px 4px 0 0;
    box-sizing: border-box;
}
/* 代码块语言栏 */
.code-lang-bar {
    display: flex;
    align-items: center;
    padding: 4px 12px;
    background: #f5f5f5;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-top: none;
    flex-shrink: 0;
}
.code-lang-badge {
    font-size: 11px;
    background: #0f172a;
    color: #fff;
    padding: 1px 8px;
    border-radius: 3px;
    margin-left: 6px;
    font-family: 'Fira Code', 'Consolas', monospace;
}

/* 表格操作栏 */
.table-toolbar {
    display: flex;
    align-items: center;
    padding: 4px 12px;
    background: #f0f7ff;
    border: 1px solid #dcdfe6;
    border-bottom: none;
    border-top: none;
    flex-shrink: 0;
    gap: 4px;
    flex-wrap: wrap;
}

.blog-editor-content {
    border: 1px solid #dcdfe6;
    border-top: none;
    border-radius: 0 0 4px 4px;
    background: #fff;
    display: flex;
    flex-direction: column;
    width: 100%;
}
.blog-prose-editor {
    flex: 1;
    min-height: 300px;
    padding: 0;
    outline: none;
    cursor: text;
    width: 100%;
}
.blog-editor-footer {
    padding: 6px 16px;
    font-size: 12px;
    color: #909399;
    border-top: 1px solid #f0f0f0;
    flex-shrink: 0;
}

/* 链接气泡菜单 */
.link-bubble {
    position: absolute;
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 6px;
    padding: 4px 8px;
    box-shadow: 0 2px 12px rgba(0,0,0,.1);
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 100;
    white-space: nowrap;
    font-size: 12px;
}
.link-bubble-url {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #0f172a;
    margin-right: 4px;
}
.blog-editor-content {
    position: relative;
}
.blog-prose-editor {
    min-height: 320px;
    padding: 0;
    outline: none;
    cursor: text;
}
.blog-prose-editor :deep(.ProseMirror) {
    min-height: 300px;
    padding: 16px 20px;
    outline: none;
}
.blog-prose-editor :deep(.ProseMirror p) {
    margin: 6px 0;
    line-height: 1.8;
    font-size: 15px;
}
.blog-prose-editor :deep(.ProseMirror h1) {
    font-size: 24px;
    font-weight: 700;
    margin: 16px 0 8px;
}
.blog-prose-editor :deep(.ProseMirror h2) {
    font-size: 20px;
    font-weight: 600;
    margin: 14px 0 6px;
}
.blog-prose-editor :deep(.ProseMirror h3) {
    font-size: 17px;
    font-weight: 600;
    margin: 12px 0 6px;
}
.blog-prose-editor :deep(.ProseMirror pre) {
    background: #2d2d2d;
    color: #ccc;
    padding: 16px 44px 16px 16px;
    border-radius: 6px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.5;
    position: relative;
}
.blog-prose-editor :deep(.ProseMirror pre)::after {
    content: '📋';
    position: absolute;
    top: 6px;
    right: 8px;
    font-size: 14px;
    cursor: pointer;
    opacity: 0;
    transition: opacity .2s;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255,255,255,.1);
    line-height: 1.4;
    pointer-events: none;
}
.blog-prose-editor :deep(.ProseMirror pre:hover)::after {
    opacity: .85;
}
.blog-prose-editor :deep(.ProseMirror code) {
    font-family: 'Fira Code', 'Consolas', monospace;
    font-size: 13px;
}
.blog-prose-editor :deep(.ProseMirror blockquote) {
    border-left: 4px solid #0f172a;
    padding: 8px 16px;
    margin: 12px 0;
    background: #f5f7fa;
    border-radius: 0 4px 4px 0;
    color: #606266;
}
.blog-prose-editor :deep(.ProseMirror table) {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0;
}
.blog-prose-editor :deep(.ProseMirror th),
.blog-prose-editor :deep(.ProseMirror td) {
    border: 1px solid #dcdfe6;
    padding: 8px 12px;
    text-align: left;
    min-width: 60px;
}
.blog-prose-editor :deep(.ProseMirror th) {
    background: #f5f7fa;
    font-weight: 600;
}
.blog-prose-editor :deep(.ProseMirror .selectedCell) {
    background: #f1f5f9;
}
.blog-prose-editor :deep(.ProseMirror img) {
    max-width: 100%;
    height: auto;
    border-radius: 6px;
    margin: 12px 0;
}
.blog-prose-editor :deep(.ProseMirror ul),
.blog-prose-editor :deep(.ProseMirror ol) {
    padding-left: 24px;
    margin: 8px 0;
}
.blog-prose-editor :deep(.ProseMirror li) {
    margin: 4px 0;
    line-height: 1.7;
}
.blog-prose-editor :deep(.ProseMirror a) {
    color: #0f172a;
    text-decoration: underline;
}
.blog-prose-editor :deep(.ProseMirror-gapcursor) {
    display: none;
}
.blog-prose-editor :deep(.ProseMirror p.is-editor-empty:first-child::before) {
    content: attr(data-placeholder);
    float: left;
    color: #adb5bd;
    pointer-events: none;
    height: 0;
}
.blog-prose-editor :deep(.is-empty)::before {
    color: #c0c4cc;
}

/* 搜索替换面板 */
.search-panel {
    padding: 8px 12px;
    border-bottom: 1px solid #e4e7ed;
    background: #fefce8;
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
    width: 100%;
    box-sizing: border-box;
}

.search-panel .search-row .el-input { width: auto !important; }
.search-row { display: flex; align-items: center; gap: 6px; }
.search-count { font-size: 12px; color: #0f172a; font-weight: 600; min-width: 50px; }
.search-count.no-result { color: #f56c6c; }

/* 文章模板 */
.template-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.template-card {
    border: 1px solid #ebeef5;
    border-radius: 8px;
    padding: 16px;
    cursor: pointer;
    transition: all .2s;
    text-align: center;
}
.template-card:hover { border-color: #0f172a; box-shadow: 0 2px 8px rgba(15,23,42,.15); }
.template-icon { font-size: 36px; margin-bottom: 8px; }
.template-name { font-size: 14px; font-weight: 600; color: #303133; margin-bottom: 4px; }
.template-desc { font-size: 12px; color: #909399; line-height: 1.4; }

/* Emoji */
.emoji-grid { display: flex; flex-wrap: wrap; gap: 4px; max-height: 320px; overflow-y: auto; }
.emoji-item {
    font-size: 24px;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: background .15s;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.emoji-item:hover { background: #f0f5ff; }

/* AI 工具 */
.ai-chat-msg { padding: 8px 10px; margin-bottom: 6px; border-radius: 6px; }
.ai-chat-msg.user { background: #e6f0ff; }
.ai-chat-msg.assistant { background: #f0f0f0; }

/* 快捷键 */
.shortcut-list { display: flex; flex-direction: column; gap: 8px; }
.shortcut-item { display: flex; align-items: center; gap: 16px; padding: 6px 0; }
.shortcut-keys { display: flex; gap: 4px; min-width: 160px; }
.shortcut-keys kbd {
    background: #f5f7fa;
    border: 1px solid #e4e7ed;
    border-radius: 4px;
    padding: 2px 8px;
    font-size: 12px;
    font-family: 'Fira Code', 'Consolas', monospace;
    box-shadow: 0 1px 0 #d0d5dd;
}
.shortcut-desc { font-size: 13px; color: #606266; }

/* 文章统计 */
.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 12px;
}
.stat-item {
    text-align: center;
    padding: 12px 8px;
    background: #f5f7fa;
    border-radius: 8px;
}
.stat-num {
    font-size: 24px;
    font-weight: 700;
    color: #303133;
}
.stat-label {
    font-size: 12px;
    color: #909399;
    margin-top: 2px;
}

/* SEO 预览卡片 */
.seo-preview-card {
    background: #fff;
    border: 1px solid #e4e7ed;
    border-radius: 8px;
    padding: 12px 16px;
    max-width: 600px;
}
.seo-url { font-size: 12px; color: #006621; line-height: 1.5; word-break: break-all; }
.seo-title { font-size: 18px; color: #1a0dab; line-height: 1.3; margin: 2px 0; cursor: pointer; }
.seo-title:hover { text-decoration: underline; }
.seo-desc { font-size: 13px; color: #545454; line-height: 1.5; word-break: break-all; }
.word-goal-progress { display: inline-flex; align-items: center; }

/* API Changelog Tab */
.blog-changelog-tabs { margin: 0; }
.changelog-manager-page { padding: 0; }
.cl-toolbar { margin-bottom: 8px; }
.cl-pagination-row { margin-top: 16px; display: flex; justify-content: flex-end; }
.cl-auto-header { display: flex; justify-content: space-between; align-items: center; }
.cl-history-section { margin-top: 8px; }
.cl-history-section h4 { margin: 8px 0; }
.cl-migration-result h4 { margin: 12px 0 8px; font-size: 15px; }
.cl-changelog-summary-list { list-style: none; padding: 0; margin: 8px 0; }
.cl-changelog-summary-list li { padding: 4px 0; font-size: 13px; }
.cl-no-guide { color: #c0c4cc; }
.cl-version-count { color: #909399; font-size: 13px; }
.cl-markdown-content { line-height: 1.8; padding: 8px 0; }
.cl-markdown-content code { background: #f5f7fa; padding: 2px 6px; border-radius: 3px; font-size: 13px; }
</style>
<style>
/* 对话框内边距（全局样式，因为 el-dialog 是 teleport 到 body 的） */
.blog-dialog .el-dialog__body { padding: 20px !important; }
/* 发布预览内容样式 */
.preview-content { font-size: 15px; line-height: 1.8; color: #303133; }
.preview-content h1 { font-size: 22px; margin: 20px 0 12px; }
.preview-content h2 { font-size: 18px; margin: 18px 0 10px; }
.preview-content h3 { font-size: 16px; margin: 16px 0 8px; }
.preview-content p { margin: 8px 0; }
.preview-content img { max-width: 100%; height: auto; border-radius: 4px; }
.preview-content blockquote { border-left: 4px solid #0f172a; padding: 8px 16px; margin: 12px 0; background: #f5f7fa; border-radius: 0 4px 4px 0; color: #606266; }
.preview-content pre { background: #1e1e1e; color: #d4d4d4; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.preview-content code { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.preview-content table { border-collapse: collapse; width: 100%; margin: 12px 0; }
.preview-content th, .preview-content td { border: 1px solid #dcdfe6; padding: 8px 12px; text-align: left; }
.preview-content th { background: #f5f7fa; font-weight: 600; }
.preview-content ul, .preview-content ol { padding-left: 24px; margin: 8px 0; }
.preview-content a { color: #0f172a; text-decoration: none; }
.preview-content a:hover { text-decoration: underline; }
/* 代码高亮预览样式（VS Code Dark 主题） */
.preview-content pre { background: #1e1e1e; color: #d4d4d4; padding: 14px 16px; border-radius: 6px; overflow-x: auto; font-size: 13px; line-height: 1.5; }
.preview-content code { font-family: 'Fira Code', Consolas, monospace; font-size: 13px; }
.preview-content .hljs-keyword { color: #c586c0; }
.preview-content .hljs-string { color: #ce9178; }
.preview-content .hljs-number { color: #b5cea8; }
.preview-content .hljs-comment { color: #6a9955; font-style: italic; }
.preview-content .hljs-built_in { color: #4ec9b0; }
.preview-content .hljs-literal { color: #569cd6; }
.preview-content .hljs-function { color: #dcdcaa; }
.preview-content .hljs-title { color: #dcdcaa; }
.preview-content .hljs-params { color: #9cdcfe; }
.preview-content .hljs-attr { color: #9cdcfe; }
.preview-content .hljs-attribute { color: #9cdcfe; }
.preview-content .hljs-type { color: #4ec9b0; }
.preview-content .hljs-meta { color: #d4d4d4; }
.preview-content .hljs-tag { color: #569cd6; }
.preview-content .hljs-name { color: #569cd6; }
.preview-content .hljs-selector-class { color: #d7ba7d; }
.preview-content .hljs-selector-id { color: #d7ba7d; }
.preview-content .hljs-selector-tag { color: #569cd6; }
.preview-content .hljs-regexp { color: #d16969; }
.preview-content .hljs-symbol { color: #569cd6; }
.preview-content .hljs-bullet { color: #d7ba7d; }
.preview-content .hljs-link { color: #569cd6; text-decoration: underline; }
.preview-content .hljs-deletion { color: #d16969; background: #470000; }
.preview-content .hljs-addition { color: #6a9955; background: #003100; }
</style>
