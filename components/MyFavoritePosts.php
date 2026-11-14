<?php namespace BABA\Favoritebutton\Components;

use BABA\Favoritebutton\Classes\FavoriteOwner;
use BABA\Favoritebutton\Classes\FavoriteService;
use BABA\Favoritebutton\Models\Favorite;
use BABA\Favoritebutton\Models\FavoriteGroup;
use Cms\Classes\ComponentBase;

class MyFavoritePosts extends ComponentBase
{
    public const ASSET_PATH = '/plugins/baba/favoritebutton/assets';

    protected $instance;

    public function componentDetails()
    {
        return [
            'name' => 'My Favorites',
            'description' => 'Saved items with search, groups, tags and notes.',
        ];
    }

    public function defineProperties()
    {
        return [
            'itemsPerPage' => [
                'title' => 'Maximum items',
                'type' => 'string',
                'default' => '100',
            ],
            'demoMode' => [
                'title' => 'Show “Load sample collection”',
                'description' => 'Used by the public demo. Adds sample groups, tags and notes on demand.',
                'type' => 'checkbox',
                'default' => '0',
            ],
            'postPage' => [
                'title' => 'Legacy post page',
                'description' => 'Kept for 1.x template compatibility; not required.',
                'type' => 'string',
                'default' => '',
            ],
        ];
    }

    public function onRun()
    {
        $this->addCss(self::ASSET_PATH . '/css/favoritebutton.css');
    }

    public function onRender()
    {
        $this->prepare();
    }

    public function onSearch()
    {
        return $this->respond($this->filtersFromRequest(), $this->instanceFromRequest());
    }

    public function onSave()
    {
        $favorite = FavoriteService::findById((int) post('favorite_id'));

        if (!$favorite) {
            $this->page['favorite_error'] = 'That saved item no longer exists.';
            return $this->respond(null, $this->instanceFromRequest());
        }

        $groupId = (int) post('group_id');
        if ($groupId > 0) {
            $group = FavoriteGroup::forOwner(FavoriteOwner::key())->where('id', $groupId)->first();
            $favorite->group_id = $group ? $group->id : null;
        } else {
            $favorite->group_id = null;
        }

        $favorite->notes = mb_substr(trim((string) post('notes')), 0, 5000);
        $favorite->save();

        FavoriteService::syncTags($favorite, (string) post('tags'));
        $this->page['favorite_message'] = 'Saved.';

        return $this->respond(null, $this->instanceFromRequest());
    }

    public function onRemove()
    {
        $favorite = FavoriteService::findById((int) post('favorite_id'));

        if ($favorite) {
            $favorite->tags()->detach();
            $favorite->delete();
            $this->page['favorite_message'] = 'Removed from your favorites.';
        }

        return $this->respond(null, $this->instanceFromRequest());
    }

    public function onCreateGroup()
    {
        $name = trim((string) post('group_name'));

        if ($name === '') {
            $this->page['favorite_error'] = 'Give the group a name.';
            return $this->respond(null, $this->instanceFromRequest());
        }

        $exists = FavoriteGroup::forOwner(FavoriteOwner::key())->where('name', $name)->exists();
        if ($exists) {
            $this->page['favorite_error'] = 'That group already exists.';
            return $this->respond(null, $this->instanceFromRequest());
        }

        FavoriteGroup::create([
            'user_id' => FavoriteOwner::userId(),
            'owner_key' => FavoriteOwner::key(),
            'name' => mb_substr($name, 0, 60),
            'color' => $this->cleanColor((string) post('group_color')),
            'sort_order' => 0,
        ]);
        $this->page['favorite_message'] = 'Group created.';

        return $this->respond(null, $this->instanceFromRequest());
    }

    public function onDeleteGroup()
    {
        $group = FavoriteGroup::forOwner(FavoriteOwner::key())->where('id', (int) post('group_id'))->first();

        if ($group) {
            Favorite::forOwner(FavoriteOwner::key())->where('group_id', $group->id)->update(['group_id' => null]);
            $group->delete();
            $this->page['favorite_message'] = 'Group deleted.';
        }

        return $this->respond(null, $this->instanceFromRequest());
    }

