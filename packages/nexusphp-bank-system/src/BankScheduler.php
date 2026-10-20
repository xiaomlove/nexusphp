<?php

namespace NexusPlugin\BankSystem;

class BankScheduler
{
    private BankSystemRepository $bankRepo;

    public function __construct()
    {
        $this->bankRepo = new BankSystemRepository();
        // 确保插件已安装并初始化
        $this->bankRepo->ensureInstalled();
    }

    /**
     * 处理每日银行任务
     */
    public function handleDailyTasks(): void
    {
        if (!get_setting('bank_system.enabled')) {
            do_log("[BANK_SCHEDULER] Bank system is disabled, skipping tasks");
            return;
        }

        do_log("[BANK_SCHEDULER] Starting daily tasks at " . date('Y-m-d H:i:s'));

        // 活期计息应最先处理，确保与后续统计一致
        $this->processDemandInterest();
        $this->processDepositInterest();
        $this->processLoanInterest();
        $this->processOverdueLoans();
        $this->processMaturedDeposits();
        $this->sendNotifications();
        
        do_log("[BANK_SCHEDULER] Completed daily tasks at " . date('Y-m-d H:i:s'));
    }

    /**
     * 处理存款利息
     */
    private function processDepositInterest(): void
    {
        $today = date('Y-m-d');
        
        // 获取所有活跃的定期存款（type='fixed'）
        $sql = "SELECT * FROM bank_deposits WHERE status = 'active' AND type = 'fixed' AND (last_interest_date IS NULL OR last_interest_date < '$today')";
        $deposits = \Nexus\Database\NexusDB::select($sql);

        foreach ($deposits as $deposit) {
            $this->calculateAndPayDepositInterest($deposit);
        }

        do_log("[BANK_SCHEDULER] Processed deposit interest for " . count($deposits) . " deposits");
    }

    /**
     * 计算并发放存款利息
     */
    private function calculateAndPayDepositInterest(array $deposit): void
    {
        $today = date('Y-m-d');
        $lastInterestDate = $deposit['last_interest_date'] ?: date('Y-m-d', strtotime($deposit['created_at']));

        // 计息截止到期日前一天（到期日不再继续计息）
        $endDate = $today;
        if (!empty($deposit['maturity_date'])) {
            $maturityYmd = date('Y-m-d', strtotime($deposit['maturity_date']));
            if ($maturityYmd < $endDate) {
                $endDate = $maturityYmd;
            }
        }

        // 计算需要计息的天数
        $daysDiff = (strtotime($endDate) - strtotime($lastInterestDate)) / 86400;

        if ($daysDiff >= 1 && (float)$deposit['interest_rate'] > 0) {
            $days = (int)floor($daysDiff);
            $dailyInterest = (float)$deposit['amount'] * (float)$deposit['interest_rate'];
            $totalInterest = $dailyInterest * $days;

            if ($totalInterest > 0) {
                // 发放利息到用户余额
                $bonusName = $this->bankRepo->getBonusName();
                $this->bankRepo->updateUserBonus(
                    (int)$deposit['user_id'],
                    (float)$totalInterest,
                    "存款利息：{$totalInterest} {$bonusName}"
                );

                // 记录利息
                $this->recordInterest((int)$deposit['user_id'], 'deposit', (int)$deposit['id'], (float)$totalInterest, (float)$deposit['interest_rate']);

                // 将最后计息日期推进到endDate
                $sql = "UPDATE bank_deposits SET last_interest_date = '$endDate', updated_at = NOW() WHERE id = {$deposit['id']}";
                \Nexus\Database\NexusDB::statement($sql);
            }
        }
    }

