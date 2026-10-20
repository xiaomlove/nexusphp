# 服务器更新手册（PHP 直装 / 无 Docker）

核心原则：**仓库是唯一代码源，服务器是只读工作副本。**

- 服务器上永远不做：`git add -A`、`git commit`、`git push`、直接手改代码。
- 服务器上可以存在仓库之外的文件：`.env`、`storage/`、`public/attachments`、`public/bitbucket`、
  `resources/upload`、你自己放的脚本、`GeoLite2-*.mmdb` …… 部署脚本不会删除它们。
- 部署 = `git fetch` + `git reset --hard` + `composer install` + `php artisan nexus:update`。

配套脚本：[`update.sh`](update.sh)。

---

## 0. 先在本地把要发布的代码推到 origin

当前仓库只有 `dev`（定制线）和 `php8`（上游线），还没有 `prod`。首次切换时从 `dev` 派生 `prod`：

```bash
git checkout dev
git push origin dev

# 派生 prod（内容与 dev 完全一致），服务器只跟踪 prod
git branch prod dev
git push -u origin prod

# 建议打个 tag，方便回滚
git tag -a deploy-$(date +%Y%m%d) -m "first prod cutover"
git push origin deploy-$(date +%Y%m%d)
```

之后发布流程固定为：

```bash
# 开发在 dev
git checkout dev && git commit ... && git push origin dev

# 发布到 prod（二选一）
git checkout prod && git merge --ff-only dev && git push origin prod
# 或只挑某几个提交
git checkout prod && git cherry-pick <sha> && git push origin prod
```

> 为什么不在服务器上直接跟踪 `dev`：`dev` 是开发线，随时可能半成品。`prod` 是“已发布”的指针，
> 服务器只需要 `git reset --hard origin/prod`，语义清晰、可回滚。

---

## 1. 一次性切换（服务器目前没有 `.git`）

### 1.1 先看清“会被加回来”的东西

你在服务器上删掉的功能，如果没在仓库里 `git rm`，切换后会被重新写回来。切换前先比对：

```bash
# 服务器上（排除 vendor/storage/.git 等运行期目录）
find . -path ./vendor -prune -o -path ./.git -prune -o -path ./storage -prune \
       -o -type f -print | sed 's|^\./||' | sort > /tmp/server-files.txt

# 本地
git ls-tree -r --name-only prod | sort > /tmp/repo-files.txt
```

把两个文件拉到一台机器上比对：

```bash
comm -23 /tmp/server-files.txt /tmp/repo-files.txt   # 服务器独有：旧文件 / 你手加的东西（保留与否自己判断）
comm -13 /tmp/server-files.txt /tmp/repo-files.txt   # 仓库有、服务器没有：删掉的功能会被加回来
```

对第二类，确认不需要的在本地处理掉再发布：

```bash
git rm -r <path> && git commit -m "prod: drop <feature>" && git push origin prod
```

### 1.2 备份

脚本会自动打包代码（排除 vendor/storage/附件）并用 `.env` 里的 DB 账号 `mysqldump`。
也可以手工再来一次：

```bash
tar czf /backup/nexusphp-code-$(date +%F).tgz -C /www/wwwroot/nexusphp --exclude=vendor --exclude=storage .
mysqldump --single-transaction -u... -p... db | gzip > /backup/nexusphp-db-$(date +%F).sql.gz
```

### 1.3 切换

```bash
# 先把脚本传到服务器（还没提交进仓库时）
scp deploy/update.sh user@host:/tmp/

# 试运行：只打印，不修改
sudo APP_DIR=/www/wwwroot/nexusphp bash /tmp/update.sh \
    --ref prod --web-user www-data --php-fpm php-fpm --dry-run

# 正式执行
sudo APP_DIR=/www/wwwroot/nexusphp bash /tmp/update.sh \
    --ref prod --web-user www-data --php-fpm php-fpm
```

脚本做的事：

