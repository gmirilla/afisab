<?php

/*
| Business rules from the "Recommended Corporate Bank Account Structure" document and owner decisions.
| Percentages are basis points (100 bps = 1%) so all money maths stays in integers.
*/
return [
    'currency' => 'NGN',

    // 5% of every new investor fund (and recycled capital) goes to the Investors Risk Fund.
    'risk_reserve_bps' => (int) env('AFISAB_RISK_RESERVE_BPS', 500),

    // 20% of net profit (after capital and expenses) is retained by the Investors Profit Account.
    'profit_retention_bps' => (int) env('AFISAB_PROFIT_RETENTION_BPS', 2000),

    // Owner decision (2026-10-14): 30% of net profit to investors, 70% to the company.
    'investor_profit_share_bps' => (int) env('AFISAB_INVESTOR_SHARE_BPS', 3000),

    // Owner decision (2026-10-14): the investor share is taken from profit AFTER the 20% retention.
    //   net profit -> 20% retained in the Investors Profit Account -> remaining 80%: 30% investors / 70% company.

    // Monthly distribution: calculated as of this day of the month (end of day); investors are paid
    // by the last day of the same month (29th / 30th / 31st).
    'distribution_cutoff_day' => (int) env('AFISAB_DISTRIBUTION_CUTOFF_DAY', 27),

    // A single purchase above this amount (kobo) must be approved by a Director (super-admin). NGN 5,000,000.
    'purchase_director_threshold' => (int) env('AFISAB_PURCHASE_DIRECTOR_THRESHOLD', 5_000_000_00),

    // Only for the cPanel split layout (web root separate from the app folder): the folder that holds index.php and
    // build/. Laravel needs it to find the compiled CSS/JS manifest from the command line too. Leave unset otherwise.
    'public_path' => env('APP_PUBLIC_PATH'),

    // Staff must confirm two-factor authentication before using the back office. On by default in production.
    'require_2fa_for_staff' => filter_var(env('AFISAB_REQUIRE_2FA', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),

    // Days before maturity to send the reminder email.
    'maturity_reminder_days' => 14,

    // Where investors send bank transfers (shown on the payment page).
    'bank' => [
        'name' => env('AFISAB_BANK_NAME', 'Set AFISAB_BANK_NAME'),
        'account_number' => env('AFISAB_BANK_ACCOUNT_NUMBER', '0000000000'),
        'account_name' => env('AFISAB_BANK_ACCOUNT_NAME', 'AFISAB NIG. LTD'),
    ],

    // Minimum subscription, in kobo (NGN 500,000).
    'minimum_investment' => 500_000_00,
];
