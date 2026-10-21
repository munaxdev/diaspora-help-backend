<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Categories;
use App\Models\Requests;
use App\Models\SubCategories;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_requests_with_and_without_registered_users(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $user = User::factory()->create();

        $registeredRequest = new Requests;
        $registeredRequest->name = 'Registered User';
        $registeredRequest->email = 'registered@example.com';
        $registeredRequest->created_at = now()->subMinute();
        $user->requests()->save($registeredRequest);

        $guestRequest = new Requests;
        $guestRequest->name = 'Guest User';
        $guestRequest->email = 'guest@example.com';
        $guestRequest->created_at = now();
        $guestRequest->save();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/view-requests');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'requests')
            ->assertJsonPath('requests.0.id', $guestRequest->id)
            ->assertJsonPath('requests.0.user_id', null)
            ->assertJsonPath('requests.0.user', null)
            ->assertJsonPath('requests.1.id', $registeredRequest->id)
            ->assertJsonPath('requests.1.user.id', $user->id);
    }

    public function test_valid_category_payload_creates_category_and_returns_201(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/add-category', [
            'name' => 'Legal Assistance',
            'description' => 'Help with legal matters.',
            'image' => UploadedFile::fake()->image('legal-assistance.png'),
        ]);

        $filename = $response->json('category.image');

        try {
            $response
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('category.name', 'Legal Assistance')
                ->assertJsonPath('category.description', 'Help with legal matters.');

            $this->assertIsString($filename);
            $this->assertTrue(File::exists(public_path('categoryimages/'.$filename)));

            $this->assertDatabaseHas('categories', [
                'name' => 'Legal Assistance',
                'description' => 'Help with legal matters.',
                'image' => $filename,
            ]);
        } finally {
            if (is_string($filename)) {
                File::delete(public_path('categoryimages/'.$filename));
            }
        }
    }

    public function test_returns_422_when_category_name_is_missing(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/add-category', [
            'description' => 'Missing category name.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('categories', 0);
    }

    public function test_updates_category_and_replaces_its_image(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $oldFilename = 'old-category-image.png';
        File::ensureDirectoryExists(public_path('categoryimages'));
        File::put(public_path('categoryimages/'.$oldFilename), 'old image');
        $category = Categories::query()->create([
            'name' => 'Old Category',
            'description' => 'Old description.',
            'image' => $oldFilename,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/update-category', [
            'category_id' => $category->id,
            'name' => 'Updated Category',
            'description' => 'Updated description.',
            'image' => UploadedFile::fake()->image('updated-category.png'),
        ]);

        $newFilename = $response->json('category.image');

        try {
            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('category.name', 'Updated Category');

            $this->assertIsString($newFilename);
            $this->assertFalse(File::exists(public_path('categoryimages/'.$oldFilename)));
            $this->assertTrue(File::exists(public_path('categoryimages/'.$newFilename)));
            $this->assertDatabaseHas('categories', [
                'id' => $category->id,
                'name' => 'Updated Category',
                'description' => 'Updated description.',
                'image' => $newFilename,
            ]);
        } finally {
            File::delete(public_path('categoryimages/'.$oldFilename));

            if (is_string($newFilename)) {
                File::delete(public_path('categoryimages/'.$newFilename));
            }
        }
    }

    public function test_deletes_category_and_its_image(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $filename = 'category-to-delete.png';
        File::ensureDirectoryExists(public_path('categoryimages'));
        File::put(public_path('categoryimages/'.$filename), 'category image');
        $category = Categories::query()->create([
            'name' => 'Category to delete',
            'image' => $filename,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->deleteJson('/api/delete-category/'.$category->id);

        try {
            $response
                ->assertOk()
                ->assertJsonPath('success', true);

            $this->assertDatabaseMissing('categories', ['id' => $category->id]);
            $this->assertFalse(File::exists(public_path('categoryimages/'.$filename)));
        } finally {
            File::delete(public_path('categoryimages/'.$filename));
        }
    }

    public function test_valid_payload_creates_sub_category_and_returns_201(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $category = Categories::query()->create(['name' => 'Legal Assistance']);
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/add-sub-category', [
            'category_id' => $category->id,
            'name' => 'Immigration Law',
            'description' => 'Immigration-related assistance.',
            'image' => UploadedFile::fake()->image('immigration-law.png'),
        ]);

        $filename = $response->json('sub_category.image');

        try {
            $response
                ->assertCreated()
                ->assertJsonPath('success', true)
                ->assertJsonPath('sub_category.name', 'Immigration Law')
                ->assertJsonPath('sub_category.category.id', $category->id);

            $this->assertIsString($filename);
            $this->assertTrue(File::exists(public_path('subcategoryimages/'.$filename)));
            $this->assertDatabaseHas('sub_categories', [
                'category_id' => $category->id,
                'name' => 'Immigration Law',
                'description' => 'Immigration-related assistance.',
                'image' => $filename,
            ]);
        } finally {
            if (is_string($filename)) {
                File::delete(public_path('subcategoryimages/'.$filename));
            }
        }
    }

    public function test_returns_sub_categories_with_their_parent_category(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $category = Categories::query()->create(['name' => 'Employment']);
        $subCategory = SubCategories::query()->create([
            'category_id' => $category->id,
            'name' => 'Job Search',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/view-sub-categories');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'sub_categories')
            ->assertJsonPath('sub_categories.0.id', $subCategory->id)
            ->assertJsonPath('sub_categories.0.category.id', $category->id)
            ->assertJsonPath('sub_categories.0.category.name', 'Employment');
    }

    public function test_updates_sub_category_parent_and_replaces_its_image(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $oldCategory = Categories::query()->create(['name' => 'Old Parent']);
        $newCategory = Categories::query()->create(['name' => 'New Parent']);
        $oldFilename = 'old-sub-category-image.png';
        File::ensureDirectoryExists(public_path('subcategoryimages'));
        File::put(public_path('subcategoryimages/'.$oldFilename), 'old image');
        $subCategory = SubCategories::query()->create([
            'category_id' => $oldCategory->id,
            'name' => 'Old Sub-category',
            'image' => $oldFilename,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson('/api/update-sub-category', [
            'sub_category_id' => $subCategory->id,
            'category_id' => $newCategory->id,
            'name' => 'Updated Sub-category',
            'image' => UploadedFile::fake()->image('updated-sub-category.png'),
        ]);

        $newFilename = $response->json('sub_category.image');

        try {
            $response
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('sub_category.name', 'Updated Sub-category')
                ->assertJsonPath('sub_category.category.id', $newCategory->id);

            $this->assertIsString($newFilename);
            $this->assertFalse(File::exists(public_path('subcategoryimages/'.$oldFilename)));
            $this->assertTrue(File::exists(public_path('subcategoryimages/'.$newFilename)));
            $this->assertDatabaseHas('sub_categories', [
                'id' => $subCategory->id,
                'category_id' => $newCategory->id,
                'name' => 'Updated Sub-category',
                'image' => $newFilename,
            ]);
        } finally {
            File::delete(public_path('subcategoryimages/'.$oldFilename));

            if (is_string($newFilename)) {
                File::delete(public_path('subcategoryimages/'.$newFilename));
            }
        }
    }

    public function test_deletes_sub_category_and_its_image(): void
    {
        $admin = User::factory()->create(['user_type' => 1]);
        $category = Categories::query()->create(['name' => 'Parent Category']);
        $filename = 'sub-category-to-delete.png';
        File::ensureDirectoryExists(public_path('subcategoryimages'));
        File::put(public_path('subcategoryimages/'.$filename), 'sub-category image');
        $subCategory = SubCategories::query()->create([
            'category_id' => $category->id,
            'name' => 'Sub-category to delete',
            'image' => $filename,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->deleteJson('/api/delete-sub-category/'.$subCategory->id);

        try {
            $response
                ->assertOk()
                ->assertJsonPath('success', true);

            $this->assertDatabaseMissing('sub_categories', ['id' => $subCategory->id]);
            $this->assertFalse(File::exists(public_path('subcategoryimages/'.$filename)));
        } finally {
            File::delete(public_path('subcategoryimages/'.$filename));
        }
    }
}
