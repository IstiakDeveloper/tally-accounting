<?php

use App\Http\Controllers\AccountCategoryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\CompanySettingController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DesignationController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FinancialYearController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalarySlipController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TaxSettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public Routes
Route::get('/', function () {
    return Inertia::render('Welcome');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

// Protected Routes (Require Authentication)
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // User Profile (All Authenticated Users)
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', function () {
            return Inertia::render('Profile/Index');
        })->name('index');
        Route::put('/', function () {
            // Update profile
        })->name('update');
        Route::put('/password', function () {
            // Update password
        })->name('password.update');
    });

    // Accounting Routes (Admin and Accountant)
    Route::middleware('role:admin,accountant')->prefix('accounting')->group(function () {
        // Chart of Accounts
        Route::resource('chart-of-accounts', ChartOfAccountController::class);
        Route::patch('chart-of-accounts/{chartOfAccount}/toggle-status', [ChartOfAccountController::class, 'toggleStatus'])
            ->name('chart-of-accounts.toggle-status');

        // Account Categories
        Route::resource('account-categories', AccountCategoryController::class);

        // Journal Entries
        Route::resource('journal-entries', JournalEntryController::class);
        Route::patch('journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])
            ->name('journal-entries.post');
        Route::patch('journal-entries/{journalEntry}/cancel', [JournalEntryController::class, 'cancel'])
            ->name('journal-entries.cancel');

        // Financial Years
        Route::resource('financial-years', FinancialYearController::class);
        Route::patch('financial-years/{financialYear}/activate', [FinancialYearController::class, 'activate'])
            ->name('financial-years.activate');

        Route::get('/bank-accounts/{bankAccount}/statement', [BankAccountController::class, 'statement'])
            ->name('bank-accounts.statement');
        // Export Bank Statement
        Route::get('/bank-accounts/{bankAccount}/export-statement', [BankAccountController::class, 'exportStatement'])
            ->name('bank-accounts.export-statement');

        // Bank Transfer
        Route::get('/bank-accounts/transfer', [BankAccountController::class, 'showTransferForm'])
            ->name('bank-accounts.transfer');
        Route::post('/bank-accounts/process-transfer', [BankAccountController::class, 'processTransfer'])
            ->name('bank-accounts.process-transfer');

        // Bank Deposit
        Route::get('/bank-accounts/deposit', [BankAccountController::class, 'showDepositForm'])
            ->name('bank-accounts.deposit');
        Route::post('/bank-accounts/process-deposit', [BankAccountController::class, 'processDeposit'])
            ->name('bank-accounts.process-deposit');

        // Bank Withdrawal
        Route::get('/bank-accounts/withdrawal', [BankAccountController::class, 'showWithdrawalForm'])
            ->name('bank-accounts.withdrawal');
        Route::post('/bank-accounts/process-withdrawal', [BankAccountController::class, 'processWithdrawal'])
            ->name('bank-accounts.process-withdrawal');

        // Bank Reconciliation
        Route::get('/bank-accounts/{bankAccount}/reconciliation', [BankAccountController::class, 'showReconciliationForm'])
            ->name('bank-accounts.reconciliation');
        Route::post('/bank-accounts/{bankAccount}/process-reconciliation', [BankAccountController::class, 'processReconciliation'])
            ->name('bank-accounts.process-reconciliation');
        // Bank Accounts
        Route::resource('bank-accounts', BankAccountController::class);
        Route::patch('bank-accounts/{bankAccount}/toggle-status', [BankAccountController::class, 'toggleStatus'])
            ->name('bank-accounts.toggle-status');


        // Bank Account Statement

    });




    // Inventory Routes (Admin and Manager)
    Route::middleware('role:admin,manager')->prefix('inventory')->group(function () {
        // Products
        Route::resource('products', ProductController::class);
        Route::patch('products/{product}/toggle-status', [ProductController::class, 'toggleStatus'])
            ->name('products.toggle-status');
        Route::get('products/{product}/adjust-stock', [ProductController::class, 'showAdjustStockForm'])
            ->name('products.adjust-stock.form');
        Route::post('products/{product}/adjust-stock', [ProductController::class, 'adjustStock'])
            ->name('products.adjust-stock');

        // Product Categories
        Route::resource('product-categories', ProductCategoryController::class);
        Route::patch('product-categories/{productCategory}/toggle-status', [ProductCategoryController::class, 'toggleStatus'])
            ->name('product-categories.toggle-status');

        // Warehouses
        Route::resource('warehouses', WarehouseController::class);
        Route::patch('warehouses/{warehouse}/toggle-status', [WarehouseController::class, 'toggleStatus'])
            ->name('warehouses.toggle-status');

        // Stock Movements
        Route::resource('stock-movements', StockMovementController::class);
    });

    // Contact Routes (Admin, Manager, Accountant)
    Route::middleware('role:admin,manager,accountant')->prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::get('/create', [ContactController::class, 'create'])->name('create');
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
        Route::get('/{contact}/edit', [ContactController::class, 'edit'])->name('edit');
        Route::put('/{contact}', [ContactController::class, 'update'])->name('update');
        Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy');
        Route::patch('/{contact}/toggle-status', [ContactController::class, 'toggleStatus'])->name('toggle-status');

        // Customer and Supplier specific routes
        Route::get('/customers', [ContactController::class, 'customers'])->name('customers');
        Route::get('/suppliers', [ContactController::class, 'suppliers'])->name('suppliers');
    });

    // Purchase and Sales Routes (Admin, Manager, Accountant)
    Route::middleware('role:admin,manager,accountant')->group(function () {
        // Purchase Orders
        Route::resource('purchase-orders', PurchaseOrderController::class);
        Route::patch('purchase-orders/{purchaseOrder}/confirm', [PurchaseOrderController::class, 'confirm'])
            ->name('purchase-orders.confirm');
        Route::patch('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])
            ->name('purchase-orders.receive');
        Route::patch('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
            ->name('purchase-orders.cancel');
        Route::get('purchase-orders/{purchaseOrder}/create-invoice', [PurchaseOrderController::class, 'createInvoice'])
            ->name('purchase-orders.create-invoice');

        // Sales Orders
        Route::resource('sales-orders', SalesOrderController::class);
        Route::patch('sales-orders/{salesOrder}/confirm', [SalesOrderController::class, 'confirm'])
            ->name('sales-orders.confirm');
        Route::patch('sales-orders/{salesOrder}/deliver', [SalesOrderController::class, 'deliver'])
            ->name('sales-orders.deliver');
        Route::patch('sales-orders/{salesOrder}/cancel', [SalesOrderController::class, 'cancel'])
            ->name('sales-orders.cancel');
        Route::get('sales-orders/{salesOrder}/create-invoice', [SalesOrderController::class, 'createInvoice'])
            ->name('sales-orders.create-invoice');

        // Invoices
        Route::resource('invoices', InvoiceController::class);
        Route::patch('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])
            ->name('invoices.cancel');
        Route::get('invoices/{invoice}/create-payment', [InvoiceController::class, 'createPayment'])
            ->name('invoices.create-payment');

        // Payments
        Route::resource('payments', PaymentController::class);
    });

    // Payroll Routes (Admin and HR Manager)
    Route::middleware('role:admin,manager')->prefix('payroll')->group(function () {
        // Departments
        Route::resource('departments', DepartmentController::class);
        Route::patch('departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])
            ->name('departments.toggle-status');

        // Designations
        Route::resource('designations', DesignationController::class);
        Route::patch('designations/{designation}/toggle-status', [DesignationController::class, 'toggleStatus'])
            ->name('designations.toggle-status');

        // Employees
        Route::resource('employees', EmployeeController::class);
        Route::patch('employees/{employee}/toggle-status', [EmployeeController::class, 'toggleStatus'])
            ->name('employees.toggle-status');

        // Allowances and Deductions
        Route::prefix('employees/{employee}')->name('employees.')->group(function () {
            Route::get('/allowances', [EmployeeController::class, 'allowances'])->name('allowances');
            Route::post('/allowances', [EmployeeController::class, 'storeAllowance'])->name('allowances.store');
            Route::delete('/allowances/{allowance}', [EmployeeController::class, 'destroyAllowance'])->name('allowances.destroy');

            Route::get('/deductions', [EmployeeController::class, 'deductions'])->name('deductions');
            Route::post('/deductions', [EmployeeController::class, 'storeDeduction'])->name('deductions.store');
            Route::delete('/deductions/{deduction}', [EmployeeController::class, 'destroyDeduction'])->name('deductions.destroy');
        });

        // Salary Slips
        Route::resource('salary-slips', SalarySlipController::class);
        Route::patch('salary-slips/{salarySlip}/pay', [SalarySlipController::class, 'pay'])
            ->name('salary-slips.pay');

        // Leave Applications
        Route::resource('leaves', LeaveApplicationController::class);
        Route::patch('leaves/{leave}/approve', [LeaveApplicationController::class, 'approve'])
            ->name('leaves.approve');
        Route::patch('leaves/{leave}/reject', [LeaveApplicationController::class, 'reject'])
            ->name('leaves.reject');
    });

    // Report Routes (Admin, Manager, Accountant)
    Route::middleware('role:admin,manager,accountant')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');

        // Financial Reports (Admin, Accountant)
        Route::middleware('role:admin,accountant')->group(function () {
            Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('/income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
            Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
            Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
            Route::get('/tax-summary', [ReportController::class, 'taxSummary'])->name('tax-summary');
        });

        // Inventory Reports
        Route::get('/inventory', [ReportController::class, 'inventory'])->name('inventory');
        Route::get('/stock-movement', [ReportController::class, 'stockMovement'])->name('stock-movement');

        // Sales Reports
        Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
        Route::get('/customer', [ReportController::class, 'customer'])->name('customer');

        // Purchase Reports
        Route::get('/purchases', [ReportController::class, 'purchases'])->name('purchases');
        Route::get('/supplier', [ReportController::class, 'supplier'])->name('supplier');

        // Payroll Reports (Admin, Manager)
        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/payroll', [ReportController::class, 'payroll'])->name('payroll');
            Route::get('/employee', [ReportController::class, 'employee'])->name('employee');
        });

        // Save Reports
        Route::post('/save', [ReportController::class, 'save'])->name('save');
        Route::get('/saved', [ReportController::class, 'saved'])->name('saved');
        Route::get('/saved/{report}', [ReportController::class, 'showSaved'])->name('saved.show');
        Route::delete('/saved/{report}', [ReportController::class, 'destroySaved'])->name('saved.destroy');
    });

    // Settings Routes (Admin Only)
    Route::middleware('role:admin')->prefix('settings')->name('settings.')->group(function () {
        // Company Settings
        Route::get('/company', [CompanySettingController::class, 'index'])->name('company');
        Route::put('/company', [CompanySettingController::class, 'update'])->name('company.update');

        // Tax Settings
        Route::get('/taxes', [TaxSettingController::class, 'index'])->name('taxes.index');
        Route::get('/taxes/create', [TaxSettingController::class, 'create'])->name('taxes.create');
        Route::post('/taxes', [TaxSettingController::class, 'store'])->name('taxes.store');
        Route::get('/taxes/{taxSetting}/edit', [TaxSettingController::class, 'edit'])->name('taxes.edit');
        Route::put('/taxes/{taxSetting}', [TaxSettingController::class, 'update'])->name('taxes.update');
        Route::delete('/taxes/{taxSetting}', [TaxSettingController::class, 'destroy'])->name('taxes.destroy');
        Route::patch('/taxes/{taxSetting}/toggle-status', [TaxSettingController::class, 'toggleStatus'])
            ->name('taxes.toggle-status');

        // Document Templates
        Route::get('/document-templates', [DocumentTemplateController::class, 'index'])->name('document-templates.index');
        Route::get('/document-templates/create', [DocumentTemplateController::class, 'create'])->name('document-templates.create');
        Route::post('/document-templates', [DocumentTemplateController::class, 'store'])->name('document-templates.store');
        Route::get('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'show'])->name('document-templates.show');
        Route::get('/document-templates/{documentTemplate}/edit', [DocumentTemplateController::class, 'edit'])->name('document-templates.edit');
        Route::put('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update'])->name('document-templates.update');
        Route::delete('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])->name('document-templates.destroy');
        Route::patch('/document-templates/{documentTemplate}/toggle-status', [DocumentTemplateController::class, 'toggleStatus'])
            ->name('document-templates.toggle-status');
        Route::patch('/document-templates/{documentTemplate}/set-default', [DocumentTemplateController::class, 'setDefault'])
            ->name('document-templates.set-default');

        // User Management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])
            ->name('users.toggle-status');
        Route::put('/users/{user}/reset-password', [UserController::class, 'resetPassword'])
            ->name('users.reset-password');
    });
});
