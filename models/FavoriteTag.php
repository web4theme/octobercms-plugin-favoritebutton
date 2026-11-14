<?php namespace BABA\Favoritebutton\Models;

use Model;

class FavoriteTag extends Model
{
    public $table = 'baba_favorites_tags';

    public $fillable = ['user_id', 'owner_key', 'name'];

    public function scopeForOwner($query, string $ownerKey)
    {
        return $query->where('owner_key', $ownerKey);
    }
}
