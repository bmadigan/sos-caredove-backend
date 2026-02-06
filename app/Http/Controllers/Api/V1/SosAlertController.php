<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SosAlertResource;
use App\Models\SosAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SosAlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $alerts = SosAlert::where('team_id', $request->user()->team_id)
            ->with('triggeredBy')
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'data' => SosAlertResource::collection($alerts),
        ]);
    }
}
