# 代码质量工具链（M0-08）

## 概述

集成 PHPStan/Larastan 静态分析 + Laravel Pint 代码格式化 + Pre-commit Hooks，确保代码质量和一致性。

## 使用方式

### 本地开发

```bash
# 检查代码风格
composer pint:test

# 自动修复代码风格
composer pint:fix

# PHPStan 静态分析
composer phpstan

# 全量质量检查
composer quality

# 全量修复
composer quality:fix
```

### Pre-commit Hook

每次 `git commit` 时自动执行：
1. **Pint 代码风格检查** — 自动修复并重新暂存
2. **PHPStan 静态分析** — Level 6 严格检查
3. **密钥泄露扫描** — 拦截硬编码密钥

```bash
# 安装 pre-commit hook
npx husky install

# 手动触发 pre-commit 检查
bash .husky/pre-commit
```

### CI 流水线

`push`/`PR` 到 `main`/`master` 分支时自动触发 `.github/workflows/code-quality.yml`：

| 步骤 | 工具 | 说明 |
|:---:|:----|:-----|
| 1 | **Laravel Pint** | 检查 PSR-12 + Laravel 规范 |
| 2 | **PHPStan** | Level 6 静态分析 |
| 3 | **密钥扫描** | 正则匹配硬编码密钥 |

## 工具链说明

### PHPStan (Level 6)

```bash
phpstan.neon          # 主配置文件
vendor/bin/phpstan    # CLI

# 级别说明:
# 0-4: 基础检查
# 5:   返回值类型（当前基线）
# 6:   缺失类型声明（目标级别）
# 7-9: 严格模式（长期目标）
```

### Laravel Pint

```bash
pint.json             # 配置（基于 Laravel preset）
vendor/bin/pint       # CLI

# 自动修复范围:
# - PSR-12 规范
# - 导入语句排序
# - 类成员排序
# - PHP 8.x 简化语法
# - 类型声明优化
```

### Pre-commit Hooks

```bash
.husky/pre-commit     # Git hook 脚本
.lint-staged.config.mjs  # Lint-staged 配置（暂存区文件）
```

## 常见问题

**Q: Pint 修复后文件变更了，需要重新 git add？**
A: Pre-commit hook 会自动重新暂存修复后的文件。

**Q: PHPStan 报错太多怎么办？**
A: 先确保 `phpstan.neon` 的 `ignoreErrors` 已覆盖 Laravel 动态调用。
   仍有问题可使用 `--generate-baseline` 生成基线文件。

**Q: 如何临时跳过 pre-commit hook？**
A: `git commit --no-verify -m "message"`
   仅限紧急情况，不建议常规使用。
