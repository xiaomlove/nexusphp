<?php

namespace NexusPlugin\BankSystem;

class BankController
{
    private BankSystemRepository $bankRepo;

    public function __construct()
    {
        $this->bankRepo = new BankSystemRepository();
    }

    /**
     * 银行主页
     */
    public function index()
    {
        global $CURUSER;

        if (!$CURUSER) {
            header('Location: /login.php');
            exit;
        }

        // 检查银行系统是否启用
        if (!get_setting('bank_system.enabled')) {
            stdmsg('错误', '银行系统暂时关闭');
            stdfoot();
            exit;
        }

        // 检查用户权限
        if (!$this->bankRepo->checkUserPermission($CURUSER)) {
            $minClass = get_setting('bank_system.min_user_class') ?: 1;
            stdmsg('权限不足', "您的等级不足，需要等级 {$minClass} 以上才能使用银行系统");
            stdfoot();
            exit;
        }

        $data = $this->getBankData($CURUSER['id']);
        
        stdhead('银行系统');
        require_once __DIR__ . '/../resources/views/bank.php';
        stdfoot();
    }

    /**
     * 获取银行数据
     */
    private function getBankData(int $userId): array
    {
        $bonusName = $this->bankRepo->getBonusName();
        $currentBonus = $this->bankRepo->getUserBonus($userId);
        $hourlyBonus = $this->bankRepo->getUserHourlyBonus($userId);
        $maxLoanAmount = $this->bankRepo->calculateMaxLoanAmount($userId);
        $currentLoan = $this->bankRepo->getUserLoan($userId);
        $deposits = $this->bankRepo->getUserDeposits($userId);
        $loanHistory = $this->bankRepo->getUserLoanHistory($userId);

        // 获取利率设置
        $interestRates = get_setting('bank_system.interest_rates') ?: [];
        $minLoanAmount = get_setting('bank_system.min_loan_amount') ?: 1000;
        $minDepositAmount = get_setting('bank_system.min_deposit_amount') ?: 5000;

        // 计算：贷款应计利息至今（仅已入账）与一次性结清金额（展示用）
        $loanAccrued = 0.0; $loanPayoff = null;
        if ($currentLoan) {
            $loanId = (int)$currentLoan['id'];
            // 已入账利息
            $sumRows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(amount),0) AS s FROM bank_interest_records WHERE type='loan' AND reference_id = $loanId");
            $loanAccrued = round((float)($sumRows[0]['s'] ?? 0), 2);
            $loanPayoff = round((float)$currentLoan['remaining_amount'] + $loanAccrued, 2);
        }

        // 计算：定期存款“累计应计至今（理论）”（仅展示用，不影响发放）
        foreach ($deposits as &$d) {
            if (($d['status'] ?? '') === 'active' && ($d['type'] ?? 'fixed') === 'fixed') {
                $startYmd = date('Y-m-d', strtotime($d['created_at']));
                $today = date('Y-m-d');
                $endYmd = $today;
                if (!empty($d['maturity_date'])) {
                    $mYmd = date('Y-m-d', strtotime($d['maturity_date']));
                    if ($mYmd < $endYmd) { $endYmd = $mYmd; }
                }
                $days = max(0, floor((strtotime($endYmd) - strtotime($startYmd)) / 86400));
                $d['accrued_theoretical'] = round((float)$d['amount'] * (float)$d['interest_rate'] * (int)$days, 2);
            }
        }
        unset($d);

