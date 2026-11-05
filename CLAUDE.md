# CLAUDE.md

## Project Summary

This repository is a Laravel monolith for the SiMarsel V2 project. It follows the default Laravel 13 structure with an MVC architecture, Blade views, Eloquent ORM, and Laravel routing for HTTP endpoints.

The primary objective is to build a structured, maintainable web application for managing work, tracking progress, and supporting operational workflows in a single system.

## Tech Stack

### Backend

- PHP 8.3.36 or newer, below 9.0 (`"php": "^8.3.36"` in `composer.json`)
- Laravel 13.33.0, pinned exactly in `composer.json`; do not upgrade it without approval
- Eloquent ORM for database access
- PHPUnit for testing
- Composer for dependency management

### Frontend

- Blade templating (server-rendered views)
- Tailwind CSS v4 (via `@tailwindcss/vite`)
- Alpine.js for client-side interactivity
- Chart.js for charts and dashboard visualizations
- Native `fetch()` for AJAX/API calls
- Vite for asset bundling
- NPM for frontend dependencies

## Important Project Structure

- `app/` — application logic, models, controllers, providers, services
- `routes/` — HTTP route definitions
- `resources/views/` — Blade templates
- `resources/views/components/` — reusable Blade components
- `resources/css/app.css` — Tailwind entrypoint
- `resources/js/app.js` — JS entrypoint (Alpine.js bootstrap, shared helpers)
- `resources/js/` — Alpine components, Chart.js setup, fetch helpers
- `database/migrations/` — database schema migrations
- `database/seeders/` — seed data
- `config/` — application configuration
- `public/` — public web entrypoint
- `tests/` — automated tests
- `storage/` — runtime files
- `bootstrap/` — application bootstrap

## Development Rules

### 1. Follow Laravel conventions

- Prefer Artisan commands when generating code:
    - `php artisan make:model`
    - `php artisan make:controller`
    - `php artisan make:migration`
    - `php artisan make:request`
    - `php artisan make:policy`
    - `php artisan make:seeder`
- Use Eloquent ORM instead of raw SQL unless absolutely necessary.
- Use dependency injection in controllers and services.
- Do not place business logic directly in route files.

### 2. Keep code organized

- Group routes by feature or responsibility.
- Keep controllers focused on request/response handling.
- Put business logic in service classes or model methods where appropriate.
- Keep Blade views simple and presentation-focused.

### 2a. Recommended architecture: Service-first, Repository when needed

This project should follow a service-oriented structure:

- `Controller` → handles HTTP input and output
- `Service` → contains business logic and orchestration
- `Repository` → encapsulates data access logic when needed
- `Model` → defines relationships, scopes, and simple business rules

Use this pattern:

- Put validation, workflow orchestration, and domain logic in services.
- Use repositories for complex queries, repeated database logic, or data-access abstraction.
- Do not put business logic directly in controllers.
- Do not create repositories for every tiny model unless data access is genuinely shared or complex.
- Prefer simple Eloquent calls in a service before adding a repository for trivial CRUD.

When a feature is small and simple, a service alone is often enough. Add a repository only when data access is repeated, becomes complex, or needs to be isolated for testing and reuse.

### 3. Database and migrations

- Always use migrations for schema changes.
- Never modify production data manually.
- Use seeders only for initial or dummy data.
- Keep model and migration definitions consistent.

### 4. Input validation

- Use Form Request classes for validation.
- Validate on the server side only.
- Do not rely on frontend validation alone.

### 5. Security

- Use `@csrf` in HTML forms.
- Send the `X-CSRF-TOKEN` header on every non-GET `fetch()` request.
- Use authentication and authorization policies for protected features.
- Prevent SQL injection by using Eloquent or query builder methods.
- Use `Hash` for passwords.
- Sanitize and escape output in Blade templates.

### 6. Testing

- Write tests for new features and bug fixes.
- Prefer feature tests or focused unit tests for the changed behavior.
- Run the relevant test set after making changes.
- Examples:
    - `php artisan test`
    - `php artisan test --filter=SomeTestName`

### 7. Frontend

