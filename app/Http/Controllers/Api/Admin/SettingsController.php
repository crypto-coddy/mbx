<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\TradeSetting;
use App\Services\TradeSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends ApiController
{
    public function __construct(protected TradeSettingService $settings) {}

    public function index(): JsonResponse
    {
        return $this->success(TradeSetting::orderBy('key')->get());
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $data = $request->validate(['value' => ['required', 'string']]);
        $setting = $this->settings->set($key, $data['value']);

        return $this->success($setting, 'Setting updated.');
    }
}
