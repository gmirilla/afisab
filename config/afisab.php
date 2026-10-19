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
    // "Allocate free capital" when proposing a cycle leaves out policies maturing within this many days (the form can override it).
    'cycle_maturity_buffer_days' => (int) env('AFISAB_CYCLE_MATURITY_BUFFER_DAYS', 30),

    'purchase_director_threshold' => (int) env('AFISAB_PURCHASE_DIRECTOR_THRESHOLD', 5_000_000_00),

    // Only for the cPanel split layout (web root separate from the app folder): the folder that holds index.php and
    // build/. Laravel needs it to find the compiled CSS/JS manifest from the command line too. Leave unset otherwise.
    'public_path' => env('APP_PUBLIC_PATH'),

    // Staff must confirm two-factor authentication before using the back office. On by default in production.
    'require_2fa_for_staff' => filter_var(env('AFISAB_REQUIRE_2FA', env('APP_ENV') === 'production'), FILTER_VALIDATE_BOOLEAN),

    // How the company is named in emails and documents.
    'company_name' => env('AFISAB_COMPANY_NAME', 'AFISAB NIG. LTD'),

    // Public website. Only facts the company has confirmed belong here.
    'slogan' => 'Building Trust, Delivering Excellence.',
    'contact' => [
        'email' => env('AFISAB_CONTACT_EMAIL', 'info@afisab.com'),
        'phone' => env('AFISAB_CONTACT_PHONE', '+2348057520669'),
        // Where website enquiries (quote requests, supplier interest, general) are emailed: one shared mailbox.
        'enquiry_email' => env('AFISAB_ENQUIRY_EMAIL', 'info@afisab.com'),
    ],

    // The public investor page promotes an investment programme, so it stays OFF until legal counsel has cleared the
    // wording (DECISIONS.md Q5). While off, the site shows only a plain "Investor portal" sign-in link.
    'public_investor_page' => filter_var(env('AFISAB_PUBLIC_INVESTOR_PAGE', false), FILTER_VALIDATE_BOOLEAN),

    // The investment agreement ("policy document") issued for every policy. See docs/POLICY.md.
    'policy' => [
        // The place code inside a policy number: MIC/10-2026/OYO/0000001. Set to what the company uses.
        'code' => env('AFISAB_POLICY_CODE', 'NG'),
        // Issue the agreement automatically when a payment is confirmed (switched off in the test suite only).
        'auto_issue' => filter_var(env('AFISAB_POLICY_AUTO_ISSUE', true), FILTER_VALIDATE_BOOLEAN),
        // Printed in clause 9.2. The software does not yet process early withdrawals (DECISIONS.md).
        'early_withdrawal_notice_months' => (int) env('AFISAB_EARLY_WITHDRAWAL_NOTICE_MONTHS', 1),
        'arbitration_seat' => env('AFISAB_ARBITRATION_SEAT', 'Abuja, Federal Capital Territory, Nigeria'),
        // How many times an investor may email themselves a copy, per policy per day.
        'investor_copies_per_day' => 3,
    ],

    // Investment receipts: AFISAB/INV/2026/0001, restarting at 0001 each year.
    'receipt' => [
        'prefix' => env('AFISAB_RECEIPT_PREFIX', 'AFISAB/INV'),
    ],

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
