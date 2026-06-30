# 离线更新包目录
# ====================
# 将离线更新包 (*.update.tar.gz) 放到此目录
# 然后执行: bash scripts/apply-update.sh updates/<update_package_name>
#
# 更新包结构:
#   hwt-update-v1.1.0.update.tar.gz/
#   ├── VERSION                  # 版本号文件
#   ├── SHA256SUMS               # 完整性校验
#   ├── CHANGELOG.md             # 更新日志
#   ├── docker-images/           # 更新的 Docker 镜像
#   │   └── hwt-api.tar
#   ├── scripts/
#   │   ├── pre-update.sh        # 更新前执行
#   │   └── post-update.sh       # 更新后执行
#   └── migrations/              # 数据库迁移 (可选)
#       └── *.sql
