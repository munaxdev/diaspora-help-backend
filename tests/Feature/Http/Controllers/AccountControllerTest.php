<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\Requests;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_authenticated_user_with_their_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedRequest = new Requests;
        $ownedRequest->name = 'Owned request';
        $ownedRequest->email = 'owned@example.com';
        $user->requests()->save($ownedRequest);

        $otherRequest = new Requests;
        $otherRequest->name = 'Other request';
        $otherRequest->email = 'other@example.com';
        $otherUser->requests()->save($otherRequest);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/account');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonCount(1, 'user.requests')
            ->assertJsonPath('user.requests.0.id', $ownedRequest->id);
    }

    public function test_request_returns_its_user(): void
    {
        $user = User::factory()->create();
        $request = new Requests;
        $request->name = 'Account request';
        $request->email = 'account@example.com';
        $user->requests()->save($request);

        $requestUser = $request->user;

        $this->assertTrue($requestUser->is($user));
    }

    public function test_updates_only_the_authenticated_account(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'phone_number' => '+254700000001',
        ]);
        $otherUser = User::factory()->create([
            'name' => 'Other User',
        ]);
        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/update-account', [
            'id' => $otherUser->id,
            'name' => 'Updated User',
            'email' => 'current@example.com',
            'phone' => '+254700000001',
            'address' => 'Nairobi',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.name', 'Updated User');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'address' => 'Nairobi',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $otherUser->id,
            'name' => 'Other User',
        ]);
    }
}
