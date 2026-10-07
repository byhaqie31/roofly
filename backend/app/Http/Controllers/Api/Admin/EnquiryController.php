<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminEnquiryResource;
use App\Models\Enquiry;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Enquiries → Messages (support.manage). Track only: replies go out from normal email. */
class EnquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(Enquiry::STATUSES)],
            'type'   => ['nullable', Rule::in(Enquiry::TYPES)],
        ]);

        $q = Enquiry::query()->with('handler:id,name')->latest('created_at')->orderBy('id');
        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")->orWhere('message', 'like', "%{$s}%"));
        }
        if ($status = $request->query('status')) { $q->where('status', $status); }
        if ($type = $request->query('type')) { $q->where('type', $type); }

        $perPage = min(100, max(1, (int) $request->integer('perPage', 20)));
        $page = $q->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        return response()->json([
            'data' => AdminEnquiryResource::collection($page->items())->resolve(),
            'meta' => [
                'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'lastPage' => $page->lastPage(),
                'newCount' => Enquiry::where('status', 'new')->count(),
            ],
        ]);
    }

    public function update(Request $request, Enquiry $enquiry, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'status'    => ['sometimes', Rule::in(Enquiry::STATUSES)],
            'adminNote' => 'sometimes|nullable|string|max:5000',
        ]);

        $before = ['status' => $enquiry->status, 'adminNote' => $enquiry->admin_note];
        $changes = ['handled_by' => $request->user()->id];
        if (array_key_exists('status', $data) && $data['status'] !== $enquiry->status) {
            $changes['status'] = $data['status'];
            $changes['status_changed_at'] = now();
        }
        if (array_key_exists('adminNote', $data)) {
            $changes['admin_note'] = $data['adminNote'];
        }
        $enquiry->update($changes);

        $audit->record(AuditLogger::ENQUIRY_UPDATED, $enquiry, $before, ['status' => $enquiry->status, 'adminNote' => $enquiry->admin_note]);

        return response()->json((new AdminEnquiryResource($enquiry->load('handler:id,name')))->resolve());
    }
}
