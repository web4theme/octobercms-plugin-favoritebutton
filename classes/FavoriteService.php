<?php namespace BABA\Favoritebutton\Classes;

use BABA\Favoritebutton\Models\Favorite;
use BABA\Favoritebutton\Models\FavoriteGroup;
use BABA\Favoritebutton\Models\FavoriteTag;

class FavoriteService
{
    public static function itemKey(string $targetType, $targetId, string $url = ''): string
    {
        if ($targetType !== '' && $targetId) {
            return sha1($targetType . ':' . $targetId);
        }

        return sha1('url:' . $url);
    }

    public static function find(string $itemKey): ?Favorite
    {
        return Favorite::forOwner(FavoriteOwner::key())->where('item_key', $itemKey)->first();
    }

    public static function findById(int $id): ?Favorite
    {
        return Favorite::forOwner(FavoriteOwner::key())->where('id', $id)->first();
    }

    public static function add(string $targetType, $targetId, array $meta = []): Favorite
    {
        $ownerKey = FavoriteOwner::key();
        $url = (string) ($meta['url'] ?? '');
        $itemKey = (string) ($meta['item_key'] ?? self::itemKey($targetType, $targetId, $url));

        $favorite = Favorite::forOwner($ownerKey)->where('item_key', $itemKey)->first();
        if ($favorite) {
            return $favorite;
        }

        return Favorite::create([
            'user_id' => FavoriteOwner::userId(),
            'owner_key' => $ownerKey,
            'item_key' => $itemKey,
            'target_type' => $targetType !== '' ? $targetType : null,
            'target_id' => $targetId ?: null,
            'title' => mb_substr((string) ($meta['title'] ?? 'Saved item'), 0, 255),
            'url' => mb_substr($url, 0, 1024),
            'excerpt' => (string) ($meta['excerpt'] ?? ''),
            'image_url' => mb_substr((string) ($meta['image_url'] ?? ''), 0, 1024),
            'group_id' => null,
            'notes' => null,
        ]);
    }

    public static function remove(string $itemKey): bool
    {
        return (bool) Favorite::forOwner(FavoriteOwner::key())->where('item_key', $itemKey)->delete();
    }

    public static function toggle(string $targetType, $targetId, array $meta = []): array
    {
        $itemKey = (string) ($meta['item_key'] ?? self::itemKey($targetType, $targetId, (string) ($meta['url'] ?? '')));
        $favorite = self::find($itemKey);

        if ($favorite) {
            $favorite->delete();

            return ['saved' => false, 'favorite' => null];
        }

        return ['saved' => true, 'favorite' => self::add($targetType, $targetId, $meta + ['item_key' => $itemKey])];
    }

    public static function count(string $targetType, $targetId): int
    {
        if (!$targetType || !$targetId) {
            return 0;
        }

        return Favorite::countForTarget($targetType, (int) $targetId);
    }

    public static function groups()
    {
        return FavoriteGroup::forOwner(FavoriteOwner::key())->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function tags()
    {
        return FavoriteTag::forOwner(FavoriteOwner::key())->orderBy('name')->get();
    }

    /**
     * Accepts "design, css, reading" and keeps tags per owner.
     */
    public static function syncTags(Favorite $favorite, string $tags): void
    {
        $ownerKey = FavoriteOwner::key();
        $names = array_values(array_unique(array_filter(array_map(function ($tag) {
            return mb_substr(trim(preg_replace('/\s+/', ' ', $tag)), 0, 40);
        }, explode(',', $tags)))));

        $ids = [];
        foreach (array_slice($names, 0, 12) as $name) {
            $tag = FavoriteTag::firstOrCreate(
                ['owner_key' => $ownerKey, 'name' => $name],
                ['user_id' => FavoriteOwner::userId()]
            );
            $ids[] = $tag->id;
        }

        $favorite->tags()->sync($ids);
    }
}
