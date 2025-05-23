<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    /** @use HasFactory<\Database\Factories\MediaFactory> */
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'name',
        'mime_type',
        'size',
        'file_name',
        'path',
        'url',
        'source',
    ];
}
