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
            'description'  => 'nullable|string',
            'category'     => 'required|in:fitness,nutrition,supplements',
            'price'        => 'required|numeric|min:0',
            'redirect_url' => 'nullable|url',
            'status'       => 'nullable|in:draft,published',
            'image'        => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
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
}
