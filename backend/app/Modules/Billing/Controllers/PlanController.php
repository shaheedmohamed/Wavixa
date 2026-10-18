<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    /**
     * List active plans for public pricing page.
     */
    public function index()
    {
        try {
            $plans = Cache::remember('plans:public', 600, function () {
                return Plan::visible()
                    ->get()
                    ->map(function (Plan $plan) {
                        return [
                            'id' => $plan->id,
                            'name' => $plan->name,
                            'slug' => $plan->slug,
                            'messages_limit' => $plan->messages_limit,
                            'price' => $plan->price,
                            'currency' => $plan->currency,
                            'interval' => $plan->interval,
                            'is_free' => $plan->is_free,
                            'is_popular' => $plan->is_popular,
                            'description' => $plan->description,
                            'features' => $plan->features ?? [],
                            'formatted_price' => $plan->formatted_price,
                        ];
                    });
            });

            return response()->json([
                'success' => true,
                'data' => $plans,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading plans: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a single plan.
     */
    public function show(string $slug)
    {
        try {
            $plan = Plan::where('slug', $slug)->where('is_active', true)->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'messages_limit' => $plan->messages_limit,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'interval' => $plan->interval,
                    'is_free' => $plan->is_free,
                    'is_popular' => $plan->is_popular,
                    'description' => $plan->description,
                    'features' => $plan->features ?? [],
                    'formatted_price' => $plan->formatted_price,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }
    }

    /**
     * Create a new plan (admin).
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'slug' => 'nullable|string|max:255|unique:plans',
                'messages_limit' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'interval' => 'nullable|string|in:month,year,one_time',
                'is_active' => 'nullable|boolean',
                'is_free' => 'nullable|boolean',
                'is_popular' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
                'description' => 'nullable|string',
                'features' => 'nullable|array',
            ]);

            if (empty($validated['slug'])) {
                $validated['slug'] = Str::slug($validated['name']);
            }

            $validated['currency'] = $validated['currency'] ?? 'USD';
            $validated['interval'] = $validated['interval'] ?? 'month';
            $validated['is_active'] = $validated['is_active'] ?? true;
            $validated['is_free'] = $validated['is_free'] ?? false;
            $validated['is_popular'] = $validated['is_popular'] ?? false;
            $validated['sort_order'] = $validated['sort_order'] ?? 0;

            $plan = Plan::create($validated);

            Cache::forget('plans:public');

            return response()->json([
                'success' => true,
                'message' => 'Plan created successfully.',
                'data' => $plan,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a plan (admin).
     */
    public function update(Request $request, int $id)
    {
        try {
            $plan = Plan::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'slug' => 'sometimes|string|max:255|unique:plans,slug,' . $id,
                'messages_limit' => 'sometimes|integer|min:0',
                'price' => 'sometimes|numeric|min:0',
                'currency' => 'sometimes|string|max:3',
                'interval' => 'sometimes|string|in:month,year,one_time',
                'is_active' => 'sometimes|boolean',
                'is_free' => 'sometimes|boolean',
                'is_popular' => 'sometimes|boolean',
                'sort_order' => 'sometimes|integer',
                'description' => 'nullable|string',
                'features' => 'nullable|array',
            ]);

            $plan->update($validated);

            Cache::forget('plans:public');

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully.',
                'data' => $plan,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating plan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a plan (admin).
     */
    public function destroy(int $id)
    {
        try {
            $plan = Plan::findOrFail($id);
            $plan->delete();

            Cache::forget('plans:public');

            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting plan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
