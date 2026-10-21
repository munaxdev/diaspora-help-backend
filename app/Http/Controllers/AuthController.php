<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
     public function Signup(Request $request): JsonResponse
    {
        Log::info($request->all());    
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'email', 'max:255'],
                'phone' => ['required', 'string', 'max:255'],
                'address' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8', 'confirmed'],
            ]);

            if (User::query()->where('email', $validated['email'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The email address is already registered.',
                    'errors' => [
                        'email' => ['The email address is already registered.'],
                    ],
                ], 422);
            }

            if (User::query()->where('phone_number', $validated['phone'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'The phone number is already registered.',
                    'errors' => [
                        'phone_number' => ['The phone number is already registered.'],
                    ],
                ], 422);
            }

            $user = DB::transaction(function () use ($validated): User {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone_number' => $validated['phone'],
                    'address' => $validated['address'],
                    'password' => $validated['password'],
                ]);

                return $user;
            });

            return response()->json([
                'success' => true,
                'message' => 'Signed up successfully. You can now log in with your credentials.',
            ], 200);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('User registration validation error: '.json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('User registration error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while registering the user.',
            ], 500);
        }
    }

    public function Signin(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string'],
            ]);

            $user = User::query()->where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account was found with this email address.',
                    'errors' => [
                        'email' => ['No account was found with this email address.'],
                    ],
                ], 422);
            }

            if (! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The password is incorrect.',
                    'errors' => [
                        'password' => ['The password is incorrect.'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'user' => $user,
                'authtoken' => $user->createToken('auth-token')->plainTextToken,
            ], 200);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('User login validation error: '.json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('User login error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while logging in.',
            ], 500);
        }
    }

    public function AdminSignin(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => ['required', 'string', 'email', 'max:255'],
                'password' => ['required', 'string'],
            ]);

            $user = User::query()->where('email', $validated['email'])->first();

            if ($user === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account was found with this email address.',
                    'errors' => [
                        'email' => ['No account was found with this email address.'],
                    ],
                ], 422);
            }

            if ($user->user_type === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No admin account was found with this email address. Contact the system administrator.',
                    'errors' => [
                        'email' => ['No admin account was found with this email address. Contact the system administrator.'],
                    ],
                ], 422);
            }

            if (! Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The password is incorrect.',
                    'errors' => [
                        'password' => ['The password is incorrect.'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'user' => $user,
                'authtoken' => $user->createToken('auth-token')->plainTextToken,
            ], 200);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('User login validation error: '.json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('User login error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while logging in.',
            ], 500);
        }
    }

    public function Signout(Request $request)
    {
        try {
            if ($request->user()) {
                // Revoke the current access token
                $request->user()->tokens()->delete();
            }

            return response()->json([
                'success' => true,
                'status' => 'logged out',
                'message' => 'You have been logged out.'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