    /**
     * 处理活期账户利息（计入活期余额）
     */
    private function processDemandInterest(): void
    {
        $today = date('Y-m-d');
        $debug = getenv('BANK_DEBUG');
        $debugOn = ($debug === false || $debug === '' || $debug === '1' || strtolower((string)$debug) === 'true');

        // 仅处理有余额、且今天尚未计息的活期账户
        $sql = "SELECT * FROM bank_demand_accounts WHERE balance > 0 AND (last_interest_date IS NULL OR last_interest_date < '$today')";
        $accounts = \Nexus\Database\NexusDB::select($sql);

        $processed = 0;
        foreach ($accounts as $acc) {
            $lastInterestDate = $acc['last_interest_date'] ?: date('Y-m-d', strtotime($acc['created_at']));
            $daysDiff = (strtotime($today) - strtotime($lastInterestDate)) / 86400;
            if ($daysDiff < 1) {
                if ($debugOn) {
                    echo "[DEMAND] skip id={$acc['id']} daysDiff=".number_format($daysDiff,2)." last=".$lastInterestDate." today=$today\n";
                }
                continue;
            }

            // 优先使用账户自带利率；否则退回设置项
            $dailyRate = (float)($acc['interest_rate'] ?? 0);
            if ($dailyRate <= 0) {
                // 后台以百分比存储，调度时需转换为小数
                $raw = (float)(get_setting('bank_system.demand_interest_rate') ?: 0);
                $dailyRate = $raw > 1 ? ($raw / 100.0) : $raw;
            }

            if ($dailyRate <= 0) {
                if ($debugOn) {
                    echo "[DEMAND] skip id={$acc['id']} rate<=0\n";
                }
                continue;
            }

            $days = (int)floor($daysDiff);
            $totalInterest = (float)$acc['balance'] * $dailyRate * $days;

            if ($totalInterest > 0) {
                $newBalance = $acc['balance'] + $totalInterest;
                $upd = "UPDATE bank_demand_accounts SET balance = $newBalance, last_interest_date = '$today', updated_at = NOW() WHERE id = {$acc['id']}";
                \Nexus\Database\NexusDB::statement($upd);
                if ($debugOn) {
                    echo "[DEMAND] updated id={$acc['id']} old_bal={$acc['balance']} new_bal=$newBalance days=$days rate=$dailyRate interest=$totalInterest\n";
                }

                // 记录利息（type=demand，参考ID为活期账户ID）
                $this->recordInterest((int)$acc['user_id'], 'demand', (int)$acc['id'], $totalInterest, $dailyRate);
                $processed++;
            }
        }

        if ($processed > 0) {
            do_log("[BANK_SCHEDULER] Processed demand interest for $processed accounts");
        }
    }

    /**
     * 处理贷款利息
     */
    private function processLoanInterest(): void
    {
        $today = date('Y-m-d');
        
        // 获取所有活跃贷款
        $sql = "SELECT * FROM bank_loans WHERE status = 'active' AND (last_interest_date IS NULL OR last_interest_date < '$today')";
        $loans = \Nexus\Database\NexusDB::select($sql);

        foreach ($loans as $loan) {
            $this->calculateAndChargeInterest($loan);
        }

        do_log("[BANK_SCHEDULER] Processed loan interest for " . count($loans) . " loans");
    }

    /**
     * 计算并收取贷款利息
     */
    private function calculateAndChargeInterest(array $loan): void
    {
        $today = date('Y-m-d');
        $lastInterestDate = $loan['last_interest_date'] ?: date('Y-m-d', strtotime($loan['created_at']));

        // 计息截止策略：
        // - 正常期（active）：截止 min(今天, 到期日)
        // - 逾期期（overdue）：截止 今天（到期后每日持续计罚息，直到结清）
        $endDate = $today;
        if (($loan['status'] ?? 'active') !== 'overdue' && !empty($loan['due_date'])) {
            $dueYmd = date('Y-m-d', strtotime($loan['due_date']));
            if ($dueYmd < $endDate) {
                $endDate = $dueYmd;
            }
        }

        // 计息策略：包含“当日入账”。
        // 若从未计息，则让 lastInterestDate 向前移动一天，保证今天至少记 1 天。
        $lastYmd = $loan['last_interest_date'] ? date('Y-m-d', strtotime($loan['last_interest_date'])) : date('Y-m-d', strtotime($loan['created_at'] . ' -1 day'));
        $daysDiff = (strtotime($endDate) - strtotime($lastYmd)) / 86400;

        if ($daysDiff >= 1 && (float)$loan['interest_rate'] > 0) {
            $days = (int)floor($daysDiff);
            
            // 计算实际利率：正常利率 + 逾期罚息率（如果逾期）
            $actualRate = (float)$loan['interest_rate'];
            if ($loan['status'] === 'overdue') {
                $penaltyRate = get_setting('bank_system.overdue_penalty_rate', 0); // 已经是小数
                $actualRate += $penaltyRate;
            }
            
            $dailyInterest = (float)$loan['remaining_amount'] * $actualRate;
            $totalInterest = $dailyInterest * $days;

            if ($totalInterest > 0) {
                // 不并入本金，仅推进计息日期并记录利息（当日已入账）
                $sql = "UPDATE bank_loans SET last_interest_date = '$endDate', updated_at = NOW() WHERE id = {$loan['id']}";
                \Nexus\Database\NexusDB::statement($sql);

                // 记录利息（使用实际利率）
                $this->recordInterest((int)$loan['user_id'], 'loan', (int)$loan['id'], (float)$totalInterest, $actualRate);
            }
        }
    }

