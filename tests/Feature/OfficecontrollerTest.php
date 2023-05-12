<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Reservation;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OfficecontrollerTest extends TestCase
{
	use RefreshDatabase;

	/**
	 * @test.
	 */
	public function test_it_lists_all_offices_in_paginated_way(): void
	{
		Office::factory(3)->create();

		$response = $this->get('/api/offices');

		$response->assertJsonCount(3,'data');
		$this->assertNotNull($response->json('meta'));
		$this->assertNotNull($response->json('links'));
	}

	/**
	 * @test.
	 */
	public function test_only_lists_offices_that_are_not_hidden_and_approved()
	{
		Office::factory(3)->create();

		Office::factory()->create(['hidden' => true]);
		Office::factory()->create(['approval_status' =>
			Office::APPROVAL_PENDING]);

		$response = $this->get('/api/offices');

		$response->assertOk();
		$response->assertJsonCount(3,'data');
	}

	/**
	 * @test.
	 */
	public function test_it_filters_by_user_id()
	{
		Office::factory(3)->create();

		$user = User::factory()->create();
		$office = Office::factory()->for($user)->create();

		$response = $this->get('/api/offices?user_id='.$user->id);

		$response->assertOk();
		$response->assertJsonCount(1,'data');
		$this->assertEquals($office->id, $response->json('data')[0]['id']);
	}
	/**
	 * @test.
	 */
	public function test_it_filters_by_visitor_id()
	{
		Office::factory(3)->create();

		$visitor = User::factory()->create();
		$office = Office::factory()->create();

		Reservation::factory()->for($office)->for($visitor)->create(0);

		$response = $this->get('/api/offices?visitor_id='.$visitor->id);

		$response->assertOk();
		$response->assertJsonCount(1,'data');
		$this->assertEquals($office->id, $response->json('data')[0]['id']);
	}

	/**
	 * @test.
	 */
	public function test_it_includes_images_tags_and_user()
	{
		$user = User::factory()->create();
		$tag = Tag::factory()->create();
		$office = Office::factory()->for($user)->create();

		$office->tags()->attach($tag);
		$office->images()->create(['path' => 'image.png']);


		$response = $this->get('/api/offices');

		$response->assertOk()->dump();
		$this->assertIsArray($response->json('data')[0]['tags']);
		$this->assertCount(1,$response->json('data')[0]['tags']);
		$this->assertIsArray($response->json('data')[0]['images']);
		$this->assertCount(1,$response->json('data')[0]['images']);
		$this->assertEquals($user->id, $response->json('data')[0]['user']['id']);
	}


	/**
	 * @test.
	 */
	public function test_it_returns_the_number_of_active_reservations()
	{
		$office = Office::factory()->create();

		Reservation::factory()->for($office)->create(['status' =>
			Reservation::STATUS_ACTIVE]);
		Reservation::factory()->for($office)->create(['status' =>
			Reservation::STATUS_CANCELLED]);

		$response = $this->get('/api/offices');

		$response->assertOk();
		$this->assertEquals(1,$response->json('data')[0]['reservations_count']);
	}

	/**
	 * @test.
	 */
	public function test_it_orders_by_distance_when_coordinates_are_provided()
	{
		//38.72147967630618, -9.142149136819379
		$office1 = Office::factory()->create([
			'lat' => '39.773365928330726',
			'lng' => '-8.807653439864218',
			'title' => 'Leiria'
		]);

		$office2 = Office::factory()->create([
			'lat' => '39.096382944913564',
			'lng' => '-9.276756017510726',
			'title' => 'Torres vedras'
		]);

		$response = $this->get('/api/offices?lat=38.72147967630618&lng=-9.142149136819379');

		$response->assertOk();
		$this->assertEquals('Torres vedras',$response->json('data')[0]['title']);
		$this->assertEquals('Leiria',$response->json('data')[1]['title']);
	}

	/**
	 * @test.
	 */
	public function test_it_shows_the_office()
	{
		$user = User::factory()->create();
		$tag = Tag::factory()->create();
		$office = Office::factory()->for($user)->create();

		$office->tags()->attach($tag);
		$office->images()->create(['path' => 'image.png']);

		Reservation::factory()->for($office)->create(['status' =>
			Reservation::STATUS_ACTIVE]);
		Reservation::factory()->for($office)->create(['status' =>
			Reservation::STATUS_CANCELLED]);


		$response = $this->get('/api/offices/'.$office->id);

		$response->assertOk();
		$this->assertIsArray($response->json('data')['tags']);
		$this->assertCount(1,$response->json('data')['tags']);
		$this->assertIsArray($response->json('data')['images']);
		$this->assertCount(1,$response->json('data')['images']);
		$this->assertEquals($user->id, $response->json('data')['user']['id']);
	}

    /**
     * @test.
     */
    public function test_it_create_an_office()
    {
        $user = User::factory()->create();
        $tag1 = Tag::factory()->create();
        $tag2 = Tag::factory()->create();

        $this->actingAs($user);

        $response = $this->postJson('api/offices',[
            'title' => 'Title of the office',
            'description' => 'description of the office',
            'lat' => '39.773365928330726',
            'lng' => '-8.807653439864218',
            'address_line1' => 'address',
            'price_per_day' => 10_000,
            'monthly_discount' => 5,
            'tags' => [
                $tag1->id, $tag2->id
            ]
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title','Title of the office')
            ->assertJsonPath('data.approval_status',Office::APPROVAL_PENDING)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonCount(2,'data.tags');

        $this->assertDatabaseHas('offices',[
            'title' => 'Title of the office'
        ]);
    }

    /**
     * @test.
     */
    public function test_it_doesnt_allow_creating_office_if_scope_not_provided()
    {
        $user = User::factory()->createQuietly();

        $token = $user->createToken('test',[]);


        $response = $this->postJson('api/offices',[],[
            'Authorization' => 'Bearer '.$token->plainTextToken
        ]);

        $response->assertStatus(403);
    }
}

