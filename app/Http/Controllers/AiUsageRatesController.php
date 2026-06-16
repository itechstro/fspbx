<?php

namespace App\Http\Controllers;

use App\Services\AiUsageRatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiUsageRatesController extends Controller
{
    public function __construct(
        protected AiUsageRatesService $aiUsageRatesService,
    ) {
    }

    public function show(): JsonResponse
    {
        if (! userCheckPermission('ai_usage_rates_view')) {
            abort(403);
        }

        return response()->json([
            'schema' => $this->aiUsageRatesService->schema(),
            'rates' => $this->aiUsageRatesService->getFlatRates(),
            'can_edit' => userCheckPermission('ai_usage_rates_edit'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        if (! userCheckPermission('ai_usage_rates_edit')) {
            abort(403);
        }

        $data = $request->validate([
            'rates' => ['required', 'array'],
            'rates.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->aiUsageRatesService->saveFlatRates((array) $data['rates']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'errors' => ['rates' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'messages' => ['success' => ['AI usage rates saved.']],
            'rates' => $this->aiUsageRatesService->getFlatRates(),
        ]);
    }
}
