<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncCoOwnersRequest;
use App\Http\Resources\PropertyCoOwnerResource;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use Illuminate\Http\Request;

class PropertyCoOwnerController extends Controller
{
    public function index(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        return PropertyCoOwnerResource::collection($property->coOwners);
    }

    public function store(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'sharePct'   => 'required|numeric|min:0.01|max:100',
            'isPrimary'  => 'boolean',
            'userId'     => 'nullable|uuid|exists:users,id',
        ]);

        $coOwner = $property->coOwners()->create([
            'name'       => $data['name'],
            'share_pct'  => $data['sharePct'],
            'is_primary' => $data['isPrimary'] ?? false,
            'user_id'    => $data['userId'] ?? null,
        ]);

        return (new PropertyCoOwnerResource($coOwner))
            ->response()->setStatusCode(201);
    }

    /** Replace all co-owners in one call — invariants (sum=100, one primary) enforced by SyncCoOwnersRequest. */
    public function sync(SyncCoOwnersRequest $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $property->coOwners()->delete();

        foreach ($request->toRows() as $row) {
            $property->coOwners()->create($row);
        }

        return PropertyCoOwnerResource::collection($property->coOwners()->get());
    }

    public function destroy(Request $request, Property $property, PropertyCoOwner $coOwner)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);
        abort_if($coOwner->is_primary, 422, 'Cannot remove the primary owner. Assign a new primary first.');

        $coOwner->delete();

        return response()->json(null, 204);
    }
}
