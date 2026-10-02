# No Agenda Website

The source code of [noagendashow.net](https://www.noagendashow.net),
a [Symfony](https://symfony.com/) application.

This application is an online media player and archive specifically made for
The No Agenda Show podcast. The project started in 2016 as a fan project with
the intention to aggregate all resources related to the show into one
easy-to-use interface. It is now the official No Agenda Show's website.

Because the podcast and many producer-made resources are published separately
this application has a built-in crawler to fetch data from different sources.
To control the flow of crawling jobs the application uses a messenger queue
wich requires to be run separately from the main application.

## Local development (PHP 8.5, PostgreSQL, Symfony CLI)

Copy the database connection details for your PostgreSQL instance into `.env.local`:

```dotenv
DATABASE_URL="postgresql://no_agenda:ChangeMe@127.0.0.1:5432/no_agenda?serverVersion=18&charset=utf8"
APP_STORAGE_PATH="%kernel.project_dir%/var/storage"
APP_SECRET="replace-with-a-random-local-secret"
MASTODON_PUBLISH=0
MAILER_DSN=null://null
```

```bash
composer install
bin/console doctrine:database:create --if-not-exists
bin/console doctrine:migrations:migrate -n
bin/console messenger:setup-transports
bin/console crawl feed
symfony server:start -d
```

The PostgreSQL baseline is in `migrations/postgresql/`. The older MySQL migrations
remain in `migrations/` for historical reference and are not executed.

### Assets

AssetMapper serves native CSS and JavaScript ES modules. There is one Stimulus
entrypoint, `assets/stimulus_bootstrap.js`. No npm, Webpack, Encore, or Sass build
is needed. JavaScript dependencies are pinned in `importmap.php`; install them
with `bin/console importmap:install`. The pinned Octopod player distribution lives
in `assets/lib/octopod/` because its original dependency was a Git revision.

`bin/console assets:build` generates the web manifest and service worker using
AssetMapper URLs. For production, run `bin/console asset-map:compile` afterward.
Do not retain compiled `public/assets/` files during development: they override
live asset updates. Styles live in `assets/styles/`, with shared theme variables
in `theme.css` and one file per component.

Episode artwork uses the original feed URLs. The imgproxy bundle remains
available for a future signed-proxy configuration.

### Search

`/search` uses `survos/search-bundle`, `#[Field]` metadata on `Episode`, and the
Doctrine adapter against PostgreSQL. It searches episode numbers, titles, and
authors, with duration filtering and sorting. Transcript content indexing and
vector search are not enabled yet. Only published episodes appear.

### Data

```bash
bin/console crawl feed
bin/console crawl shownotes --all
bin/console crawl transcript --all
bin/console crawl chapters --all
```

The current upstream RSS feed is a rolling window, not the full historical
archive. Missing upstream files are logged by the crawlers. `prepare --all`
publishes unpublished episodes and may download audio to calculate durations.
Keep Mastodon, mail, and push publishing disabled for local archive imports.

### Docker

The Dockerfile builds PHP/AssetMapper and nginx images. Compose uses PostgreSQL
and has no separate Node asset service. Start with `docker compose up -d --build`.
The optional `compose.services.yaml` adds a Messenger worker. This fork uses the
current entity model and PostgreSQL migrations rather than upstream's MySQL DDL.

### Tests

Create a separate test database (Doctrine appends `_test`) and configure its
connection in `.env.test.local` if needed:

```bash
bin/console doctrine:database:create --env=test --if-not-exists
bin/console doctrine:migrations:migrate --env=test -n
bin/console doctrine:fixtures:load --env=test -n
vendor/bin/phpunit
node --test assets/tests/*.test.js
```

The JavaScript tests use Node's built-in runner and the AssetMapper-installed
Luxon module. Node is only needed for tests, not to build or serve the site.

## Database Entities

![Database Diagram](assets/docs/database.svg)

## Push notifications

To enable push notification support you'll need to generate VAPID keys.

```shell
npx web-push generate-vapid-keys
```

Add the keys to your `.env.local` file.

## Production deployment

`no-agenda.survos.com` runs on the `no-agenda` Dokku app on `fsn1`, using
the Heroku PHP buildpack and a dedicated PostgreSQL 18 service (`no-agenda-db`).
Predeploy compiles AssetMapper assets and runs migrations. `/health` is the
startup check. Transcript and shownote files persist at `/app/var/storage`.

Development uses the Doctrine search adapter. Production uses the shared private
Elasticsearch service with an app-scoped key and the `no_agenda_` index prefix.
Create/populate the episode metadata index after loading the database:

```bash
ssh dokku@fsn1 enter no-agenda web php bin/console elastic:index:create episodes -n
ssh dokku@fsn1 enter no-agenda web php bin/console elastic:index:populate episodes -n
```

The worker consumes crawler, scheduler, and asynchronous indexing jobs. Keep its
Dokku restart policy at `unless-stopped` so timed worker exits restart. Production
secrets belong in Dokku config; outbound publishing stays disabled until explicitly
configured. Transcript full-text indexing and AI enrichment are not yet enabled.
