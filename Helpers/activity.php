<?php

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

if (!function_exists('activity')) {
    /**
     * Create a new activity logger instance.
     *
     * @return \App\Helpers\ActivityLogger
     */
    function activity() {
        return new \App\Helpers\ActivityLogger();
    }
}

namespace App\Helpers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    protected $user;
    protected $model;
    protected $properties = [];

    /**
     * Set the user that caused the activity.
     *
     * @param \App\Models\User|null $user
     * @return $this
     */
    public function causedBy($user = null) {
        $this->user = $user ?? Auth::user();

        return $this;
    }

    /**
     * Set the model that was affected by the activity.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return $this
     */
    public function performedOn(Model $model) {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the properties of the activity.
     *
     * @param array $properties
     * @return $this
     */
    public function withProperties(array $properties = []) {
        $this->properties = $properties;

        return $this;
    }

    /**
     * Log the activity.
     *
     * @param string $action
     * @return \App\Models\AuditLog
     */
    public function log(string $action) {
        $moduleMap = [
            'App\Models\User' => 'users',
            'App\Models\ChartOfAccount' => 'accounts',
            'App\Models\AccountCategory' => 'accounts',
            'App\Models\JournalEntry' => 'journal_entries',
            'App\Models\FinancialYear' => 'financial_years',
            'App\Models\Product' => 'products',
            'App\Models\ProductCategory' => 'products',
            'App\Models\Warehouse' => 'warehouses',
            'App\Models\StockMovement' => 'inventory',
            'App\Models\Contact' => 'contacts',
            'App\Models\PurchaseOrder' => 'purchases',
            'App\Models\SalesOrder' => 'sales',
            'App\Models\Invoice' => 'invoices',
            'App\Models\Payment' => 'payments',
            'App\Models\Department' => 'departments',
            'App\Models\Designation' => 'designations',
            'App\Models\Employee' => 'employees',
            'App\Models\SalarySlip' => 'payroll',
            'App\Models\LeaveApplication' => 'leaves',
            'App\Models\TaxSetting' => 'settings',
            'App\Models\CompanySetting' => 'settings',
            'App\Models\DocumentTemplate' => 'settings',
            'App\Models\BankAccount' => 'accounts',
        ];

        $log = new AuditLog();

        if ($this->user) {
            $log->user_id = $this->user->id;
        }

        $log->ip_address = request()->ip();
        $log->user_agent = request()->userAgent();
        $log->action = $action;

        if ($this->model) {
            $modelClass = get_class($this->model);
            $log->module = $moduleMap[$modelClass] ?? 'other';
            $log->reference_id = $this->model->id;
        } else {
            $log->module = 'general';
        }

        if (!empty($this->properties)) {
            if (isset($this->properties['old'])) {
                $log->old_values = $this->properties['old'];
            }

            if (isset($this->properties['new'])) {
                $log->new_values = $this->properties['new'];
            } else {
                $log->new_values = $this->properties;
            }
        }

        $log->save();

        return $log;
    }
}
