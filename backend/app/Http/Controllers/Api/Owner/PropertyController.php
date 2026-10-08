<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePropertyRequest;
use App\Http\Requests\UpdatePropertyRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Property;
use App\Models\PropertyCoOwner;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        return PropertyResource::collection(
            Property::with('coOwners')
                ->where('owner_id', $request->user()->id)
                ->latest()
                ->get()
        );
    }

    public function store(StorePropertyRequest $request)
    {
        $property = Property::create(array_merge($request->toModelAttributes(), [
            'owner_id' => $request->user()->id,
        ]));

        PropertyCoOwner::create([
            'property_id' => $property->id,
            'user_id'     => $request->user()->id,
            'name'        => $request->user()->name,
            'share_pct'   => 100.00,
            'is_primary'  => true,
        ]);

        return (new PropertyResource($property->load('coOwners')))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        return new PropertyResource($property->load('coOwners'));
    }

    public function update(UpdatePropertyRequest $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $property->update($request->toModelAttributes());

        if (($rows = $request->toCoOwnerRows()) !== null) {
            $property->coOwners()->delete();
            foreach ($rows as $row) {
                $property->coOwners()->create($row);
            }
        }

        return new PropertyResource($property->load('coOwners'));
    }

    public function destroy(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);
        $property->delete();

        return response()->json(null, 204);
    }
}
