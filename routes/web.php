<?php

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\Investor;
use App\Http\Controllers\Public\EnquiryController as PublicEnquiryController;
use App\Http\Controllers\Public\SiteController;
use App\Http\Controllers\Public\VerifyController;
use App\Http\Controllers\Webhooks\PaystackWebhookController;
use Illuminate\Support\Facades\Route;

// The public marketing site
Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/what-we-trade', [SiteController::class, 'trade'])->name('public.trade');
Route::get('/for-buyers', [SiteController::class, 'buyers'])->name('public.buyers');
Route::get('/for-suppliers', [SiteController::class, 'suppliers'])->name('public.suppliers');
Route::get('/how-we-work', [SiteController::class, 'how'])->name('public.how');
Route::get('/about', [SiteController::class, 'about'])->name('public.about');
Route::get('/contact', [SiteController::class, 'contact'])->name('public.contact');
Route::get('/privacy', [SiteController::class, 'privacy'])->name('public.privacy');
Route::get('/terms', [SiteController::class, 'terms'])->name('public.terms');
Route::get('/risk-notice', [SiteController::class, 'risk'])->name('public.risk');
Route::get('/investors', [SiteController::class, 'investors'])->name('public.investors');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
Route::post('/enquiries', [PublicEnquiryController::class, 'store'])->middleware('throttle:6,10')->name('public.enquiries.store');

Route::post('/webhooks/paystack', PaystackWebhookController::class)->middleware('throttle:300,1')->name('webhooks.paystack');

// Public certificate verification (rate limited; token is unguessable)
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/verify', [VerifyController::class, 'form'])->name('verify.form');
    Route::post('/verify', [VerifyController::class, 'lookup'])->name('verify.lookup');
    Route::get('/verify/{token}', [VerifyController::class, 'show'])->name('verify.show');
});

