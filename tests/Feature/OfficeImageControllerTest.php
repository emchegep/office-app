<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test.
     */
    public function testItUploadsAnImageAndStoresItUnderTheOffice()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);


        $response = $this->post("/api/offices/{$office->id}/images",[
            'image' => UploadedFile::fake()->image('image.jpg')
        ]);

        $response->assertCreated();

        Storage::disk('public')->assertExists($response->json('data.path'));
    }

    /**
     * @test.
     */
    public function testItDeletesAnImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.png'
        ]);
        $image = $office->images()->create([
            'path' => 'image.png'
        ]);


        $this->actingAs($user);

        $response = $this->delete("/api/offices/{$office->id}/images/{$image->id}");


        $response->assertOk();
        $this->assertModelMissing($image);

    }

    /**
     * @test.
     */
    public function testItDoesntDeletesTheOnlyImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $image = $office->images()->create([
            'path' => 'image.png'
        ]);


        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");


        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete the only image']);

    }

    /**
     * @test.
     */
    public function testItDoesntDeletesTheImageThatBelongsToAnotherResource()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();
        $office2 = Office::factory()->for($user)->create();

        $image = $office2->images()->create([
            'path' => 'image.png'
        ]);


        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");


        $response->assertUnprocessable();

        $response->assertJsonValidationErrors(['image' => 'Cannot delete this image']);

    }


    /**
     * @test.
     */
    public function testItDoesntDeletesTheFeaturedImage()
    {

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $office->images()->create([
            'path' => 'image.png'
        ]);

        $image = $office->images()->create([
            'path' => 'image.png'
        ]);

        $office->update(['featured_image_id' => $image->id]);


        $this->actingAs($user);

        $response = $this->deleteJson("/api/offices/{$office->id}/images/{$image->id}");


        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['image' => 'Cannot delete the featured image']);

    }
}
