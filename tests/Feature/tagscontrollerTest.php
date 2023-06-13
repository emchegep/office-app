<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class tagscontrollerTest extends TestCase
{
    use LazilyRefreshDatabase;
	/**
	 * @test
	 */
	public function itListsTags()
	{
		$response = $this->get('/api/tags');

		$response->assertStatus(200);

		$this->assertNotNull($response->json('data')[0]['id']);
	}
}
