# NexusPHP 银行系统插件

一个功能完整的银行系统插件，为 NexusPHP 提供存款、贷款、利息管理等核心金融功能。

## ⚠️ 重要说明

- **版本兼容性**：本插件仅在 NexusPHP 1.9.6 版本测试，旧版本兼容性未知
- **授权要求**：本插件无需授权即可正常使用

## 功能特性

### 💰 存款服务
- **活期存款**：无固定期限，随时存取，按日计息
- **定期存款**：固定期限，不同期限享受不同利率
- **自动计息**：每日自动计算并发放利息
- **提前支取**：支持提前支取（定期需扣除手续费）

### 🏦 贷款服务
- **灵活额度**：贷款额度 = 时魔 × 系数 + 常数（可配置）
- **分档利率**：不同期限享受不同贷款利率
- **自动计息**：每日自动计算贷款利息
- **提前还款**：支持一次性结清（本金+应计利息）

### 📊 管理功能
- **实时统计**：用户资产概览、站点银行数据
- **利息记录**：完整的利息发放记录
- **到期提醒**：存款到期、贷款逾期提醒
- **自动处理**：逾期贷款自动扣款、到期存款自动返还

### 🎨 用户界面
- **现代设计**：简洁美观
- **响应式布局**：完美适配桌面和移动设备
- **实时数据**：动态显示应计利息、到期信息
- **友好提示**：清晰的操作说明和规则展示

## 安装指南

### 1. 下载插件

将插件下载到站点根目录的 `packages` 文件夹：

```bash
# 创建 packages 目录（如果不存在）
mkdir -p packages

# 将下载的插件解压到 packages 目录
# 确保目录结构为：packages/nexusphp-bank-system/
```

### 2. 修改 Composer 配置

在站点根目录的 `composer.json` 中需要添加两个部分：

#### 2.1 添加仓库配置
在 `repositories` 部分添加：
```json
{
    "repositories": {
        "bank-system": {
            "type": "path",
            "url": "./packages/nexusphp-bank-system"
        }
    }
}
```

#### 2.2 添加自动加载配置
在 `autoload.psr-4` 部分添加：
```json
{
    "autoload": {
        "psr-4": {
            "NexusPlugin\\BankSystem\\": "packages/nexusphp-bank-system/src/"
        }
    }
}
```

#### 2.3 添加依赖配置
在 `require` 或 `require-dev` 部分添加：
```json
{
    "require-dev": {
        "madrays/nexusphp-bank-system": "dev-main"
    }
}
```

**重要说明**：
- 插件必须放在 `packages/nexusphp-bank-system/` 目录下
- 文件夹名称 `nexusphp-bank-system` 不能修改
- 命名空间 `NexusPlugin\\BankSystem\\` 不能修改

#### 2.4 完整配置示例
你的 `composer.json` 应该包含以下部分：
```json
{
    "autoload": {
        "psr-4": {
            "Nexus\\": "nexus/",
            "App\\": "app/",
            "NexusPlugin\\BankSystem\\": "packages/nexusphp-bank-system/src/"
        }
    },
    "require-dev": {
        "madrays/nexusphp-bank-system": "dev-main"
    },
    "repositories": {
        "bank-system": {
            "type": "path",
            "url": "./packages/nexusphp-bank-system"
        }
    }
}
```

### 3. 安装插件

```bash
# 重新生成自动加载文件并清除缓存
composer dump-autoload && php artisan config:clear && php artisan cache:clear

# 重新加载自动加载
composer dump-autoload

# 安装插件
composer require madrays/nexusphp-bank-system

# 执行插件安装
php artisan plugin install madrays/nexusphp-bank-system
```

### 4. 运行数据库迁移

