# Running on Replit

This project is a Laravel 8 application running on PHP 8.2.

## Development

- The `Start application` workflow serves Laravel on `0.0.0.0:5000`.
- Local development uses SQLite at `database/database.sqlite`.
- PHP dependencies are installed from `composer.lock`.
- Compiled frontend assets are already present in `public/css` and `public/js`.

To apply database schema changes, run:

```sh
php artisan migrate
```

The imported frontend lockfile currently includes a transitive dependency blocked by Replit's package security policy. The checked-in compiled assets are used instead of rebuilding them.