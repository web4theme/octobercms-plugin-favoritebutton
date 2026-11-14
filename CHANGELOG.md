# Changelog

## 2.0.0 — 2026-11-14

### Added

- Groups with names and colours.
- Tags with editing and filters.
- Search across titles, notes, excerpts, URLs and tags.
- Private notes per favorite.
- Guest favorites via a signed cookie; still supports logged-in users.
- Generic model support (RainLab.Blog is optional).
- Backend Favorites list with searchable titles/notes.
- Live demo page.

### Changed

- New `baba_favorites_items`, groups, tags and pivot tables.
- `favoriteButton`, `myFavoritePosts` and `postUsers` aliases preserved.
- Removed the hard RainLab.User/RainLab.Blog dependency.
- Modern scoped CSS, dark mode and reduced motion.

### Fixed

- 1.x hardcoded `vdomah_blogviews_views` table name is now `baba_blogviews_views`.
- Legacy favorites are copied instead of lost.

## 1.0.5 — 2018-11-20

- Added post users component.
