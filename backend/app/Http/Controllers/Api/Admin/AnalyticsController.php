<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnalyticsRangeRequest;
use App\Http\Resources\Admin\AdminLeadResource;
use App\Http\Resources\Admin\LeadEventResource;
use App\Models\AnalyticsEvent;
use App\Models\Lead;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Read-only platform analytics (spec § 5). Counts only — never money, never PII beyond lead email. */
class AnalyticsController extends Controller
{
    public function overview(AnalyticsRangeRequest $request): JsonResponse
    {
        [$from, $to] = $request->range();
        $days = (int) $from->diffInDays($to) + 1;

        $inRange = fn () => AnalyticsEvent::whereBetween('created_at', [$from, $to]);

        $visitorIds = $inRange()->distinct()->pluck('visitor_id');
        $firstSeen = AnalyticsEvent::whereIn('visitor_id', $visitorIds)
            ->selectRaw('visitor_id, MIN(created_at) as first_at')->groupBy('visitor_id')->pluck('first_at', 'visitor_id');
        $newVisitors = $firstSeen->filter(fn ($t) => Carbon::parse($t)->gte($from))->count();

        $views = $inRange()->where('event', 'page_view')->count();
        $demoEntries = $inRange()->where('event', 'demo_enter')->count();
        $demoVisitors = $inRange()->where('event', 'demo_enter')->distinct()->count('visitor_id');
        $leads = Lead::whereBetween('first_seen_at', [$from, $to])->count();
        // server link is the source of truth; the client register beacon is timeline-only —
        // registrations (and the funnel's "registered" figure) come from Lead.converted_user_id,
        // never from the client-fired 'register' analytics event.
        $registeredLeads = Lead::whereBetween('first_seen_at', [$from, $to])->whereNotNull('converted_user_id')->count();

        // Daily series, oldest first.
        $dayKeys = [];
        for ($d = $from->copy(); $d <= $to; $d->addDay()) {
            $dayKeys[] = $d->toDateString();
        }
        $bucket = function (string $event, bool $distinctVisitor) use ($inRange, $dayKeys) {
            $q = $inRange();
            if ($event !== '*') { $q->where('event', $event); }
            $rows = $q->get(['visitor_id', 'created_at'])->groupBy(fn ($e) => $e->created_at->toDateString());
            return array_map(fn ($k) => isset($rows[$k]) ? ($distinctVisitor ? $rows[$k]->unique('visitor_id')->count() : $rows[$k]->count()) : 0, $dayKeys);
        };
        $leadsByDay = Lead::whereBetween('first_seen_at', [$from, $to])->get(['first_seen_at'])->groupBy(fn ($l) => $l->first_seen_at->toDateString());
        $registeredLeadsByDay = Lead::whereBetween('first_seen_at', [$from, $to])->whereNotNull('converted_user_id')
            ->get(['first_seen_at'])->groupBy(fn ($l) => $l->first_seen_at->toDateString());

        $topPages = $inRange()->where('event', 'page_view')->whereNotNull('path')
            ->select('path', DB::raw('count(*) as views'))->groupBy('path')->orderByDesc('views')->limit(10)->get()
            ->map(fn ($r) => ['path' => $r->path, 'views' => (int) $r->views])->values();
        $referrers = $inRange()->where('event', 'page_view')
            ->select(DB::raw("COALESCE(referrer, 'direct') as referrer"), DB::raw('count(distinct visitor_id) as visitors'))
            ->groupBy(DB::raw("COALESCE(referrer, 'direct')"))
            ->orderByDesc('visitors')->orderBy('referrer')->limit(10)->get()
            ->map(fn ($r) => ['referrer' => $r->referrer, 'visitors' => (int) $r->visitors])->values();

        $visitors = $visitorIds->count();

        return response()->json([
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'days' => $days],
            'tiles' => [
                'views' => $views, 'visitors' => $visitors, 'newVisitors' => $newVisitors, 'demoEntries' => $demoEntries,
                'leads' => $leads, 'registrations' => $registeredLeads,
                'conversionPct' => $visitors > 0 ? (int) round($registeredLeads / $visitors * 100) : 0,
            ],
            'series' => [
                'days' => $dayKeys,
                'views' => $bucket('page_view', false),
                'visitors' => $bucket('*', true),
                'leads' => array_map(fn ($k) => isset($leadsByDay[$k]) ? $leadsByDay[$k]->count() : 0, $dayKeys),
                'registrations' => array_map(fn ($k) => isset($registeredLeadsByDay[$k]) ? $registeredLeadsByDay[$k]->count() : 0, $dayKeys),
            ],
            'funnel' => ['visitors' => $visitors, 'demo' => $demoVisitors, 'leads' => $leads, 'registered' => $registeredLeads],
            'topPages' => $topPages,
            'referrers' => $referrers,
        ]);
    }

    private function leadQuery(Request $request): Builder
    {
        $q = Lead::query()->with('convertedUser:id,name')->orderByDesc('last_seen_at')->orderBy('id');
        if ($s = trim((string) $request->query('q', ''))) { $q->where('email', 'like', "%{$s}%"); }
        if ($src = $request->query('source')) { $q->where('source', $src); }
        if ($request->boolean('converted')) { $q->whereNotNull('converted_user_id'); }
        return $q;
    }

    /** Attaches page_views_count + demo_entered to each lead from its visitor's events (one query each). */
    private function decorate($leads): void
    {
        $vids = $leads->pluck('visitor_id')->filter()->values();
        $views = AnalyticsEvent::whereIn('visitor_id', $vids)->where('event', 'page_view')
            ->select('visitor_id', DB::raw('count(*) as c'))->groupBy('visitor_id')->pluck('c', 'visitor_id');
        $demo = AnalyticsEvent::whereIn('visitor_id', $vids)->where('event', 'demo_enter')->distinct()->pluck('visitor_id')->flip();
        foreach ($leads as $lead) {
            $lead->page_views_count = (int) ($views[$lead->visitor_id] ?? 0);
            $lead->demo_entered = isset($demo[$lead->visitor_id]);
        }
    }

    public function leads(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, (int) $request->integer('perPage', 20)));
        $page = $this->leadQuery($request)->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));
        $items = collect($page->items());
        $this->decorate($items);

        return response()->json([
            'data' => AdminLeadResource::collection($items)->resolve(),
            'meta' => ['page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage()],
        ]);
    }

    public function lead(Lead $lead): JsonResponse
    {
        $lead->load('convertedUser:id,name');
        $this->decorate(collect([$lead]));
        $events = $lead->visitor_id
            ? AnalyticsEvent::where('visitor_id', $lead->visitor_id)->latest('created_at')->limit(20)->get()
            : collect();

        LeadEventResource::forLead($lead->email);
        $eventsPayload = LeadEventResource::collection($events)->resolve();
        LeadEventResource::forLead(null);

        return response()->json((new AdminLeadResource($lead))->resolve() + ['events' => $eventsPayload]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $query = $this->leadQuery($request);
        $audit->record(AuditLogger::ANALYTICS_EXPORTED, null, [], ['filters' => $request->only('q', 'source', 'converted')]);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'source', 'firstSeenAt', 'lastSeenAt', 'pageViews', 'demoEntered', 'convertedOwnerName']);
            $query->chunk(500, function ($rows) use ($out) {
                $this->decorate($rows);
                foreach (AdminLeadResource::collection($rows)->resolve() as $r) {
                    fputcsv($out, [$r['email'], $r['source'], $r['firstSeenAt'], $r['lastSeenAt'], $r['pageViews'], $r['demoEntered'] ? 'yes' : 'no', $r['convertedOwnerName']]);
                }
            });
            fclose($out);
        }, 'roofly-leads-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
