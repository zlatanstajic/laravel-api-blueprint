# Laravel API Blueprint Project Rules

The single source of project guidance for any coding agent working in this repository. [`CLAUDE.md`](CLAUDE.md) imports this file; add shared rules here.

## Overview

Laravel API Blueprint is a Laravel 13 / PHP 8.5.x JSON API for personal todo items, authenticated with Laravel Sanctum personal access tokens. There is no meaningful frontend: `resources/views` holds a single welcome page, and `package.json` installs Husky and the OpenAPI-to-Postman documentation tool. Every todo belongs to exactly one user, and that ownership is enforced by a global Eloquent scope rather than by per-query `where` clauses.

## Commands

This is not a Sail or Docker project; run everything directly on the host.

```bash
composer test        # Full gate: rector (dry-run) → peck → pint --test → phpstan → sloppy → phpunit --coverage --min=100
composer fix         # Auto-fix: rector process + pint (run before committing)
composer serve       # Runs server + queue listener + pail logs concurrently
composer setup       # Fresh install: deps, key, migrate:fresh --seed, ide-helper, npm install, test

php artisan docs:build   # Static OpenAPI site into build/docs (git-ignored); what CI deploys to Pages
```

Individual gate steps are `composer test:rector`, `composer test:peck`, `composer test:pint`, `composer test:phpstan`, `composer test:sloppy`, and `composer test:phpunit`. Sloppy blocks high and critical findings; medium findings are advisory. Husky runs the full gate before commits and `composer test:sloppy` before pushes. The application, development dependencies, and CI require PHP 8.5.x only. Run the smallest relevant test before finalizing:

```bash
php artisan test --compact tests/Unit/TodoServiceTest.php
php artisan test --compact --filter=test_name
```

## Coverage is 100%, not a target

- `composer test:phpunit` runs `php artisan test --coverage --min=100`. A single uncovered branch fails the gate, and the Husky pre-commit hook runs the same gate, so uncovered code cannot be committed without `--no-verify`.
- Every new conditional, `catch` block, or early return needs a test in the same change. Do not add speculative code paths "for later": they have to be covered now.
- Do not remove or weaken existing tests to make the gate pass, and do not lower `--min`.

## Ownership is a global scope, and it is silent when nobody is authenticated

- `Todo::booted()` adds `App\Models\Scopes\UserScope`, which appends `where user_id = Auth::id()` — **but only when `Auth::check()` is true**. With no authenticated user the scope adds nothing and every todo in the table is visible.
- That means ownership is not enforced at the repository or service layer at all. `TodoRepository` calls plain `Todo::all()`, `findOrFail()`, and `destroy()`; correctness depends entirely on the request being authenticated by `auth:sanctum`. Never expose a todo route outside that middleware group, and never call the repository from an unauthenticated context (console command, queued job, seeder) expecting it to be owner-scoped.
- `user_id` is assigned in `TodoController::store()` from `$request->user()->id` after validation. It is deliberately absent from the validation rules: do not add it, or a client could create todos for another user.
- `TodoSeeder` and `TodoFactory` bypass all of this — the factory assigns `user_id` with `numberBetween(1, 2)`, which only lines up because `UserSeeder` creates exactly two users. Change one and you must change the other.

## Layering: Controller to Service to Repository to Model

- Controllers validate input, delegate to a constructor-injected service, and shape the response. They never touch Eloquent.
- Services hold business logic and delegate persistence to a repository. Repositories are the only layer that talks to the model.
- `App\Http\Controllers\Controller` is an abstract base providing `successResponse()` (wraps the payload in `data`) and `errorResponse()` (wraps the message in `error`). Extend it; do not build ad-hoc `response()->json()` envelopes in a controller.
- API output goes through `App\Http\Resources\TodoResource`, which emits a fixed `type` / `id` / `attributes` shape. Adding a column to the todos table does not expose it: add it to the resource too.

## Error responses: validate before the `try`, and expose only expected domain failures

- Controllers validate before their `try` block, so invalid input reaches Laravel's handler as a 422 with `message` and `errors`. Never move validation inside the `try`.
- `bootstrap/app.php` renders API exceptions as JSON and avoids guest redirects; missing authentication remains a JSON 401 without an Accept header.
- Controllers handle expected domain failures in specific catch blocks. Only `TodoNotFoundException` (404), `TodoDeleteFailedException` (409), and `UserInvalidCredentialsException` (401) may expose their translated domain messages and status codes. Every other `Throwable` returns the translated generic 500 response through `serverErrorResponse()`, regardless of its message or code. Never trust an arbitrary exception's `getCode()` as an HTTP status. Pass the caught failure to `serverErrorResponse()`; it logs only the exception type and source file/line, never the message, exception object, trace, or request data.
- Token authentication uses `UserInvalidCredentialsException` for both unknown email addresses and incorrect passwords. Do not reveal account existence through different response statuses or messages. Unknown-email attempts perform a discarded hash with the configured algorithm and cost; keep that work independent of the submitted password.
- Domain exceptions extend `Exception`, resolve messages from translation keys, and use `Symfony\Component\HttpFoundation\Response` constants as their codes. Catch domain exceptions explicitly at the controller operations that expect them.
- `tests/Feature/ApiExceptionTest.php`, `ApiAuthenticationTest.php`, `ApiValidationTest.php`, and `TokenAuthenticationTest.php` guard these response boundaries.

