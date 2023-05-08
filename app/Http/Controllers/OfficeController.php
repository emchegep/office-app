<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OfficeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $offices = Office::query()
			->where('approval_status',Office::APPROVAL_APPROVED)
			->where('hidden',false)
			->when(request('host_id'),
				fn($builder, $hostId) => $builder->whereUserId($hostId))
			->when(request('user_id'),
				fn(Builder $builder, $userId) => $builder
					->whereRelation('reservations','user_id','=',$userId))
			->when(request('lat') && request('lng'),
				fn($builder) => $builder->nearestTo(request('lat'), request('lng')),
				fn($builder) => $builder->oldest('id'))
			->with(['images','tags','user'])
			->withCount(['reservations' => fn($builder) => $builder->where('status',Reservation::STATUS_ACTIVE)])
			->paginate(20);

        return OfficeResource::collection($offices);
    }

    /**
     * Display the specified resource.
     */
    public function show(Office $office)
    {
        $office
			->loadCount(
				['reservations' => fn($builder) => $builder->where('status',
					Reservation::STATUS_ACTIVE)])
			->load(['images','tags','user']);


        return OfficeResource::make($office);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Office $office)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateOfficeRequest $request, Office $office)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Office $office)
    {
        //
    }
}
