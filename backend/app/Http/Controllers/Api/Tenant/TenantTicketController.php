<?php

namespace App\Http\Controllers\Api\Tenant;

use App\Enums\ReporterRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketWithRefsResource;
use App\Models\Agreement;
use App\Models\Ticket;
use Illuminate\Http\Request;

class TenantTicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::where('reporter_id', $request->user()->id)->latest();

        if ($request->filled('expand')) {
            return TicketWithRefsResource::collection(
                $query->with(['unit.property.coOwners', 'reporter', 'comments'])->get()
            );
        }

        return TicketResource::collection($query->get());
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_if($ticket->reporter_id !== $request->user()->id, 403);

        if ($request->filled('expand')) {
            $ticket->load(['unit.property.coOwners', 'reporter', 'comments']);

            return new TicketWithRefsResource($ticket);
        }

        return new TicketResource($ticket);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category'    => 'required|in:plumbing,electrical,appliance,structural,pest,other',
            'priority'    => 'required|in:low,medium,high,urgent',
            'title'       => 'required|string|max:100',
            'description' => 'required|string',
        ]);

        // Derive the unit from the tenant's active agreement. unitId/reporterId/reporterRole in the
        // body are ignored — never trusted from the client.
        $agreement = Agreement::where('tenant_id', $request->user()->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        abort_if(! $agreement, 422, 'No active agreement — cannot file a ticket.');

        $ticket = Ticket::create(array_merge($data, [
            'unit_id'       => $agreement->unit_id,
            'reporter_id'   => $request->user()->id,
            'reporter_role' => ReporterRole::TENANT,
        ]));

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function addComment(StoreTicketCommentRequest $request, Ticket $ticket)
    {
        abort_if($ticket->reporter_id !== $request->user()->id, 403);

        $comment = $ticket->comments()->create([
            'author_id'   => $request->user()->id,
            'author_role' => ReporterRole::TENANT,
            'body'        => $request->validated('body'),
        ]);

        return (new TicketCommentResource($comment))->response()->setStatusCode(201);
    }
}
