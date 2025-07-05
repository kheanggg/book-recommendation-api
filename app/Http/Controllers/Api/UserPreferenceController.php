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

            // Step 3: Add new preferences and scores
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

        return response()->json(['message' => 'Preferences and scores saved successfully']);
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

    public function recordGenreInteraction(Request $request)
    {
        if (!$request->user()) {
            return;
        }
        
        $request->validate([
            'genre_id' => 'required|exists:genres,genre_id',
            'book_id' => 'required|exists:books,book_id',  // add book_id since you want to record book visits too
        ]);

        $user = $request->user();
        $userId = $user->user_id;
        $genreId = $request->genre_id;
        $bookId = $request->book_id;

        // Increment genre score or insert new with score = 1
        $existing = DB::table('user_genre_scores')
            ->where('user_id', $userId)
            ->where('genre_id', $genreId)
            ->first();

        if ($existing) {
            DB::table('user_genre_scores')
                ->where('user_id', $userId)
                ->where('genre_id', $genreId)
                ->update([
                    'score' => $existing->score + 1,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('user_genre_scores')->insert([
                'user_id' => $userId,
                'genre_id' => $genreId,
                'score' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Record user activity: store book visit
        // Delete oldest record if more than 5 visits for this user
        $count = DB::table('user_activities')
            ->where('user_id', $userId)
            ->count();

        if ($count >= 5) {
            DB::table('user_activities')
                ->where('user_id', $userId)
                ->orderBy('created_at', 'asc')
                ->limit(1)
                ->delete();
        }

        // Optional: prevent duplicate entries for same book (delete old visit before insert)
        DB::table('user_activities')
            ->where('user_id', $userId)
            ->where('book_id', $bookId)
            ->delete();

        // Insert new visit record
        DB::table('user_activities')->insert([
            'user_id' => $userId,
            'book_id' => $bookId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['message' => 'Genre interaction and book visit recorded']);
    }

}
