<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chanels extends Model
{
    use HasFactory;

    protected $fillable = ['name','username','language_code'];
}
