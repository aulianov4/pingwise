<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TelegramChat extends Model
{
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'title',
        'type',
    ];

    protected $casts = [
        'chat_id' => 'integer',
    ];
}
