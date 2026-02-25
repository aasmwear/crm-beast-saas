# CRM Beast — Local Runbook

Exact commands for local development.

---

## Start Environment

```bash
./vendor/bin/sail up -d
```

---

## Database

```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
# Or specific seeder:
./vendor/bin/sail artisan db:seed --class=Database\\Seeders\\RolesAndPermissionsSeeder
```

---

## Frontend Build

```bash
./vendor/bin/sail npm run build
# Or if npm is available locally:
npm run build
```

---

## Tests

```bash
./vendor/bin/sail php artisan test
# Or with PHPUnit directly:
./vendor/bin/sail php artisan test -q
```

---

## Cache Clear

```bash
./vendor/bin/sail php artisan optimize:clear
```

---

## Permission Cache Clear (Spatie)

```bash
./vendor/bin/sail php artisan cache:clear
# Or programmatically: app(PermissionRegistrar::class)->forgetCachedPermissions();
```

---

## Full QA (from package.json)

```bash
./vendor/bin/sail artisan optimize:clear
./vendor/bin/sail pint
./vendor/bin/sail php -d xdebug.mode=off vendor/bin/phpstan analyse --memory-limit=1G
vue-tsc --noEmit
vite build
./vendor/bin/sail php artisan test
```

---

## Dev Login (local)

```
GET /dev/login?org=acme
```

Logs in as Super Admin or first user for the given org.
