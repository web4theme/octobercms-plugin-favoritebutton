<?php namespace BABA\Favoritebutton\Components;

use BABA\Favoritebutton\Models\Favorite;
use Cms\Classes\ComponentBase;

/**
 * Legacy “who favorited this” component. Works only when user IDs are stored.
 */
class PostUsers extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name' => 'Post Users',
            'description' => 'Shows logged-in users who saved this item.',
        ];
    }

    public function defineProperties()
    {
        return [
            'post_id' => [
                'title' => 'Target ID',
                'type' => 'number',
            ],
            'model_class' => [
                'title' => 'Model class',
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    public function init()
    {
        $this->addCss('/plugins/baba/favoritebutton/assets/css/favoritebutton.css');
    }

    public function onRender()
    {
        $users = [];
        $id = (int) $this->property('post_id');
        $type = (string) $this->property('model_class', '');

        if ($id > 0 && class_exists(\RainLab\User\Models\User::class)) {
            $ids = Favorite::where('target_id', $id)
                ->when($type !== '', function ($query) use ($type) {
                    $query->where('target_type', $type);
                })
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->unique()
                ->values();

            if ($ids->count()) {
                $users = \RainLab\User\Models\User::whereIn('id', $ids)->get();
            }
        }

        $this->page['users'] = $users;
    }
}