The frontend stack is **Blade + Tailwind CSS + Alpine.js + Vite + Chart.js**, with native `fetch()` for AJAX/API calls.

- Render pages server-side with Blade. Do not introduce an SPA framework (Vue, React, Inertia, Livewire) without explicit approval.
- Style with Tailwind utility classes. Avoid custom CSS unless a utility cannot express it; put shared tokens in `@theme` in `resources/css/app.css`.
- Use Alpine.js (`x-data`, `x-show`, `x-on`, `x-model`) for interactivity such as modals, dropdowns, tabs, and inline forms. Move non-trivial logic out of inline attributes into `Alpine.data()` components registered in `resources/js/`.
- Use Chart.js for charts. Fetch chart data as JSON from a dedicated endpoint, or pass it from the controller with `Js::from()`/`@json`. Do not build chart datasets in Blade.
- Use native `fetch()` for AJAX. Do not add jQuery or Axios.
- Keep fetch logic in a shared helper in `resources/js/` that sets `Accept: application/json`, `X-Requested-With: XMLHttpRequest`, and the `X-CSRF-TOKEN` header read from `<meta name="csrf-token">`.
- Handle `422` responses by showing Laravel validation errors next to the fields, and handle other non-2xx responses with a user-facing error message.
- AJAX endpoints return JSON from controllers (use API Resources for model data). Validation still goes through Form Requests.
- Load assets only through `@vite(['resources/css/app.css', 'resources/js/app.js'])` in the layout.
- Import Alpine.js and Chart.js through Vite from NPM; do not load them from a CDN.

### 8. Error handling

- Use exceptions with a specific meaning. Do not use `throw new Exception(...)` for business conditions or HTTP errors.
- Create business exceptions in `app/Exceptions/`, named after the condition they represent (for example, `TaskAlreadyCompletedException`).
- Do not set the HTTP status code through the second argument of `Exception`. `new Exception('...', 404)` does **not** produce an HTTP 404.
- Services may throw a specific exception when a business rule is violated or a meaningful application-level condition cannot be fulfilled.
- Controllers do not use `try`/`catch` just to pass on or reshape errors from services. Let Laravel's exception handling mechanism deal with them.
- Validate request input (required, type, format, length, etc.) with Form Requests. Handle business rules (duplicate data, invalid status, process conditions) in services or domain logic.
- Do not catch unexpected errors (database, programming, or infrastructure failures) just to change the response message. Let Laravel return a 500 and keep the technical details in the log, not in the response.
- Return the response that fits the request type: HTML for web requests, JSON for JSON/API requests. Do not force every exception into JSON in this Blade application.
- Never expose stack traces, database queries, credentials, or other internal details to the user. Keep `APP_DEBUG=false` outside local development.

#### Before introducing a new exception

1. Check whether an existing Laravel or framework exception already represents the condition.
2. Reuse that exception when its meaning matches the situation.
3. Create a custom exception only when the condition is a distinct business or domain case.
4. Do not create custom exceptions merely to wrap generic errors.

| Condition | Use |
|---|---|
| Resource not found | Laravel's not-found mechanisms, such as `findOrFail()` or route model binding |
| Invalid request data | Form Request validation |
| Business rule violation | A custom business exception, when needed |
| Unexpected database or system failure | Do not convert it into a business exception |

## Local Setup

### Initial install

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### Database

This project uses MySQL as the default database.

Update `.env` with the MySQL connection settings:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simarsel
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate
```

### Frontend assets

```bash
npm install
npm install alpinejs chart.js   # first-time only, if not yet in package.json
npm run build                   # production build
npm run dev                     # Vite dev server with hot reload
```

### Run the app

```bash
php artisan serve
```

Access:

```text
http://localhost:8000
```

## Common Commands

```bash
php artisan serve
php artisan route:list
php artisan make:model ModelName
php artisan make:controller ControllerName
php artisan make:migration create_table_name
php artisan make:request StoreSomethingRequest
php artisan make:seeder SeederName
php artisan migrate
php artisan migrate:fresh --seed
php artisan test
php artisan optimize
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Code Standards

