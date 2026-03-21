<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramChat extends Model
{
    protected $fillable = [
        'chat_id',
        'title',
        'type',
    ];

    protected $casts = [
        'chat_id' => 'integer',
    ];

    /**
     * Сайты, привязанные к этому чату
     */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'telegram_chat_id');
    }
}
