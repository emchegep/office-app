<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserReservationsControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function testItListReservationsThatBelongsToTheUser()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create();

        $image = $reservation->office->images()->create([
            'path' => 'office_image.jpg'
        ]);

        $reservation->office()->update(['featured_image_id' => $image->id]);

        Reservation::factory()->for($user)->count(2)->create();
        Reservation::factory()->count(3)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations');

        $response
            ->assertJsonStructure(['data','meta','links'])
            ->assertJsonCount(3,'data')
            ->assertJsonStructure(['data' => ['*' => ['id','office']]])
            ->assertJsonPath('data.0.office.featured_image.id',$image->id);

    }

    public function testItListReservationsFilteredByDateRange()
    {
        $user = User::factory()->create();

        $fromDate = '2023-03-03';
        $toDate = '2023-04-04';

        // Within the date range
       Reservation::factory()->for($user)->create([
           'start_date' => '2023-03-01',
           'end_date' => '2023-03-15'
       ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-04-15'
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-03-29'
        ]);

        // Within the range but belongs to a different user
        Reservation::factory()->create([
            'start_date' => '2023-03-25',
            'end_date' => '2023-03-29'
        ]);

        // Outside the date range
        Reservation::factory()->for($user)->create([
            'start_date' => '2023-02-25',
            'end_date' => '2023-03-01'
        ]);

        Reservation::factory()->for($user)->create([
            'start_date' => '2023-05-01',
            'end_date' => '2023-05-02'
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
                'from_date' => $fromDate,
                'to_date' => $toDate
            ]));

        $response->assertJsonCount(3,'data');
    }

    public function testItFiltersReservationsByStatus()
    {
        $user = User::factory()->create();

        $reservation = Reservation::factory()->for($user)->create([
            'status' => Reservation::STATUS_ACTIVE
        ]);

        $reservation2 = Reservation::factory()->for($user)->cancelled()
            ->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
                'status' => Reservation::STATUS_ACTIVE,
            ]));

        $response
            ->assertJsonPath('data.0.id',$reservation->id)
            ->assertJsonCount(1,'data');

    }

    public function testItFiltersReservationsByOffice()
    {
        $user = User::factory()->create();

        $office = Office::factory()->create();

        $reservation = Reservation::factory()->for($office)->for($user)->create();

        $reservation2 = Reservation::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->getJson('/api/reservations?'.http_build_query([
                'office_id' => $office->id,
            ]));

        $response
            ->assertJsonPath('data.0.id',$reservation->id)
            ->assertJsonCount(1,'data');

    }
}
