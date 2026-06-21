# Travel Memory Postman Tests

Import both files into Postman:

- `Travel-Memory-API.postman_collection.json`
- `Travel-Memory-Local.postman_environment.json`

Recommended flow:

1. Start Laravel: `php artisan serve`
2. Run migrations and seed data: `php artisan migrate --seed`
3. Select the `Travel Memory Local` environment in Postman.
4. Run `01 Auth/Login Demo User`.
5. Run `03 Trips/List Trips`; this saves `trip_id`.
6. Run `04 Movements/Create Movement`; this saves `movement_id`.
7. Test detail, favorite, photos, and stats requests.

Demo login:

```text
email: demo@travelmemory.app
password: password123
```

For file upload requests, choose local image files in Postman before sending.
