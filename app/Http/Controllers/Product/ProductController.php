<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        if ($user->user_type !== 'professional' || $user->profession_type !== 'supplement_supplier') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only supplement suppliers can create products.'
            ], 403);
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'category'     => 'required|in:fitness,nutrition,supplements',
            'price'        => 'required|numeric|min:0',
            'description'  => 'nullable|string',
            'redirect_url' => 'nullable|url',
            'status'       => 'nullable|in:draft,published',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $validated['image'] = $path; 
        }

        $product = Product::create(array_merge($validated, [
            'supplier_id' => $user->id, 
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data'    => $product
        ], 201);
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->user_type === 'professional' && $user->profession_type === 'supplement_supplier') {
            $products = Product::where('supplier_id', $user->id)->get();
        } else {
            $products = Product::where('status', 'published')->get();
        }

        return response()->json([
            'success' => true,
            'data'    => $products
        ]);
    }

    public function supplierProduct()
    {
        $user = auth()->user();

        $products = Product::query()
            ->join('users', 'products.supplier_id', '=', 'users.id')
            ->leftJoin('user_profiles', 'users.id', '=', 'user_profiles.user_id')
            ->select(
                'products.*', 
                'users.name as supplier_name', 
                'users.email as supplier_email',
                'user_profiles.image as supplier_image'
            )
            ->when($user->user_type === 'professional' && $user->profession_type === 'supplement_supplier', function ($q) use ($user) {
                return $q->where('products.supplier_id', $user->id);
            }, function ($q) {
                return $q->where('products.status', 'published');
            })
            ->get();

        $products->transform(function ($product) {
            if ($product->image && !str_starts_with($product->image, 'http')) {
                $product->image = asset('storage/' . $product->image);
            }
            
            if ($product->supplier_image && !str_starts_with($product->supplier_image, 'http')) {
                $product->supplier_image = asset('storage/' . $product->supplier_image);
            }

            return $product;
        });

        return response()->json([
            'success' => true,
            'count'   => $products->count(),
            'data'    => $products
        ]);
    }

    public function updateProductStatus(Request $request, $id)
    {
        try {
            $product = Product::where('supplier_id', auth()->id())
                        ->findOrFail($id);

            $newStatus = strtolower($request->status); 

            if (!in_array($newStatus, ['draft', 'published'])) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Invalid status. Please use "draft" or "published".',
                    'received_value' => $request->status
                ], 400);
            }

            $product->update([
                'status' => $newStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => "Product status successfully updated to " . ucfirst($newStatus),
                'data' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'current_status' => $product->status
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Product not found or you do not have permission to edit this.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = Product::where('supplier_id', auth()->id())
                        ->findOrFail($id);

            $validated = $request->validate([
                'name'         => 'sometimes|required|string|max:255',
                'description'  => 'nullable|string',
                'category'     => 'sometimes|required|in:fitness,nutrition,supplements',
                'price'        => 'sometimes|required|numeric|min:0',
                'redirect_url' => 'nullable|url',
                'status'       => 'nullable|in:draft,published',
                'image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                $validated['image'] = $request->file('image')->store('products', 'public');
            }

            $product->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data'    => $product
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Product not found or you do not have permission to edit this.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::where('supplier_id', auth()->id())
                        ->findOrFail($id);

            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Product not found or you do not have permission to delete this.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'error' => 'Something went wrong: ' . $e->getMessage()
            ], 500);
        }
    }

}
