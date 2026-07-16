# Admin Panel Boilerplate — Product Requirements Document (PRD)

**Version:** 1.0

**Date:** 2026-02-08

**Author:** (Provided by requestor)

---

## 1. Executive summary

Create a professional, extensible, long-term **Admin Panel Boilerplate** for internal organizations to rapidly scaffold, develop, and maintain Laravel projects. The boilerplate must be:

- Minimal in third-party dependencies (only trusted, actively-maintained packages when required).
- Modern and future-proof (aligned to 2026 Laravel best practices and PHP versions).
- Modular: easy creation/removal of feature modules via CLI commands (e.g. `php artisan make:feature UserManagement`).
- Production-ready: secure defaults, testing, CI/CD, observability, performance optimisations.

The deliverable is a codebase template + CLI scaffolding + docs that teams can use to start new projects or standardize multiple internal apps.

---

## 2. Goals & success metrics

**Goals**
- Reduce new feature onboarding time by 60% for backend developers and 50% for frontend work.
- Provide consistent role/permission model and themeable UI components across projects.
- Keep runtime and maintenance dependencies stable and minimal.

**Success metrics**
- Time to scaffold a new feature module with working CRUD endpoints and views & tests: \< 10 minutes.
- Automated test coverage for core modules: ≥ 85%.
- Security baseline: OWASP Top 10 mitigations in place at project start.

---

## 3. Stakeholders

- Product Owner / Program Manager
- Backend Engineers (Laravel)
- Frontend Engineers (Blade + Alpine.js + Tailwind)
- DevOps / SRE
- QA / Test Engineers
- Security Engineers

---

## 4. Scope

**In-scope**
- Base code architecture (controllers, services, models, migrations, views).
- Role-based access control (RBAC) scaffolding and examples.
- CLI module generator: `make:feature` and complementary commands.
- Theming system with global components (admin, user panels, multi-role panels).
- Example feature modules (UserManagement, Roles, AuditLog, Settings).
- Test harness and CI config templates.
- Documentation generator and onboarding guide.

**Out-of-scope (initially)**
- Multiple tenant architecture by default (provide as optional extension).
- Full multi-language i18n beyond English (scaffolded support only).
- Large enterprise SSO integrations (provide hooks and examples).

---

## 5. Assumptions

- Project will target Laravel 12 (modern stable release in 2025/2026) and PHP 8.5+ for best performance and language features.
- The team prefers custom code and only uses trusted packages (e.g., Spatie family, Laravel official packages) when they materially reduce risk or maintenance.
- Frontend stack will be Blade + Alpine.js + Tailwind CSS (server-rendered views with sprinkles of client interactivity).

---

## 6. Technology stack (recommended)

- **Framework:** Laravel 12 (project template compatible with latest LTS minor versions).
- **Language:** PHP 8.5+ (use LTS supported runtime).
- **Frontend:** Blade templates, Alpine.js (progressive JS), Tailwind CSS (utility-first styling).
- **Auth & API:** Laravel Sanctum for SPA / token-based needs.
- **RBAC:** Spatie `laravel-permission` (optional but recommended for fine-grained permissions).
- **Queue / WebSockets:** Laravel Reverb or Horizon depending on needs.
- **Build tools:** Vite for asset bundling and dev workflows.
- **DB:** MySQL / Postgres (SQL-first approach); support migrations & seeders.
- **Testing:** Pest / PHPUnit + Laravel Dusk optional for browser E2E.
- **CI/CD:** GitHub Actions or GitLab CI templates included.
- **Observability:** Sentry (errors), Prometheus + Grafana (metrics) integration examples.

*(Rationale for choices and references are included in the developer notes outside the boilerplate — these choices reflect 2026 industry defaults.)*

---

## 7. High-level architecture

1. **Monorepo vs template per project**
   - Provide a single **boilerplate repository** that can be cloned to start a new project (recommended). Optionally provide a monorepo example for internal microservices.

2. **Layered server architecture**
   - Presentation: Blade components + global layout + per-module views
   - Application: Controllers -> Actions/Requests -> Services
   - Domain: Eloquent Models + Value Objects + Domain Services
   - Infrastructure: Repositories, external API adapters, queues, storage

