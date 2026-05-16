# Fix MySQL access denied / reset local database

If you see `SQLSTATE[HY000] [1045] Access denied for user ...`, Docker already created a MySQL data volume using old credentials. MySQL does not update users/passwords when you later edit `.env` or `docker-compose.yml`.

Run this from the project folder:

```bash
docker compose down -v
docker compose up --build
```

This deletes only the local Docker database volume for this project and recreates it with the credentials in `docker-compose.yml`.

Default local services:

- API: http://localhost:8000/api/v1
- phpMyAdmin: http://localhost:8081
- MySQL host from Laravel container: mysql
- MySQL port from Laravel container: 3306
- MySQL port from your computer: 3307
- Database: travel_with_you
- Username: root
- Password: rootpass
