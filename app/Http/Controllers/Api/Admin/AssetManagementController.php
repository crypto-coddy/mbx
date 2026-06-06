<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Asset;
use App\Models\Trade;
use App\Services\AdminActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetManagementController extends ApiController
{
    public function __construct(protected AdminActivityLogger $activityLogger) {}

    public function index(): JsonResponse
    {
        return $this->success(Asset::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'symbol' => ['required', 'string', 'unique:assets,symbol'],
            'display_name' => ['required', 'string'],
            'currency' => ['sometimes', 'string'],
            'min_trade_amount' => ['sometimes', 'numeric'],
            'max_trade_amount' => ['sometimes', 'numeric'],
            'api_config' => ['sometimes', 'array'],
        ]);

        $asset = Asset::create($data);

        return $this->success($asset, 'Asset created.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string'],
            'display_name' => ['sometimes', 'string'],
            'icon_url' => ['sometimes', 'nullable', 'string'],
            'min_trade_amount' => ['sometimes', 'numeric'],
            'max_trade_amount' => ['sometimes', 'numeric'],
            'api_config' => ['sometimes', 'array'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        $asset->update($data);

        return $this->success($asset->fresh(), 'Asset updated.');
    }

    public function toggle(int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);
        $asset->update([
            'is_active' => ! $asset->is_active,
            'trading_enabled' => ! $asset->is_active,
        ]);

        return $this->success($asset->fresh(), 'Asset toggled.');
    }

    public function destroy(int $id): JsonResponse
    {
        $asset = Asset::findOrFail($id);

        if (Trade::where('asset_id', $id)->exists()) {
            return $this->error('Cannot delete asset with existing trades.', null, 422);
        }

        $asset->delete();

        return $this->success(null, 'Asset deleted.');
    }
}
