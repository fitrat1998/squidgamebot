<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'link',
        'file_name',
        'file_id',
        'file_size',
        'mime_type',
    ];
}
