<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Categories;
use App\Models\SubCategories;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_payload_creates_request_and_returns_201(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/submit-request', [
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+254700000000',
            'address' => 'Nairobi, Kenya',
            'subject' => 'Legal assistance',
            'description' => 'I need help reviewing a legal document.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Request submitted successfully.')
            ->assertJsonPath('request.user_id', $user->id)
            ->assertJsonPath('request.phone_number', '+254700000000')
            ->assertJsonPath('request.request_description', 'I need help reviewing a legal document.');

        $this->assertDatabaseHas('requests', [
            'user_id' => $user->id,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone_number' => '+254700000000',
            'address' => 'Nairobi, Kenya',
            'subject' => 'Legal assistance',
            'request_description' => 'I need help reviewing a legal document.',
        ]);
    }

    public function test_nullable_contact_fields_can_be_omitted(): void
    {
        $response = $this->postJson('/api/submit-request', [
            'subject' => 'General assistance',
            'description' => 'I need help with an enquiry.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('requests', [
            'user_id' => null,
            'name' => null,
            'email' => null,
            'phone_number' => null,
            'address' => null,
            'subject' => 'General assistance',
            'request_description' => 'I need help with an enquiry.',
        ]);
    }

    public function test_returns_422_when_subject_and_description_are_missing(): void
    {
        $response = $this->postJson('/api/submit-request');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['subject', 'description']);

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_returns_422_when_user_does_not_exist(): void
    {
        $response = $this->postJson('/api/submit-request', [
            'user_id' => 999999,
            'subject' => 'General assistance',
            'description' => 'I need help with an enquiry.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('user_id');

        $this->assertDatabaseCount('requests', 0);
    }

    public function test_returns_categories_with_their_sub_categories_without_authentication(): void
    {
        $category = Categories::query()->create([
            'name' => 'Legal Assistance',
            'image' => 'legal.png',
        ]);

        $subCategory = SubCategories::query()->create([
            'category_id' => $category->id,
            'name' => 'Immigration',
            'image' => 'immigration.png',
        ]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.id', $category->id)
            ->assertJsonPath('categories.0.image', asset('categoryimages/legal.png'))
            ->assertJsonCount(1, 'categories.0.sub_categories')
            ->assertJsonPath('categories.0.sub_categories.0.id', $subCategory->id)
            ->assertJsonPath(
                'categories.0.sub_categories.0.image',
                asset('subcategoryimages/immigration.png')
            );
    }
}
