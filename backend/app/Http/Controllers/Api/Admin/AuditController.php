<?php
// backend/app/Http/Controllers/Api/Admin/AuditController.php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AuditEntryResource;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AdminPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    private const SUBJECT_TYPES = ['user' => User::class];

    public function index(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 25)));
        $page = $this->query($request)->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        return response()->json([
            'data' => AuditEntryResource::collection($page->items())->resolve(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'createdAt', 'action', 'actorName', 'subjectType', 'subjectId', 'subjectName', 'reason', 'before', 'after']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach (AuditEntryResource::collection($rows)->resolve() as $r) {
                    fputcsv($out, [
                        $r['id'], $r['createdAt'], $r['action'], $r['actorName'], $r['subjectType'], $r['subjectId'],
                        $r['subjectName'], $r['reason'], json_encode($r['before']), json_encode($r['after']),
                    ]);
                }
            });
            fclose($out);
        }, 'roofly-audit-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function query(Request $request): Builder
    {
        $user = $request->user();
        $q = Activity::inLog(AuditLogger::LOG_NAME)->with(['causer', 'subject'])
            ->latest('created_at')->latest('id');

        // Without audit.view you only ever see what you did yourself (spec § 5).
        // An actorId filter still applies on top — asking for someone else's
        // entries while scoped to your own must return nothing, not your own.
        if (! $user->can(AdminPermissions::AUDIT_VIEW)) {
            $q->where('causer_id', $user->id);
        }
        if ($actorId = $request->query('actorId')) {
            $q->where('causer_id', $actorId);
        }
        if ($action = $request->query('action')) {
            $q->where('event', $action);
        }
        if ($type = $request->query('subjectType')) {
            $q->where('subject_type', self::SUBJECT_TYPES[$type] ?? $type);
        }
        if ($subjectId = $request->query('subjectId')) {
            $q->where('subject_id', $subjectId);
        }
        if ($from = $request->query('from')) {
            $q->where('created_at', '>=', $from . ' 00:00:00');
        }
        if ($to = $request->query('to')) {
            $q->where('created_at', '<=', $to . ' 23:59:59');
        }

        return $q;
    }
}