- Follow Laravel naming conventions.
- Keep class, file, method, and variable names clear and consistent.
- Use English names for code symbols unless the user explicitly requests otherwise.
- Keep code clean, concise, and readable.
- Avoid excessive comments; only add them when logic is not obvious.
- Prioritize maintainability and reusability.

## Recommended Patterns

### Controller

```php
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());

    return redirect()->route('users.index')->with('success', 'User created successfully.');
}
```

### Model

- Put relationships in the model.
- Use accessors and mutators when needed.
- Avoid putting complex queries directly in Blade views.

### Blade

- Use reusable components for repeated UI.
- Do not put business logic in Blade templates.
- Use native Blade structures such as `@foreach`, `@if`, and `@csrf`.
- Include `<meta name="csrf-token" content="{{ csrf_token() }}">` in the base layout.

### Alpine.js + fetch()

```js
// resources/js/http.js
const token = document.querySelector('meta[name="csrf-token"]').content;

export async function http(url, { method = 'GET', body } = {}) {
    const response = await fetch(url, {
        method,
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token,
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw { status: response.status, data };
    }

    return data;
}
```

```js
// resources/js/app.js
import Alpine from 'alpinejs';
import { http } from './http';

Alpine.data('taskForm', () => ({
    form: { title: '' },
    errors: {},
    loading: false,

    async submit() {
        this.loading = true;
        this.errors = {};

        try {
            await http('/tasks', { method: 'POST', body: this.form });
            this.form.title = '';
        } catch ({ status, data }) {
            if (status === 422) this.errors = data.errors;
        } finally {
            this.loading = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
```

```blade
<div x-data="taskForm">
    <input x-model="form.title" class="rounded-md border px-3 py-2">
    <p x-show="errors.title" x-text="errors.title?.[0]" class="text-sm text-red-600"></p>
    <button @click="submit" :disabled="loading" class="rounded-md bg-blue-600 px-4 py-2 text-white">Save</button>
</div>
```

### Chart.js

```js
// resources/js/app.js (register before Alpine.start())
import Chart from 'chart.js/auto';

Alpine.data('chart', (url, type = 'line') => ({
    async init() {
        const { labels, datasets } = await http(url);
        new Chart(this.$refs.canvas, { type, data: { labels, datasets } });
    },
}));
```

```blade
<div x-data="chart('{{ route('reports.progress') }}')">
    <canvas x-ref="canvas"></canvas>
</div>
```

## AI / Agent Workflow

### Before changing code

1. Read only the files needed to understand the task.
2. Identify the affected routes, controllers, models, and migrations.
3. Check whether similar functionality already exists.
4. Make the smallest relevant change.

### After changing code

1. Run the relevant tests.
2. Check for PHP syntax or runtime errors.
3. Confirm migrations and config remain consistent.
4. Do not change unrelated files.

### When errors occur

- Check the output from `php artisan test` or other relevant Artisan commands.
- Focus on the root cause rather than patching blindly.
- Install missing dependencies with Composer or NPM as needed.

## Git Conventions

- Use clear and descriptive commit messages.
- Use feature or fix branches appropriately.
- Do not commit generated build output or vendor files unless explicitly required.
- Ensure changes are tested before committing.

## General Project Direction

This project is expected to grow as a maintainable Laravel monolith, with clean architecture and room for future features such as:

- user/admin authentication
- role and permission management
- CRUD modules
- admin dashboard
- reporting and export features
- internal API support if needed later

Do not over-engineer this repo into a microservice architecture. Since this is a monolith, the priority is clean structure, manageable scale, and high maintainability.

## Special Rules for AI

- Prioritize Laravel best practices.
- Do not change global configuration without a valid reason.
- If a task affects the database, always consider migrations and seeders.
- If a task touches auth or authorization, account for policy, middleware, and route rules.
- Do not rewrite the default framework structure without a clear justification.
- Keep changes aligned with the needs of the SiMarsel V2 project rather than generic Laravel defaults.

## Recommended Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

## Final Guidance

This repository should be treated as a modern Laravel monolith built with maintainability and clarity in mind. Use a simple, safe, and scalable approach. Prioritize the features that are truly needed, follow the standard Laravel structure, and ensure every change is tested before being considered complete.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
