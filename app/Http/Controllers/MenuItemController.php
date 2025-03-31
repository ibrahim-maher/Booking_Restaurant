<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\MenuCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MenuItemController extends Controller
{
    /**
     * Display a listing of all menu items
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = MenuItem::query();
            
            // Apply filters if provided
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }
            
            if ($request->has('available')) {
                $query->where('is_available', $request->available == 'true');
            } else {
                // Default to showing only available items for public view
                $query->where('is_available', true);
            }

            // Handle search query
            if ($request->has('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('ingredients', 'like', "%{$searchTerm}%");
                });
            }
            
            // Apply sorting
            $sortBy = $request->sort_by ?? 'category';
            $sortDir = $request->sort_dir ?? 'asc';
            
            if (in_array($sortBy, ['name', 'category', 'price', 'created_at'])) {
                $query->orderBy($sortBy, $sortDir);
            }

            if ($sortBy === 'category') {
                $query->orderBy('name', 'asc'); // Secondary sort by name
            }
            
            // Pagination
            $perPage = $request->per_page ?? 15;
            $menuItems = $query->paginate($perPage);
            
            // Add image URLs
            collect($menuItems->items())->map(function ($item) {
                if ($item->image_path) {
                    $item->image_url = url('storage/' . $item->image_path);
                }
                return $item;
            });

            return response()->json([
                'menu_items' => $menuItems
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching menu items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching menu items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get all menu categories
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories()
    {
        try {
            // Check if we have a separate MenuCategory model
            if (class_exists('\App\Models\MenuCategory')) {
                $categories = MenuCategory::orderBy('display_order')
                    ->orderBy('name')
                    ->get();
            } else {
                // Otherwise get categories from menu items
                $categories = MenuItem::select('category')
                    ->distinct()
                    ->orderBy('category')
                    ->pluck('category')
                    ->map(function($category) {
                        return [
                            'name' => $category,
                            'description' => null,
                            'display_order' => 0
                        ];
                    });
            }

            return response()->json([
                'categories' => $categories
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching menu categories: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching menu categories',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get menu items by category
     *
     * @param string $category
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByCategory($category, Request $request)
    {
        try {
            $query = MenuItem::where('category', $category)
                ->where('is_available', true);

            // Apply sorting
            $sortBy = $request->sort_by ?? 'name';
            $sortDir = $request->sort_dir ?? 'asc';
            
            if (in_array($sortBy, ['name', 'price', 'created_at'])) {
                $query->orderBy($sortBy, $sortDir);
            }
            
            $menuItems = $query->get();
            
            // Add image URLs
            $menuItems->transform(function ($item) {
                if ($item->image_path) {
                    $item->image_url = url('storage/' . $item->image_path);
                }
                return $item;
            });

            if ($menuItems->isEmpty()) {
                return response()->json([
                    'message' => 'No menu items found in this category'
                ], 404);
            }

            return response()->json([
                'category' => $category,
                'menu_items' => $menuItems
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching menu items by category: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching menu items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get featured menu items
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeatured(Request $request)
    {
        try {
            $limit = $request->limit ?? 6;
            
            $featuredItems = MenuItem::where('is_available', true)
                ->where('is_featured', true)
                ->inRandomOrder()
                ->take($limit)
                ->get();
                
            // Add image URLs
            $featuredItems->transform(function ($item) {
                if ($item->image_path) {
                    $item->image_url = url('storage/' . $item->image_path);
                }
                return $item;
            });

            return response()->json([
                'featured_items' => $featuredItems
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching featured menu items: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching featured menu items',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Display the specified menu item
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $menuItem = MenuItem::find($id);

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], 404);
            }
            
            // Add image URL
            if ($menuItem->image_path) {
                $menuItem->image_url = url('storage/' . $menuItem->image_path);
            }
            
            // Get related items (same category)
            $relatedItems = MenuItem::where('category', $menuItem->category)
                ->where('id', '!=', $menuItem->id)
                ->where('is_available', true)
                ->inRandomOrder()
                ->take(4)
                ->get()
                ->transform(function ($item) {
                    if ($item->image_path) {
                        $item->image_url = url('storage/' . $item->image_path);
                    }
                    return $item;
                });

            return response()->json([
                'menu_item' => $menuItem,
                'related_items' => $relatedItems
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching menu item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Store a newly created menu item (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'price' => 'required|numeric|min:0',
                'category' => [
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) {
                        // Check if category exists if we have a MenuCategory model
                        if (class_exists('\App\Models\MenuCategory')) {
                            $exists = MenuCategory::where('name', $value)->exists();
                            if (!$exists) {
                                $fail('The selected category does not exist.');
                            }
                        }
                    },
                ],
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_available' => 'boolean',
                'is_featured' => 'boolean',
                'ingredients' => 'nullable|string|max:500',
                'allergens' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $menuItem = new MenuItem();
            $menuItem->name = $request->name;
            $menuItem->slug = Str::slug($request->name); // Create URL-friendly slug
            $menuItem->description = $request->description;
            $menuItem->price = $request->price;
            $menuItem->category = $request->category;
            $menuItem->is_available = $request->has('is_available') ? $request->is_available : true;
            $menuItem->is_featured = $request->has('is_featured') ? $request->is_featured : false;
            $menuItem->ingredients = $request->ingredients;
            $menuItem->allergens = $request->allergens;

            // Handle image upload
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('menu_items', 'public');
                $menuItem->image_path = $imagePath;
            }

            $menuItem->save();
            
            // Add image URL
            if ($menuItem->image_path) {
                $menuItem->image_url = url('storage/' . $menuItem->image_path);
            }

            return response()->json([
                'message' => 'Menu item created successfully',
                'menu_item' => $menuItem
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating menu item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update the specified menu item (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update($id, Request $request)
    {
        try {
            $menuItem = MenuItem::find($id);

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string|max:1000',
                'price' => 'sometimes|required|numeric|min:0',
                'category' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    function ($attribute, $value, $fail) {
                        // Check if category exists if we have a MenuCategory model
                        if (class_exists('\App\Models\MenuCategory')) {
                            $exists = MenuCategory::where('name', $value)->exists();
                            if (!$exists) {
                                $fail('The selected category does not exist.');
                            }
                        }
                    },
                ],
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'is_available' => 'boolean',
                'is_featured' => 'boolean',
                'ingredients' => 'nullable|string|max:500',
                'allergens' => 'nullable|string|max:255',
                'remove_image' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update fields if they exist in the request
            if ($request->has('name')) {
                $menuItem->name = $request->name;
                $menuItem->slug = Str::slug($request->name);
            }
            if ($request->has('description')) $menuItem->description = $request->description;
            if ($request->has('price')) $menuItem->price = $request->price;
            if ($request->has('category')) $menuItem->category = $request->category;
            if ($request->has('is_available')) $menuItem->is_available = $request->is_available;
            if ($request->has('is_featured')) $menuItem->is_featured = $request->is_featured;
            if ($request->has('ingredients')) $menuItem->ingredients = $request->ingredients;
            if ($request->has('allergens')) $menuItem->allergens = $request->allergens;

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($menuItem->image_path) {
                    Storage::disk('public')->delete($menuItem->image_path);
                }
                
                $imagePath = $request->file('image')->store('menu_items', 'public');
                $menuItem->image_path = $imagePath;
            }
            
            // Handle image removal if requested
            if ($request->has('remove_image') && $request->remove_image && $menuItem->image_path) {
                Storage::disk('public')->delete($menuItem->image_path);
                $menuItem->image_path = null;
            }

            $menuItem->save();
            
            // Add image URL
            if ($menuItem->image_path) {
                $menuItem->image_url = url('storage/' . $menuItem->image_path);
            }

            return response()->json([
                'message' => 'Menu item updated successfully',
                'menu_item' => $menuItem
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating menu item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Toggle menu item availability
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleAvailability($id)
    {
        try {
            $menuItem = MenuItem::find($id);

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], 404);
            }

            $menuItem->is_available = !$menuItem->is_available;
            $menuItem->save();

            return response()->json([
                'message' => 'Menu item availability updated',
                'is_available' => $menuItem->is_available
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error toggling menu item availability: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Toggle menu item featured status
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleFeatured($id)
    {
        try {
            $menuItem = MenuItem::find($id);

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], 404);
            }

            $menuItem->is_featured = !$menuItem->is_featured;
            $menuItem->save();

            return response()->json([
                'message' => 'Menu item featured status updated',
                'is_featured' => $menuItem->is_featured
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error toggling menu item featured status: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Remove the specified menu item (admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $menuItem = MenuItem::find($id);

            if (!$menuItem) {
                return response()->json([
                    'message' => 'Menu item not found'
                ], 404);
            }

            // Delete image if exists
            if ($menuItem->image_path) {
                Storage::disk('public')->delete($menuItem->image_path);
            }

            $menuItem->delete();

            return response()->json([
                'message' => 'Menu item deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting menu item: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting menu item',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Get menu items statistics (admin only)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        try {
            $totalItems = MenuItem::count();
            $activeItems = MenuItem::where('is_available', true)->count();
            $featuredItems = MenuItem::where('is_featured', true)->count();
            
            // Items per category
            $itemsByCategory = MenuItem::select('category', DB::raw('count(*) as total'))
                ->groupBy('category')
                ->orderBy('total', 'desc')
                ->get();
            
            // Recently added items
            $recentItems = MenuItem::orderBy('created_at', 'desc')
                ->take(5)
                ->get();
                
            // Add image URLs to recent items
            $recentItems->transform(function ($item) {
                if ($item->image_path) {
                    $item->image_url = url('storage/' . $item->image_path);
                }
                return $item;
            });

            return response()->json([
                'total_items' => $totalItems,
                'active_items' => $activeItems,
                'featured_items' => $featuredItems,
                'items_by_category' => $itemsByCategory,
                'recent_items' => $recentItems
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching menu stats: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error fetching menu statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Create a new menu category (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:50|unique:menu_categories,name',
                'description' => 'nullable|string|max:255',
                'display_order' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Create new category
            $category = new MenuCategory();
            $category->name = $request->name;
            $category->slug = Str::slug($request->name);
            $category->description = $request->description;
            $category->display_order = $request->display_order ?? 0;
            $category->save();

            return response()->json([
                'message' => 'Menu category created successfully',
                'category' => $category
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating menu category: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error creating menu category',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Update a menu category (admin only)
     *
     * @param int $id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCategory($id, Request $request)
    {
        try {
            $category = MenuCategory::find($id);

            if (!$category) {
                return response()->json([
                    'message' => 'Menu category not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('menu_categories')->ignore($id)
                ],
                'description' => 'nullable|string|max:255',
                'display_order' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $oldName = $category->name;

            if ($request->has('name')) {
                $category->name = $request->name;
                $category->slug = Str::slug($request->name);
            }
            if ($request->has('description')) $category->description = $request->description;
            if ($request->has('display_order')) $category->display_order = $request->display_order;
            
            $category->save();

            // Update category name in all menu items if changed
            if ($request->has('name') && $oldName !== $request->name) {
                MenuItem::where('category', $oldName)->update(['category' => $request->name]);
            }

            return response()->json([
                'message' => 'Menu category updated successfully',
                'category' => $category
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating menu category: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error updating menu category',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Delete a menu category (admin only)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroyCategory($id)
    {
        try {
            $category = MenuCategory::find($id);

            if (!$category) {
                return response()->json([
                    'message' => 'Menu category not found'
                ], 404);
            }

            // Check if category is in use
            $itemsCount = MenuItem::where('category', $category->name)->count();
            if ($itemsCount > 0) {
                return response()->json([
                    'message' => 'Cannot delete category. It is used by ' . $itemsCount . ' menu items.'
                ], 400);
            }

            $category->delete();

            return response()->json([
                'message' => 'Menu category deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting menu category: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error deleting menu category',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Reorder menu categories (admin only)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reorderCategories(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'categories' => 'required|array',
                'categories.*.id' => 'required|integer|exists:menu_categories,id',
                'categories.*.display_order' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();
            
            foreach ($request->categories as $categoryData) {
                MenuCategory::where('id', $categoryData['id'])
                    ->update(['display_order' => $categoryData['display_order']]);
            }
            
            DB::commit();

            return response()->json([
                'message' => 'Categories reordered successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reordering categories: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error reordering categories',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }
}