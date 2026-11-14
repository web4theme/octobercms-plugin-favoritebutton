# Favorite Button for October CMS

Save pages and models with **groups, tags, search and private notes**. Works for guests and logged-in users, with no hard dependency on RainLab.Blog or RainLab.User.

## Features

- Save any CMS page, or any model by class + ID.
- Stable `item_key` for several cards on one page.
- Guest favorites via a signed HttpOnly cookie; logged-in favorites via `Auth::getUser()`.
- Groups with names and colours.
- Tags with comma-separated editing and tag filters.
- Notes per saved item.
- Search across titles, notes, excerpts, URLs and tags.
- Filter by group and tag.
- Remove favorites.
- Backend **Favorites** list with searchable titles/notes, owner IDs, groups and timestamps.
- All CSS scoped to `.bf-*` / `.bmf-*`; dark mode and reduced motion supported.
- October CMS 4.4 (primary) and 3.5+; PHP 8.0+; no Composer/JavaScript dependencies.

## Installation

```bash
php artisan plugin:install BABA.Favoritebutton
```

Or copy to `plugins/baba/favoritebutton`, then:

```bash
php artisan october:migrate
php artisan october:util purge manifest
php artisan cache:clear
```

## Components

### Favorite button

```twig
[favoriteButton]
title = "Design systems that survive a rewrite"
url = "#design-systems"
excerpt = "How to keep a component library useful."
item_key = "demo:design-systems"
```

Or for a model:

```twig
[favoriteButton]
model_class = "RainLab\Blog\Models\Post"
model_id = "{{ :id }}"
title = "{{ post.title }}"
url = "{{ post.url }}"
```

### My favorites

```twig
[myFavoritePosts]
itemsPerPage = 100
demoMode = 0
```

Add `{% framework %}`, `{% styles %}` and `{% scripts %}` to the layout. October's AJAX framework powers the search, save, tag and note forms.

## Data model

| Table | Purpose |
|---|---|
| `baba_favorites_items` | One saved item per owner. |
| `baba_favorites_groups` | Per-owner groups. |
| `baba_favorites_tags` | Per-owner tags. |
| `baba_favorites_favorite_tag` | Item ↔ tag pivot. |

`owner_key` is an HMAC of `user:{id}` or `guest:{random token}`. Raw cookies and IPs are not stored.

## Upgrading from 1.x

Run the migrations. Rows in `baba_favorites_favorites` are copied to `baba_favorites_items`; the old table is kept. The component aliases remain `favoriteButton`, `myFavoritePosts` and `postUsers`.

## Live demo

<https://october.web4theme.com/favorites> — backend <https://october.web4theme.com/admin> (`demo` / `demo`). Data resets daily.

## License

MIT.
