<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\ClientPortalNotification;
use App\Support\Mobile\MobileApiContext;
use App\Support\Mobile\MobileApiPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user, 401);

        $query = ClientPortalNotification::query()
            ->where('client_portal_user_id', $user->id)
            ->latest('created_at');

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $items = $query->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'items' => collect($items->items())->map(fn (ClientPortalNotification $item) => MobileApiPresenter::notification($item))->values()->all(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'unread_count' => ClientPortalNotification::query()
                    ->where('client_portal_user_id', $user->id)
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function read(Request $request, ClientPortalNotification $notification): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user && (int) $notification->client_portal_user_id === (int) $user->id, 404);

        if (!$notification->read_at) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = MobileApiContext::user($request);
        abort_unless($user, 401);

        ClientPortalNotification::query()
            ->where('client_portal_user_id', $user->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'ok' => true,
        ]);
    }
}