1. 备份代码 + 数据库；
2. 目录里没有 `.git` 就 `git init` + `git remote add origin <你的 fork>`；
3. `git fetch origin prod`；
4. `git update-ref refs/heads/prod FETCH_HEAD` → `git symbolic-ref HEAD refs/heads/prod` →
   `git reset --hard prod`（不用 `checkout`，避免 “untracked would be overwritten”）；
5. `composer install --no-dev --optimize-autoloader`；
6. `php artisan nexus:update`（补 `.env`、extra queries、`migrate`）；
7. `php artisan optimize:clear`、`filament:optimize`（若存在）、`horizon:terminate`；
8. `chown` 运行期目录、reload php-fpm；
9. 列出 “服务器有、仓库没有” 的文件清单。

`git init` 建的 `.git` 在项目根目录（`public/` 之外），nginx docroot 指向 `public/`，不会被外部访问。

### 1.4 切换后检查

```bash
cd /www/wwwroot/nexusphp
git status -sb                     # 只应看到 ?? 开头的“服务器独有文件”
git log -1 --oneline               # 应为 prod 的提交
php artisan migrate:status | grep Pending   # 应无输出
curl -sI https://你的域名/ | head -1
tail -n 50 storage/logs/laravel-$(date +%F).log
```

对 `git status` 里 `??` 的文件逐项处理：

- 运行期（`.env`、`storage/*`、附件、mmdb、你自己的运维脚本）→ 保留；
- 旧版本残留的 PHP 文件（重构后已不存在的类/页面）→ 删掉，避免被直接访问。

处理完后再跑一次 `git status -sb`，理想状态是干净的（或被 `.gitignore` 覆盖后为空）。

---

## 2. 日常更新（以后每次都一样）

```bash
sudo APP_DIR=/www/wwwroot/nexusphp bash /www/wwwroot/nexusphp/deploy/update.sh \
    --ref prod --php-fpm php-fpm
```

如果 `deploy/update.sh` 已经提交进仓库，它会随 `git reset --hard` 自动更新，直接跑即可。

发布前务必：

- 本地 `composer install` 与线上 PHP 版本一致（`composer.json` 要求 PHP 8.2–8.5）；
- 有数据库迁移时先在测试库跑一遍；
- 前端资源（`public/css`、`public/js`）是提交进仓库的，服务器不需要 node/npm。

---

## 3. 回滚

```bash
cd /www/wwwroot/nexusphp
git log --oneline -5                 # 找上一个版本
git reset --hard <prev-sha>
composer install --no-dev --optimize-autoloader
php artisan nexus:update             # 幂等，可重复跑
systemctl reload php-fpm
```

数据库迁移不可逆时再恢复备份：

```bash
gunzip < /path/to/nexusphp-backup/db-YYYYmmdd-HHMMSS.sql.gz | mysql -u... -p... <db>
```

脚本执行失败时会自动在终端打印上面这几条回滚命令。

---

## 4. 常见问题

| 现象 | 处理 |
|---|---|
| `git reset --hard` 后代码没变 | 服务器 repo 的 `origin` 指向不对：`git remote -v` 检查，`--origin` 指定正确地址 |
| 页面 500 / `Class not found` | `composer install --no-dev -o` 没跑或 OPcache 未刷新：`systemctl reload php-fpm` |
| 新功能没生效 | `php artisan optimize:clear`；确认 `php-fpm` 真的 reload 了 |
| 队列任务跑的是旧代码 | `php artisan horizon:terminate`（或 `supervisorctl restart`） |
| 附件 404 | `public/attachments`、`public/bitbucket` 是指向真实目录的软链，`nexus:update` 会按 settings 重建；确认目标目录存在 |
| 更新后 `.env` 少变量 | `nexus:update` 会自动补 `.env.example` 里新增的键，不会覆盖已有值 |

**不要用** `php artisan nexus:update --tag=...`：它写死了从 `xiaomlove/nexusphp` 下载代码，
会把你的定制全部覆盖。需要代码更新时只用 `git fetch` + `reset --hard`。