    public function onLoadDemo()
    {
        if (!$this->booleanProperty('demoMode', false)) {
            return $this->respond(null, $this->instanceFromRequest());
        }

        $owner = FavoriteOwner::key();
        $userId = FavoriteOwner::userId();

        $design = FavoriteGroup::firstOrCreate(
            ['owner_key' => $owner, 'name' => 'Design'],
            ['user_id' => $userId, 'color' => '#0f766e', 'sort_order' => 1]
        );
        $reading = FavoriteGroup::firstOrCreate(
            ['owner_key' => $owner, 'name' => 'Reading'],
            ['user_id' => $userId, 'color' => '#7c3aed', 'sort_order' => 2]
        );

        $samples = [
            ['Design systems that survive a rewrite', '#design-systems', 'How to keep a component library useful after the third redesign.', $design, 'ui, design systems, components', 'Re-read the section on token layers before the next sprint.'],
            ['The quiet cost of a bad list screen', '#list-screens', 'Filters, empty states and the details that decide whether a backend feels fast.', $reading, 'october, backend, ux', 'Compare with the Bookings list screen.'],
            ['Notes on accessible motion', '#accessible-motion', 'When animation helps, when it distracts, and how to respect reduced motion.', $design, 'accessibility, motion', 'Use the reduced-motion test in the poll demo.'],
            ['Shipping a demo that resets daily', '#daily-reset', 'A small checklist for public demos that never keep visitor data.', $reading, 'demo, operations', 'The reset script runs at 04:00 Asia/Shanghai.'],
        ];

        foreach ($samples as [$title, $url, $excerpt, $group, $tags, $note]) {
            $favorite = FavoriteService::add('page', 0, [
                'item_key' => sha1('demo:' . $url),
                'title' => $title,
                'url' => $url,
                'excerpt' => $excerpt,
            ]);
            $favorite->group_id = $group->id;
            $favorite->notes = $note;
            $favorite->save();
            FavoriteService::syncTags($favorite, $tags);
        }

        $this->page['favorite_message'] = 'Sample collection loaded. Try search, groups and notes.';

        return $this->respond(null, $this->instanceFromRequest());
    }

    protected function prepare(?string $instance = null, ?array $filters = null): void
    {
        $filters = $filters ?: $this->filtersFromRequest();
        $this->instance = $instance ?: 'bmf-' . substr(sha1($this->alias . '|' . (string) $this->id), 0, 12);

        $owner = FavoriteOwner::key();
        $query = Favorite::forOwner($owner)->with(['group', 'tags']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($inner) use ($q) {
                $inner->where('title', 'like', '%' . $q . '%')
                    ->orWhere('notes', 'like', '%' . $q . '%')
                    ->orWhere('excerpt', 'like', '%' . $q . '%')
                    ->orWhere('url', 'like', '%' . $q . '%')
                    ->orWhereHas('tags', function ($tag) use ($q) {
                        $tag->where('name', 'like', '%' . $q . '%');
                    });
            });
        }

        if ($filters['group'] > 0) {
            $query->where('group_id', $filters['group']);
        }

        if ($filters['tag'] > 0) {
            $query->whereHas('tags', function ($tag) use ($filters) {
                $tag->where('id', $filters['tag']);
            });
        }

        $this->page['favorite_instance'] = $this->instance;
        $this->page['favorite_query'] = $filters['q'];
        $this->page['favorite_group'] = $filters['group'];
        $this->page['favorite_tag'] = $filters['tag'];
        $this->page['favorites'] = $query->orderByDesc('updated_at')->take(max(10, (int) $this->property('itemsPerPage', 100)))->get();
        $this->page['favorite_groups'] = FavoriteService::groups();
        $this->page['favorite_tags'] = FavoriteService::tags();
        $this->page['favorite_demo_mode'] = $this->booleanProperty('demoMode', false);
        $this->page['favorite_total'] = Favorite::forOwner($owner)->count();
        $this->page['favorite_csrf'] = csrf_token();
        $this->page['favorite_message'] = $this->page['favorite_message'] ?? null;
        $this->page['favorite_error'] = $this->page['favorite_error'] ?? null;
        $this->page['favorite_search_handler'] = $this->alias . '::onSearch';
        $this->page['favorite_save_handler'] = $this->alias . '::onSave';
        $this->page['favorite_remove_handler'] = $this->alias . '::onRemove';
        $this->page['favorite_group_handler'] = $this->alias . '::onCreateGroup';
        $this->page['favorite_group_delete_handler'] = $this->alias . '::onDeleteGroup';
        $this->page['favorite_demo_handler'] = $this->alias . '::onLoadDemo';
    }

    protected function respond(?array $filters = null, ?string $instance = null): array
    {
        $this->prepare($instance, $filters);

        return ['#' . $this->instance => $this->renderPartial('@body.htm')];
    }

    protected function instanceFromRequest(): ?string
    {
        $instance = preg_replace('/[^a-zA-Z0-9\-]/', '', (string) post('instance', ''));

        return $instance !== '' && strpos($instance, 'bmf-') === 0 ? $instance : null;
    }

    protected function filtersFromRequest(): array
    {
        return [
            'q' => mb_substr(trim((string) (post('q', get('q')))), 0, 120),
            'group' => max(0, (int) post('group', get('group'))),
            'tag' => max(0, (int) post('tag', get('tag'))),
        ];
    }

    protected function cleanColor(string $color): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#0f766e';
    }

    protected function booleanProperty(string $name, bool $default): bool
    {
        $value = $this->property($name, $default ? '1' : '0');

        return is_bool($value) ? $value : in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
