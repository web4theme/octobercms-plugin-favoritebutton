<?php namespace BABA\Favoritebutton\Behaviors;

use BABA\Favoritebutton\Classes\FavoriteOwner;
use BABA\Favoritebutton\Models\Favorite;
use October\Rain\Extension\ExtensionBase;

/**
 * Optional RainLab.User helper. Kept for backwards compatibility with 1.x.
 */
class Favoriteability extends ExtensionBase
{
    protected $model;

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function favoritesOf($class)
    {
        return Favorite::forOwner(FavoriteOwner::keyForUser((int) $this->model->id))
            ->where('target_type', $class)
            ->with(['group', 'tags'])
            ->get();
    }

    public function addFavorite($object): void
    {
        if (method_exists($object, 'addFavorite')) {
            $object->addFavorite($this->model);
        }
    }

    public function removeFavorite($object): void
    {
        if (method_exists($object, 'removeFavorite')) {
            $object->removeFavorite($this->model);
        }
    }

    public function toggleFavorite($object): void
    {
        if (method_exists($object, 'toggleFavorite')) {
            $object->toggleFavorite($this->model);
        }
    }

    public function isFavorited($object): bool
    {
        return method_exists($object, 'isFavorited') && $object->isFavorited($this->model);
    }

    public function hasFavorited($object): bool
    {
        return $this->isFavorited($object);
    }
}
