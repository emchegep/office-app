<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserReservationController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->tokenCan('reservations.show'),
            Response::HTTP_FORBIDDEN
        );

        // Validation
        Validator(request()->all(),[
            'office_id' => ['integer'],
            'status' => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'from_date' => ['date','required_with:to_date'],
            'to_date' => ['date','required_with:from_date','after:from_date']
        ])->validate();

        $reservations = Reservation::query()
            ->where('user_id', auth()->id())
            ->when(request('office_id'),
                fn($query) => $query->where('office_id',request('office_id'))
            )
            ->when(request('status'),
                fn($query) => $query->where('status',request('status'))
            )
            ->when(request('from_date') && request('to_date'),
               fn($query) => $query->betweenDates(request('from_date'),request('to_date'))
            )
            ->with(['office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    public function create()
    {
        abort_unless(auth()->user()->tokenCan('reservations.make'),
            Response::HTTP_FORBIDDEN
        );

        validator(request()->all(),[
            'office_id' => ['required','integer'],
            'start_date' => ['required','date:Y-m-d','after:'.now()->addDay()->toDateString()],
            'end_date' => ['required','date:Y-m-d','after:start_date'],
        ]);

        try {
            $office = Office::findOrFail(request('office_id'));
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'office_id' => 'Invalid office_id'
            ]);
        }

        throw_if($office->user_id == auth()->id(),
            ValidationException::withMessages([
            'office_id' => 'You cannot make reservation on your own office'
        ]));



        $reservation = Cache::lock('reservations_office'.$office->id,10)->block(3,function () use ($office){
            if ($office->reservations()->activebetween(request('start_date'), request('end_date'))->exists()) {
                throw ValidationException::withMessages([
                    'office_id' => 'You cannot make a reservation during this time'
                ]);
            }

            // add 1 to account for the last day
            $numberOfDays = Carbon::parse(request('end_date'))->endOfDay()
                ->diffInDays(Carbon::parse(request('start_date'))->startOfDay
                ()) + 1;

            throw_if($numberOfDays < 2, ValidationException::withMessages([
                'office_id' => 'You cannot make reservation for only 1 day'
            ]));


            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 and $office->monthly_discount) {
                $price -= ($price * $office->monthly_discount / 100);
            }

            return Reservation::create([
                'user_id' => auth()->id(),
                'office_id' => $office->id,
                'start_date' => request('start_date'),
                'end_date' => request('end_date'),
                'status' => Reservation::STATUS_ACTIVE,
                'price' => $price,
            ]);
        });

        return ReservationResource::make($reservation->load('office'));
    }
}
