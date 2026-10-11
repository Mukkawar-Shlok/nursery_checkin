<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

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
            throw new BadRequestHttpException(__('api.errors.tenant_header_invalid'));
        }

        $this->context->set(Tenant::findOrFail($id));

        return $next($request);
    }
}
