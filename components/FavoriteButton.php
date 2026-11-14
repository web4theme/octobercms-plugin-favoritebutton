<?php namespace BABA\Favoritebutton\Components;

use BABA\Favoritebutton\Classes\FavoriteOwner;
use BABA\Favoritebutton\Classes\FavoriteService;
use BABA\Favoritebutton\Models\Favorite;
use Cms\Classes\ComponentBase;

class FavoriteButton extends ComponentBase
{
    public const ASSET_PATH = '/plugins/baba/favoritebutton/assets';

    protected $instance;

    public function componentDetails()
    {
        return [
            'name' => 'Favorite Button',
            'description' => 'Save a page or model to the visitor’s favorites.',
        ];
    }

    public function defineProperties()
    {
        return [
            'model_class' => [
                'title' => 'Model class',
                'description' => 'Optional. Leave empty to favorite the current page.',
                'type' => 'dropdown',
                'default' => '',
                'options' => 'getModelClassOptions',
            ],
            'model_id' => [
                'title' => 'Model ID',
                'description' => 'Optional. The record to favorite.',
                'type' => 'string',
                'default' => '',
            ],
            'title' => [
                'title' => 'Title',
                'description' => 'Optional label stored with the favorite.',
                'type' => 'string',
                'default' => '',
            ],
            'url' => [
                'title' => 'URL',
                'description' => 'Optional link for the saved item.',
                'type' => 'string',
                'default' => '',
            ],
            'excerpt' => [
                'title' => 'Excerpt',
                'type' => 'textarea',
                'default' => '',
            ],
            'image_url' => [
                'title' => 'Image URL',
                'type' => 'string',
                'default' => '',
            ],
            'item_key' => [
                'title' => 'Stable item key',
                'description' => 'Required when several page-level buttons share one URL.',
                'type' => 'string',
                'default' => '',
            ],
            'showCount' => [
                'title' => 'Show total saves',
                'type' => 'checkbox',
                'default' => '1',
            ],
        ];
    }

    public function getModelClassOptions(): array
    {
        $options = ['' => 'Current page'];

        if (class_exists(\RainLab\Blog\Models\Post::class)) {
            $options[\RainLab\Blog\Models\Post::class] = 'RainLab Blog post';
        }

        return $options;
    }

    public function onRun()
    {
        $this->addCss(self::ASSET_PATH . '/css/favoritebutton.css');
    }

    public function onRender()
    {
        $this->prepare();
    }

    public function onToggle()
    {
        $instance = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) post('instance', ''));
        if ($instance === '' || strpos($instance, 'bf-') !== 0) {
            $instance = null;
        }

        $target = $this->targetFromRequest();
        FavoriteService::toggle($target['type'], $target['id'], $target['meta']);
        $this->prepare($instance, $target);

        return ['#' . $this->instance => $this->renderPartial('@button.htm')];
    }

    protected function prepare(?string $instance = null, ?array $target = null): void
    {
        $target = $target ?: $this->resolveTarget();
        $this->instance = $instance ?: $this->makeInstanceId($target['item_key']);

        $favorite = FavoriteService::find($target['item_key']);
        $count = Favorite::where('item_key', $target['item_key'])->count();

        $this->page['bf_instance'] = $this->instance;
        $this->page['bf_item_key'] = $target['item_key'];
        $this->page['bf_target_type'] = $target['type'];
        $this->page['bf_target_id'] = $target['id'];
        $this->page['bf_title'] = $target['meta']['title'];
        $this->page['bf_url'] = $target['meta']['url'];
        $this->page['bf_excerpt'] = $target['meta']['excerpt'];
        $this->page['bf_image_url'] = $target['meta']['image_url'];
        $this->page['bf_saved'] = $favorite !== null;
        $this->page['bf_count'] = $count;
        $this->page['bf_show_count'] = $this->booleanProperty('showCount', true);
        $this->page['bf_handler'] = $this->alias . '::onToggle';
        $this->page['bf_csrf'] = csrf_token();
    }

    protected function resolveTarget(): array
    {
        return $this->makeTarget(
            (string) $this->property('model_class', ''),
            $this->property('model_id', ''),
            (string) $this->property('title', ''),
            (string) $this->property('url', ''),
            (string) $this->property('excerpt', ''),
            (string) $this->property('image_url', ''),
            (string) $this->property('item_key', '')
        );
    }

    protected function targetFromRequest(): array
    {
        return $this->makeTarget(
            (string) post('target_type', ''),
            post('target_id', ''),
            (string) post('title', ''),
            (string) post('url', ''),
            (string) post('excerpt', ''),
            (string) post('image_url', ''),
            (string) post('item_key', '')
        );
    }

    protected function makeTarget(string $type, $id, string $title, string $url, string $excerpt, string $imageUrl, string $itemKey): array
    {
        $type = trim($type);
        $id = (int) $id;

        if ($type !== '' && $id > 0 && class_exists($type)) {
            $model = $type::find($id);
            if ($model) {
                $title = $title !== '' ? $title : (string) ($model->title ?? $model->name ?? class_basename($model));
                $url = $url !== '' ? $url : (string) ($model->url ?? '');
                $excerpt = $excerpt !== '' ? $excerpt : (string) ($model->excerpt ?? $model->summary ?? '');
                $itemKey = $itemKey !== '' ? $itemKey : FavoriteService::itemKey($type, $id, $url);
            }
        }

        if ($type === '' || $id <= 0) {
            $type = 'page';
            $id = 0;
            $url = $url !== '' ? $url : request()->url();
            $title = $title !== '' ? $title : 'Saved page';
            $itemKey = $itemKey !== '' ? $itemKey : FavoriteService::itemKey('', 0, $url);
        }

        return [
            'type' => $type,
            'id' => $id,
            'item_key' => $itemKey,
            'meta' => [
                'item_key' => $itemKey,
                'title' => $title,
                'url' => $url,
                'excerpt' => $excerpt,
                'image_url' => $imageUrl,
            ],
        ];
    }

    protected function makeInstanceId(string $itemKey): string
    {
        return 'bf-' . substr(sha1($this->alias . '|' . $itemKey . '|' . (string) $this->id), 0, 12);
    }

    protected function booleanProperty(string $name, bool $default): bool
    {
        $value = $this->property($name, $default ? '1' : '0');

        return is_bool($value) ? $value : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
