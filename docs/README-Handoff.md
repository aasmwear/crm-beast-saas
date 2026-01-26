# CRM Beast — Feature Handoff

## New Modules & Endpoints
- Attendance: `/org/{org}/attendance` (+ clock-in/out, CSV export)
- Announcements: `/org/{org}/announcements`
- Project Messages: `/org/{org}/projects/{project}/messages`
- Tasks submission/review: `/org/{org}/tasks/{task}/submit|review`
- Clients Pipeline: `/org/{org}/clients/pipeline`
- Projects Board/Calendar/List
- Notifications Center/Settings
- Admin Settings: `/admin/settings`
- Admin Roles: `/admin/roles`
- Users Import/Export: `/admin/users/{export|import}`

## Migrations
See `database/migrations/2025_10_19_*`. All columns are nullable & additive.

## Commands
- `backup:snapshot` — best-effort pg_dump to `storage/app/backup`.

## After deploy
```
./vendor/bin/sail php artisan migrate
./vendor/bin/sail php artisan db:seed --class=Database\\Seeders\\DemoSeeder
npm run qa
./vendor/bin/sail php artisan test
```
