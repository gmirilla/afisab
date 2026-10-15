<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/** Read-only view of the audit trail: who did what, to what, and when. */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'who' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = Activity::with(['causer', 'subject'])->orderByDesc('id');
        if (! empty($f['q'])) {
            $query->where('description', 'like', '%'.$f['q'].'%');
        }
        if (! empty($f['type'])) {
            $query->where('subject_type', $f['type']);
        }
        if (! empty($f['who'])) {
            $query->whereHasMorph('causer', ['user'], fn ($q) => $q->where('name', 'like', '%'.$f['who'].'%')->orWhere('email', 'like', '%'.$f['who'].'%'));
        }
        if (! empty($f['from'])) {
            $query->where('created_at', '>=', Carbon::parse($f['from'])->startOfDay());
        }
        if (! empty($f['to'])) {
            $query->where('created_at', '<=', Carbon::parse($f['to'])->endOfDay());
        }

        return view('admin.audit', [
            'entries' => $query->paginate(50)->withQueryString(),
            'types' => Activity::query()->whereNotNull('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type'),
            'filters' => $f,
        ]);
    }
}
