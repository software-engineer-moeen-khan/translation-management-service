# Translation Management Service

An API-driven service for storing translations in multiple locales, tagging them by
context and exporting them as JSON for frontend applications such as Vue.js.

Built with Laravel 13 and PHP 8.4. No CRUD or translation packages are used; the only
runtime dependency beyond the framework is Laravel Sanctum for API tokens.

- [Quick start with Docker](#quick-start-with-docker)
- [Running without Docker](#running-without-docker)
- [Using the API](#using-the-api)
- [Seeding 100k+ records](#seeding-100k-records)
- [Tests and coverage](#tests-and-coverage)
- [Performance](#performance)
- [Design choices](#design-choices)
- [Security](#security)
- [CDN support](#cdn-support)
- [Configuration](#configuration)
- [Trade-offs and next steps](#trade-offs-and-next-steps)

## Quick start with Docker

Requires Docker with the Compose plugin.

```sh
docker compose up -d --build
```

This starts nginx, PHP-FPM, MySQL 8.4 and Redis. On boot the app container runs the
migrations and seeds the default locales (`en`, `fr`, `es`), tags (`mobile`,
`desktop`, `web`) and a demo user.

| What             | Where                                     |
|------------------|-------------------------------------------|
| API              | http://localhost:8080/api/v1              |
| API docs         | http://localhost:8080/docs                |
| Demo credentials | `admin@example.com` / `password`          |

Load 100k translations for testing:

```sh
docker compose exec app php artisan translations:seed --count=100000
```

Run the test suite with coverage (builds an image with dev dependencies and pcov):

```sh
docker compose run --rm test
```

The published port can be changed with `APP_PORT=9000 docker compose up -d`.

## Running without Docker

Requires PHP 8.3+ with the `pdo_sqlite` extension and Composer.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The API is then available at http://localhost:8000/api/v1 and the docs at
http://localhost:8000/docs. The default `.env` uses SQLite and a file cache so nothing
else has to be installed; set the `DB_*` variables to point at MySQL instead.

## Using the API

The full reference is the OpenAPI document served at `/docs`
([public/docs/openapi.yaml](public/docs/openapi.yaml)).

| Method           | Path                        | Purpose                                         |
|------------------|-----------------------------|-------------------------------------------------|
| `POST`           | `/api/v1/auth/login`        | Exchange credentials for a bearer token         |
| `POST`           | `/api/v1/auth/logout`       | Revoke the current token                        |
| `GET`, `POST`    | `/api/v1/locales`           | List locales, add a new language                |
| `GET`            | `/api/v1/tags`              | List tags                                       |
| `GET`            | `/api/v1/translations`      | List and search by `key`, `content`, `tags`, `locale` |
| `POST`           | `/api/v1/translations`      | Create a translation                            |
| `GET`            | `/api/v1/translations/{id}` | View a translation                              |
| `PATCH`, `PUT`   | `/api/v1/translations/{id}` | Update a translation                            |
| `DELETE`         | `/api/v1/translations/{id}` | Delete a translation                            |
| `GET`            | `/api/v1/export/{locale}`   | JSON export for frontends                       |

A short tour:

```sh
BASE=http://localhost:8080/api/v1

# 1. Get a token
TOKEN=$(curl -s -X POST $BASE/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"password"}' | sed -E 's/.*"token":"([^"]+)".*/\1/')

# 2. Create a translation (unknown tags are created on the fly)
curl -s -X POST $BASE/translations \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"locale":"en","key":"auth.login.title","content":"Sign in","tags":["web","mobile"]}'

# 3. Search
curl -s "$BASE/translations?key=login&tags=web&locale=en" -H "Authorization: Bearer $TOKEN"

# 4. Export for the frontend
curl -s "$BASE/export/en" -H "Authorization: Bearer $TOKEN"
# {"auth.login.title":"Sign in"}

curl -s "$BASE/export/en?format=nested&tags=web" -H "Authorization: Bearer $TOKEN"
# {"auth":{"login":{"title":"Sign in"}}}
```

Notes on behaviour:

- **Search** filters are combined with AND. `tags=web,mobile` matches translations
  carrying *any* of the tags. Results use cursor pagination: follow `links.next`.
- **Updates** change only the fields that are sent. Sending `tags` replaces the tags;
  leaving it out keeps them.
- **Export** accepts `format=flat` (default) or `format=nested`, and an optional `tags`
  filter. The body is the bare key/value document, so it can be passed straight to
  `vue-i18n`'s `setLocaleMessage`.

## Seeding 100k+ records

```sh
php artisan translations:seed                       # 100,000 rows over en, fr, es
php artisan translations:seed --count=500000        # more rows
php artisan translations:seed --locales=en,de,ja    # other locales (created if missing)
```

The command writes chunked multi-row `INSERT`s and tags the rows with set-based
`INSERT ... SELECT` statements, so 100k rows take about 1.5 s on SQLite and about 3.5 s
on MySQL in Docker. It can be run repeatedly. Model factories for every model are
available as well for tests.

To create a real user instead of the demo one:

```sh
php artisan user:create jane@example.com --name=Jane   # prints a generated password
```

## Tests and coverage

```sh
php artisan test                                 # everything, including performance tests
php artisan test --exclude-group=performance     # skip the 100k-record tests
PERFORMANCE_REPORT=1 php artisan test --testsuite=Performance   # print measured timings
vendor/bin/pint --test                           # PSR-12 check
```

There are 123 tests in three suites:

- **Unit** – export formats, the full-text expression builder, export caching.
- **Feature** – every endpoint (happy paths, validation, auth, 404/409), the console
  commands, the seeder, and a check that the OpenAPI document lists exactly the routes
  that exist.
- **Performance** – every endpoint against 100,000 translations, asserting the
  budgets from the brief (200 ms per endpoint, 500 ms for the export).

Line coverage of `app/` is **99.8 %**, measured with pcov by `docker compose run --rm test`
(a coverage driver is needed to measure it locally: `php artisan test --coverage`).
The one line not covered is the MySQL-only full-text branch, because the suite runs
on SQLite; that branch was checked against MySQL by hand (see below).

## Performance

Measured over HTTP against the Docker stack (nginx → PHP-FPM → MySQL 8.4, Redis cache)
on an Apple Silicon laptop, with **200,000 translations** loaded: 100k spread over
`en`/`fr`/`es` and another 100k in a single locale to stress the export. Median of 5 to 7
requests.

| Endpoint                                         | Median  | Budget |
|--------------------------------------------------|--------:|-------:|
| List translations (25 / 100 per page)            | 11 / 16 ms | 200 ms |
| Search by key                                    | 12 ms   | 200 ms |
| Search by key, no match (worst case: full scan)  | 82 ms   | 200 ms |
| Search by content (very common word)             | 92 ms   | 200 ms |
| Search by content, two words                     | 36 ms   | 200 ms |
| Search by tag / by locale                        | 11 ms   | 200 ms |
| Search with all four filters                     | 70 ms   | 200 ms |
| View / create / update                           | 9 / 13 / 13 ms | 200 ms |
| Login                                            | 67 ms   | 200 ms |
| Export, 33k keys, right after a write (uncached) | 62 ms   | 500 ms |
| Export, 100k keys, right after a write (uncached)| 159 ms  | 500 ms |
| Export, 100k keys, nested, uncached              | 197 ms  | 500 ms |
| Export, 100k keys, cached (7.6 MB body)          | 42 ms   | 500 ms |
| Export, unchanged (`304 Not Modified`)           | 8 ms    | 500 ms |

The performance test suite asserts the same budgets in-process on every run.

## Design choices

### Schema

```
locales (id, code UNIQUE, name, export_version)
tags (id, name UNIQUE)
translations (id, locale_id FK, key, content, UNIQUE (locale_id, key), FULLTEXT (content))
tag_translation (translation_id, tag_id, PK (translation_id, tag_id), INDEX (tag_id, translation_id))
```

- **Locales are rows, not columns or an enum**, so adding a language is a `POST /locales`
  with no deploy or migration.
- **One row per (locale, key)**. The unique index enforces integrity and is also the
  index the export scans.
- **Tags are normalised** through a pivot with an index in each direction: "tags of
  this translation" and "translations with this tag" are both index lookups.

### Always-fresh export without recomputing it

The brief asks for two things that pull in opposite directions: the export must always
return the latest data, and it must be fast on large datasets.

Each locale has an `export_version` counter. Every write to a translation or its tags
increments it **in the same database transaction** as the write. The export then:

1. reads the locale row (one indexed lookup);
2. derives an ETag from `(locale, version, format, tags)` and answers `304` straight
   away if the client already has that version, without touching the translations;
3. otherwise returns the payload cached under a key that contains the version, building
   it from the database only on the first request after a change.

Because the version is part of the cache key, a write never has to find and delete
cache entries: readers simply move to a new key and the old one expires. There is no
window in which stale data can be served, and no cache-invalidation code to get wrong.

When the payload does have to be built, rows are fetched as key/value pairs directly by
the database driver (no model or object per row) and encoded once.

### Search and pagination

- **Keyset (cursor) pagination** ordered by primary key. Page 4,000 costs the same as
  page 1 and no `COUNT(*)` over the table is ever needed.
- **Content search** uses the `FULLTEXT` index on MySQL (boolean mode, every word
  required, prefix matching). On other databases it falls back to an escaped `LIKE`.
- **Key search** is a substring match so that `login` finds `auth.login.title`.
- Tag and locale filters are sub-selects on indexed columns; relations are eager
  loaded, so a page is always exactly three queries.

### Code structure

```
Http/Controllers   thin: validate, delegate, shape the response
Http/Requests      validation and input normalisation
Http/Resources     response shape
Services           TranslationService (writes + version bump in one transaction),
                   TranslationExportService (ETag + caching), AuthService
Contracts          TranslationRepository, LocaleRepository
Repositories       Eloquent implementations of the contracts
Enums / DTOs       ExportFormat, TranslationSearchCriteria
Console/Commands   translations:seed, user:create
```

Services depend on repository contracts rather than Eloquent, which keeps persistence
replaceable and lets the export service be unit tested with a fake repository. Export
formats are an enum, so a new format is one new case. Code style is PSR-12, enforced
with Pint (`pint.json`).

## Security

- **Token authentication** with Sanctum personal access tokens. Tokens are stored
  hashed, expire after 24 hours by default and can be revoked with `/auth/logout`.
  Session authentication is disabled: a bearer token is the only credential.
- **Login hardening**: rate limited per email + IP, identical response for unknown
  emails and wrong passwords, and the same amount of hashing work in both cases so
  timing does not reveal whether an account exists.
- **Rate limiting** on every API endpoint, per user.
- **Validation** of every input with length limits and strict patterns for keys, tags
  and locale codes. User input never reaches SQL except through bound parameters;
  `LIKE` wildcards are escaped and full-text operators are stripped.
- **Errors** are always JSON and never include stack traces, SQL or model names.
- **Headers**: `X-Content-Type-Options`, `X-Frame-Options` and `Referrer-Policy` on
  every response.
- **Container**: PHP-FPM runs as an unprivileged user, nginx only executes
  `index.php`, and no secrets are baked into the image.

## CDN support

By default the export requires a token and is sent with `Cache-Control: private, no-cache`.
To serve it from a CDN:

```dotenv
TRANSLATIONS_EXPORT_PUBLIC=true
TRANSLATIONS_EXPORT_CDN_MAX_AGE=0
```

The endpoint then needs no token and responds with
`Cache-Control: public, max-age=0, s-maxage=<n>, must-revalidate` plus an `ETag`. With
`s-maxage=0` the CDN keeps the payload at the edge but revalidates each request with a
conditional GET, which the origin answers with an 8 ms `304`; clients therefore still
always receive the latest translations while the multi-megabyte body is served by the
CDN. Raising the value trades that guarantee for fewer origin requests. The rest of the
API stays token-protected either way.

## Configuration

| Variable                          | Default | Meaning                                              |
|-----------------------------------|---------|------------------------------------------------------|
| `SANCTUM_TOKEN_EXPIRATION`        | `1440`  | Minutes until a token expires                        |
| `API_RATE_LIMIT`                  | `300`   | Requests per minute per user                         |
| `LOGIN_RATE_LIMIT`                | `5`     | Login attempts per minute per email + IP             |
| `TRANSLATIONS_EXPORT_PUBLIC`      | `false` | Serve the export without a token (for CDNs)          |
| `TRANSLATIONS_EXPORT_CDN_MAX_AGE` | `0`     | Seconds a CDN may reuse an export without revalidating |
| `TRANSLATIONS_EXPORT_CACHE_TTL`   | `3600`  | Seconds a built export stays cached (`0` disables)   |
| `BCRYPT_ROUNDS`                   | `10`    | Password hashing cost                                |

## Trade-offs and next steps

- **Password hashing cost is 10, not Laravel's default 12.** Cost 12 takes about 230 ms
  per login on the test machine, which breaks the 200 ms budget; cost 10 takes about
  65 ms and is the minimum OWASP recommends. It is one environment variable to raise.
- **Substring key search scans.** It is comfortably fast at 200k rows (82 ms worst
  case) but grows linearly. Past a few million rows it should move to an n-gram
  full-text index or a search engine.
- **Full-text search is word based on MySQL**, so `come` does not match `welcome` there
  (it does with the `LIKE` fallback), and MySQL's stop words and minimum word length
  apply.
- **Key uniqueness follows the database collation**: case-insensitive on MySQL,
  case-sensitive on SQLite.
- **Nested export** cannot represent a key that is also the prefix of another
  (`menu` and `menu.file`); the deeper key wins. The flat format is lossless.
- **No roles or scopes**: any authenticated user can read and write. Token abilities
  would be the next step for read-only frontend tokens.
- The demo user is seeded only outside production.
