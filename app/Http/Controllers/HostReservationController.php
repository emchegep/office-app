<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class HostReservationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->tokenCan('reservations.show'),
            Response::HTTP_FORBIDDEN
        );

        // Validation
        Validator(request()->all(),[
            'office_id' => ['integer'],
            'user_id' => ['integer'],
            'status' => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'from_date' => ['date','required_with:to_date'],
            'to_date' => ['date','required_with:from_date','after:from_date']
        ])->validate();

        $reservations = Reservation::query()
            ->whereRelation('office','user_id','=', auth()->id())
            ->when(request('office_id'),
                fn($query) => $query->where('office_id',request('office_id'))
            )
            ->when(request('user_id'),
                fn($query) => $query->where('user_id',request('user_id'))
            )
            ->when(request('status'),
                fn($query) => $query->where('status',request('status'))
            )
            ->when(request('from_date') && request('to_date'),
                function ($query) {
                    $query->where(function ($query){
                        return $query->whereBetween('start_date',[request('from_date'),request('to_date')])
                            ->orWhereBetween('end_date', [request('from_date'),request('to_date')]);
                    });
                }
            )
            ->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }
}
