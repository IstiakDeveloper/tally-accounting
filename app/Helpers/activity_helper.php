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
