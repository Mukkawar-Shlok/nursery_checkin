<?php

namespace App\Models\Concerns;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes every query to the current tenant and fills tenant_id on create.
 * Fails closed: querying without a tenant in context throws.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            $query->where($query->qualifyColumn('tenant_id'), app(TenantContext::class)->id());
        });

        static::creating(function ($model) {
            $model->tenant_id ??= app(TenantContext::class)->id();
        });
    }
}
