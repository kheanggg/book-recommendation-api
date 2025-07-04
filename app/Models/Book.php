<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $primaryKey = 'book_id';
    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = ['title', 'author', 'description', 'book_cover'];

    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'book_genre', 'book_id', 'genre_id');
    }
}
