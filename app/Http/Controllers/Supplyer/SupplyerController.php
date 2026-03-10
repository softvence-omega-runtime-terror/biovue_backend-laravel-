<?php

namespace App\Http\Controllers\Supplyer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class SupplyerController extends Controller
{
    public function index()
    {
        try {
            $user = auth()->user();

            $totalProducts = Product::where('supplier_id', $user->id)->count();
            $activeProducts = Product::where('supplier_id', $user->id)
                                ->where('status', 'published')->count();
            $draftProducts = Product::where('supplier_id', $user->id)
                                ->where('status', 'draft')->count();
            
            $products = Product::where('supplier_id', $user->id)
                ->whereIn('status', ['published'])
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

    public function userIndex()
    {
        try {
            $users = User::orderBy('created_at', 'desc')
                        ->get()
                        ->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                                'email' => $user->email,
                                'user_type' => ucfirst($user->user_type),
                                'profession_type' => $user->profession_type ? ucfirst(str_replace('_', ' ', $user->profession_type)) : null,
                                'profile_image' => $user->image ? asset('storage/' . $user->image) : null,
                                'joined_at' => $user->created_at->format('Y-m-d'),
                            ];
                        });

            return response()->json([
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }


}