3. **Module boundaries**
   - Each feature module is a folder under `app/Admin/<FeatureName>` or `app/Modules/<FeatureName>` containing Controllers, Models, Views, Requests, Policies, Tests, Migrations and Seeders.
   - Modules should be decoupled and self-contained; module removal should be possible by removing the folder + running provided cleanup commands.

4. **Routing**
   - Route groups and naming conventions: `admin.*`, `api.admin.*`, `user.*`
   - Health & system routes separated and guarded.

---

## 8. Folder & code structure (recommended)

```
app/
├── Admin/                        # Admin modules (feature-based)
│   ├── UserManagement/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Requests/
│   │   ├── Policies/
│   │   ├── Views/
│   │   │   ├── index.blade.php
│   │   │   ├── create.blade.php
│   │   │   └── edit.blade.php
│   │   ├── Migrations/
│   │   ├── Seeders/
│   │   └── Tests/
├── Actions/                      # Small single-purpose classes for controller logic
├── Services/                     # Business logic and integrations
├── Repositories/                 # DB abstraction (if used)
├── Policies/
├── Console/                      # Custom artisan commands (scaffolding)
resources/
├── views/
│   ├── components/               # Global Blade components (table, form, modal)
│   ├── layouts/                  # app.blade.php, admin.blade.php
│   └── themes/                   # theme tokens / templates
public/
├── assets/                       # compiled assets (Vite)

routes/
├── admin.php
├── web.php
├── api.php

database/
├── migrations/
├── seeders/

tests/
```

**Naming conventions**
- Controllers: `FeatureNameController` for resourceful controllers; for complex flows, use `FeatureName/CrudController`.
- Blade components: `x-admin.table`, `x-admin.form`, `x-theme.header`.
- Route names: `admin.<module>.<action>`

---

## 9. Module scaffold & CLI

**Goal:** Developers should create a fully wired module with `php artisan make:feature UserManagement`.

**`make:feature` responsibilities:**
- Create folder under `app/Admin/UserManagement` with Controllers, Models, Requests, Policies, Views, Seeders and Tests.
- Register routes in `routes/admin.php` (scaffold entry with comments).
- Create base migration file with recommended fields (id, uuid, soft deletes, timestamps, created_by, updated_by).
- Create basic Blade views: index, create, edit, show.
- Wire default permissions via `spatie/laravel-permission` (optional flag `--with-permissions`).
- Emit a report at the end with `next steps` (migrate, seed, run tests).

**Command examples:**
```
php artisan make:feature UserManagement
php artisan make:feature ProductCatalog --with-api --with-permissions
php artisan make:feature AuditLog --stateless
```

**Design notes:**
- Keep generator templates in `stubs/feature/` to allow easy customization.
- Provide `--dry-run` and `--force` flags.

---

## 10. Controller & request structure

- Controllers should be thin; business logic in Actions/Services.
- Use Form Request objects for validation and authorization.
- Use Policies (or Spatie permissions) to guard controller actions.

**Example controller pattern**
```php
class UserManagementController extends Controller
{
    public function index(IndexUserRequest $request)
    {
        $columns = ColumnConfig::for('users');
        $data = TableBuilder::from(User::query(), $columns)->paginate();
        return view('admin.user_management.index', compact('data', 'columns'));
    }
}
```

---

## 11. Reusable UI components & patterns

- Build a set of global Blade components (table, pagination, modal, toast, form controls) that accept configuration arrays.
- Use the **AJAX HTML fragment swapping** approach for large tables (initial page loads full layout; Blade partials returned for table body updates) so logic remains server-driven while UX is snappy.
- Provide a `x-admin.table` wrapper that uses Alpine.js to fetch `?page=&search=&sort=` partials.
- Theme tokens (colors, spacing, typography) stored centrally in `resources/views/themes` and `tailwind.config.js`.

---

## 12. Data & DB design

- Enforce UUIDs when cross-system interoperability is required; otherwise use integer ids for performance where appropriate.
- Provide base migration template with common columns: `id`, `uuid`, `created_by`, `updated_by`, `deleted_at`, `meta (json)`, `timestamps`.
- Index recommendations: Always index columns used in `ORDER BY`, `WHERE` (for cursor pagination use the same index). Include composite indices where search + sort are common.
- Use `cursorPaginate()` for extremely large datasets and provide an opt-in switch in the table builder.

---

## 13. API strategy

