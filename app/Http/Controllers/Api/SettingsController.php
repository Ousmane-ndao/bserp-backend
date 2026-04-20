<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function companyShow(): JsonResponse
    {
        $settings = CompanySetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'company_name' => 'BSERP',
                'address' => null,
                'city' => null,
                'country' => 'Maroc',
            ]
        );

        return response()->json($settings);
    }

    public function companyUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);

        $settings = CompanySetting::query()->firstOrCreate(['id' => 1]);
        $settings->update($validated);

        return response()->json($settings);
    }
}
