<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage', User::class);

        $query = AuditLog::query()->with('user')->latest();

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->string('auditable_type'));
        }
        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', $request->integer('auditable_id'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return AuditLogResource::collection($query->paginate(50));
    }
}
