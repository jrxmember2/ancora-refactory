<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\AncoraAuth;
use App\Support\AuditLogPresenter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditUserAction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->shouldAudit($request)) {
            return $next($request);
        }

        $user = AncoraAuth::user($request);
        $route = $request->route();
        $routeName = (string) ($route?->getName() ?: 'sem_rota');
        $entityType = explode('.', $routeName)[0] ?: null;
        $entityId = $this->firstRouteEntityId($request);

        try {
            $response = $next($request);
            if (!$request->attributes->get('audit.skip_generic')) {
                $this->write($request, $user, $routeName, $entityType, $entityId, $response->getStatusCode());
            }

            return $response;
        } catch (\Throwable $e) {
            $this->write($request, $user, $routeName, $entityType, $entityId, 500);
            throw $e;
        }
    }

    private function shouldAudit(Request $request): bool
    {
        return !in_array(strtoupper($request->method()), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function firstRouteEntityId(Request $request): ?int
    {
        foreach ((array) $request->route()?->parameters() as $parameter) {
            if (is_numeric($parameter)) {
                return (int) $parameter;
            }

            if (is_object($parameter) && method_exists($parameter, 'getKey')) {
                return (int) $parameter->getKey();
            }
        }

        return null;
    }

    private function write(Request $request, mixed $user, string $routeName, ?string $entityType, ?int $entityId, int $status): void
    {
        try {
            $entityType = AuditLogPresenter::entityTypeForRoute($routeName, $entityType);

            AuditLog::query()->create([
                'user_id' => $user?->id,
                'user_email' => $user?->email ?? 'desconhecido',
                'action' => $routeName,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => AuditLogPresenter::detailsFromRequest($request, $routeName, $status),
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Auditoria nunca deve derrubar a operação principal do usuário.
        }
    }
}
