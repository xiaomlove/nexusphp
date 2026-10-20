<?php

namespace NexusPlugin\BankSystem;

use Nexus\Plugin\BasePlugin;

class BankSystemRepository extends BasePlugin
{
    const ID = 'nexusphp-bank-system';
    const VERSION = '1.0.0';
    const COMPATIBLE_NP_VERSION = '1.7.21';

    /**
     * 获取魔力别名（从站点翻译文件中获取）
     */
    public function getBonusName(): string
    {
        return nexus_trans('user.labels.seedbonus') ?: '魔力';
    }

    /**
     * 获取时魔（每小时魔力，包含所有加成）
     */
    public function getUserHourlyBonus(int $userId): float
    {
        // 使用calculate_seed_bonus函数获取完整的时魔计算结果
        $seedBonusResult = calculate_seed_bonus($userId);

        // 获取基础时魔
        $baseBonus = $seedBonusResult['seed_bonus'] ?? 0;

        // 获取各种加成因子
        $donortimes_bonus = get_setting('bonus.donortimes', 1);
        $officialAdditionalFactor = get_setting('bonus.official_addition', 0);
        $haremFactor = get_setting('bonus.harem_addition', 0);

        // 计算总时魔（包含所有加成）
        $totalBonus = $baseBonus;

        // 检查是否为捐赠者
        $sql = "SELECT * FROM users WHERE id = $userId";
        $result = \Nexus\Database\NexusDB::select($sql);
        $user = $result[0] ?? null;

        if ($user && is_donor($user) && $donortimes_bonus > 1) {
            $totalBonus *= $donortimes_bonus;
        }

        // 添加官方种子加成
        if ($officialAdditionalFactor > 0) {
            $officialAddition = ($seedBonusResult['official_bonus'] ?? 0) * $officialAdditionalFactor;
            $totalBonus += $officialAddition;
        }

        // 添加后宫加成
        if ($haremFactor > 0) {
            $haremBonus = $this->calculateHaremAddition($userId);
            $haremAddition = $haremBonus * $haremFactor;
            $totalBonus += $haremAddition;
        }

        // 添加勋章加成
        $medalAdditionalFactor = $seedBonusResult['medal_additional_factor'] ?? 0;
        if ($medalAdditionalFactor > 0) {
            $medalAddition = ($seedBonusResult['medal_bonus'] ?? 0) * $medalAdditionalFactor;
            $totalBonus += $medalAddition;
        }

        return floatval($totalBonus);
    }

