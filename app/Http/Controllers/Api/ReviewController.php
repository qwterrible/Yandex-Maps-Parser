<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $org = Organization::where('user_id', $request->user()->id)->first();

        if (!$org) {
            return response()->json([
                'data'    => [],
                'meta'    => ['current_page' => 1, 'last_page' => 1, 'per_page' => 50, 'total' => 0],
                'summary' => null,
            ]);
        }

        $perPage = 50;
        $reviews = $org->reviews()->orderByDesc('published_at')->paginate($perPage);

        return response()->json([
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'per_page'     => $reviews->perPage(),
                'total'        => $reviews->total(),
            ],
            'summary' => [
                'name'          => $org->name,
                'rating'        => $org->rating,
                'ratings_count' => $org->ratings_count,
                'reviews_count' => $org->reviews_count,
            ],
        ]);
    }
}