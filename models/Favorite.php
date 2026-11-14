<?php namespace BABA\Favoritebutton\Models;

use Model;

/**
 * A saved favorite. Works for logged-in users and cookie-based guests.
 */
class Favorite extends Model
{
    public $table = 'baba_favorites_items';

    public $fillable = [
        'user_id', 'owner_key', 'item_key', 'target_type', 'target_id',
        'title', 'url', 'excerpt', 'image_url', 'group_id', 'notes',
    ];

    public $casts = [
        'target_id' => 'integer',
        'group_id' => 'integer',
    ];

    public $belongsTo = [
        'group' => [FavoriteGroup::class, 'key' => 'group_id'],
    ];

    public $belongsToMany = [
        'tags' => [
            FavoriteTag::class,
            'table' => 'baba_favorites_favorite_tag',
            'key' => 'favorite_id',
            'otherKey' => 'tag_id',
            'order' => 'name',
        ],
    ];

    public function scopeForOwner($query, string $ownerKey)
    {
        return $query->where('owner_key', $ownerKey);
    }

    public function getTagsLabelAttribute(): string
    {
        return $this->tags->pluck('name')->implode(', ');
    }

    public static function countForTarget(string $targetType, int $targetId): int
    {
        return static::where('target_type', $targetType)->where('target_id', $targetId)->count();
    }
}
