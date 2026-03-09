<?php

namespace App\Http\Controllers\Supplyer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplyerController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();

            $totalProducts = \App\Models\Product::where('supplier_id', $user->id)->count();
            $activeProducts = \App\Models\Product::where('supplier_id', $user->id)
                                ->where('status', 'published')->count();
            $draftProducts = \App\Models\Product::where('supplier_id', $user->id)
                                ->where('status', 'draft')->count();
            
            $products = \App\Models\Product::where('supplier_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($product) {
                    return [
                        'id'            => $product->id,
                        'product_image' => $product->image ? asset('storage/' . $product->image) : null,
                        'product_name'  => $product->name,
                        'redirect_url'  => $product->redirect_url,
                        'status'        => ucfirst($product->status),
                        'price'         => '$' . number_format($product->price, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'stats' => [
                    'total_products'  => $totalProducts,
                    'active_products' => $activeProducts,
                    'draft_products'  => $draftProducts,
                    'total_orders'    => 342, 
                ],
                'recent_products' => $products
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
