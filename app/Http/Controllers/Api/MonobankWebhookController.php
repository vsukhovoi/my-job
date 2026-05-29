<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMonobankPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MonobankWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->json()->all();

        if (empty($data['data']['statementItem'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $statement = $data['data']['statementItem'];

        Log::info('Monobank webhook received', ['statement_id' => $statement['id'] ?? null]);

        if (($statement['status'] ?? '') !== 'DONE') {
            return response()->json(['status' => 'ignored'], 200);
        }

        ProcessMonobankPayment::dispatch($statement);

        return response()->json(['status' => 'ok'], 200);
    }
}
