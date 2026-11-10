<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use App\Models\Requests;
use App\Models\SubCategories;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminController extends Controller
{
    public function ViewRequests(): JsonResponse
    {
        try {
            $requests = Requests::query()
                ->with('user')
                ->latest()
                ->get();

            return response()->json([
                'success' => true,
                'requests' => $requests,
            ]);
        } catch (Throwable $exception) {
            Log::error('Request retrieval error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the requests.',
            ], 500);
        }
    }

    public function MarkViewed(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'request_id' => ['required', 'integer', Rule::exists('requests', 'id')],
            ]);

            $submittedRequest = Requests::query()->findOrFail($validated['request_id']);
            $submittedRequest->is_viewed = 1;
            $submittedRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Request marked as viewed successfully.',
                'request' => $submittedRequest,
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Mark request as viewed validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Mark request as viewed error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while marking the request as viewed.',
            ], 500);
        }
    }

    public function UpdateRequest(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'request_id' => ['required', 'integer', Rule::exists('requests', 'id')],
                'status' => ['required', 'string', 'max:100'],
                'comments' => ['nullable', 'string', 'max:255'],
            ]);

            $submittedRequest = Requests::query()->findOrFail($validated['request_id']);
            $submittedRequest->is_completed = $validated['status'];
            $submittedRequest->comments = $validated['comments'] ?? null;
            $submittedRequest->save();

            return response()->json([
                'success' => true,
                'message' => 'Request updated successfully.',
                'request' => $submittedRequest,
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Request update validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            Log::error('Request update error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the request.',
            ], 500);
        }
    }

    public function AddCategory(Request $request): JsonResponse
    {
        $filename = null;

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'services' => ['nullable', 'array'],
                'services.*' => ['required', 'string', 'max:255'],
                'image' => [
                    'nullable',
                    'file',
                    'mimes:jpg,jpeg,png',
                    'extensions:jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = Str::uuid() . '.' . $file->extension();
                File::ensureDirectoryExists(public_path('categoryimages'));
                $file->move(public_path('categoryimages'), $filename);
                $validated['image'] = $filename;
            }

            $category = Categories::query()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Category added successfully.',
                'category' => $category,
            ], 201);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Category validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            if ($filename !== null) {
                File::delete(public_path('categoryimages/' . $filename));
            }

            Log::error('Category creation error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding the category.',
            ], 500);
        }
    }

    public function ViewCategories(): JsonResponse
    {
        try {
            $categories = Categories::all();

            $categories->each(function (Categories $category): void {
                $category->image = asset('categoryimages/' . $category->image);
            });

            return response()->json([
                'success' => true,
                'categories' => $categories,
            ]);
        } catch (Throwable $exception) {
            Log::error('Category retrieval error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the categories.',
            ], 500);
        }
    }

    public function UpdateCategory(Request $request): JsonResponse
    {
        $newFilename = null;

        try {
            $validated = $request->validate([
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'services' => ['nullable', 'array'],
                'services.*' => ['required', 'string', 'max:255'],
                'image' => [
                    'sometimes',
                    'nullable',
                    'file',
                    'mimes:jpg,jpeg,png',
                    'extensions:jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            $category = Categories::query()->findOrFail($validated['category_id']);
            $oldFilename = $category->image;
            unset($validated['category_id']);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $newFilename = Str::uuid() . '.' . $file->extension();
                File::ensureDirectoryExists(public_path('categoryimages'));
                $file->move(public_path('categoryimages'), $newFilename);
                $validated['image'] = $newFilename;
            }

            $category->update($validated);

            if ($newFilename !== null && $oldFilename !== null) {
                File::delete(public_path('categoryimages/' . basename($oldFilename)));
            }

            return response()->json([
                'success' => true,
                'message' => 'Category updated successfully.',
                'category' => $category,
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Category update validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            if ($newFilename !== null) {
                File::delete(public_path('categoryimages/' . $newFilename));
            }

            Log::error('Category update error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the category.',
            ], 500);
        }
    }

    public function DeleteCategory(int $id): JsonResponse
    {
        try {
            $category = Categories::query()->find($id);

            if ($category === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found.',
                ], 404);
            }

            $filename = $category->image;
            $category->delete();

            if ($filename !== null) {
                File::delete(public_path('categoryimages/' . basename($filename)));
            }

            return response()->json([
                'success' => true,
                'message' => 'Category deleted successfully.',
            ]);
        } catch (Throwable $exception) {
            Log::error('Category deletion error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the category.',
            ], 500);
        }
    }

    public function AddSubCategory(Request $request): JsonResponse
    {
        $filename = null;

        try {
            $validated = $request->validate([
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'image' => [
                    'nullable',
                    'file',
                    'mimes:jpg,jpeg,png',
                    'extensions:jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = Str::uuid() . '.' . $file->extension();
                File::ensureDirectoryExists(public_path('subcategoryimages'));
                $file->move(public_path('subcategoryimages'), $filename);
                $validated['image'] = $filename;
            }

            $subCategory = SubCategories::query()->create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Sub-category added successfully.',
                'sub_category' => $subCategory->load('category'),
            ], 201);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Sub-category validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            if ($filename !== null) {
                File::delete(public_path('subcategoryimages/' . $filename));
            }

            Log::error('Sub-category creation error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while adding the sub-category.',
            ], 500);
        }
    }

    public function ViewSubCategories(): JsonResponse
    {
        try {
            $subCategories = SubCategories::query()
                ->with('category')
                ->latest()
                ->get();

            $subCategories->each(function (SubCategories $subCategory): void {
                if ($subCategory->image !== null) {
                    $subCategory->image = asset('subcategoryimages/' . $subCategory->image);
                }
            });

            return response()->json([
                'success' => true,
                'sub_categories' => $subCategories,
            ]);
        } catch (Throwable $exception) {
            Log::error('Sub-category retrieval error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the sub-categories.',
            ], 500);
        }
    }

    public function UpdateSubCategory(Request $request): JsonResponse
    {
        $newFilename = null;

        try {
            $validated = $request->validate([
                'sub_category_id' => ['required', 'integer', Rule::exists('sub_categories', 'id')],
                'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')],
                'name' => ['sometimes', 'required', 'string', 'max:255'],
                'description' => ['sometimes', 'nullable', 'string', 'max:255'],
                'image' => [
                    'sometimes',
                    'nullable',
                    'file',
                    'mimes:jpg,jpeg,png',
                    'extensions:jpg,jpeg,png',
                    'max:5120',
                ],
            ]);

            $subCategory = SubCategories::query()->findOrFail($validated['sub_category_id']);
            $oldFilename = $subCategory->image;
            unset($validated['sub_category_id']);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $newFilename = Str::uuid() . '.' . $file->extension();
                File::ensureDirectoryExists(public_path('subcategoryimages'));
                $file->move(public_path('subcategoryimages'), $newFilename);
                $validated['image'] = $newFilename;
            }

            $subCategory->update($validated);

            if ($newFilename !== null && $oldFilename !== null) {
                File::delete(public_path('subcategoryimages/' . basename($oldFilename)));
            }

            return response()->json([
                'success' => true,
                'message' => 'Sub-category updated successfully.',
                'sub_category' => $subCategory->load('category'),
            ]);
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            Log::error('Sub-category update validation error: ' . json_encode($errors));

            return response()->json([
                'success' => false,
                'message' => collect($errors)->flatten()->first() ?? 'Please check the submitted information.',
                'errors' => $errors,
            ], 422);
        } catch (Throwable $exception) {
            if ($newFilename !== null) {
                File::delete(public_path('subcategoryimages/' . $newFilename));
            }

            Log::error('Sub-category update error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the sub-category.',
            ], 500);
        }
    }

    public function DeleteSubCategory(int $id): JsonResponse
    {
        try {
            $subCategory = SubCategories::query()->find($id);

            if ($subCategory === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sub-category not found.',
                ], 404);
            }

            $filename = $subCategory->image;
            $subCategory->delete();

            if ($filename !== null) {
                File::delete(public_path('subcategoryimages/' . basename($filename)));
            }

            return response()->json([
                'success' => true,
                'message' => 'Sub-category deleted successfully.',
            ]);
        } catch (Throwable $exception) {
            Log::error('Sub-category deletion error: ' . $exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the sub-category.',
            ], 500);
        }
    }
}
