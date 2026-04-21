<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Automation\ProcessWhatsappMessageRequest;
use App\Services\Automation\AutomationConversationService;
use App\Services\Automation\IncomingAutomationMessageData;
use Illuminate\Http\JsonResponse;

class AutomationController extends Controller
{
    public function processWhatsappMessage(
        ProcessWhatsappMessageRequest $request,
        AutomationConversationService $conversation,
    ): JsonResponse {
        $response = $conversation->process(IncomingAutomationMessageData::fromArray($request->validated()));

        return response()->json($response, (int) data_get($response, 'meta.http_status', 200));
    }
}
