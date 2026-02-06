<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::where('team_id', $request->user()->team_id)
            ->with('deviceTokens')
            ->get();

        return response()->json([
            'data' => UserResource::collection($users),
        ]);
    }
}