    /**
     * 确保插件已安装（自动迁移和初始化）
     */
    public function ensureInstalled(): void
    {
        try {
            // 检查关键表是否存在
            $tables = ['bank_loans', 'bank_deposits', 'bank_interest_records', 'bank_demand_accounts'];
            $missingTables = [];
            
            foreach ($tables as $table) {
                $result = \Nexus\Database\NexusDB::select("SHOW TABLES LIKE '$table'");
                if (empty($result)) {
                    $missingTables[] = $table;
                }
            }
            
            if (!empty($missingTables)) {
                do_log("[BANK_SYSTEM] Missing tables: " . implode(', ', $missingTables) . ", running migrations...");
                $this->runMigrations(__DIR__ . '/../database/migrations');
            }
            
            // 确保设置已初始化
            $this->initializeSettingsIfNeeded();
            
            // 确保公共文件已复制
            $this->install();
            
        } catch (\Throwable $e) {
            do_log("[BANK_SYSTEM] ensureInstalled failed: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 插件安装时执行
     */
    public function install()
    {
        $this->runMigrations(__DIR__ . '/../database/migrations');
        // 注释掉设置初始化，避免在安装时调用saveSetting函数
        // $this->initializeSettings();
        do_log("Bank System Plugin installed successfully!");
    }

    /**
     * 插件启动时执行
     */
    public function boot(): void
    {
        $this->initializeSettingsIfNeeded();
        $this->registerHooks();
        do_log("Bank System Plugin booted!");
    }

    /**
     * 插件卸载时执行
     */
    public function uninstall()
    {
        $this->removeSettings();
        do_log("Bank System Plugin uninstalled successfully!");
    }

    /**
     * 初始化默认设置（仅在需要时）
     */
    private function initializeSettingsIfNeeded(): void
    {
        $defaultSettings = SettingsManager::getDefaultSettings();

        // 检查哪些设置不存在，只保存不存在的设置
        $settingsToSave = [];
        foreach ($defaultSettings as $key => $value) {
            $settingKey = 'bank_system.' . $key;
            if (get_setting($settingKey) === null) {
                $settingsToSave[$key] = $value;
            }
        }

        if (!empty($settingsToSave)) {
            $this->saveSettingsDirectly('bank_system', $settingsToSave);
        }
    }

    /**
     * 直接保存设置到数据库，避免使用saveSetting函数
     */
    private function saveSettingsDirectly(string $prefix, array $nameAndValue): void
    {
        try {
            $data = [];
            $datetimeNow = date('Y-m-d H:i:s');

            foreach ($nameAndValue as $name => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $data[] = [
                    'name' => "$prefix.$name",
                    'value' => $value,
                    'created_at' => $datetimeNow,
                    'updated_at' => $datetimeNow,
                    'autoload' => 'yes'
                ];
            }

            if (!empty($data)) {
                // 使用Laravel的upsert方法，避免使用原始SQL和sqlesc函数
                \App\Models\Setting::query()->upsert($data, ['name'], ['value', 'updated_at']);
                clear_setting_cache();
            }
        } catch (\Exception $e) {
            do_log("Bank System: Failed to save settings directly: " . $e->getMessage());
        }
    }

    /**
     * 删除设置
     */
    private function removeSettings(): void
    {
        $sql = "DELETE FROM settings WHERE name LIKE 'bank_system.%'";
        \Nexus\Database\NexusDB::statement($sql);
    }

    /**
     * 注册Hook
     */
    protected function registerHooks(): void
    {
        global $hook;

        // 添加银行系统链接到导航菜单
        $hook->addAction('nexus_header', [$this, 'addBankSystemLink'], 10, 0);

        // 添加设置标签页
        $hook->addFilter('nexus_setting_tabs', [$this, 'addSettingTab'], 10, 1);
    }

    /**
     * 添加银行系统链接到菜单末尾
     */
    public function addBankSystemLink(): void
    {
        try {
            // 检查银行系统是否启用
            $enabled = get_setting('bank_system.enabled', false);
            if (!$enabled) {
                return;
            }

            // 检查导航开关设置
            $showInNav = get_setting('bank_system.show_in_navigation', true);
            if (!$showInNav) {
                return;
            }

            // 依据最低用户等级控制可见性
            global $CURUSER;
            $minClass = get_setting('bank_system.min_user_class', \App\Models\User::CLASS_USER);
            if (empty($CURUSER) || (int)($CURUSER['class'] ?? -1) < (int)$minClass) {
                return;
            }

            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var mainMenu = document.querySelector("#mainmenu");
                if (mainMenu) {
                    var bankLi = document.createElement("li");
                    var bankLink = document.createElement("a");
                    bankLink.href = "bank.php";
                    bankLink.innerHTML = "&nbsp;银行系统&nbsp;";
                    bankLi.appendChild(bankLink);
                    mainMenu.appendChild(bankLi);
                }
            });
            </script>';
        } catch (\Throwable $e) {
            // 静默处理错误，避免影响页面加载
            do_log("[BANK_SYSTEM] addBankSystemLink failed: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 添加设置标签页
     */
    public function addSettingTab(array $tabs): array
    {
        try {
            $tabs[] = SettingsManager::getSettingTab();
            return $tabs;
        } catch (\Throwable $e) {
            do_log("[BANK_SYSTEM] addSettingTab() failed: " . $e->getMessage(), 'error');
            return $tabs;
        }
    }

    /**
     * 计算后宫加成（兼容版本）
     */
    private function calculateHaremAddition(int $userId): float
    {
        try {
            $sql = "SELECT SUM(seed_points_per_hour) as addition FROM users
                    WHERE invited_by = $userId
                    AND status = " . \App\Models\User::STATUS_CONFIRMED . "
                    AND enabled = " . \App\Models\User::ENABLED_YES;
            $result = \Nexus\Database\NexusDB::select($sql);

            $addition = floatval($result[0]['addition'] ?? 0);
            do_log("[BANK_SYSTEM_HAREM_ADDITION], user: $userId, addition: $addition");
            return $addition;
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to calculate harem addition: " . $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * 获取用户当前魔力
     */
    public function getUserBonus(int $userId): float
    {
        $sql = "SELECT seedbonus FROM users WHERE id = $userId";
        $result = \Nexus\Database\NexusDB::select($sql);

        return floatval($result[0]['seedbonus'] ?? 0);
    }

    /**
     * 更新用户魔力
     */
    public function updateUserBonus(int $userId, float $amount, string $comment = ''): bool
    {
        try {
            $sql = "UPDATE users SET seedbonus = seedbonus + $amount WHERE id = $userId";
            \Nexus\Database\NexusDB::statement($sql);

            // 记录魔力变动日志
            if (!empty($comment)) {
                $this->logBonusChange($userId, $amount, $comment);
            }

            return true;
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to update user bonus: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 记录魔力变动日志
     */
    private function logBonusChange(int $userId, float $amount, string $comment): void
    {
        try {
            $comment = addslashes($comment);
            $sql = "INSERT INTO bonus_comments (uid, bonus, comment, added_at) VALUES ($userId, $amount, '$comment', NOW())";
            \Nexus\Database\NexusDB::statement($sql);
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to log bonus change: " . $e->getMessage(), 'error');
        }
    }

    /**
     * 计算最大贷款额度（基于时魔）
     */
    public function calculateMaxLoanAmount(int $userId): float
    {
        $hourlyBonus = $this->getUserHourlyBonus($userId);
        $loanRatio = (float)(get_setting('bank_system.loan_ratio') ?: 24);
        $loanConstant = (float)(get_setting('bank_system.loan_ratio_constant') ?: 0);
        return $hourlyBonus * $loanRatio + $loanConstant;
    }

    /**
     * 检查用户是否有权限使用银行
     */
    public function checkUserPermission(array $user): bool
    {
        $minClass = get_setting('bank_system.min_user_class') ?: 1;
        return $user['class'] >= $minClass;
    }

    /**
     * 获取用户当前贷款信息
     */
    public function getUserLoan(int $userId): ?array
    {
        $sql = "SELECT * FROM bank_loans WHERE user_id = $userId AND status IN ('active', 'overdue') ORDER BY created_at DESC LIMIT 1";
        $result = \Nexus\Database\NexusDB::select($sql);

        return $result[0] ?? null;
    }

    /**
     * 创建贷款
     */
    public function createLoan(int $userId, float $amount, float $interestRate, int $termDays): bool
    {
        try {
            $dueDate = date('Y-m-d H:i:s', strtotime("+{$termDays} days"));
            $createdAt = date('Y-m-d H:i:s');

            $sql = "INSERT INTO bank_loans (user_id, amount, interest_rate, term_days, remaining_amount, due_date, status, created_at, updated_at)
                    VALUES ($userId, $amount, $interestRate, $termDays, $amount, '$dueDate', 'active', '$createdAt', '$createdAt')";
            \Nexus\Database\NexusDB::statement($sql);

            // 发放贷款金额
            $bonusName = $this->getBonusName();
            $this->updateUserBonus($userId, $amount, "银行贷款：{$amount} {$bonusName}");

            // 借款当日立即入账一天利息，并推进 last_interest_date = 今天
            $row = \Nexus\Database\NexusDB::select("SELECT id FROM bank_loans WHERE user_id = $userId ORDER BY id DESC LIMIT 1");
            $loanId = (int)($row[0]['id'] ?? 0);
            if ($loanId > 0 && $interestRate > 0) {
                $todayYmd = date('Y-m-d');
                $dailyInterest = round($amount * $interestRate, 2);
                if ($dailyInterest > 0) {
                    $this->recordInterest($userId, 'loan', $loanId, $dailyInterest, $interestRate);
                }
                $upd = "UPDATE bank_loans SET last_interest_date = '$todayYmd', updated_at = NOW() WHERE id = $loanId";
                \Nexus\Database\NexusDB::statement($upd);
            }

            return true;
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to create loan: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 还款
     */
    public function repayLoan(int $userId, float $amount): array
    {
        $loan = $this->getUserLoan($userId);
        if (!$loan) {
            return ['success' => false, 'message' => '没有找到有效的贷款记录'];
        }

        $userBonus = $this->getUserBonus($userId);
        if ($userBonus < $amount) {
            $bonusName = $this->getBonusName();
            return ['success' => false, 'message' => "{$bonusName}不足"];
        }

        try {
            // 扣除还款金额
            $bonusName = $this->getBonusName();
            $this->updateUserBonus($userId, -$amount, "银行还款：{$amount} {$bonusName}");

            // 更新贷款记录
            $newBalance = $loan['remaining_amount'] - $amount;
            $updatedAt = date('Y-m-d H:i:s');

            if ($newBalance <= 0) {
                // 贷款已还清
                $sql = "UPDATE bank_loans SET remaining_amount = 0, status = 'paid', paid_at = '$updatedAt', updated_at = '$updatedAt' WHERE id = {$loan['id']}";
                \Nexus\Database\NexusDB::statement($sql);
                $message = '贷款已全部还清！';
            } else {
                $sql = "UPDATE bank_loans SET remaining_amount = $newBalance, updated_at = '$updatedAt' WHERE id = {$loan['id']}";
                \Nexus\Database\NexusDB::statement($sql);
                $message = "还款成功，剩余欠款：{$newBalance} {$bonusName}";
            }

            return ['success' => true, 'message' => $message];
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to repay loan: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => '还款失败，请稍后重试'];
        }
    }

    /**
     * 创建存款
     */
    public function createDeposit(int $userId, float $amount, float $interestRate, int $termDays, string $type = 'fixed'): bool
    {
        $userBonus = $this->getUserBonus($userId);
        if ($userBonus < $amount) {
            return false;
        }

        try {
            $createdAt = date('Y-m-d H:i:s');

            // 扣除存款金额
            $bonusName = $this->getBonusName();
            $this->updateUserBonus($userId, -$amount, "银行存款：{$amount} {$bonusName}");

            if ($type === 'demand') {
                // 活期：增加活期账户余额，不写入 bank_deposits
                $account = $this->getOrCreateDemandAccount($userId, $interestRate);
                $now = $createdAt;
                $newRate = $account['interest_rate'] > 0 ? $account['interest_rate'] : $interestRate;
                // 为避免下次调度按历史天数补发，存入当日将 last_interest_date 推进为今天
                $sql = "UPDATE bank_demand_accounts 
                        SET balance = balance + $amount, 
                            interest_rate = $newRate, 
                            last_interest_date = IF(last_interest_date IS NULL OR last_interest_date < CURDATE(), CURDATE(), last_interest_date),
                            updated_at = '$now' 
                        WHERE user_id = $userId";
                \Nexus\Database\NexusDB::statement($sql);
                return true;
            } else {
                // 定期：写入 bank_deposits，标记 type=fixed
                $maturityDate = date('Y-m-d H:i:s', strtotime("+{$termDays} days"));
                $sql = "INSERT INTO bank_deposits (user_id, amount, interest_rate, term_days, maturity_date, type, status, created_at, updated_at)
                        VALUES ($userId, $amount, $interestRate, $termDays, '$maturityDate', 'fixed', 'active', '$createdAt', '$createdAt')";
                \Nexus\Database\NexusDB::statement($sql);
                return true;
            }
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to create deposit: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 获取用户存款列表
     */
    public function getUserDeposits(int $userId): array
    {
        $sql = "SELECT * FROM bank_deposits WHERE user_id = $userId ORDER BY created_at DESC";
        return \Nexus\Database\NexusDB::select($sql);
    }

    /**
     * 获取用户贷款历史（含进行中、已结清、逾期等）
     */
    public function getUserLoanHistory(int $userId): array
    {
        $loans = \Nexus\Database\NexusDB::select("SELECT * FROM bank_loans WHERE user_id = $userId ORDER BY created_at DESC");
        if (empty($loans)) { return []; }

        // 汇总每笔贷款的利息记录
        $sumRows = \Nexus\Database\NexusDB::select(
            "SELECT reference_id, COALESCE(SUM(amount),0) AS s 
             FROM bank_interest_records 
             WHERE user_id = $userId AND type='loan' 
             GROUP BY reference_id"
        );
        $loanIdToInterest = [];
        foreach ($sumRows as $r) {
            $loanIdToInterest[(int)$r['reference_id']] = (float)$r['s'];
        }

        // 注入利息汇总字段
        foreach ($loans as &$loan) {
            $lid = (int)$loan['id'];
            $loan['interest_sum'] = $loanIdToInterest[$lid] ?? 0.0;
        }
        unset($loan);

        return $loans;
    }

    /**
     * 聚合仪表盘数据（供 public/bank.php 旧入口使用）
     */
    public function getDashboardData(int $userId): array
    {
        $settings = $this->getSettings();
        $bonusName = $this->getBonusName();
        $currentBonus = $this->getUserBonus($userId);
        $hourlyBonus = $this->getUserHourlyBonus($userId);
        $maxLoanAmount = $this->calculateMaxLoanAmount($userId);
        $currentLoan = $this->getUserLoan($userId);
        $deposits = $this->getUserDeposits($userId);
        $demandAccount = $this->getOrCreateDemandAccount($userId, (float)($settings['demand_interest_rate'] ?? 0.0));

        // 为定期存款补充"应计至今"展示字段
        $nowTs = time();
        foreach ($deposits as &$dep) {
            if (($dep['status'] ?? '') === 'active' && (($dep['type'] ?? 'fixed') === 'fixed')) {
                $startStr = $dep['last_interest_date'] ?: $dep['created_at'];
                $startTs = strtotime(date('Y-m-d', strtotime($startStr)));
                // 利息计算截止到前一天，不包含当天
                $endTs = strtotime(date('Y-m-d', $nowTs - 86400));
                if (!empty($dep['maturity_date'])) {
                    $matTs = strtotime($dep['maturity_date']);
                    if ($matTs < $endTs) { $endTs = $matTs; }
                }
                $elapsed = max(0, $endTs - $startTs);
                $daysFloat = $elapsed / 86400;
                $dep['accrued_theoretical'] = round((float)$dep['amount'] * (float)$dep['interest_rate'] * $daysFloat, 2);
            }
        }
        unset($dep);

        // 贷款应计利息（已入账）与一次性结清额
        $loanAccrued = 0.0;
        $loanPayoff = null;
        if ($currentLoan && in_array($currentLoan['status'], ['active','overdue'], true)) {
            // 已入账利息（从利息记录汇总）
            $loanId = (int)$currentLoan['id'];
            $sumRows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(amount),0) AS s FROM bank_interest_records WHERE type='loan' AND reference_id = $loanId");
            $loanAccrued = round((float)($sumRows[0]['s'] ?? 0), 2);
            $loanPayoff = round((float)$currentLoan['remaining_amount'] + $loanAccrued, 2);
        }

        $fixedActive = $this->getUserFixedActiveStats($userId);
        $siteOverview = $this->getSiteOverview();
        $recentInterest = $this->getRecentInterest($userId, 9);
        $maturingDeposits = $this->getMaturingDeposits($userId, 7);
        $loanHistory = $this->getUserLoanHistory($userId);

        $settingsForDisplay = [
            'demand_interest_rate' => (float)($settings['demand_interest_rate'] ?? 0),
            'loan_interest_rates' => $settings['loan_interest_rates'] ?? [],
            'fixed_deposit_rates' => $settings['fixed_deposit_rates'] ?? [],
            'loan_ratio' => (float)($settings['loan_ratio'] ?? 24),
            'loan_ratio_constant' => (float)($settings['loan_ratio_constant'] ?? 0),
            'overdue_penalty_rate' => (float)($settings['overdue_penalty_rate'] ?? 0),
        ];

        // 分别处理贷款和存款利率，不混合
        $loanRates = [];
        foreach (($settings['loan_interest_rates'] ?? []) as $r) {
            $loanRates[] = [
                'term_days' => (int)($r['term_days'] ?? 0),
                'loan_rate' => (float)($r['loan_rate'] ?? 0),
            ];
        }
        
        $depositRates = [];
        foreach (($settings['fixed_deposit_rates'] ?? []) as $r) {
            $depositRates[] = [
                'term_days' => (int)($r['term_days'] ?? 0),
                'deposit_rate' => (float)($r['interest_rate'] ?? 0),
            ];
        }
        
        // 为了向后兼容，保留混合数组（但只用于显示，不用于表单选择）
        $termMap = [];
        foreach ($depositRates as $r) {
            $td = $r['term_days'];
            if (!isset($termMap[$td])) { $termMap[$td] = ['term_days' => $td]; }
            $termMap[$td]['deposit_rate'] = $r['deposit_rate'];
        }
        foreach ($loanRates as $r) {
            $td = $r['term_days'];
            if (!isset($termMap[$td])) { $termMap[$td] = ['term_days' => $td]; }
            $termMap[$td]['loan_rate'] = $r['loan_rate'];
        }
        ksort($termMap);
        $interestRates = array_values($termMap);

        $data = [
            'bonusName' => $bonusName,
            'currentBonus' => $currentBonus,
            'userHourlyBonus' => $hourlyBonus,
            'maxLoanAmount' => $maxLoanAmount,
            'currentLoan' => $currentLoan,
            'loanAccrued' => $loanAccrued,
            'loanPayoff' => $loanPayoff,
            'deposits' => $deposits,
            'demandAccount' => $demandAccount,
            // 展示用活期利率以后台设置为准（小数），避免受旧账户利率影响
            'demandInterestRate' => (float)($settings['demand_interest_rate'] ?? ($demandAccount['interest_rate'] ?? 0)),
            'interestRates' => $interestRates, // 混合数组，仅用于显示
            'loanRates' => $loanRates, // 纯贷款利率数组
            'depositRates' => $depositRates, // 纯存款利率数组
            'minLoanAmount' => (float)($settings['min_loan_amount'] ?? 1000),
            'minDepositAmount' => (float)($settings['min_fixed_amount'] ?? 1000),
            'penaltyRate' => (float)($settings['early_withdrawal_penalty'] ?? 0.1),
            'recentInterest' => $recentInterest,
            'maturingDeposits' => $maturingDeposits,
            'siteOverview' => $siteOverview,
            'settingsForDisplay' => $settingsForDisplay,
            'loanHistory' => $loanHistory,
            'userOverview' => [
                'bonus' => $currentBonus,
                'demand_balance' => (float)($demandAccount['balance'] ?? 0),
                'fixed_active_total' => (float)$fixedActive['total'],
                'fixed_active_count' => (int)$fixedActive['count'],
                'loan_outstanding' => (float)($currentLoan['remaining_amount'] ?? 0),
                'hourly_bonus' => $hourlyBonus,
                'total_asset' => $currentBonus + (float)($demandAccount['balance'] ?? 0) + (float)$fixedActive['total'],
                'net_asset' => $currentBonus + (float)($demandAccount['balance'] ?? 0) + (float)$fixedActive['total'] - (float)($currentLoan['remaining_amount'] ?? 0),
            ],
            'fixedActiveCount' => (int)$fixedActive['count'],
            'fixedActiveTotal' => (float)$fixedActive['total'],
        ];

        return $data;
    }

	private function getUserFixedActiveStats(int $userId): array
	{
		$sql = "SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS s FROM bank_deposits WHERE user_id = $userId AND type='fixed' AND status='active'";
		$res = \Nexus\Database\NexusDB::select($sql);
		return [
			'count' => (int)($res[0]['c'] ?? 0),
			'total' => (float)($res[0]['s'] ?? 0),
		];
	}

	public function getOrCreateDemandAccount(int $userId, float $defaultRate): array
	{
		$rows = \Nexus\Database\NexusDB::select("SELECT * FROM bank_demand_accounts WHERE user_id = $userId LIMIT 1");
		if (!empty($rows)) return $rows[0];
		$rate = $defaultRate > 0 ? $defaultRate : 0.0;
		$now = date('Y-m-d H:i:s');
		$sql = "INSERT INTO bank_demand_accounts (user_id, balance, interest_rate, last_interest_date, created_at, updated_at) VALUES ($userId, 0, $rate, NULL, '$now', '$now')";
		\Nexus\Database\NexusDB::statement($sql);
		$rows = \Nexus\Database\NexusDB::select("SELECT * FROM bank_demand_accounts WHERE user_id = $userId LIMIT 1");
		return $rows[0] ?? ['user_id' => $userId, 'balance' => 0, 'interest_rate' => $rate, 'last_interest_date' => null];
	}

	private function getSiteOverview(): array
	{
		$rows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(balance),0) AS demand_total, COUNT(CASE WHEN balance>0 THEN 1 END) AS demand_count FROM bank_demand_accounts");
		$demand_total = (float)($rows[0]['demand_total'] ?? 0);
		$demand_count = (int)($rows[0]['demand_count'] ?? 0);
		$rows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(amount),0) AS s, COUNT(*) AS c FROM bank_deposits WHERE type='fixed' AND status='active'");
		$fixed_active_total = (float)($rows[0]['s'] ?? 0);
		$fixed_count = (int)($rows[0]['c'] ?? 0);
		$rows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(remaining_amount),0) AS s, COUNT(*) AS c FROM bank_loans WHERE status IN ('active','overdue')");
		$loan_outstanding = (float)($rows[0]['s'] ?? 0);
		$loan_count = (int)($rows[0]['c'] ?? 0);
		return compact('demand_total','demand_count','fixed_active_total','fixed_count','loan_outstanding','loan_count');
	}

	private function getRecentInterest(int $userId, int $days = 9): array
	{
		$start = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
		$sql = "SELECT calculation_date AS date,
					COALESCE(SUM(CASE WHEN type IN ('deposit','demand') THEN amount ELSE 0 END),0) AS deposit_amount,
					COALESCE(SUM(CASE WHEN type = 'loan' THEN amount ELSE 0 END),0) AS loan_amount
				FROM bank_interest_records
				WHERE user_id = $userId AND calculation_date >= '$start'
				GROUP BY calculation_date
				ORDER BY calculation_date ASC";
		$rows = \Nexus\Database\NexusDB::select($sql);
		// 兼容字段：amount = 净值（存款利息 - 贷款利息）
		foreach ($rows as &$r) {
			$dep = (float)($r['deposit_amount'] ?? 0);
			$loan = (float)($r['loan_amount'] ?? 0);
			$r['amount'] = round($dep - $loan, 2);
		}
		unset($r);
		return $rows;
	}

	private function getMaturingDeposits(int $userId, int $days = 7): array
	{
		$sql = "SELECT id, amount, maturity_date FROM bank_deposits WHERE user_id = $userId AND type='fixed' AND status='active' AND maturity_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL $days DAY) ORDER BY maturity_date ASC";
		return \Nexus\Database\NexusDB::select($sql);
	}

	/**
	 * 活期支取：从活期账户转回站点余额
	 */
	public function withdrawDemand(int $userId, float $amount): array
	{
		if ($amount <= 0) {
			return ['success' => false, 'message' => '金额必须大于0'];
		}

		try {
			// 读取默认活期利率（小数）用于账户初始化
			$raw = (float)(get_setting('bank_system.demand_interest_rate') ?: 0);
			$defaultRate = $raw > 1 ? ($raw / 100.0) : $raw;
			$account = $this->getOrCreateDemandAccount($userId, $defaultRate);
			$balance = (float)($account['balance'] ?? 0);

			if ($balance < $amount) {
				return ['success' => false, 'message' => '活期余额不足'];
			}

			// 扣减活期余额
			$newBal = $balance - $amount;
			$now = date('Y-m-d H:i:s');
			$upd = "UPDATE bank_demand_accounts SET balance = $newBal, updated_at = '$now' WHERE id = {$account['id']}";
			\Nexus\Database\NexusDB::statement($upd);

			// 增加用户站点余额
			$bonusName = $this->getBonusName();
			$this->updateUserBonus($userId, $amount, "活期支取：{$amount} {$bonusName}");

			return ['success' => true, 'message' => "支取成功，已转入 {$amount} {$bonusName}"];
		} catch (\Throwable $e) {
			do_log('[BANK_SYSTEM] withdrawDemand failed: ' . $e->getMessage(), 'error');
			return ['success' => false, 'message' => '支取失败，请稍后重试'];
		}
	}

    /**
     * 获取插件设置（向后兼容 public/bank.php 调用）
     */
    public function getSettings(): array
    {
        try {
            $rows = \Nexus\Database\NexusDB::select("SELECT name, value FROM settings WHERE name LIKE 'bank_system.%'");
            $settings = [];
            foreach ($rows as $row) {
                $key = substr($row['name'], strlen('bank_system.'));
                $val = $row['value'];
                // 尝试解析 JSON（例如利率分档）
                $decoded = json_decode($val, true);
                $settings[$key] = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
            }

            // 默认值补全
            $defaults = [
                'enabled' => true,
                'show_in_navigation' => true,
                'demand_interest_rate' => 1, // 百分比输入：1 表示 1%
                'loan_ratio' => 24,
                'loan_ratio_constant' => 0,
                'early_withdrawal_penalty' => 0.1,
                'min_loan_amount' => 1000,
                'min_fixed_amount' => 1000,
                'min_demand_amount' => 100,
                'fixed_deposit_rates' => [],
                'loan_interest_rates' => [],
            ];
            foreach ($defaults as $k => $v) {
                if (!array_key_exists($k, $settings) || $settings[$k] === '' || $settings[$k] === null) {
                    $settings[$k] = $v;
                }
            }

            // 类型转换和百分比到小数转换
            // 后台以百分比输入，这里统一转换为小数参与计算
            $settings['demand_interest_rate'] = (float)$settings['demand_interest_rate'] / 100;
            $settings['loan_ratio'] = (float)$settings['loan_ratio'];
            $settings['loan_ratio_constant'] = (float)$settings['loan_ratio_constant'];
            $settings['early_withdrawal_penalty'] = (float)$settings['early_withdrawal_penalty'] / 100;
            $settings['overdue_penalty_rate'] = (float)$settings['overdue_penalty_rate'] / 100;
            
            // 转换分级利率
            if (isset($settings['loan_interest_rates']) && is_array($settings['loan_interest_rates'])) {
                foreach ($settings['loan_interest_rates'] as &$rate) {
                    if (isset($rate['loan_rate'])) {
                        $rate['loan_rate'] = (float)$rate['loan_rate'] / 100;
                    }
                }
            }
            
            if (isset($settings['fixed_deposit_rates']) && is_array($settings['fixed_deposit_rates'])) {
                foreach ($settings['fixed_deposit_rates'] as &$rate) {
                    if (isset($rate['interest_rate'])) {
                        $rate['interest_rate'] = (float)$rate['interest_rate'] / 100;
                    }
                }
            }

            return $settings;
        } catch (\Throwable $e) {
            do_log('[BANK_SYSTEM] getSettings failed: ' . $e->getMessage(), 'error');
            return [
                'enabled' => true,
                // 兜底：小数形式（1%）
                'demand_interest_rate' => 0.01,
                'loan_ratio' => 24,
                'loan_ratio_constant' => 0,
                'early_withdrawal_penalty' => 0.1,
                'min_loan_amount' => 1000,
                'min_fixed_amount' => 1000,
                'min_demand_amount' => 100,
                'fixed_deposit_rates' => [],
                'loan_interest_rates' => [],
            ];
        }
    }

    /**
     * 提前支取存款
     */
    public function withdrawDeposit(int $depositId, int $userId): array
    {
        $sql = "SELECT * FROM bank_deposits WHERE id = $depositId AND user_id = $userId AND status = 'active' LIMIT 1";
        $result = \Nexus\Database\NexusDB::select($sql);
        $deposit = $result[0] ?? null;

        if (!$deposit) {
            return ['success' => false, 'message' => '存款记录不存在'];
        }

        try {
            // 使用统一的设置读取，确保百分比已转换为小数
            $settings = $this->getSettings();
            $penaltyRate = (float)($settings['early_withdrawal_penalty'] ?? 0.1); // 小数，如 0.01 代表 1%
            $penalty = $deposit['amount'] * $penaltyRate;
            $returnAmount = $deposit['amount'] - $penalty;

            // 返还存款（扣除手续费）
            $bonusName = $this->getBonusName();
            $this->updateUserBonus($userId, $returnAmount, "提前支取存款：{$returnAmount} {$bonusName}（手续费：{$penalty}）");

            // 更新存款状态
            $withdrawnAt = date('Y-m-d H:i:s');
            $sql = "UPDATE bank_deposits SET status = 'withdrawn', withdrawn_at = '$withdrawnAt', penalty = $penalty, updated_at = '$withdrawnAt' WHERE id = $depositId";
            \Nexus\Database\NexusDB::statement($sql);

            return ['success' => true, 'message' => "提前支取成功，扣除手续费 {$penalty} {$bonusName}"];
        } catch (\Exception $e) {
            do_log("[BANK_SYSTEM] Failed to withdraw deposit: " . $e->getMessage(), 'error');
            return ['success' => false, 'message' => '支取失败，请稍后重试'];
        }
    }

    /**
     * 记录利息（供控制器与调度器复用）
     */
    public function recordInterest(int $userId, string $type, int $referenceId, float $amount, float $rate): void
    {
        try {
            $calculationDate = date('Y-m-d');
            $createdAt = date('Y-m-d H:i:s');
            $type = addslashes($type);
            $sql = "INSERT INTO bank_interest_records (user_id, type, reference_id, amount, rate, calculation_date, created_at, updated_at)
                    VALUES ($userId, '$type', $referenceId, $amount, $rate, '$calculationDate', '$createdAt', '$createdAt')";
            \Nexus\Database\NexusDB::statement($sql);
        } catch (\Throwable $e) {
            do_log('[BANK_SYSTEM] recordInterest failed: ' . $e->getMessage(), 'error');
        }
    }
}
