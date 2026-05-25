<?php

namespace App\Http\Controllers\Api\Hub\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Hub\HubApiContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

abstract class HubApiController extends Controller
{
    protected function validateRequest(
        Request $request,
        array $rules,
        array $messages = [],
        array $attributes = [],
    ): array|JsonResponse {
        $validator = Validator::make(
            $request->all(),
            $rules,
            array_merge($this->defaultValidationMessages(), $messages),
            array_merge($this->defaultValidationAttributes(), $attributes),
        );

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        return $validator->validated();
    }

    protected function unauthorizedResponse(string $message = 'Sessão inválida ou expirada.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 401);
    }

    protected function forbiddenResponse(string $message = 'Você não possui permissão para acessar este módulo.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 403);
    }

    protected function notFoundResponse(string $message = 'Recurso não encontrado.'): JsonResponse
    {
        return response()->json([
            'message' => $message,
        ], 404);
    }

    protected function requireAuthorizedUser(
        Request $request,
        array $routeNames = [],
        array $moduleSlugs = [],
        string $forbiddenMessage = 'Você não possui permissão para acessar este módulo.',
    ): User|JsonResponse {
        $user = HubApiContext::user($request);

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        if ($moduleSlugs !== [] && !$this->userHasAnyModule($user, $moduleSlugs)) {
            return $this->forbiddenResponse($forbiddenMessage);
        }

        if ($routeNames !== [] && !$this->userCanAnyRoute($user, $routeNames)) {
            return $this->forbiddenResponse($forbiddenMessage);
        }

        return $user;
    }

    protected function userCanAnyRoute(User $user, array $routeNames): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $routeNames = array_values(array_filter(array_map(
            static fn ($routeName) => trim((string) $routeName),
            $routeNames,
        )));

        if ($routeNames === []) {
            return true;
        }

        if ($user->relationLoaded('routePermissions')) {
            $allowed = $user->routePermissions
                ->pluck('route_name')
                ->filter()
                ->map(fn ($routeName) => trim((string) $routeName))
                ->all();

            return count(array_intersect($routeNames, $allowed)) > 0;
        }

        return $user->routePermissions()
            ->whereIn('route_name', $routeNames)
            ->exists();
    }

    protected function userHasAnyModule(User $user, array $moduleSlugs): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        $moduleSlugs = array_values(array_filter(array_map(
            static fn ($slug) => trim((string) $slug),
            $moduleSlugs,
        )));

        if ($moduleSlugs === []) {
            return true;
        }

        if ($user->relationLoaded('modules')) {
            $allowed = $user->modules
                ->filter(fn ($module) => (bool) $module->is_enabled)
                ->pluck('slug')
                ->map(fn ($slug) => trim((string) $slug))
                ->all();

            return count(array_intersect($moduleSlugs, $allowed)) > 0;
        }

        return $user->modules()
            ->where('is_enabled', 1)
            ->whereIn('slug', $moduleSlugs)
            ->exists();
    }

    protected function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function defaultValidationMessages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto válido.',
            'email' => 'Informe um :attribute válido.',
            'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'confirmed' => 'A confirmação de :attribute não confere.',
            'unique' => 'Este :attribute já está em uso.',
            'exists' => 'O valor informado para :attribute não foi encontrado.',
            'max' => 'O campo :attribute não pode ser maior que :max.',
            'max.string' => 'O campo :attribute não pode ter mais de :max caracteres.',
            'min' => 'O campo :attribute deve ser no mínimo :min.',
            'min.string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'in' => 'O valor informado para :attribute é inválido.',
            'nullable' => 'O campo :attribute é inválido.',
        ];
    }

    private function defaultValidationAttributes(): array
    {
        return [
            'app_version' => 'versão do aplicativo',
            'assigned_user_id' => 'responsável',
            'biometric_enabled' => 'proteção por biometria',
            'client_condominium_id' => 'condomínio',
            'current_password' => 'senha atual',
            'device_name' => 'nome do dispositivo',
            'demand_tag_id' => 'etapa da demanda',
            'email' => 'e-mail',
            'fcm_token' => 'token do dispositivo',
            'filter' => 'filtro',
            'id' => 'identificador',
            'message' => 'mensagem',
            'name' => 'nome',
            'page' => 'página',
            'password' => 'senha',
            'password_confirmation' => 'confirmação da senha',
            'per_page' => 'quantidade por página',
            'platform' => 'plataforma',
            'priority' => 'prioridade',
            'q' => 'busca',
            'scope' => 'escopo',
            'status' => 'status',
            'theme_preference' => 'tema',
            'workflow_stage' => 'etapa',
        ];
    }
}
