<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

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
			->when(request('user_id'),
				fn($builder, $userId) => $builder->whereUserId($userId))
			->when(request('visitor_id'),
				fn(Builder $builder, $visitorId) => $builder
					->whereRelation('reservations','user_id','=',$visitorId))
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

    public function create(Request $request): JsonResource
    {
        if (! auth()->user()->tokenCan('office.create'))
        {
            abort(Response::HTTP_FORBIDDEN);
        }


        $attributes = validator($request->all(),[
            'title' => ['required','string'],
            'description' => ['required','string'],
            'lat' => ['required','numeric'],
            'lng' => ['required','numeric'],
            'address_line1' => ['required','string'],
            'hidden' => ['bool'],
            'price_per_day' => ['required','integer','min:0'],
            'monthly_discount' => ['integer','min:0'],

            'tags' => ['array'],
            'tags.*' => ['integer',Rule::exists('tags','id')]
        ])->validate();

        $attributes['approval_status'] = Office::APPROVAL_PENDING;

        $office = auth()->user()->offices()->create(
            Arr::except($attributes,['tags'])
        );
        $office->tags()->sync($attributes['tags']);

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
