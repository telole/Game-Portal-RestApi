<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    protected $guarded = [ 'id']; 
    protected $table = 'scores';

    protected $dates = [
        'created_at',
        'updated_at'
    ];

public function game()
{
    return $this->belongsTo(Game::class, 'id');
}

public function user()
{
    return $this->hasOne(User::class, 'username');
}


// public function gameVersion()
// {
//     return $this->belongsTo(GameVersion::class);
// }

// public function game()
// {
//     return $this->belongsTo(Game::class);
// }
}