    /**
     * 处理逾期贷款
     */
    private function processOverdueLoans(): void
    {
        $today = date('Y-m-d H:i:s');
        $autoDeductDays = get_setting('bank_system.auto_deduct_days') ?: 7;
        $overdueDate = date('Y-m-d H:i:s', strtotime("-{$autoDeductDays} days"));
        
        // 标记逾期贷款
        $sql = "UPDATE bank_loans SET status = 'overdue', updated_at = NOW() WHERE status = 'active' AND due_date < '$today'";
        \Nexus\Database\NexusDB::statement($sql);

        // 自动扣款策略：
        // - 若允许负余额：达到严重逾期阈值后一次性扣清
        // - 若不允许负余额：逾期即开始、每次调度都尽可能扣除现有资金（持续扣）
        $allowNegative = (bool)(get_setting('bank_system.allow_negative_balance') ?: false);
        $sql = $allowNegative
            ? "SELECT * FROM bank_loans WHERE status = 'overdue' AND due_date < '$overdueDate'"
            : "SELECT * FROM bank_loans WHERE status = 'overdue'";
        $severeOverdueLoans = \Nexus\Database\NexusDB::select($sql);

        foreach ($severeOverdueLoans as $loan) {
            $this->autoDeductOverdueLoan($loan);
        }

        do_log("[BANK_SCHEDULER] Processed " . count($severeOverdueLoans) . " severely overdue loans");
    }

    /**
     * 自动扣款逾期贷款
     */
    private function autoDeductOverdueLoan(array $loan): void
    {
        $allowNegative = (bool)(get_setting('bank_system.allow_negative_balance') ?: false);

        // 1) 先扣活期余额（不动定期）
        // 后台以百分比存储，创建账户时转换为小数
        $raw = (float)(get_setting('bank_system.demand_interest_rate') ?: 0);
        $defaultRate = $raw > 1 ? ($raw / 100.0) : $raw;
        $demand = $this->bankRepo->getOrCreateDemandAccount((int)$loan['user_id'], $defaultRate);
        $demandBal = (float)($demand['balance'] ?? 0);
        $fromDemand = min($demandBal, (float)$loan['remaining_amount']);
        if ($fromDemand > 0) {
            $newBal = $demandBal - $fromDemand;
            $upd = "UPDATE bank_demand_accounts SET balance = $newBal, updated_at = NOW() WHERE id = {$demand['id']}";
            \Nexus\Database\NexusDB::statement($upd);
        }

        $remainingAfterDemand = (float)$loan['remaining_amount'] - $fromDemand;
        $deductFromBonus = 0.0;
        if ($remainingAfterDemand > 0) {
            // 2) 再扣用户站点余额（seedbonus）
            $userBonus = $this->bankRepo->getUserBonus($loan['user_id']);
            $deductFromBonus = $allowNegative ? $remainingAfterDemand : min($userBonus, $remainingAfterDemand);
            if ($deductFromBonus > 0) {
                $bonusName = $this->bankRepo->getBonusName();
                $comment = $allowNegative ? "逾期贷款自动扣款（可负余额）：{$deductFromBonus} {$bonusName}" : "逾期贷款自动扣款：{$deductFromBonus} {$bonusName}";
                $this->bankRepo->updateUserBonus($loan['user_id'], -$deductFromBonus, $comment);
            }
        }

        $totalDeducted = $fromDemand + $deductFromBonus;
        if ($totalDeducted <= 0) {
            return;
        }

        // 更新贷款余额
        $newRemainingAmount = (float)$loan['remaining_amount'] - $totalDeducted;
        if ($newRemainingAmount <= 0) {
            $paidAt = date('Y-m-d H:i:s');
            $sql = "UPDATE bank_loans SET remaining_amount = 0, status = 'paid', paid_at = '$paidAt', updated_at = '$paidAt' WHERE id = {$loan['id']}";
            \Nexus\Database\NexusDB::statement($sql);
        } else {
            $sql = "UPDATE bank_loans SET remaining_amount = $newRemainingAmount, updated_at = NOW() WHERE id = {$loan['id']}";
            \Nexus\Database\NexusDB::statement($sql);
        }

        // 发送通知
        $this->sendOverdueNotification($loan['user_id'], $totalDeducted, max(0, $newRemainingAmount));
    }

