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
            ->orWhereNull('end_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ads
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
        $ad = AdsSetting::findOrFail($id);
        
        if ($ad->getRawOriginal('image')) {
            Storage::disk('public')->delete($ad->getRawOriginal('image'));
        }
        
        $ad->delete();

        return response()->json(['message' => 'Ad deleted successfully']);
    }
}