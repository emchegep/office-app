<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Http\Requests\StoreOfficeRequest;
use App\Http\Requests\UpdateOfficeRequest;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApprovalNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
    public function show(Office $office): JsonResource
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


        $attributes = (new OfficeValidator())->validate(
            $office = new Office(),
            request()->all());

        $attributes['approval_status'] = Office::APPROVAL_PENDING;
        $attributes['user_id'] = auth()->id();

        $office = DB::transaction(function () use ($office, $attributes){
             $office->fill(
                Arr::except($attributes,['tags'])
            )->save();

            if (isset($attributes['tags'])){
                $office->tags()->attach($attributes['tags']);
            }

            return $office;
        });

        Notification::send(
            User::firstWhere('name','peter'),
            new OfficePendingApprovalNotification($office));

        return OfficeResource::make($office->load(['images','tags','user']));
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
    public function update(Office $office): JsonResource
    {
        if (! auth()->user()->tokenCan('office.update'))
        {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('update', $office);

        $attributes = (new OfficeValidator())->validate(
            $office, request()->all()
        );

        $office->fill(Arr::except($attributes,['tags']));

        if ($requiresApproval = $office->isDirty(['lat','lng',
            'price_per_day'])){
            $office->fill(['approval_status' => Office::APPROVAL_PENDING]);
        }


        DB::transaction(function () use ($office, $attributes){

            $office->save();

            if (isset($attributes['tags'])){
                $office->tags()->sync($attributes['tags']);
            }

        });

        if ($requiresApproval){
            Notification::send(
                User::firstWhere('name','peter'),
                new OfficePendingApprovalNotification($office)
            );
        }

        return OfficeResource::make($office->load(['images','tags','user']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Office $office)
    {
        if (! auth()->user()->tokenCan('office.delete'))
        {
            abort(Response::HTTP_FORBIDDEN);
        }

        $this->authorize('delete', $office);

        throw_if(
            $office->reservations()->where('status',
                Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office' => 'cannot delete this office!'])
        );

        $office->delete();
    }

}
