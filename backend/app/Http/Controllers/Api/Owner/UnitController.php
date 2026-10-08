<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Property;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /** GET /units — every unit across the owner's properties. */
    public function all(Request $request)
    {
        return UnitResource::collection(
            Unit::whereHas('property', fn ($q) =>
                $q->where('owner_id', $request->user()->id)
            )->latest()->get()
        );
    }

    /** GET /properties/{property}/units */
    public function index(Request $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        return UnitResource::collection($property->units);
    }

    /** POST /properties/{property}/units */
    public function store(StoreUnitRequest $request, Property $property)
    {
        abort_if($property->owner_id !== $request->user()->id, 403);

        $unit = $property->units()->create($request->toModelAttributes());

        return (new UnitResource($unit))->response()->setStatusCode(201);
    }

    /** GET /units/{unit} */
    public function show(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);

        return new UnitResource($unit);
    }

    /** PATCH /units/{unit} */
    public function update(UpdateUnitRequest $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);
        $unit->update($request->toModelAttributes());

        return new UnitResource($unit);
    }

    /** DELETE /units/{unit} */
    public function destroy(Request $request, Unit $unit)
    {
        $this->authorizeOwner($request, $unit);
        $unit->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, Unit $unit): void
    {
        abort_if($unit->property->owner_id !== $request->user()->id, 403);
    }
}
