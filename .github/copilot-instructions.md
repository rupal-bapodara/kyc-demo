# Project Coding Standards

## General

- Follow Laravel conventions and the existing project structure.
- Before creating new files, inspect the existing codebase and reuse existing patterns.
- Keep implementations simple, readable, and maintainable.
- Do not introduce unnecessary abstractions or design patterns.
- Use dependency injection instead of creating dependencies manually.
- Follow SOLID principles where they provide real value.
- Do not duplicate business logic.
- Do not modify unrelated files while implementing a feature.

## Controllers

- Controllers must remain thin.
- Controllers are responsible only for HTTP concerns.
- Do not put business logic in controllers.
- Do not put database queries directly in controllers.
- Do not use `Model::where()`, `Model::create()`, etc. directly in controllers when the operation belongs to a service/repository.
- Use Form Requests for validation.

## Services

- Put business logic in Service classes.
- Services coordinate application workflows.
- Services should not contain HTTP-specific logic.
- Services should not return HTTP responses.
- Use database transactions in the service when an operation contains multiple related database changes.

## Repositories

- Repositories are responsible for database/Eloquent operations.
- Do not put business rules in repositories.
- Use repository interfaces when a repository abstraction is required.
- Inject repository interfaces into services.
- Do not create repositories for trivial operations unless the project architecture requires them.

## Validation

- Use Form Request classes for request validation.
- Do not put validation logic directly inside controllers.
- Create reusable custom Rule classes for complex validation rules.
- Keep validation rules separate from business logic.

## API

- Use RESTful routes and HTTP methods.
- Use appropriate HTTP status codes.
- Use API Resources for consistent response structures.
- Never expose passwords, password hashes, tokens, or other sensitive fields.
- Keep API response structures consistent.

## Authentication

- Use Laravel Sanctum for API token authentication unless the project explicitly requires another authentication mechanism.
- Authentication logic belongs in a Service.
- Token creation/revocation should not be implemented directly inside controllers.

## Database

- Use Eloquent relationships instead of unnecessary manual queries.
- Use migrations for all schema changes.
- Use transactions for multi-step operations that must succeed or fail together.
- Avoid N+1 queries.
- Use eager loading when relationships are required.
- Do not retrieve unnecessary columns or records.

## Queue

- Use Laravel Jobs for asynchronous operations.
- Jobs that perform slow operations such as emails, notifications, external API calls, or heavy processing should be queued.
- Use Redis for queue processing where configured.
- Do not perform slow operations directly inside API requests when they can be asynchronous.

## Mail

- Emails should be sent through Mailables/Notifications.
- Slow email operations should be queued.
- Do not send emails directly from controllers.

<!-- ## Testing

- Use Pest PHP for tests.
- Prefer feature tests for API and application behavior.
- Use unit tests only for meaningful isolated business logic.
- Do not create unit tests merely because a class exists.
- Use factories for test data.
- Use Queue::fake() when testing queued jobs.
- Use Mail::fake() when testing mail dispatching.
- Use Event::fake() when testing events.
- Tests must not depend on real external services.
- Test successful scenarios and important failure/validation cases.
- Keep tests focused on behavior rather than implementation details. -->

<!--
## Code Formatting

- Use Laravel Pint for PHP code formatting.
- Follow the existing project formatting conventions.
- Run Pint after implementing PHP changes.
- Do not manually reformat unrelated files.
- Do not disable Pint rules just to make formatting pass.
- Before completing a feature, verify formatting with:

  `docker compose exec app ./vendor/bin/pint --test`
-->

## Error Handling

- Do not expose stack traces or internal implementation details through APIs.
- Use Laravel's exception handling mechanisms.
- Return meaningful and consistent API error responses.
- Log unexpected application errors appropriately.

## API Error Handling

- All API endpoints must return JSON responses.
- Use appropriate HTTP status codes.
- Successful responses should have a consistent JSON structure.
- Validation failures should return HTTP 422 with validation errors.
- Authentication failures should return HTTP 401.
- Authorization failures should return HTTP 403.
- Resource not found should return HTTP 404.
- Conflict/business rule failures should use an appropriate 4xx status code.
- Unexpected server errors should return HTTP 500 without exposing stack traces, SQL queries, credentials, or internal implementation details.
- Do not add generic try/catch blocks to every controller method.
- Catch exceptions only when the application can meaningfully handle them.
- Use Laravel's exception handling mechanism for unexpected exceptions.
- Log unexpected exceptions appropriately.
- API error responses should contain a clear, client-safe message.
- Do not return HTML error pages from API endpoints.

## Security

- Never hard-code passwords, API keys, tokens, or credentials.
- Use `.env` for environment-specific configuration.
- Never commit `.env`.
- Validate and authorize user input.
- Follow Laravel's built-in security mechanisms.

## Code Quality

- Prefer readable code over clever code.
- Use meaningful class, method, and variable names.
- Keep methods small and focused.
- Avoid deeply nested conditions.
- Avoid duplicated code.
- Add comments only when they explain why something is done, not what the code obviously does.
- Do not modify unrelated files while implementing a feature.

<!--
## CI/CD

- All tests must pass before code is considered complete.
- Use Pest PHP for automated tests.
- Use Laravel Pint for code formatting checks.
- Use PHPStan/Larastan for static analysis.
-->

## Before Implementation

Before writing code:

1. Inspect the existing project structure.
2. Check the Laravel version.
3. Check existing architecture and conventions.
4. Reuse existing classes where appropriate.
5. Identify which layer the new logic belongs to.
6. Implement the smallest clean solution.
7. Run relevant tests/linting after implementation.

## Architecture

Use this general flow for application features:

Request
→ Form Request
→ Controller
→ Service
→ Repository
→ Model/Database

For asynchronous processing:

Controller
→ Service
→ Dispatch Job
→ Redis
→ Queue Worker
→ Job
→ Mail/Notification/External Service

Do not skip architectural layers when the operation contains meaningful business logic.

## Important

Do not generate unnecessary files.

Do not create a Service, Repository, Interface, DTO, Action, Event, Listener, or other abstraction simply because it is theoretically possible.

Choose the simplest architecture that satisfies the project's requirements and existing conventions.
