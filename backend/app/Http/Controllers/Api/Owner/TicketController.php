<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\ReporterRole;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketStatusRequest;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketWithRefsResource;
use App\Models\Ticket;
use App\Models\Unit;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::whereHas('unit.property', fn ($q) =>
            $q->where('owner_id', $request->user()->id)
        )->latest();

        if ($request->filled('expand')) {
            return TicketWithRefsResource::collection(
                $query->with(['unit.property.coOwners', 'reporter', 'comments'])->get()
            );
        }

        return TicketResource::collection($query->get());
    }

    public function store(StoreTicketRequest $request)
    {
        $unit = Unit::findOrFail($request->validated('unitId'));
        abort_if($unit->property->owner_id !== $request->user()->id, 403);

        $ticket = Ticket::create(array_merge($request->toModelAttributes(), [
            'reporter_id'   => $request->user()->id,
            'reporter_role' => ReporterRole::OWNER,
        ]));

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, Ticket $ticket)
    {
        $this->authorizeOwner($request, $ticket);

        if ($request->filled('expand')) {
            $ticket->load(['unit.property.coOwners', 'reporter', 'comments']);
            return new TicketWithRefsResource($ticket);
        }

        return new TicketResource($ticket);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $this->authorizeOwner($request, $ticket);

        $data = $request->validate([
            'category'    => 'sometimes|in:plumbing,electrical,appliance,structural,pest,other',
            'priority'    => 'sometimes|in:low,medium,high,urgent',
            'title'       => 'sometimes|string|max:100',
            'description' => 'sometimes|string',
        ]);

        $ticket->update($data);

        return new TicketResource($ticket);
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $this->authorizeOwner($request, $ticket);

        $ticket->delete();

        return response()->json(null, 204);
    }

    public function updateStatus(UpdateTicketStatusRequest $request, Ticket $ticket)
    {
        $this->authorizeOwner($request, $ticket);

        $next = TicketStatus::from($request->validated('status'));

        if (! $ticket->canTransitionTo($next)) {
            return response()->json([
                'message' => "Cannot transition from {$ticket->status->value} to {$next->value}.",
            ], 422);
        }

        $ticket->update([
            'status'      => $next,
            'resolved_at' => $next === TicketStatus::RESOLVED ? now() : $ticket->resolved_at,
        ]);

        return new TicketResource($ticket);
    }

    private function authorizeOwner(Request $request, Ticket $ticket): void
    {
        abort_if(
            $ticket->unit->property->owner_id !== $request->user()->id,
            403
        );
    }
}