- Provide resourceful API routes under `/api/admin/` guarded by Sanctum tokens or API keys.
- API responses: use `Resources` (Laravel API Resource classes) for consistency and versioning.
- Support both HTML (Blade) server-rendered paths and JSON endpoints for SPA integrations.

---

## 14. Authentication & authorization

- Default: Laravel auth + Sanctum.
- RBAC: Spatie `laravel-permission` integrated with commands to seed basic roles (admin, staff, user).
- Audit logging: model events should write to an `audit_logs` table (author, action, diff).
- Session management & idle timeout should have sane defaults and be configurable.

---

## 15. Performance & scalability

- Use `cursorPaginate()` and indexed queries for large tables (documented caveats included in the README).
- Cache expensive lookups at application layer (Redis) with tagging where appropriate.
- Queue long-running work with Redis / database queues and provide sample worker / Horizon setup.
- Provide recommendations for horizontal scaling (stateless web servers + shared sessions via Redis).

---

## 16. Security

- Default to L7 protections: CSRF for forms, XSS escaping by Blade, parameterized queries via Eloquent.
- Enforce strong password rules and optional passkeys (pluggable).
- Rate-limit critical endpoints and provide per-second rate limiting options in `RouteServiceProvider`.
- Provide secure defaults for env files and `.env.example` (no secrets, clear guidance on secrets management).
- Include automated dependency scanning and secret scanning as part of CI.

---

## 17. Testing & quality

- Unit tests with Pest/PHPUnit for services, request validation, and policies.
- Feature tests for important flows (auth, permissions, CRUD).
- Integration tests for database migrations and seeding.
- Optional Dusk tests for critical UI flows.
- Set up GitHub Actions matrix runner for PHP versions and DB drivers.

---

## 18. CI/CD & release process

- Provide example GitHub Actions workflows:
  - `ci.yml` (lint, tests, static analysis)
  - `deploy.yml` (build assets with Vite, run migrations, deploy to staging/production)
- Include `release` conventions and tags, and migration rollout patterns (blue/green where available).

---

## 19. Observability & operations

- Include Sentry integration for exception telemetry (sample init code).
- Provide hooks for metrics (Prometheus exporter example) and log shipping (ELK/Datadog examples).
- Include a `health` route & `ping` endpoint for load balancers.

---

## 20. Documentation & developer experience

- `README.md` with quick-start (clone, composer install, env, migrate, seed, npm install, npm run dev).
- `CONTRIBUTING.md` with code style (PSR-12), commit message rules, PR checklist.
- Developer onboarding guide: how to scaffold new module, run tests, debug, and extend themes.
- API docs auto-generated (OpenAPI stub) for `/api/admin`.

---

## 21. Example deliverables (MVP)

1. Boilerplate repo: default auth, user management module, RBAC, audit log module, CLI `make:feature`.
2. Global Blade components library with examples.
3. CI config and basic observability scaffolding.
4. Developer guides & example feature walk-through.

---

## 22. Roadmap & milestones

**M1 (2 weeks)**
- Core repo, auth scaffolding, basic user management, starter Blade components, basic `make:feature` stub.

**M2 (3 weeks)**
- Expand module scaffolds (roles, audit logs), seeders, spatie integration, cursor pagination table component, tests.

**M3 (2 weeks)**
- CI/CD pipelines, observability, security hardening, documentation and examples.

**M4 (ongoing)**
- Optional features: multi-tenancy, SSO connectors, additional theme variants.

---

## 23. Acceptance criteria

- `php artisan make:feature UserManagement` creates a working module with controllers, views, migrations, seeders, and tests.
- Role-based access control is enforceable via policies or permissions on every sample endpoint.
- Table components handle at-scale datasets using cursor pagination and AJAX partial updates.
- Full CI run passes on PRs; example project can be deployed to staging using provided `deploy.yml`.

---

## 24. Appendix — Code snippets & best practices

- **TableBuilder**: use a server-side builder pattern with `cursorPaginate()` opt-in for high volumes.
- **Action classes**: single responsibility objects used by controllers and queued jobs.
- **FormRequests**: centralize validation & authorization logic.
- **Componentization**: prefer small, reusable Blade components over monolithic views.

---

## 25. Next steps

1. Review this PRD with stakeholders and confirm constraints (allowed packages, hosting environment, CI platform).
2. Approve MVP scope & milestone dates.
3. Begin M1 implementation with a 2-week sprint and weekly demos.

---

*End of PRD*

