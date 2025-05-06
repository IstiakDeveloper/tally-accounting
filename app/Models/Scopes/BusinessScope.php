<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $businessId = session('active_business_id');
        if ($businessId) {
            $builder->where('business_id', $businessId);
        }
    }
}
