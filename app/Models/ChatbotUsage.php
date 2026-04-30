<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatbotUsage extends Model
{
    protected $table = 'chatbot_usage';

    protected $fillable = [
        'user_id',
        'usage_date',
        'ai_calls_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