```bash
# 创建银行系统表
php artisan migrate --path=packages/nexusphp-bank-system/database/migrations/2024_01_01_000001_create_bank_tables.php --force

# 添加活期账户和存款类型
php artisan migrate --path=packages/nexusphp-bank-system/database/migrations/2024_01_01_000002_add_demand_accounts_and_deposit_type.php --force

# 扩展利息记录类型
php artisan migrate --path=packages/nexusphp-bank-system/database/migrations/2024_01_01_000003_alter_interest_records_add_demand.php --force
```

### 5. 设置定时任务

```bash
cd packages/nexusphp-bank-system

# 安装定时任务（每小时第5分钟执行）
./setup-cron.sh install --minute 5 --site-root /path/to/your/site

# 验证定时任务
./setup-cron.sh verify --site-root /path/to/your/site
```

### 6. 复制公共文件

如果安装后出现视图文件缺失错误，需要手动复制公共文件：

```bash
# 复制银行系统插件的公共文件到站点public目录
cp -r packages/nexusphp-bank-system/public/* public/
```

### 7. 清除缓存

```bash
php artisan config:clear
php artisan cache:clear
composer dump-autoload
```

## 配置说明

### 后台设置

进入 **设置 > 银行系统** 进行配置：

- **启用银行系统**：开启/关闭银行功能
- **活期日利率**：活期存款的日利率（小数形式，如 0.01 = 1%）
- **定期利率分档**：不同期限的定期存款利率
- **贷款利率分档**：不同期限的贷款利率
- **贷款额度系数**：时魔 × 系数 + 常数
- **最小金额限制**：存款/贷款的最小金额
- **提前支取手续费**：定期存款提前支取的手续费比例

### 定时任务

调度脚本：`run-bank-scheduler.sh`（包装 `bank-scheduler.php`，包含并发控制、日志与错误抑制）。

安装示例：

```bash
# 每分钟执行一次（开发/验证）
./setup-cron.sh install --minute '*/1' --site-root /path/to/your/site --log-file /path/to/your/site/packages/nexusphp-bank-system/logs/bank_scheduler.log

# 每小时第 5 分钟执行（生产推荐）
./setup-cron.sh install --minute 5 --site-root /path/to/your/site --log-file /path/to/your/site/packages/nexusphp-bank-system/logs/bank_scheduler.log
```

验证与查看：

```bash
./setup-cron.sh show
./setup-cron.sh verify --site-root /path/to/your/site --log-file /path/to/your/site/packages/nexusphp-bank-system/logs/bank_scheduler.log
```

系统每次运行会执行：

1. **活期计息**：将利息按天计入活期余额
2. **定期计息**：为 active 的定期入账利息（到期返本）
3. **贷款计息**：
   - 正常期：按“日利率”计息，封顶到到期日
   - 逾期期：按“日利率+罚息率”每日持续计息，直到结清
4. **逾期处理**：到期自动标记逾期
5. **自动扣款**：
   - 允许负余额=开启：达到阈值(`auto_deduct_days`)后一次性扣清
   - 允许负余额=关闭：逾期后每次调度都尽可能扣现有余额（先活期、再站点余额），直至还清
6. **存款到期返还与通知**：返还定期本金并推送通知（如开启）

### 策略总览（贷款计息与扣款）

- 放款当日：立即入账首日利息，`last_interest_date=今天`。
- 每日计息：比较 `last_interest_date` 与今天，若跨日则将区间天数一次性入账；正常期封顶到到期日；逾期期每日持续按“日利率+罚息率”计息。
- 展示口径：
  - 当前欠款=本金(`remaining_amount`)
  - 应计利息=已入账利息汇总（与数据库一致）
  - 一次性结清需=本金+应计利息
- 自动扣款两路径：
  1) 允许负余额=开：逾期天数≥`auto_deduct_days`后“一次性扣清”（先活期，再站点余额，可负）。
  2) 允许负余额=关：一旦逾期，每次定时任务都“尽可能扣除现有余额”（先活期，再站点余额），余额不足则保留欠款并持续扣到清零。

时序举例：