        return compact(
            'bonusName', 'currentBonus', 'hourlyBonus', 'maxLoanAmount', 
            'currentLoan', 'deposits', 'loanHistory', 'interestRates', 'minLoanAmount', 'minDepositAmount',
            'loanAccrued', 'loanPayoff'
        );
    }

    /**
     * 申请贷款
     */
    public function applyLoan()
    {
        global $CURUSER;

        if (!$CURUSER || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /bank.php');
            exit;
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $termDays = intval($_POST['term_days'] ?? 7);

        // 验证输入
        $minAmount = get_setting('bank_system.min_loan_amount') ?: 1000;
        $maxAmount = $this->bankRepo->calculateMaxLoanAmount($CURUSER['id']);

        // 余额为负禁止申请贷款
        $currentBonus = $this->bankRepo->getUserBonus($CURUSER['id']);
        if ($currentBonus < 0) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("当前{$bonusName}为负，暂不可申请贷款");
            return;
        }

        if ($amount < $minAmount) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("贷款金额不能少于 {$minAmount} {$bonusName}");
            return;
        }

        if ($amount > $maxAmount) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("贷款金额不能超过 {$maxAmount} {$bonusName}（基于您的时魔）");
            return;
        }

        // 检查是否已有贷款
        $existingLoan = $this->bankRepo->getUserLoan($CURUSER['id']);
        if ($existingLoan) {
            $this->returnWithError('您已有未还清的贷款，请先还清后再申请新贷款');
            return;
        }

        // 检查是否有配置的贷款利率
        $settings = $this->bankRepo->getSettings();
        if (empty($settings['loan_interest_rates'])) {
            $this->returnWithError('系统暂未配置贷款期限和利率，请联系管理员');
            return;
        }

        // 获取对应期限的利率
        $interestRate = $this->getInterestRate($termDays, 'loan');
        if ($interestRate === null) {
            $this->returnWithError('无效的贷款期限，请选择系统配置的期限');
            return;
        }

        // 创建贷款
        if ($this->bankRepo->createLoan($CURUSER['id'], $amount, $interestRate, $termDays)) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithSuccess("贷款申请成功！已发放 {$amount} {$bonusName} 到您的账户");
        } else {
            $this->returnWithError('贷款申请失败，请稍后重试');
        }
    }

    /**
     * 还款
     */
    public function repayLoan()
    {
        global $CURUSER;

        if (!$CURUSER || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /bank.php');
            exit;
        }

        $postedAmount = floatval($_POST['amount'] ?? 0);

        $loan = $this->bankRepo->getUserLoan($CURUSER['id']);
        if (!$loan) {
            $this->returnWithError('没有找到有效的贷款记录');
            return;
        }

        // 计算至今应计利息（仅记录汇总）与一次性应还
        $loanId = (int)$loan['id'];
        $sumRows = \Nexus\Database\NexusDB::select("SELECT COALESCE(SUM(amount),0) AS s FROM bank_interest_records WHERE type='loan' AND reference_id = $loanId");
        $accrued = round((float)($sumRows[0]['s'] ?? 0), 2);
        $required = round((float)$loan['remaining_amount'] + $accrued, 2);

        // 强制一次性结清
        if (abs($postedAmount - $required) > 0.01) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("仅支持一次性结清，应还：{$required} {$bonusName}");
            return;
        }

        // 余额校验
        $userBonus = $this->bankRepo->getUserBonus($CURUSER['id']);
        if ($userBonus < $required) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("{$bonusName}不足，需 {$required} {$bonusName}");
            return;
        }

        // 扣款并结清
        try {
            $bonusName = $this->bankRepo->getBonusName();
            // 扣除总额
            $this->bankRepo->updateUserBonus($CURUSER['id'], -$required, "贷款结清：{$required} {$bonusName}");

            // 当日计息已通过调度入账，无需再额外入账

            // 更新贷款为已结清，并推进 last_interest_date 到今天或到期日（取早者）
            $paidAt = date('Y-m-d H:i:s');
            $settleDateYmd = $eligibleEnd;
            $sql = "UPDATE bank_loans SET remaining_amount = 0, status = 'paid', paid_at = '$paidAt', last_interest_date = '$settleDateYmd', updated_at = '$paidAt' WHERE id = {$loan['id']}";
            \Nexus\Database\NexusDB::statement($sql);

            $this->returnWithSuccess('贷款已一次性结清');
        } catch (\Exception $e) {
            do_log('[BANK_SYSTEM] repayLoan(one-off) failed: ' . $e->getMessage(), 'error');
            $this->returnWithError('还款失败，请稍后重试');
        }
    }

    /**
     * 创建存款
     */
    public function createDeposit()
    {
        global $CURUSER;

        if (!$CURUSER || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /bank.php');
            exit;
        }

        $amount = floatval($_POST['amount'] ?? 0);
        $depositType = $_POST['deposit_type'] ?? 'fixed'; // demand|fixed
        $termDays = intval($_POST['term_days'] ?? 0);

        // 验证输入
        $minFixed = get_setting('bank_system.min_fixed_amount') ?: 1000;
        $minDemand = get_setting('bank_system.min_demand_amount') ?: 100;
        $minAmount = $depositType === 'demand' ? $minDemand : $minFixed;
        $currentBonus = $this->bankRepo->getUserBonus($CURUSER['id']);

        if ($amount < $minAmount) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("存款金额不能少于 {$minAmount} {$bonusName}");
            return;
        }

        if ($amount > $currentBonus) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithError("{$bonusName}不足");
            return;
        }

        // 获取对应期限的利率
        if ($depositType === 'demand') {
            $termDays = 0;
            $interestRate = $this->getInterestRate(null, 'deposit_demand');
        } else {
            // 检查是否有配置的定期存款利率
            $settings = $this->bankRepo->getSettings();
            if (empty($settings['fixed_deposit_rates'])) {
                $this->returnWithError('系统暂未配置定期存款期限和利率，请联系管理员');
                return;
            }
            
            $interestRate = $this->getInterestRate($termDays, 'deposit_fixed');
        }
        if ($interestRate === null) {
            $this->returnWithError('无效的存款期限，请选择系统配置的期限');
            return;
        }

        // 创建存款
        if ($this->bankRepo->createDeposit($CURUSER['id'], $amount, $interestRate, $termDays, $depositType)) {
            $bonusName = $this->bankRepo->getBonusName();
            $this->returnWithSuccess("存款成功！已存入 {$amount} {$bonusName}");
        } else {
            $this->returnWithError('存款失败，请稍后重试');
        }
    }

    /**
     * 提前支取存款
     */
    public function withdrawDeposit()
    {
        global $CURUSER;

        if (!$CURUSER || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /bank.php');
            exit;
        }

        $depositId = intval($_POST['deposit_id'] ?? 0);

        if ($depositId <= 0) {
            $this->returnWithError('无效的存款ID');
            return;
        }

        $result = $this->bankRepo->withdrawDeposit($depositId, $CURUSER['id']);
        
        if ($result['success']) {
            $this->returnWithSuccess($result['message']);
        } else {
            $this->returnWithError($result['message']);
        }
    }

    /**
     * 获取指定期限的利率
     */
    private function getInterestRate(?int $termDays, string $type): ?float
    {
        // 新版利率逻辑
        $settings = $this->bankRepo->getSettings();

        if ($type === 'loan') {
            $list = $settings['loan_interest_rates'] ?? [];
            foreach ($list as $item) {
                if (($item['term_days'] ?? null) == $termDays) {
                    return (float)($item['loan_rate'] ?? 0);
                }
            }
            // 如果没有找到对应期限的配置，返回null表示无效
            return null;
        }

        if ($type === 'deposit_demand') {
            // 设置中已转换为小数，默认改为 0.01（1%）
            return (float)($settings['demand_interest_rate'] ?? 0.01);
        }

        if ($type === 'deposit_fixed') {
            $list = $settings['fixed_deposit_rates'] ?? [];
            foreach ($list as $item) {
                if (($item['term_days'] ?? null) == $termDays) {
                    return (float)($item['interest_rate'] ?? 0);
                }
            }
            // 如果没有找到对应期限的配置，返回null表示无效
            return null;
        }

        return null;
    }

    /**
     * 返回成功消息
     */
    private function returnWithSuccess(string $message): void
    {
        $_SESSION['bank_message'] = ['type' => 'success', 'text' => $message];
        header('Location: /bank.php');
        exit;
    }

    /**
     * 返回错误消息
     */
    private function returnWithError(string $message): void
    {
        $_SESSION['bank_message'] = ['type' => 'error', 'text' => $message];
        header('Location: /bank.php');
        exit;
    }
}
