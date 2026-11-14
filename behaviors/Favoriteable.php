<?php namespace BABA\Favoritebutton\Behaviors;

use BABA\Favoritebutton\Classes\FavoriteOwner;
use BABA\Favoritebutton\Classes\FavoriteService;
use BABA\Favoritebutton\Models\Favorite;
use October\Rain\Extension\ExtensionBase;

/**
 * Adds favorite helpers to any model without hard-coding RainLab.Blog.
 */
class Favoriteable extends ExtensionBase
{
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    protected function itemKey(): string
    {
        return sha1(get_class($this->model) . ':' . $this->model->getKey());
    }

    protected function meta(): array
    {
        return [
            'title' => (string) ($this->model->title ?? $this->model->name ?? class_basename($this->model)),
            'url' => (string) ($this->model->url ?? ''),
            'excerpt' => (string) ($this->model->excerpt ?? $this->model->summary ?? ''),
            'image_url' => (string) ($this->model->image_url ?? ''),
        ];
    }

    public function addFavorite($user = null): void
    {
        if ($user) {
            $ownerKey = FavoriteOwner::keyForUser((int) $user->id);
            $itemKey = $this->itemKey();
            $favorite = Favorite::where('owner_key', $ownerKey)->where('item_key', $itemKey)->first();

            if (!$favorite) {
                Favorite::create([
                    'user_id' => $user->id,
                    'owner_key' => $ownerKey,
                    'item_key' => $itemKey,
                    'target_type' => get_class($this->model),
                    'target_id' => $this->model->getKey(),
                    'title' => mb_substr($this->meta()['title'], 0, 255),
                    'url' => mb_substr($this->meta()['url'], 0, 1024),
                    'excerpt' => $this->meta()['excerpt'],
                ]);
            }

            return;
        }

        FavoriteService::add(get_class($this->model), $this->model->getKey(), $this->meta());
    }

    public function removeFavorite($user = null): void
    {
        if ($user) {
            Favorite::where('owner_key', FavoriteOwner::keyForUser((int) $user->id))
                ->where('item_key', $this->itemKey())
                ->delete();

            return;
        }

        FavoriteService::remove($this->itemKey());
    }

    public function toggleFavorite($user = null): void
    {
        $this->isFavorited($user) ? $this->removeFavorite($user) : $this->addFavorite($user);
    }

    public function isFavorited($user = null): bool
    {
        $ownerKey = $user ? FavoriteOwner::keyForUser((int) $user->id) : FavoriteOwner::key();

        return Favorite::where('owner_key', $ownerKey)->where('item_key', $this->itemKey())->exists();
    }

    public function favoritedBy()
    {
        return Favorite::where('target_type', get_class($this->model))
            ->where('target_id', $this->model->getKey())
            ->whereNotNull('user_id')
            ->get();
    }

    public function getFavoritesCountAttribute(): int
    {
        return Favorite::countForTarget(get_class($this->model), (int) $this->model->getKey());
    }
}