## Unit tests are stub-based and do not boot the database

- `tests/Unit` extends `PHPUnit\Framework\TestCase` directly (not `Tests\TestCase`) and drives repositories through hand-written subclasses such as `TodoStub`, which override `all()`, `findOrFail()`, `create()`, and `destroy()` with static fixtures. There is no `RefreshDatabase`, no migration run, and no container by default.
- Follow that pattern for new unit tests rather than reaching for a database. Reset the stub's static properties in `setUp()`; leftover state leaks between tests.
- `tests/Feature` extends `Tests\TestCase`. Authentication, validation, and factory tests do not require a database; `TodoOwnershipTest` uses real bearer tokens and `RefreshDatabase`, explicitly setting SQLite `:memory:` and clearing the connection URL before migrations. Keep database integration tests isolated from the configured application database, and do not convert the existing stub-based unit tests to them.

## User-facing strings live in translation files

- Response and exception messages resolve through `__('messages.*')`, with `resources/lang/en/messages.php` and `resources/lang/sr/messages.php`. Add every new key to **both** files; a missing Serbian key renders the raw key string.
- Peck spell-checks the codebase (`composer test:peck`). Project-specific vocabulary belongs in the `ignore.words` list in [`peck.json`](peck.json), not in an inline suppression.

## Conventions

- Every PHP file starts with `declare(strict_types=1);`. Rector's PHP sets and the Laravel set list run in the gate, so hand-written code that Rector would rewrite fails `composer test:rector`; run `composer fix` first.
- PHPStan runs at level 6 over `app` with Larastan. Do not add entries to `ignoreErrors`; fix the type instead.
- Formatting is Laravel Pint with the custom rules in [`pint.json`](pint.json). Classes and public methods carry short docblocks — match the surrounding density rather than stripping them.
- Routes live in `routes/api.php` under a `throttle:60,1,api:` group, with a stricter `throttle:3,1,tokens:` on `POST /api/tokens`. Keep the key prefixes distinct so the counters cannot collide. Named routes follow `resource.action`.
- The default database is SQLite at `database/database.sqlite`, created by `composer setup`. `composer setup` runs `migrate:fresh` and destroys existing local data.
- Branch names are kebab-case, off `master`, prefixed by the issue they close: `issues/12-short-description`. Do not use snake_case or camelCase in a branch name.
- Never read or write `.env`, and never commit credentials. The seeded admin account is configured through `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`, which `config/app.php` exposes as `app.admin`; `UserFactory::admin()` reads that config. Call `env()` only inside `config/`: once the configuration is cached, `env()` elsewhere returns its fallback.
- Do not run `git add` or `git commit` on your own initiative.

## API documentation is generated, never hand-written

- Scramble derives the OpenAPI document from controllers, validation rules, and route names at build time. There is no committed spec to update: change the code and the document follows. Run `php artisan scramble:analyze` to confirm it still generates cleanly.
- Document metadata (title, description, version, server list) lives in [`config/scramble.php`](config/scramble.php). Bump `info.version` there when the API surface changes.
- `php artisan docs:build` (defined in [`routes/console.php`](routes/console.php)) renders Scramble's own Blade view to a static `index.html` with the spec inlined, plus `api.json`. Do not hand-write a viewer page; the command reuses the package view so the static site and `/docs/api` cannot diverge.
- `dedoc/scramble` is a **dev** dependency. Keep every reference to it out of `app/` and out of service providers, or a `composer install --no-dev` deploy breaks at boot. `routes/console.php` is safe because it is only loaded in the console.
- [`.github/workflows/docs.yml`](.github/workflows/docs.yml) runs `composer test` on pull requests into `master` and pushes to `master` or `issues/**`; successful validation gates a documentation build for every triggered run, with artifact upload and deployment restricted to `master`. Pages deployments share a concurrency group. Only the deployment job has Pages and OIDC write permissions.

## Development schema viewer

- Truss is a development dependency. `config/truss.php` enables its viewer only when `APP_ENV=local`, disables baseline-file writes, and disables its optional MCP server. Keep package references out of application providers so no-dev installs remain bootable.
- `/truss` and `/truss/api/schema` expose schema structure locally; never enable them for public environments. `tests/Feature/SchemaViewerTest.php` verifies local access and non-local denial.

## Generated assets

- [`assets/img/og-image.png`](assets/img/og-image.png) is produced by [`scripts/gen-og-image.py`](scripts/gen-og-image.py) (Pillow, deterministic output). Edit the script and rerun `python3 scripts/gen-og-image.py`; never hand-edit the PNG.
- The Postman collection is generated by `scripts/gen-postman-collection.mjs` from Scramble OpenAPI. Run `composer docs:postman` after API changes, `npm run test:postman` for generator tests, and `npm run postman:check` after building docs to detect drift. Never hand-edit the collection or embed credentials. CI checks it during the documentation build.
- `_ide_helper.php`, `_ide_helper_models.php`, and `.phpstorm.meta.php` are generated by `composer artisan:ide-helper` and are git-ignored.
