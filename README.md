# Travel Memory Backend

Laravel API backend for the Travel Memory university demo project. The API is used by a Kotlin Android app to register users, manage trips, save movements/memories, upload photos, manage drafts, and toggle favorites.

## Features

- Sanctum token authentication.
- Register, login, logout, authenticated user, Google login, and password reset endpoints.
- Trip CRUD with separate normal trip and draft trip routes.
- Movement/memory CRUD inside trips.
- Photo upload and delete for movements.
- Trip draft and movement draft save/publish flows.
- Favorite/unfavorite trips and movements.
- Profile update with avatar upload.
- Demo seed data for presentation.

## Tech Stack

- PHP 8.4
- Laravel 12
- Laravel Sanctum
- MySQL 8
- Docker Compose

## Local Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

The Android emulator should call:

```text
http://10.0.2.2:8000/api/v1
```

Browser or Postman on the same machine can call:

```text
http://localhost:8000/api/v1
```

## Environment

Important `.env` values for local demo:

```env
APP_NAME="Travel Memory"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://10.0.2.2:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel_with_you
DB_USERNAME=root
DB_PASSWORD=

FILESYSTEM_DISK=public
GOOGLE_CLIENT_ID=
```

Use the Docker database values when running through Docker Compose:

```env
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=travel_with_you
DB_USERNAME=root
DB_PASSWORD=rootpass
```

## Database and Seed Data

Run migrations and seed demo data:

```bash
php artisan migrate --seed
```

Reset everything for a clean presentation:

```bash
php artisan migrate:fresh --seed
php artisan storage:link
```

Demo account:

```text
Email: demo@example.com
Password: password123
```

The seeder creates multiple sample trips, movements, and favorite records. Photo records use sample public storage paths for demo data; normal uploaded photos are stored in `storage/app/public/memory_photos`.

## Storage and Photo Uploads

Create the public storage link before testing image uploads:

```bash
php artisan storage:link
```

Uploaded movement photos are returned as URLs like:

```text
http://10.0.2.2:8000/storage/memory_photos/example.jpg
```

If Android cannot load images, check:

- `APP_URL` is set to `http://10.0.2.2:8000` for emulator testing.
- `public/storage` exists and points to `storage/app/public`.
- The Laravel server was started with `--host=0.0.0.0 --port=8000`.

## Docker Setup

Start the backend, MySQL, and phpMyAdmin:

```bash
docker compose up --build
```

The app container runs:

- `php artisan key:generate --force`
- `php artisan migrate --force`
- `php artisan db:seed --force`
- `php artisan storage:link --force`
- `php artisan serve --host=0.0.0.0 --port=8000`

Docker URLs:

- API: `http://localhost:8000/api/v1`
- Android emulator API: `http://10.0.2.2:8000/api/v1`
- phpMyAdmin: `http://localhost:8081`
- MySQL from host: `127.0.0.1:3307`

If `docker compose up` cannot connect to `dockerDesktopLinuxEngine`, start Docker Desktop and make sure the Linux engine is running, then retry the command.

Backup non-Docker commands:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

## API Response Format

Successful endpoints return:

```json
{
  "success": true,
  "message": "Action completed successfully",
  "data": {}
}
```

Error endpoints return:

```json
{
  "success": false,
  "message": "Error message here"
}
```

Validation errors also include an `errors` object so Android can show field-specific messages.

## Main API Routes

Auth:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`
- `GET /api/v1/auth/me`
- `POST /api/v1/auth/logout`
- `POST /api/v1/auth/google`

Profile and account:

- `GET /api/v1/profile`
- `POST|PUT|PATCH /api/v1/profile`
- `DELETE /api/v1/account`
- `GET /api/v1/user/stats`

Trips:

- `GET /api/v1/trips`
- `POST /api/v1/trips`
- `GET /api/v1/trips/{id}`
- `PUT /api/v1/trips/{id}`
- `DELETE /api/v1/trips/{id}`
- `PATCH /api/v1/trips/{id}/favorite`

Trip drafts:

- `GET /api/v1/trips/drafts`
- `POST /api/v1/trips/drafts`
- `PUT /api/v1/trips/drafts/{id}`
- `DELETE /api/v1/trips/drafts/{id}`
- `POST /api/v1/trips/drafts/{id}/publish`

Movements/memories:

- `GET /api/v1/trips/{trip}/movements`
- `POST /api/v1/trips/{trip}/movements`
- `GET /api/v1/movements`
- `POST /api/v1/movements`
- `GET /api/v1/movements/{id}`
- `PUT /api/v1/movements/{id}`
- `DELETE /api/v1/movements/{id}`
- `PATCH /api/v1/movements/{id}/favorite`

Movement drafts:

- `GET /api/v1/drafts`
- `POST /api/v1/drafts`
- `GET /api/v1/drafts/{id}`
- `PUT /api/v1/drafts/{id}`
- `DELETE /api/v1/drafts/{id}`
- `POST /api/v1/drafts/{id}/photos`
- `POST /api/v1/drafts/{id}/publish`

Photos:

- `GET /api/v1/movements/{memory}/photos`
- `POST /api/v1/movements/{memory}/photos`
- `DELETE /api/v1/photos/{photo}`

## Demo Testing Checklist

1. Register a new user.
2. Login and copy the Bearer token.
3. Call `GET /api/v1/auth/me`.
4. Create a trip.
5. List trips and open trip detail.
6. Update the trip.
7. Create a movement inside the trip.
8. Upload a movement photo and open the returned image URL.
9. Delete the uploaded photo.
10. Toggle favorite on the trip and movement.
11. Save a trip draft, publish it, then delete another draft through `/trips/drafts/{id}`.
12. Save a movement draft and publish it.
13. Update profile with text and avatar.
14. Delete a normal trip through `DELETE /api/v1/trips/{id}`.

## Known Demo Limitations

- Seeded sample photo paths are database records for demo content. Uploading real photos through the API creates real files in public storage.
- Draft photo upload is supported for movement drafts, but normal movement photo upload is the recommended presentation flow.
- Google login requires a valid `GOOGLE_CLIENT_ID`; email/password login is the safest demo path.
