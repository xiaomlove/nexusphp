<?php

return [
    'title' => '银行系统',
    'subtitle' => '安全可靠的魔力管理服务',
    
    'labels' => [
        'current_bonus' => '当前魔力',
        'hourly_bonus' => '时魔（每小时魔力）',
        'max_loan_amount' => '最大贷款额度',
        'active_deposits' => '活跃存款数',
        'loan_amount' => '贷款金额',
        'loan_term' => '贷款期限',
        'deposit_amount' => '存款金额',
        'deposit_term' => '存款期限',
        'repay_amount' => '还款金额',
        'interest_rate' => '利率',
        'due_date' => '到期日期',
        'maturity_date' => '到期日期',
        'status' => '状态',
        'remaining_amount' => '剩余欠款',
        'penalty' => '手续费',
    ],

    'buttons' => [
        'apply_loan' => '申请贷款',
        'repay_loan' => '立即还款',
        'create_deposit' => '立即存款',
        'withdraw_early' => '提前支取',
    ],

    'sections' => [
        'loan_service' => '贷款服务',
        'deposit_service' => '存款服务',
        'current_loan' => '当前贷款信息',
        'deposit_records' => '我的存款记录',
    ],

    'status' => [
        'active' => '活跃',
        'paid' => '已还清',
        'overdue' => '逾期',
        'matured' => '已到期',
        'withdrawn' => '已支取',
        'defaulted' => '违约',
    ],

    'messages' => [
        'loan_success' => '贷款申请成功！已发放 :amount :bonus_name 到您的账户',
        'loan_failed' => '贷款申请失败，请稍后重试',
        'repay_success' => '还款成功',
        'repay_failed' => '还款失败',
        'deposit_success' => '存款成功！已存入 :amount :bonus_name',
        'deposit_failed' => '存款失败，请稍后重试',
        'withdraw_success' => '提前支取成功',
        'withdraw_failed' => '支取失败',
        'insufficient_bonus' => ':bonus_name 不足',
        'loan_exists' => '您已有未还清的贷款，请先还清后再申请新贷款',
        'invalid_amount' => '金额无效',
        'invalid_term' => '期限无效',
        'permission_denied' => '权限不足',
        'system_disabled' => '银行系统暂时关闭',
        'min_amount_error' => '金额不能少于 :min_amount :bonus_name',
        'max_amount_error' => '金额不能超过 :max_amount :bonus_name',
        'confirm_withdraw' => '提前支取将扣除手续费，确定要支取吗？',
    ],

    'notifications' => [
        'loan_due_soon' => '您的贷款将于 :due_date 到期，剩余欠款：:amount :bonus_name，请及时还款以避免逾期。',
        'deposit_mature_soon' => '您的存款（:amount :bonus_name）将于 :maturity_date 到期，届时本金将自动返还到您的账户。',
        'loan_overdue_deduct' => '您的贷款已逾期，系统已自动从您的账户扣除 :amount :bonus_name。',
        'deposit_matured' => '您的存款已到期，本金 :amount :bonus_name 已返还到您的账户。感谢您使用银行服务！',
    ],

    'help' => [
        'loan_ratio' => '最大贷款额度 = 时魔 × 此比例（小时数）',
        'interest_calculation' => '利息按日计算，复利计息',
        'early_withdrawal' => '提前支取存款将扣除一定比例的手续费',
        'overdue_penalty' => '贷款逾期将产生额外罚息',
        'auto_deduct' => '严重逾期的贷款将自动从账户余额中扣除',
    ],
];
