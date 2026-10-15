# Admin-managed package photos

Admins (and event managers) can now change a package's **photo**, **price**,
and **description** from **Admin > Packages**, and guests see the changes
right away on the booking pages.

## What changed

- **New upload endpoint** `api/admin/package-photo.php` — accepts JPG/PNG/WEBP
  up to 5 MB, validates the real MIME type, and stores the file in
  `uploads/packages/`. Returns a site-relative path such as
  `uploads/packages/pkg_ab12cd34.jpg`.
- **Room packages** — the Add/Edit Room Package modal now has a photo picker
  with a live preview and a "Reset to default photo" button. The chosen path is
  saved to `room_packages.image_path` through `api/admin/packages-save.php`.
- **Event packages** — event packages now have their own photo too. New column
  `event_packages.image_path` (see `database/migration_package_photos.sql`),
  returned by the admin and public package APIs.
- **Guest side** — the booking room cards, the event package details card, the
  reservation summary, and the reservation status page all read the photo from
  the database. Prices and descriptions were already served from the database,
  so edits appear on refresh.
- **Path handling** — `cvrResolvePackageImage()` in `js/main.js` resolves both
  the seeded `../images/...` paths and the new `uploads/packages/...` paths
  correctly from any page depth (guest pages and admin pages).

## Run this once

```bash
mysql -u root casa_vergara < database/migration_package_photos.sql
```

Also make sure `uploads/packages/` exists and is writable by PHP:

```bash
mkdir -p uploads/packages && chmod 755 uploads/packages
```

If the packages migration (`database/migration_packages.sql`) has not been run
yet, Admin > Packages still falls back to its old localStorage-only mode and
photo uploads are unavailable — run both migrations for the full feature.
