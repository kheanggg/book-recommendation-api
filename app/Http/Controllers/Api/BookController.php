<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    // Handle Get All Books
    public function index(Request $request)
    {
        $query = Book::query();

        if ($request->has('title')) {
            $query->where('title', 'LIKE', '%' . $request->title . '%');
        }
        if ($request->has('author')) {
            $query->where('author', 'LIKE', '%' . $request->author . '%');
        }
        if ($request->has('genre')) {
            $query->whereHas('genres', function ($q) use ($request) {
                $q->where('name', 'LIKE', '%' . $request->genre . '%');
            });
        }

        $perPage = $request->input('per_page', 10);

        $books = $query->with('genres')->paginate($perPage);

        return response()->json($books);
    }

    // Handle Book Based on User Preferences
    public function booksByTopGenresAndRecentVisits(Request $request)
    {
        $user = $request->user();

        $perPage = $request->input('per_page', 8);

        // Get top 3 genres by score for the user
        $topGenres = DB::table('user_genre_scores')
            ->where('user_id', $user->user_id)
            ->orderByDesc('score')
            ->limit(3)
            ->pluck('genre_id')
            ->toArray();

        // Get last 4 visited books by user
        $recentBooks = DB::table('user_activities')
            ->where('user_id', $user->user_id)
            ->orderByDesc('created_at')
            ->limit(4)
            ->pluck('book_id')
            ->toArray();

        // Get genres from recent books
        $recentGenres = DB::table('book_genre') // assuming pivot table book_genre(book_id, genre_id)
            ->whereIn('book_id', $recentBooks)
            ->pluck('genre_id')
            ->unique()
            ->toArray();

        // Combine genres from top scores and recent visits
        $combinedGenres = array_unique(array_merge($topGenres, $recentGenres));

        if (empty($combinedGenres)) {
            // No genres to filter by, return empty collection or all books
            return response()->json([
                'data' => [],
                'message' => 'No preferences or recent visits found'
            ]);
        }

        // Fetch books that belong to any of these combined genres, Shuffle the results at the application level after fetching
        $books = Book::whereHas('genres', function ($q) use ($combinedGenres) {
            $q->whereIn('genres.genre_id', $combinedGenres); // disambiguate here
        })
        ->with('genres')
        ->get()
        ->shuffle()
        ->take($perPage);


        return response()->json([
            'data' => $books->values(),
        ]);
    }




    // Handle Show Specific Book
    public function show($id)
    {
        $book = Book::with('genres')->find($id);

        if (!$book) {
            return response()->json([
                'message' => 'Book not found.'
            ], 404);
        }

        return response()->json($book);
    }

}
