<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\AdminActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = AdminActivityLog::with('admin:id,name')->latest('created_at');

        if ($request->filled('admin_id')) {
            $query->where('admin_id', $request->input('admin_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }
}
