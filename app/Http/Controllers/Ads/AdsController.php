<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdsController extends Controller
{
    public function index()
    {
        $ads = AdsSetting::where('end_date', '>=', now()->toDateString())
            ->where('status', true)
            ->orWhereNull('end_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ads
        ]);
    }

    public function adminIndex(Request $request)
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access. Admins only.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data'    => AdsSetting::all()
        ]);
    }

    
    public function storeOrUpdate(Request $request)
    {
        $validated = $request->validate([
            'id'         => 'nullable|exists:ads_settings,id',
            'ads_title'  => 'required|string|max:255',
            'ads_type'   => 'nullable|string',
            'image'      => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'placement'  => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            'status'    => 'nullable|boolean'
        ]);

        
        $ad = AdsSetting::find($request->id);

        
        if ($request->hasFile('image')) {
            
            if ($ad && $ad->getRawOriginal('image')) {
                Storage::disk('public')->delete($ad->getRawOriginal('image'));
            }

            $path = $request->file('image')->store('ads', 'public');
            $validated['image'] = $path;
        } else {

            unset($validated['image']);
        }

        $result = AdsSetting::updateOrCreate(
            ['id' => $request->id], 
            $validated              
            
        );

        $status = $ad ? 'updated' : 'created';

        return response()->json([
            'success' => true,
            'message' => "Ad successfully {$status}",
            'data' => $result
        ], $ad ? 200 : 201);
    }


    public function destroy($id)
    {
        try {
            $ad = AdsSetting::find($id);

            if (!$ad) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ad not found with the provided ID.'
                ], 404);
            }

            if (!auth()->check() || strcasecmp(auth()->user()->role, 'Admin') !== 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized action. Only admins can delete ads.'
                ], 403);
            }

            $imagePath = $ad->getRawOriginal('image');
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }

            $ad->delete();

            return response()->json([
                'success' => true,
                'message' => 'Ad deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error occurred',
                'error' => $e->getMessage() 
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        $ad = AdsSetting::findOrFail($id);
        $ad->status = !$ad->status;
        $ad->save();

        return response()->json([
            'success' => true,
            'message' => 'Ad status updated successfully',
            'data' => $ad
        ]);
    }
}