```
T0 放款 -> 立即入账首日利息
T1 每日调度 -> 若跨日则入账该自然日利息
Td 到期 -> 状态=overdue；之后每日按(日利率+罚息率)入账，直到结清
Tk 自动扣款 -> 依照两路径策略从活期→站点余额进行扣除
```

## 使用说明

### 用户操作

1. **访问银行**：点击导航菜单中的"银行系统"
2. **查看资产**：在概览页面查看个人资产状况
3. **存款操作**：
   - 活期：随时存取，无手续费
   - 定期：选择期限，享受更高利率
4. **贷款操作**：
   - 申请贷款：选择金额和期限
   - 提前还款：一次性结清本金+利息

### 管理员操作

1. **系统设置**：在后台配置各项参数
2. **数据监控**：查看银行系统运行状况
3. **日志查看**：监控定时任务执行情况

## 技术规格

- **PHP版本**：8.0+
- **数据库**：MySQL 5.7+
- **框架**：NexusPHP 1.7.21+
- **前端**：原生HTML/CSS/JavaScript
- **定时任务**：Cron（推荐每小时执行）

## 文件结构

```
nexusphp-bank-system/
├── src/                          # 核心业务逻辑
│   ├── BankSystemRepository.php  # 数据访问层
│   ├── BankScheduler.php         # 定时任务处理
│   ├── BankController.php        # 控制器
│   └── SettingsManager.php       # 设置管理
├── database/migrations/          # 数据库迁移
├── resources/views/              # 视图模板
├── public/                       # 公共文件
├── run-bank-scheduler.sh         # 定时任务脚本
├── setup-cron.sh                 # 定时任务安装工具
└── test-scheduler.sh             # 测试工具
```

## 故障排除

### 视图文件缺失

如果出现"视图文件不存在"或"模板文件缺失"错误：

```bash
# 手动复制公共文件
cp -r packages/nexusphp-bank-system/public/* public/

# 确保文件权限正确
chmod -R 755 public/bank*
```

### 定时任务未执行

```bash
# 检查定时任务状态
./setup-cron.sh show

# 手动测试执行
./test-scheduler.sh --site-root /path/to/site --debug

# 查看日志
tail -n 50 logs/bank_scheduler.log
```

### 数据库问题

```bash
# 检查表是否存在
SHOW TABLES LIKE 'bank_%';

# 检查利息记录
SELECT * FROM bank_interest_records WHERE calculation_date = CURDATE();
```

## 更新日志

### v1.0.0
- 初始版本发布
- 完整的存款/贷款功能
- 自动计息和到期处理
- 现代用户界面
- 定时任务管理工具

## 许可证

MIT License - 详见 [LICENSE](LICENSE) 文件

## 支持

如有问题或建议，请提交 Issue 或联系开发者。

## ☕ 请作者喝杯奶茶
如果这个项目对你有帮助，可以请作者喝杯奶茶，您的支持是我持续创作的动力 ❤️

<div align="center">
  <div style="display: inline-block; text-align: center; margin: 0 20px;">
    <img src="https://pic2.ziyuan.wang/user/madrays/2025/03/wechat-qr_3b12b18852890.jpg" alt="微信赞赏码" width="200"/>
    <p style="margin: 10px 0; font-size: 16px;">
      <span style="background: #07c160; color: white; padding: 4px 12px; border-radius: 4px;">微信赞赏</span>
    </p>
    <p style="color: #666; font-size: 14px;">感谢支持💗</p>
  </div>
  <div style="display: inline-block; text-align: center; margin: 0 20px;">
    <img src="https://pic2.ziyuan.wang/user/madrays/2025/03/alipay-qr_053c36d2fe096.jpg" alt="支付宝赞赏码" width="200"/>
    <p style="margin: 10px 0; font-size: 16px;">
      <span style="background: #1677ff; color: white; padding: 4px 12px; border-radius: 4px;">支付宝赞赏</span>
    </p>
    <p style="color: #666; font-size: 14px;">加大电力⚡️</p>
  </div>
</div>
