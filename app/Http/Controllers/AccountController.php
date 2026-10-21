<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountController extends Controller
{
    public function Account(): JsonResponse
    {
        try {
            $user = User::query()
                ->with('requests')
                ->findOrFail(Auth::id());

            return response()->json([
                'success' => true,
                'user' => $user,
            ]);
        } catch (Throwable $exception) {
            Log::error('Account retrieval error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the account.',
            ], 500);
        }
    }

    public function UpdateAccount(Request $request): JsonResponse
    {
        try {
            $user = User::query()->findOrFail(Auth::id());

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user),
                ],
                'phone' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('users', 'phone_number')->ignore($user),
                ],
                'address' => ['required', 'string', 'max:255'],
            ]);

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone'],
                'address' => $validated['address'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully.',
                'user' => $user->load('requests'),
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Account update validation error: '.json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Account update error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the account.',
            ], 500);
        }
    }
}
