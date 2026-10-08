<?php

namespace App\Http\Controllers\Api\Owner;

use App\Enums\ReporterRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Resources\TicketCommentResource;
use App\Models\Ticket;

class TicketCommentController extends Controller
{
    public function store(StoreTicketCommentRequest $request, Ticket $ticket)
    {
        abort_if(
            $ticket->unit->property->owner_id !== $request->user()->id,
            403
        );

        $comment = $ticket->comments()->create([
            'author_id'   => $request->user()->id,
            'author_role' => ReporterRole::OWNER,
            'body'        => $request->validated('body'),
        ]);

        return (new TicketCommentResource($comment))->response()->setStatusCode(201);
    }
}
