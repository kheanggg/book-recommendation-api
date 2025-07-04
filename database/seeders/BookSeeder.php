<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Book;
use App\Models\Genre;
use Faker\Factory as Faker;

class BookSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        $genreIds = Genre::pluck('genre_id')->toArray();

        // Custom arrays to build titles
        $articles = ['A', 'The', 'One', 'Some', 'Any'];
        $adjectives = ['Silent', 'Lonely', 'Dark', 'Bright', 'Ancient', 'Cold', 'Hidden'];
        $nouns = ['River', 'Mountain', 'Forest', 'Dream', 'City', 'Secret', 'Shadow', 'Light'];

        // Exactly 20 fixed authors
        $authors = [
            'Tony Stark', 'Bruce Wayne', 'Peter Parker', 'Clark Kent', 'Diana Prince',
            'Barry Allen', 'Arthur Curry', 'Natasha Romanoff', 'Steve Rogers', 'Bruce Banner',
            'Wanda Maximoff', 'Stephen Strange', 'Scott Lang', 'Carol Danvers', 'T\'Challa',
            'Vision', 'Sam Wilson', 'Bucky Barnes', 'Nick Fury', 'Loki Laufeyson',
        ];

        // Generate all possible unique titles
        $allTitles = [];
        foreach ($articles as $article) {
            foreach ($adjectives as $adj) {
                foreach ($nouns as $noun) {
                    $allTitles[] = "$article $adj $noun";
                }
            }
        }

        // Shuffle titles to randomize
        shuffle($allTitles);

        // Limit to 100 titles (or less if total is smaller)
        $uniqueTitles = array_slice($allTitles, 0, 100);

        $bookCovers = [];
        for ($i = 1; $i <= 10; $i++) {
            $bookCovers[] = "http://localhost:8000/book_covers/book_cover_{$i}.jpg"; // adjust filenames if different
        }

        // Create 100 books
        for ($i = 0; $i < count($uniqueTitles); $i++) {
            $title = $uniqueTitles[$i];
            $author = $authors[$i % count($authors)]; // Cycle through authors

            $cover = $bookCovers[$i % count($bookCovers)];

            $book = Book::create([
                'title' => $title,
                'author' => $author,
                'description' => $faker->paragraph(),
                'book_cover' => $cover,
            ]);

            $randomGenres = $faker->randomElements($genreIds, rand(1, 3));
            $book->genres()->attach($randomGenres);
        }
    }
}
