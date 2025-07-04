<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Book;

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
