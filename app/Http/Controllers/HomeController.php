<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\Requests;
use App\Models\SubCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HomeController extends Controller
{
    public function SubmitRequest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
                'name' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
                'subject' => ['required', 'string', 'max:255'],
                'description' => ['required', 'string'],
            ]);

            $submittedRequest = new Requests;
            $submittedRequest->user_id = $validated['user_id'] ?? null;
            $submittedRequest->name = $validated['name'] ?? null;
            $submittedRequest->email = $validated['email'] ?? null;
            $submittedRequest->phone_number = $validated['phone'] ?? null;
            $submittedRequest->address = $validated['address'] ?? null;
            $submittedRequest->subject = $validated['subject'];
            $submittedRequest->request_description = $validated['description'];
            $submittedRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Request submitted successfully.',
            ], 201);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Request submission validation error: '.json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Request submission error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while submitting the request.',
            ], 500);
        }
    }

    public function FetchCategories(): JsonResponse
    {
        try {
            $categories = Categories::all();

            $categories->each(function (Categories $category): void {
                if ($category->image !== null) {
                    $category->image = asset('categoryimages/'.$category->image);
                }
            });

            return response()->json([
                'success' => true,
                'categories' => $categories,
            ]);
        } catch (Throwable $exception) {
            Log::error('Home category retrieval error: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the categories.',
            ], 500);
        }
    }
}
