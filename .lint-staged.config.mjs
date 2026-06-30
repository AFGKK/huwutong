/**
 * Lint-staged 配置
 *
 * M0-08: 只对暂存区的文件运行代码质量检查
 * 配合 husky pre-commit hook 使用
 *
 * 安装: npm install --save-dev lint-staged
 */

export default {
    '*.php': [
        'vendor/bin/pint',
        'vendor/bin/phpstan analyse --no-progress --error-format=raw',
    ],
    '*.{vue,js,ts}': [
        'npx eslint --fix',
    ],
    '*.{json,md,yaml,yml}': [
        'npx prettier --check',
    ],
};
