export interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
}

export interface PaginatedData<T> {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: Pagination;
}


// User model
export interface User {
    id: number;
    name: string;
    email: string;
    phone?: string;
    role: 'admin' | 'accountant' | 'manager' | 'user';
    is_active: boolean;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
}

// Authentication
export interface Auth {
    user: User;
}

// Page Props provided by Inertia
export interface PageProps {
    auth: Auth;
    errors: Record<string, string>;
    flash?: {
        message?: string;
        success?: string;
        error?: string;
    };
}

// Account Category
export interface AccountCategory {
    id: number;
    name: string;
    type: 'Asset' | 'Liability' | 'Equity' | 'Revenue' | 'Expense';
    created_at: string;
    updated_at: string;
    accounts?: ChartOfAccount[];
}

// Chart of Accounts
export interface ChartOfAccount {
    id: number;
    account_code: string;
    name: string;
    category_id: number;
    category: AccountCategory;
    description?: string;
    is_active: boolean;
    created_by: number;
    createdBy: User;
    created_at: string;
    updated_at: string;
    journal_items?: JournalItem[];
}

// Financial Year
export interface FinancialYear {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Journal Entry
export interface JournalEntry {
    id: number;
    reference_number: string;
    financial_year_id: number;
    financialYear: FinancialYear;
    entry_date: string;
    narration: string;
    status: 'draft' | 'posted' | 'cancelled';
    created_by: number;
    createdBy: User;
    created_at: string;
    updated_at: string;
    items: JournalItem[];
    total_debit: number;
    total_credit: number;
}

// Journal Item
export interface JournalItem {
    id: number;
    journal_entry_id: number;
    account_id: number;
    account: ChartOfAccount;
    type: 'debit' | 'credit';
    amount: number;
    description?: string;
    created_at: string;
    updated_at: string;
}

// Product Category
export interface ProductCategory {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Product
export interface Product {
    id: number;
    code: string;
    name: string;
    description?: string;
    category_id: number;
    category: ProductCategory;
    unit: string;
    purchase_price: number;
    selling_price: number;
    reorder_level: number;
    is_active: boolean;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
    stock_balance?: number;
}

// Warehouse
export interface Warehouse {
    id: number;
    name: string;
    address?: string;
    contact_person?: string;
    contact_number?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Stock Movement
export interface StockMovement {
    id: number;
    reference_number: string;
    type: 'purchase' | 'sale' | 'transfer' | 'adjustment';
    transaction_date: string;
    warehouse_id: number;
    warehouse: Warehouse;
    product_id: number;
    product: Product;
    quantity: number;
    unit_price: number;
    remarks?: string;
    related_journal_entry_id?: number;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
}

// Stock Balance
export interface StockBalance {
    id: number;
    product_id: number;
    product: Product;
    warehouse_id: number;
    warehouse: Warehouse;
    quantity: number;
    average_cost: number;
    created_at: string;
    updated_at: string;
}

// Contact
export interface Contact {
    id: number;
    name: string;
    type: 'customer' | 'supplier' | 'both';
    contact_person?: string;
    phone?: string;
    email?: string;
    address?: string;
    tax_number?: string;
    account_receivable_id?: number;
    account_receivable?: ChartOfAccount;
    account_payable_id?: number;
    account_payable?: ChartOfAccount;
    is_active: boolean;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
}

// Purchase Order
export interface PurchaseOrder {
    id: number;
    reference_number: string;
    supplier_id: number;
    supplier: Contact;
    order_date: string;
    expected_delivery_date?: string;
    status: 'draft' | 'confirmed' | 'received' | 'cancelled';
    total_amount: number;
    remarks?: string;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
    items: PurchaseOrderItem[];
}

// Purchase Order Item
export interface PurchaseOrderItem {
    id: number;
    purchase_order_id: number;
    product_id: number;
    product: Product;
    quantity: number;
    unit_price: number;
    discount: number;
    tax_amount: number;
    total: number;
    created_at: string;
    updated_at: string;
}

// Sales Order
export interface SalesOrder {
    id: number;
    reference_number: string;
    customer_id: number;
    customer: Contact;
    order_date: string;
    delivery_date?: string;
    status: 'draft' | 'confirmed' | 'delivered' | 'cancelled';
    total_amount: number;
    remarks?: string;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
    items: SalesOrderItem[];
}

// Sales Order Item
export interface SalesOrderItem {
    id: number;
    sales_order_id: number;
    product_id: number;
    product: Product;
    quantity: number;
    unit_price: number;
    discount: number;
    tax_amount: number;
    total: number;
    created_at: string;
    updated_at: string;
}

// Invoice
export interface Invoice {
    id: number;
    reference_number: string;
    type: 'sales' | 'purchase';
    contact_id: number;
    contact: Contact;
    sales_order_id?: number;
    sales_order?: SalesOrder;
    purchase_order_id?: number;
    purchase_order?: PurchaseOrder;
    invoice_date: string;
    due_date: string;
    sub_total: number;
    discount: number;
    tax_amount: number;
    total: number;
    amount_paid: number;
    status: 'unpaid' | 'partially_paid' | 'paid' | 'cancelled';
    journal_entry_id?: number;
    journal_entry?: JournalEntry;
    remarks?: string;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
    payments?: Payment[];
}

// Payment
export interface Payment {
    id: number;
    reference_number: string;
    invoice_id: number;
    invoice: Invoice;
    payment_date: string;
    amount: number;
    payment_method: 'cash' | 'bank' | 'mobile_banking';
    transaction_id?: string;
    account_id: number;
    account: ChartOfAccount;
    journal_entry_id: number;
    journal_entry: JournalEntry;
    remarks?: string;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
}

// Department
export interface Department {
    id: number;
    name: string;
    description?: string;
    manager_id?: number;
    manager?: User;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Designation
export interface Designation {
    id: number;
    name: string;
    description?: string;
    department_id: number;
    department: Department;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Employee
export interface Employee {
    id: number;
    employee_id: string;
    user_id: number;
    user: User;
    department_id: number;
    department: Department;
    designation_id: number;
    designation: Designation;
    joining_date: string;
    employment_status: 'permanent' | 'probation' | 'contract' | 'part-time';
    contract_end_date?: string;
    basic_salary: number;
    salary_account_id: number;
    salary_account: ChartOfAccount;
    bank_name?: string;
    bank_account_number?: string;
    tax_identification_number?: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    allowances?: EmployeeAllowance[];
    deductions?: EmployeeDeduction[];
}

// Allowance Type
export interface AllowanceType {
    id: number;
    name: string;
    type: 'fixed' | 'percentage';
    value: number;
    is_taxable: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Deduction Type
export interface DeductionType {
    id: number;
    name: string;
    type: 'fixed' | 'percentage';
    value: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Employee Allowance
export interface EmployeeAllowance {
    id: number;
    employee_id: number;
    employee: Employee;
    allowance_type_id: number;
    allowance_type: AllowanceType;
    amount: number;
    created_at: string;
    updated_at: string;
}

// Employee Deduction
export interface EmployeeDeduction {
    id: number;
    employee_id: number;
    employee: Employee;
    deduction_type_id: number;
    deduction_type: DeductionType;
    amount: number;
    created_at: string;
    updated_at: string;
}

// Salary Slip
export interface SalarySlip {
    id: number;
    reference_number: string;
    employee_id: number;
    employee: Employee;
    month_year: string;
    basic_salary: number;
    total_allowances: number;
    total_deductions: number;
    net_salary: number;
    payment_status: 'unpaid' | 'paid';
    payment_date?: string;
    journal_entry_id?: number;
    journal_entry?: JournalEntry;
    remarks?: string;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
    details: SalarySlipDetail[];
}

// Salary Slip Detail
export interface SalarySlipDetail {
    id: number;
    salary_slip_id: number;
    type: 'allowance' | 'deduction';
    name: string;
    amount: number;
    created_at: string;
    updated_at: string;
}

// Leave Type
export interface LeaveType {
    id: number;
    name: string;
    days_allowed_per_year: number;
    is_paid: boolean;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Leave Application
export interface LeaveApplication {
    id: number;
    employee_id: number;
    employee: Employee;
    leave_type_id: number;
    leave_type: LeaveType;
    start_date: string;
    end_date: string;
    total_days: number;
    reason: string;
    status: 'pending' | 'approved' | 'rejected';
    approved_by?: number;
    approver?: User;
    remarks?: string;
    created_at: string;
    updated_at: string;
}

// Tax Setting
export interface TaxSetting {
    id: number;
    name: string;
    rate: number;
    description?: string;
    is_active: boolean;
    account_id: number;
    account: ChartOfAccount;
    created_at: string;
    updated_at: string;
}

// Company Setting
export interface CompanySetting {
    id: number;
    name: string;
    legal_name?: string;
    tax_identification_number?: string;
    registration_number?: string;
    address: string;
    city?: string;
    state?: string;
    postal_code?: string;
    country: string;
    phone: string;
    email?: string;
    website?: string;
    logo?: string;
    currency: string;
    currency_symbol: string;
    date_format: string;
    time_format: string;
    timezone: string;
    fiscal_year_start_month: string;
    decimal_separator: string;
    thousand_separator: string;
    invoice_prefix: string;
    purchase_prefix: string;
    sales_prefix: string;
    receipt_prefix: string;
    payment_prefix: string;
    journal_prefix: string;
    created_at: string;
    updated_at: string;
}

// Bank Account
export interface BankAccount {
    id: number;
    account_name: string;
    account_number: string;
    bank_name: string;
    branch_name?: string;
    swift_code?: string;
    routing_number?: string;
    address?: string;
    contact_person?: string;
    contact_number?: string;
    account_id: number;
    account: ChartOfAccount;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

export interface BankAccountIndexProps {
    bankAccounts: Pagination<BankAccount>;
    totalBalance: number;
    formattedTotalBalance: string;
    filters: {
        status?: string;
        bank_name?: string;
        search?: string;
    };
    companySetting: CompanySetting;
}

export interface BankAccountShowProps {
    bankAccount: BankAccount;
    balance: number;
    formattedBalance: string;
    recentTransactions: Transaction[];
    companySetting: CompanySetting;
}

export interface BankAccountFormProps {
    chartAccounts: ChartOfAccount[];
    bankAccount?: BankAccount;
}

export interface BankAccountStatementProps {
    bankAccount: BankAccount;
    statementData: Transaction[];
    fromDate: string;
    toDate: string;
    openingBalance: number;
    totalDebits: number;
    totalCredits: number;
    netMovement: number;
    closingBalance: number;
    formattedOpeningBalance: string;
    formattedTotalDebits: string;
    formattedTotalCredits: string;
    formattedNetMovement: string;
    formattedClosingBalance: string;
    currencySymbol: string;
    companySetting: CompanySetting;
}

export interface BankTransferProps {
    bankAccounts: BankAccount[];
    companySetting: CompanySetting;
}

export interface BankDepositProps {
    bankAccounts: BankAccount[];
    incomeAccounts: ChartOfAccount[];
    companySetting: CompanySetting;
}

export interface BankWithdrawalProps {
    bankAccounts: BankAccount[];
    expenseAccounts: ChartOfAccount[];
    companySetting: CompanySetting;
}

export interface BankReconciliationProps {
    bankAccount: BankAccount;
    systemBalance: number;
    formattedSystemBalance: string;
    companySetting: CompanySetting;
}

// Report Template
export interface ReportTemplate {
    id: number;
    name: string;
    type: 'financial' | 'inventory' | 'sales' | 'purchase' | 'payroll';
    description?: string;
    structure?: any;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Saved Report
export interface SavedReport {
    id: number;
    report_template_id: number;
    report_template: ReportTemplate;
    name: string;
    parameters?: any;
    from_date?: string;
    to_date?: string;
    data?: any;
    created_by: number;
    creator: User;
    created_at: string;
    updated_at: string;
}

// Audit Log
export interface AuditLog {
    id: number;
    user_id?: number;
    user?: User;
    ip_address?: string;
    user_agent?: string;
    action: 'create' | 'update' | 'delete' | 'login' | 'logout';
    module: string;
    reference_id?: string;
    old_values?: any;
    new_values?: any;
    description?: string;
    created_at: string;
    updated_at: string;
}
