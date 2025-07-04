<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Genre;

class GenreSeeder extends Seeder
{
    public function run()
    {
        $genres = [
            'Fantasy',
            'Science Fiction',
            'Mystery',
            'Romance',
            'Horror',
            'Historical',
            'Thriller',
            'Biography',
            'Self-help',
            'Poetry'
        ];

        foreach ($genres as $name) {
            Genre::create(['name' => $name]);
        }
    }
}
