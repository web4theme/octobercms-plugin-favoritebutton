<?php namespace BABA\Favoritebutton;

use Backend;
use BABA\Favoritebutton\Components\FavoriteButton;
use BABA\Favoritebutton\Components\MyFavoritePosts;
use BABA\Favoritebutton\Components\PostUsers;
use Db;
use Schema;
use Session;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = [];

    public function pluginDetails()
    {
        return [
            'name' => 'Favorite Button',
            'description' => 'Save favorites with groups, tags, search and notes.',
            'author' => 'BABA',
            'icon' => 'icon-bookmark',
            'homepage' => 'https://web4theme.com',
        ];
    }

    public function boot()
    {
        // RainLab.User and RainLab.Blog are optional. Never hard-require them.
        if (class_exists(\RainLab\User\Models\User::class)) {
            \RainLab\User\Models\User::extend(function ($model) {
                $model->implement[] = 'BABA.Favoritebutton.Behaviors.Favoriteability';
            });
        }

        if (class_exists(\RainLab\Blog\Models\Post::class)) {
            \RainLab\Blog\Models\Post::extend(function ($model) {
                $model->implement[] = 'BABA.Favoritebutton.Behaviors.Favoriteable';
            });

            $this->registerViewCounter();
        }
    }

    protected function registerViewCounter()
    {
        if (!class_exists(\Rainlab\Blog\Components\Post::class) || !Schema::hasTable('baba_blogviews_views')) {
            return;
        }

        $app = $this->app;
        $plugin = $this;

        \Rainlab\Blog\Components\Post::extend(function ($component) use ($app, $plugin) {
            if ($app->runningInBackend()) {
                return;
            }

            $routerParameters = $component->getController()->getRouter()->getParameters();
            $slugValue = $component->property('slug');
            $slugValueFromUrl = null;

            if (preg_match('/^\{\{([^\}]+)\}\}$/', $slugValue, $matches)) {
                $paramName = trim($matches[1]);
                if (substr($paramName, 0, 1) === ':') {
                    $routeParamName = substr($paramName, 1);
                    $slugValueFromUrl = $routerParameters[$routeParamName] ?? null;
                }
            }

            if (!$slugValueFromUrl) {
                return;
            }

            if (!Session::has('postsviewed')) {
                Session::put('postsviewed', []);
            }

            $post = \RainLab\Blog\Models\Post::where('slug', $slugValueFromUrl)->first();
            if ($post && !in_array($post->getKey(), Session::get('postsviewed'))) {
                $plugin->setViews($post);
                Session::push('postsviewed', $post->getKey());
            }

            return true;
        });

        \RainLab\Blog\Models\Post::extend(function ($model) {
            $model->addDynamicMethod('getViewsAttribute', function () use ($model) {
                $row = Db::table('baba_blogviews_views')->where('post_id', $model->getKey())->first();

                return $row ? (int) $row->views : 0;
            });
        });
    }

    public function setViews($post, $views = null)
    {
        $query = Db::table('baba_blogviews_views')->where('post_id', $post->getKey());

        if ($query->exists()) {
            $row = $query->first();
            $query->update(['views' => $views ?? ((int) $row->views + 1)]);
        } else {
            Db::table('baba_blogviews_views')->insert([
                'post_id' => $post->getKey(),
                'views' => $views ?? 1,
            ]);
        }
    }

    public function registerComponents()
    {
        return [
            FavoriteButton::class => 'favoriteButton',
            MyFavoritePosts::class => 'myFavoritePosts',
            PostUsers::class => 'postUsers',
        ];
    }

    public function registerPermissions()
    {
        return [
            'baba.favoritebutton.manage_favorites' => [
                'tab' => 'Favorites',
                'label' => 'View and manage saved favorites',
            ],
        ];
    }

    public function registerNavigation()
    {
        return [
            'favorites' => [
                'label' => 'Favorites',
                'url' => Backend::url('baba/favoritebutton/favorites'),
                'icon' => 'icon-bookmark',
                'permissions' => ['baba.favoritebutton.*'],
                'order' => 545,
                'sideMenu' => [
                    'favorites' => [
                        'label' => 'Saved favorites',
                        'icon' => 'icon-list',
                        'url' => Backend::url('baba/favoritebutton/favorites'),
                        'permissions' => ['baba.favoritebutton.manage_favorites'],
                    ],
                ],
            ],
        ];
    }
}