    /**
     * 处理到期存款
     */
    private function processMaturedDeposits(): void
    {
        $today = date('Y-m-d H:i:s');
        
        $sql = "SELECT * FROM bank_deposits WHERE status = 'active' AND type = 'fixed' AND maturity_date <= '$today'";
        $maturedDeposits = \Nexus\Database\NexusDB::select($sql);

        foreach ($maturedDeposits as $deposit) {
            // 返还本金
            $bonusName = $this->bankRepo->getBonusName();
            $this->bankRepo->updateUserBonus(
                $deposit['user_id'], 
                $deposit['amount'], 
                "存款到期返还：{$deposit['amount']} {$bonusName}"
            );

            // 更新存款状态
            $sql = "UPDATE bank_deposits SET status = 'matured', matured_at = '$today', updated_at = '$today' WHERE id = {$deposit['id']}";
            \Nexus\Database\NexusDB::statement($sql);

            // 发送到期通知
            $this->sendMaturityNotification($deposit['user_id'], $deposit['amount']);
        }

        do_log("[BANK_SCHEDULER] Processed " . count($maturedDeposits) . " matured deposits");
    }

    /**
     * 发送通知
     */
    private function sendNotifications(): void
    {
        if (!get_setting('bank_system.notify_maturity') && !get_setting('bank_system.notify_overdue')) {
            return;
        }

        $notifyDays = get_setting('bank_system.notify_days_before') ?: 3;
        $notifyDate = date('Y-m-d H:i:s', strtotime("+{$notifyDays} days"));

        // 即将到期的存款通知
        if (get_setting('bank_system.notify_maturity')) {
            $now = date('Y-m-d H:i:s');
            $sql = "SELECT * FROM bank_deposits WHERE status = 'active' AND maturity_date <= '$notifyDate' AND maturity_date > '$now'";
            $upcomingDeposits = \Nexus\Database\NexusDB::select($sql);

            foreach ($upcomingDeposits as $deposit) {
                $this->sendUpcomingMaturityNotification($deposit['user_id'], $deposit);
            }
        }

        // 即将到期的贷款通知
        if (get_setting('bank_system.notify_overdue')) {
            $now = date('Y-m-d H:i:s');
            $sql = "SELECT * FROM bank_loans WHERE status = 'active' AND due_date <= '$notifyDate' AND due_date > '$now'";
            $upcomingLoans = \Nexus\Database\NexusDB::select($sql);

            foreach ($upcomingLoans as $loan) {
                $this->sendUpcomingDueNotification($loan['user_id'], $loan);
            }
        }
    }

    /**
     * 记录利息
     */
    private function recordInterest(int $userId, string $type, int $referenceId, float $amount, float $rate): void
    {
        $calculationDate = date('Y-m-d');
        $createdAt = date('Y-m-d H:i:s');
        $sql = "INSERT INTO bank_interest_records (user_id, type, reference_id, amount, rate, calculation_date, created_at, updated_at)
                VALUES ($userId, '$type', $referenceId, $amount, $rate, '$calculationDate', '$createdAt', '$createdAt')";
        \Nexus\Database\NexusDB::statement($sql);
    }

    /**
     * 发送逾期扣款通知
     */
    private function sendOverdueNotification(int $userId, float $deductAmount, float $remainingAmount): void
    {
        $bonusName = $this->bankRepo->getBonusName();
        $subject = '银行逾期贷款自动扣款通知';
        $message = "您的贷款已逾期，系统已自动从您的账户扣除 {$deductAmount} {$bonusName}。";
        
        if ($remainingAmount > 0) {
            $message .= "剩余欠款：{$remainingAmount} {$bonusName}，请尽快还清。";
        } else {
            $message .= "您的贷款已全部还清。";
        }
        
        // 这里应该调用站点的消息系统发送站内信
        // send_message($userId, $subject, $message);
    }

    /**
     * 发送存款到期通知
     */
    private function sendMaturityNotification(int $userId, float $amount): void
    {
        $bonusName = $this->bankRepo->getBonusName();
        $subject = '银行存款到期通知';
        $message = "您的存款已到期，本金 {$amount} {$bonusName} 已返还到您的账户。感谢您使用银行服务！";
        
        // send_message($userId, $subject, $message);
    }

    /**
     * 发送即将到期通知
     */
    private function sendUpcomingMaturityNotification(int $userId, array $deposit): void
    {
        $bonusName = $this->bankRepo->getBonusName();
        $subject = '银行存款即将到期提醒';
        $message = "您的存款（{$deposit['amount']} {$bonusName}）将于 {$deposit['maturity_date']} 到期，届时本金将自动返还到您的账户。";
        
        // send_message($userId, $subject, $message);
    }

    /**
     * 发送贷款即将到期通知
     */
    private function sendUpcomingDueNotification(int $userId, array $loan): void
    {
        $bonusName = $this->bankRepo->getBonusName();
        $subject = '银行贷款即将到期提醒';
        $message = "您的贷款将于 {$loan['due_date']} 到期，剩余欠款：{$loan['remaining_amount']} {$bonusName}，请及时还款以避免逾期。";
        
        // send_message($userId, $subject, $message);
    }
}
