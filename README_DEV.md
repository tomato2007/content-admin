# content-admin (dev)

## Run (Docker dev container)

This repo is intended to be developed and run inside Docker to avoid host PHP/Composer drift.

### 1) Start the dev container

```bash
docker compose up -d
```

### 2) Run commands inside the container

Composer:
```bash
docker compose exec app composer install
```

Artisan:
```bash
docker compose exec app php artisan migrate
```

### 3) Filament admin panel

- URL: `http://localhost/admin`
- Login: `admin@local.test`
- Password: `admin12345`

Change this immediately for any non-local environment.

## Database

The Laravel app connects to the host PostgreSQL service (not to a DB container).
Because `docker-compose.yml` uses `network_mode: host`, `DB_HOST=127.0.0.1` works from inside the container.
