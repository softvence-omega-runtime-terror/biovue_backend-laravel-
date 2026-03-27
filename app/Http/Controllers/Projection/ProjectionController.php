<?php

namespace App\Http\Controllers\Projection;

use App\Http\Controllers\Controller;
use App\Models\ProjectionData;
use App\Models\ProjectionCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectionController extends Controller
{
    public function generateProjection(Request $request)
    {
        $request->validate([
            'image'      => 'required|image|mimes:jpeg,png,jpg,webp',
            'timeframe'  => 'required|string',
            'resolution' => 'required|string',
        ]);

        $user = auth()->user();

        $credits = ProjectionCredit::where('user_id', $user->id)->first();
        if (!$credits || $credits->projection_limit <= 0) {
            return response()->json(['success' => false, 'message' => 'Insufficient credits'], 403);
        }

        try {
            $imagePath = $request->file('image')->store('projections/inputs', 'public');

            $response = Http::attach(
                'image', file_get_contents($request->file('image')), 'input.jpg'
            )->post('https://ai.biovuedigitalwellness.com/api/v1/projection/combined', [
                'user_id'    => $user->id,
                'timeframe'  => $request->timeframe,
                'resolution' => $request->resolution,
            ]);

            if ($response->successful()) {
                $aiData = $response->json();

                DB::beginTransaction();

                $projection = ProjectionData::create([
                    'projection_id'    => $aiData['projection_id'],
                    'user_id'          => $user->id,
                    'input_image'      => $imagePath,
                    'timeframe'        => $aiData['timeframe'],
                    'resolution'       => $aiData['resolution'],
                    'projections_data' => $aiData['projections'],
                    'summary_data'     => $aiData['summary'],
                ]);

                $credits->decrement('projection_limit');
                
                DB::commit();

                return response()->json(['success' => true, 'data' => $projection], 201);
            }

            return response()->json(['success' => false, 'message' => 'AI API Error'], 502);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Projection Error: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        $projection = ProjectionData::where('user_id', auth()->id())->findOrFail($id);
        
        $aiDomain = "https://ai.biovuedigitalwellness.com/api/v1/";
        $pData = $projection->projections_data;

        return response()->json([
            'success' => true,
            'title'   => 'Projection Results',
            'subtitle' => 'Visualizing your trajectory over the next ' . $projection->timeframe,
            'input_image' => asset('storage/' . $projection->input_image), 

            'data' => [
                'current_lifestyle' => [
                    'label'            => "If you continue your current lifestyle without changes for " . $projection->timeframe,
                    'image'            => $aiDomain . ($pData['current_lifestyle']['projection_url'] ?? ''),
                    'timeframe'        => $projection->timeframe,
                    'est_bmi'          => $pData['current_lifestyle']['est_bmi'] ?? 'N/A',
                    'est_weight'       => $pData['current_lifestyle']['est_weight'] ?? 'N/A',
                    'expected_changes' => $pData['current_lifestyle']['expected_changes'] ?? [],
                ],
                'future_goal' => [
                    'label'            => "Achieving your goal in " . $projection->timeframe,
                    'image'            => $aiDomain . ($pData['future_goal']['projection_url'] ?? ''),
                    'timeframe'        => $projection->timeframe,
                    'est_bmi'          => $pData['future_goal']['est_bmi'] ?? 'N/A',
                    'est_weight'       => $pData['future_goal']['est_weight'] ?? 'N/A',
                    'expected_changes' => $pData['future_goal']['expected_changes'] ?? [],
                ]
            ]
        ]);
    }
}