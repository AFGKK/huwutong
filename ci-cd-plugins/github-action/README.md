# GitHub Action — HWT License

在 GitHub Actions 工作流中自动获取/激活 HWT License。

## 使用示例

```yaml
# .github/workflows/build.yml
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Fetch HWT License
        id: license
        uses: huwutong/hwt-license-action@v1
        with:
          action: fetch
          token: ${{ secrets.HWT_CI_TOKEN }}

      - name: Use license
        run: |
          echo "License: ${{ steps.license.outputs.license-key }}"

      - name: Activate license
        if: success()
        uses: huwutong/hwt-license-action@v1
        with:
          action: activate
          token: ${{ secrets.HWT_CI_TOKEN }}
          license-key: ${{ steps.license.outputs.license-key }}
```

## 输入

| 参数 | 必填 | 默认值 | 说明 |
|------|------|--------|------|
| `action` | 是 | `fetch` | `fetch` 或 `activate` |
| `token` | 是 | — | HWT CI/CD API Token |
| `license-key` | 否(activate时必填) | — | License key |
| `api-url` | 否 | `https://api.huwutong.com` | API 地址 |

## 输出

| 输出 | 说明 |
|------|------|
| `license-key` | License key |
| `license-status` | License 状态 |