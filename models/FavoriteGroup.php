<?php namespace BABA\Favoritebutton\Models;

use Model;

class FavoriteGroup extends Model
{
    public $table = 'baba_favorites_groups';

    public $fillable = ['user_id', 'owner_key', 'name', 'color', 'sort_order'];

    public $rules = [
        'name' => 'required|max:60',
    ];

    public $hasMany = [
        'favorites' => [Favorite::class, 'key' => 'group_id'],
    ];

    public function scopeForOwner($query, string $ownerKey)
    {
        return $query->where('owner_key', $ownerKey);
    }
}
