<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

/**
 * Audit log (PRD 6.7), hanya Super Admin.
 */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::with('causer')->latest('id');

        if ($request->filled('log')) {
            $query->where('log_name', $request->log);
        }
        if ($request->filled('causer')) {
            $request->causer === 'system'
                ? $query->whereNull('causer_id')
                : $query->where('causer_id', $request->causer);
        }
        if ($search = $request->search) {
            $query->where('description', 'like', "%{$search}%");
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $activities = $query->paginate(30)->withQueryString();

        $logNames = Activity::select('log_name')->distinct()->orderBy('log_name')->pluck('log_name');
        $causers = User::whereIn('id', Activity::whereNotNull('causer_id')->select('causer_id')->distinct())
            ->orderBy('name')->get(['id', 'name']);

        return view('admin.logs.index', compact('activities', 'logNames', 'causers'));
    }
}
