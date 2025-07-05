<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPreferenceController extends Controller
{
    // Save user genre preferences
    public function store(Request $request)
    {
        $user = $request->user(); // assumes user is authenticated

        $request->validate([
            'genres' => 'required|array|min:1',
            'genres.*' => 'exists:genres,genre_id',
        ]);

        DB::transaction(function () use ($user, $request) {
            // Step 1: Clear old preferences
            DB::table('user_preferences')->where('user_id', $user->user_id)->delete();

            // Step 2: Clear old genre scores
            DB::table('user_genre_scores')->where('user_id', $user->user_id)->delete();

            // ✅ Step 3: Clear recent book visits
            DB::table('user_activities')->where('user_id', $user->user_id)->delete();

            // Step 4: Add new preferences and scores
            $prefs = [];
            $scores = [];

            foreach ($request->genres as $genreId) {
                $prefs[] = [
                    'user_id' => $user->user_id,
                    'genre_id' => $genreId,
                ];

                $scores[] = [
                    'user_id' => $user->user_id,
                    'genre_id' => $genreId,
                    'score' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('user_preferences')->insert($prefs);
            DB::table('user_genre_scores')->insert($scores);
        });

        return response()->json(['message' => 'Preferences, scores, and visit history cleared and saved successfully']);
    }

    public function index(Request $request)
    {
        $user = $request->user(); // via Passport middleware

        $genres = DB::table('genres')
            ->leftJoin('user_genre_scores', function ($join) use ($user) {
                $join->on('genres.genre_id', '=', 'user_genre_scores.genre_id')
                    ->where('user_genre_scores.user_id', '=', $user->user_id);
            })
            ->select('genres.genre_id', 'genres.name', DB::raw('COALESCE(user_genre_scores.score, 0) as score'))
            ->get();

        return response()->json($genres);
    }

}
