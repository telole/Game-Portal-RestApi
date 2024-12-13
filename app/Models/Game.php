<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    //
    protected $guarded = ['id'];
    protected $table = 'games';

    public function User() {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function scores() {
        return $this->hasMany(Score::class, 'id');
    }
    public function versions() {
        return $this->hasMany(GameVersion::class, 'game_id', 'id');
    }
}