Route::middleware(['auth', 'verified', 'active', 'throttle:240,1'])->group(function () {
    // Two-factor set-up is reachable even when staff are blocked from everything else until they finish it.
    Route::prefix('account')->name('account.')->group(function () {
        Route::get('/security', [SecurityController::class, 'show'])->name('security');
        Route::post('/security/enable', [SecurityController::class, 'enable'])->name('security.enable');
        Route::post('/security/confirm', [SecurityController::class, 'confirm'])->name('security.confirm');
        Route::post('/security/recovery-codes', [SecurityController::class, 'recoveryCodes'])->name('security.recovery');
        Route::delete('/security', [SecurityController::class, 'disable'])->name('security.disable');
    });

    Route::middleware('twofactor')->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        // PDFs: owner, or staff with certificates.issue (checked in the controller)
        Route::get('/investments/{investment}/certificate', [DocumentController::class, 'certificate'])->name('documents.certificate');
        Route::get('/investments/{investment}/statement', [DocumentController::class, 'statement'])->name('documents.statement');

        // Investor portal
        Route::prefix('investor')->name('investor.')->middleware('role:investor')->group(function () {
            Route::get('/', [Investor\InvestmentController::class, 'dashboard'])->name('dashboard');
            Route::get('/profile', [Investor\ProfileController::class, 'edit'])->name('profile');
            Route::put('/profile', [Investor\ProfileController::class, 'update'])->name('profile.update');

            Route::get('/plans', [Investor\InvestmentController::class, 'plans'])->name('plans');
            Route::post('/investments', [Investor\InvestmentController::class, 'store'])->name('investments.store');
            Route::get('/investments/{investment}', [Investor\InvestmentController::class, 'show'])->name('investments.show');
            Route::delete('/investments/{investment}', [Investor\InvestmentController::class, 'cancel'])->name('investments.cancel');

            Route::post('/investments/{investment}/pay/paystack', [Investor\PaymentController::class, 'paystack'])->name('payments.paystack');
            Route::post('/investments/{investment}/pay/transfer', [Investor\PaymentController::class, 'transfer'])->name('payments.transfer');
            Route::get('/payments/callback', [Investor\PaymentController::class, 'callback'])->name('payments.callback');
            Route::post('/payments/{payment}/proof', [Investor\PaymentController::class, 'proof'])->name('payments.proof');
        });

        // Staff back office
        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/', Admin\DashboardController::class)->middleware('role:super-admin|accounts|sales|procurement|hr|compliance|auditor')->name('dashboard');

            // Investor directory: read-only, for anyone with investors.view
            Route::middleware('permission:investors.view')->prefix('investors')->name('investors')->group(function () {
                Route::get('/', [Admin\InvestorDirectoryController::class, 'index']);
                Route::get('/export', [Admin\InvestorDirectoryController::class, 'export'])->name('.export');
                Route::get('/{user}', [Admin\InvestorDirectoryController::class, 'show'])->name('.show');
            });

            Route::middleware('permission:payments.confirm')->group(function () {
                Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments');
                Route::get('/payments/{payment}/proof', [Admin\PaymentController::class, 'proof'])->name('payments.proof');
                Route::post('/payments/{payment}/confirm', [Admin\PaymentController::class, 'confirm'])->name('payments.confirm');
                Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
            });

            // Monthly profit distribution: view (accounts, auditors), prepare/pay (accounts), approve (directors)
            Route::middleware('permission:payouts.prepare|ledger.view')->group(function () {
                Route::get('/distributions', [Admin\DistributionController::class, 'index'])->name('distributions');
                Route::get('/distributions/{run}', [Admin\DistributionController::class, 'show'])->name('distributions.show');
            });
            Route::middleware('permission:payouts.prepare')->group(function () {
                Route::post('/distributions', [Admin\DistributionController::class, 'calculate'])->name('distributions.calculate');
                Route::post('/distributions/{run}/recalculate', [Admin\DistributionController::class, 'recalculate'])->name('distributions.recalculate');
                Route::get('/distributions/{run}/export', [Admin\DistributionController::class, 'export'])->name('distributions.export');
                Route::post('/distributions/{run}/pay', [Admin\DistributionController::class, 'pay'])->name('distributions.pay');
                Route::post('/distribution-lines/{line}/pay', [Admin\DistributionController::class, 'payLine'])->name('distributions.pay-line');
            });
            Route::post('/distributions/{run}/approve', [Admin\DistributionController::class, 'approve'])
                ->middleware('permission:payouts.approve')->name('distributions.approve');

            // Reports (read-only, CSV with ?format=csv)
            Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
                Route::get('/', [Admin\ReportController::class, 'index'])->name('index');
                Route::get('/investors', [Admin\ReportController::class, 'investors'])->name('investors');
                Route::get('/cycles', [Admin\ReportController::class, 'cycles'])->name('cycles');
                Route::get('/trial-balance', [Admin\ReportController::class, 'trialBalance'])->name('trial-balance');
                Route::get('/profit-loss', [Admin\ReportController::class, 'profitAndLoss'])->name('profit-loss');
                Route::get('/distributions', [Admin\ReportController::class, 'distributions'])->name('distributions');
            });

            // Risk controls and Takaful cover
            Route::get('/risk', [Admin\RiskController::class, 'index'])->middleware('permission:risk.view')->name('risk');
            Route::post('/risk/limits', [Admin\RiskController::class, 'saveLimits'])->middleware('permission:risk.manage')->name('risk.limits');
            Route::post('/risk/takaful', [Admin\RiskController::class, 'storeTakaful'])->middleware('permission:takaful.manage')->name('risk.takaful');
            Route::post('/risk/takaful/{policy}/pay', [Admin\RiskController::class, 'payTakaful'])->middleware('permission:takaful.manage')->name('risk.takaful.pay');

            // HR and payroll
            Route::prefix('hr')->name('hr.')->group(function () {
                Route::get('/employees', [Admin\HrController::class, 'employees'])->middleware('permission:staff.manage')->name('employees');
                Route::post('/employees/{employee?}', [Admin\HrController::class, 'saveEmployee'])->middleware('permission:staff.manage')->name('employees.save');

                Route::get('/payroll', [Admin\HrController::class, 'payroll'])->middleware('permission:payroll.manage|payroll.approve|payroll.pay|ledger.view')->name('payroll');
                Route::get('/payroll/{run}', [Admin\HrController::class, 'show'])->middleware('permission:payroll.manage|payroll.approve|payroll.pay|ledger.view')->name('payroll.show');
                Route::post('/payroll', [Admin\HrController::class, 'prepare'])->middleware('permission:payroll.manage')->name('payroll.prepare');
                Route::post('/payroll-adjustments', [Admin\HrController::class, 'addAdjustment'])->middleware('permission:payroll.manage')->name('payroll.adjustments.store');
                Route::delete('/payroll-adjustments/{adjustment}', [Admin\HrController::class, 'removeAdjustment'])->middleware('permission:payroll.manage')->name('payroll.adjustments.destroy');
                Route::post('/payroll/{run}/approve', [Admin\HrController::class, 'approve'])->middleware('permission:payroll.approve')->name('payroll.approve');
                Route::post('/payroll/{run}/pay', [Admin\HrController::class, 'pay'])->middleware('permission:payroll.pay')->name('payroll.pay');
                Route::get('/payroll/{run}/export', [Admin\HrController::class, 'export'])->middleware('permission:payroll.pay|payroll.manage')->name('payroll.export');

                // Pension administrators, and PAYE / pension / NHF owed after each payroll
                Route::get('/pension-administrators', [Admin\PensionAdministratorController::class, 'index'])->middleware('permission:staff.manage|payroll.manage|payroll.pay|ledger.view')->name('pfas');
                Route::post('/pension-administrators/{administrator?}', [Admin\PensionAdministratorController::class, 'save'])->middleware('permission:staff.manage')->name('pfas.save');
                Route::get('/remittances', [Admin\RemittanceController::class, 'index'])->middleware('permission:payroll.manage|payroll.approve|payroll.pay|ledger.view')->name('remittances');
                Route::post('/remittances/{remittance}/pay', [Admin\RemittanceController::class, 'pay'])->middleware('permission:payroll.pay')->name('remittances.pay');
                Route::get('/remittances/{remittance}/schedule', [Admin\RemittanceController::class, 'schedule'])->middleware('permission:payroll.pay|payroll.manage')->name('remittances.schedule');
            });

            // PAYE / pension / NHF rates: super-admin edits and activates, HR and Directors read
            Route::prefix('statutory')->name('statutory')->group(function () {
                Route::get('/', [Admin\StatutoryRulesController::class, 'index'])->middleware('permission:statutory.manage|payroll.manage|payroll.approve|ledger.view');
                Route::middleware('permission:statutory.manage')->group(function () {
                    Route::post('/', [Admin\StatutoryRulesController::class, 'store'])->name('.store');
                    Route::put('/{set}', [Admin\StatutoryRulesController::class, 'update'])->name('.update');
                    Route::post('/{set}/copy', [Admin\StatutoryRulesController::class, 'copy'])->name('.copy');
                    Route::post('/{set}/activate', [Admin\StatutoryRulesController::class, 'activate'])->name('.activate');
                    Route::delete('/{set}', [Admin\StatutoryRulesController::class, 'destroy'])->name('.destroy');
                });
            });

            // Audit trail and staff accounts
            Route::get('/audit', [Admin\AuditController::class, 'index'])->middleware('permission:audit.view')->name('audit');
            Route::middleware('permission:users.manage')->group(function () {
                Route::get('/users', [Admin\UserController::class, 'index'])->name('users');
                Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
                Route::put('/users/{user}/role', [Admin\UserController::class, 'updateRole'])->name('users.role');
                Route::post('/users/{user}/toggle', [Admin\UserController::class, 'toggle'])->name('users.toggle');
            });

            // Trading: cycles, purchases, expenses, stock, sales, suppliers and buyers
            Route::prefix('trading')->name('trading.')->group(function () {
                Route::middleware('permission:trading.view')->group(function () {
                    Route::get('/cycles', [Admin\Trading\CycleController::class, 'index'])->name('cycles');
                    Route::get('/cycles/{cycle}', [Admin\Trading\CycleController::class, 'show'])->name('cycles.show');
                    Route::get('/suppliers', [Admin\Trading\PartyController::class, 'suppliers'])->name('suppliers');
                    Route::get('/buyers', [Admin\Trading\PartyController::class, 'buyers'])->name('buyers');
                    Route::get('/inventory', [Admin\Trading\PartyController::class, 'inventory'])->name('inventory');
                });

                Route::middleware('permission:cycles.manage')->group(function () {
                    Route::post('/cycles', [Admin\Trading\CycleController::class, 'store'])->name('cycles.store');
                    Route::post('/cycles/{cycle}/allocations', [Admin\Trading\CycleController::class, 'allocate'])->name('cycles.allocate');
                    Route::delete('/allocations/{allocation}', [Admin\Trading\CycleController::class, 'deallocate'])->name('cycles.deallocate');
                    Route::post('/cycles/{cycle}/cancel', [Admin\Trading\CycleController::class, 'cancel'])->name('cycles.cancel');
                });
                Route::middleware('permission:cycles.approve')->group(function () {
                    Route::post('/cycles/{cycle}/approve', [Admin\Trading\CycleController::class, 'approve'])->name('cycles.approve');
                    Route::post('/cycles/{cycle}/reject', [Admin\Trading\CycleController::class, 'reject'])->name('cycles.reject');
                });
                Route::middleware('permission:cycles.manage')->group(function () {
                    Route::post('/cycles/{cycle}/close', [Admin\Trading\CycleController::class, 'close'])->name('cycles.close');
                });

                Route::post('/cycles/{cycle}/purchases', [Admin\Trading\PurchaseController::class, 'propose'])->middleware('permission:purchases.propose')->name('purchases.propose');
                Route::post('/purchases/{purchase}/cancel', [Admin\Trading\PurchaseController::class, 'cancel'])->middleware('permission:purchases.propose')->name('purchases.cancel');
                Route::post('/purchases/{purchase}/approve', [Admin\Trading\PurchaseController::class, 'approve'])->middleware('permission:purchases.approve')->name('purchases.approve');
                Route::post('/purchases/{purchase}/reject', [Admin\Trading\PurchaseController::class, 'reject'])->middleware('permission:purchases.approve')->name('purchases.reject');
                Route::post('/purchases/{purchase}/pay', [Admin\Trading\PurchaseController::class, 'pay'])->middleware('permission:purchases.pay')->name('purchases.pay');
                Route::post('/purchases/{purchase}/receive', [Admin\Trading\PurchaseController::class, 'receive'])->middleware('permission:inventory.manage')->name('purchases.receive');

                Route::post('/cycles/{cycle}/expenses', [Admin\Trading\ExpenseController::class, 'propose'])->middleware('permission:cycles.manage|purchases.propose')->name('expenses.propose');
                Route::post('/expenses/{expense}/pay', [Admin\Trading\ExpenseController::class, 'pay'])->middleware('permission:purchases.pay')->name('expenses.pay');
                Route::post('/expenses/{expense}/reject', [Admin\Trading\ExpenseController::class, 'reject'])->middleware('permission:purchases.approve')->name('expenses.reject');

                Route::middleware('permission:sales.manage')->group(function () {
                    Route::post('/cycles/{cycle}/sales', [Admin\Trading\SaleController::class, 'store'])->name('sales.store');
                    Route::post('/sales/{sale}/confirm', [Admin\Trading\SaleController::class, 'confirm'])->name('sales.confirm');
                    Route::post('/sales/{sale}/cancel', [Admin\Trading\SaleController::class, 'cancel'])->name('sales.cancel');
                });
                Route::post('/sales/{sale}/receive', [Admin\Trading\SaleController::class, 'receive'])->middleware('permission:sales.receive')->name('sales.receive');

                Route::post('/suppliers/{supplier?}', [Admin\Trading\PartyController::class, 'saveSupplier'])->middleware('permission:suppliers.manage')->name('suppliers.save');
                Route::post('/buyers/{buyer?}', [Admin\Trading\PartyController::class, 'saveBuyer'])->middleware('permission:buyers.manage')->name('buyers.save');
            });

            // Treasury: funds overview, ledger drill-down, bank reconciliation
            Route::get('/funds', [Admin\TreasuryController::class, 'funds'])->middleware('permission:funds.view')->name('funds');
            Route::middleware('permission:ledger.view')->group(function () {
                Route::get('/ledger/{code}', [Admin\TreasuryController::class, 'account'])->name('ledger.account');
                Route::get('/reconciliation', [Admin\TreasuryController::class, 'reconciliations'])->name('reconciliation');
            });
            Route::post('/reconciliation', [Admin\TreasuryController::class, 'reconcile'])
                ->middleware('permission:reconciliation.manage')->name('reconciliation.store');

            // Ledger corrections: Accounts requests a reversal, a Director approves (different person)
            Route::middleware('permission:ledger.view')->get('/ledger/entry/{entry}', [Admin\ReversalController::class, 'entry'])->name('ledger.entry');
            Route::middleware('permission:reversals.request|reversals.approve|ledger.view')->get('/reversals', [Admin\ReversalController::class, 'index'])->name('reversals');
            Route::middleware('permission:reversals.request')->post('/ledger/entry/{entry}/reverse', [Admin\ReversalController::class, 'request'])->name('reversals.request');
            Route::middleware('permission:reversals.approve')->group(function () {
                Route::post('/reversals/{reversal}/approve', [Admin\ReversalController::class, 'approve'])->name('reversals.approve');
                Route::post('/reversals/{reversal}/reject', [Admin\ReversalController::class, 'reject'])->name('reversals.reject');
            });

            // Capital payouts at maturity: prepare/pay (accounts), approve/reject (directors)
            Route::get('/payouts', [Admin\CapitalPayoutController::class, 'index'])
                ->middleware('permission:payouts.prepare|payouts.approve|ledger.view')->name('payouts');
            Route::middleware('permission:payouts.prepare')->group(function () {
                Route::post('/payouts/prepare/{investment}', [Admin\CapitalPayoutController::class, 'prepare'])->name('payouts.prepare');
                Route::post('/payouts/{payout}/pay', [Admin\CapitalPayoutController::class, 'pay'])->name('payouts.pay');
            });
            Route::middleware('permission:payouts.approve')->group(function () {
                Route::post('/payouts/{payout}/approve', [Admin\CapitalPayoutController::class, 'approve'])->name('payouts.approve');
                Route::post('/payouts/{payout}/reject', [Admin\CapitalPayoutController::class, 'reject'])->name('payouts.reject');
            });

            Route::middleware('permission:kyc.review')->group(function () {
                Route::get('/kyc', [Admin\KycController::class, 'index'])->name('kyc');
                Route::get('/kyc/{profile}/document', [Admin\KycController::class, 'document'])->name('kyc.document');
                Route::post('/kyc/{profile}/review', [Admin\KycController::class, 'review'])->name('kyc.review');
            });

            Route::middleware('permission:paystack.manage')->prefix('paystack')->name('paystack')->group(function () {
                Route::get('/', [Admin\PaystackController::class, 'index']);
                Route::post('/', [Admin\PaystackController::class, 'save'])->name('.save');
                Route::post('/test', [Admin\PaystackController::class, 'test'])->name('.test');
                Route::post('/payments/{payment}/reverify', [Admin\PaystackController::class, 'reverify'])->name('.reverify');
            });
            // Messages from the public website
            Route::middleware('permission:enquiries.manage')->prefix('enquiries')->name('enquiries')->group(function () {
                Route::get('/', [Admin\EnquiryController::class, 'index']);
                Route::get('/{enquiry}', [Admin\EnquiryController::class, 'show'])->name('.show');
                Route::put('/{enquiry}', [Admin\EnquiryController::class, 'update'])->name('.update');
                Route::post('/{enquiry}/supplier', [Admin\EnquiryController::class, 'makeSupplier'])->middleware('permission:suppliers.manage')->name('.supplier');
            });

            Route::middleware('permission:bank.manage')->group(function () {
                Route::get('/bank-accounts', [Admin\BankAccountController::class, 'index'])->name('bank-accounts');
                Route::post('/bank-accounts', [Admin\BankAccountController::class, 'store'])->name('bank-accounts.store');
                Route::put('/bank-accounts/{account}', [Admin\BankAccountController::class, 'update'])->name('bank-accounts.update');
            });
            Route::middleware('permission:plans.manage')->group(function () {
                Route::get('/plans', [Admin\PlanController::class, 'index'])->name('plans');
                Route::post('/plans', [Admin\PlanController::class, 'store'])->name('plans.store');
                Route::put('/plans/{plan}', [Admin\PlanController::class, 'update'])->name('plans.update');
            });
        });
    });
});
