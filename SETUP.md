# Travel With You API - Local Setup

## Requirements
- Docker Desktop
- Git

## First run
```bash
cp .env.example .env
docker compose up --build
```

The API will run at:
- http://localhost:8000

phpMyAdmin will run at:
- http://localhost:8081

Database credentials:
- Host inside Docker: `mysql`
- Host from your computer: `127.0.0.1`
- Port from your computer: `3307`
- Database: `travel_with_you`
- User: `twy_user`
- Password: `twy_pass`
- Root password: `rootpass`

## Useful commands
```bash
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate:fresh
docker compose exec app php artisan test
docker compose exec app php artisan storage:link
```

## Android base URL
Use this for Android Emulator:
```text
http://10.0.2.2:8000/api/v1
```

Use this for browser/Postman on your computer:
```text
http://localhost:8000/api/v1
```
