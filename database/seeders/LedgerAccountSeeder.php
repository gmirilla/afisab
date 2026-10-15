<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            [LedgerAccount::MAIN_OPS, 'Main Operations Account', 'asset', 'Primary current account; all investor funds enter here first.'],
            [LedgerAccount::RISK_FUND, 'Investors Risk Fund', 'asset', 'Receives 5% of investor funds; risk cushion, Takaful, growth projects.'],
            [LedgerAccount::INVESTOR_PROFIT, 'Investors Profit Account', 'asset', 'Receives buyer proceeds; retains 20% of net profit per completed cycle.'],
            [LedgerAccount::DOMICILIARY, 'Domiciliary Account (USD/GBP/EUR)', 'asset', 'Export proceeds and foreign-currency payments.'],
            [LedgerAccount::INVENTORY, 'Commodity Inventory', 'asset', 'Commodities held at cost.'],
            [LedgerAccount::INVESTOR_FUNDS, 'Investor Funds Held', 'liability', 'Capital owed to investors.'],
            [LedgerAccount::INVESTOR_PROFIT_PAYABLE, 'Investor Profit Payable', 'liability', 'Approved monthly profit shares owed to investors until paid.'],
            [LedgerAccount::TRADING_EXPENSES, 'Trading Expenses', 'expense', 'Transport, storage, labour and packaging costs of trading cycles.'],
            [LedgerAccount::INSURANCE_EXPENSE, 'Takaful / Insurance Expense', 'expense', 'Takaful premiums, paid from the Investors Risk Fund.'],
            [LedgerAccount::PAYROLL_EXPENSE, 'Payroll Expense', 'expense', 'Salaries, allowances and commissions (gross).'],
            [LedgerAccount::STATUTORY_PAYABLE, 'Payroll Deductions Payable', 'liability', 'Tax, pension and other deductions withheld, owed to the authorities.'],
            [LedgerAccount::TRADING_INCOME, 'Trading Income', 'income', 'Gross trading margin on commodity sales.'],
        ];

        foreach ($accounts as [$code, $name, $type, $description]) {
            LedgerAccount::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'type' => $type, 'is_system' => true, 'description' => $description],
            );
        }
    }
}
