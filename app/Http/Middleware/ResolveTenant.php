<?php

namespace App\Http\Middleware;

use App\Exceptions\Domain\TenantHeaderInvalid;
use App\Exceptions\Domain\TenantNotFound;
use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Must run before SubstituteBindings (see bootstrap/app.php) so that
 * route model binding is already tenant-scoped.
 */
final class ResolveTenant
{
    public function __construct(private TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Tenant-ID');

        if (! is_string($id) || ! Str::isUuid($id)) {
            throw new TenantHeaderInvalid;
        }

        $this->context->set(Tenant::find($id) ?? throw new TenantNotFound);

        return $next($request);
    }
}
