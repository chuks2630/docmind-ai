# Dev Environment Conventions

Project-specific setup notes for the DocMind AI backend. These exist because the
defaults (native Windows PHP, default Sail ports, default Docker Compose
project location) don't work on this machine — read this before assuming
something is broken.

## 1. All backend commands run through Sail, from WSL2

Laravel Sail's own `sail` script detects the host OS via `uname` and only
recognizes macOS, Linux, and WSL2. It explicitly rejects Git Bash on native
Windows (`MINGW64_NT-...`) with:

```
Unsupported operating system [MINGW64_NT-10.0-22631]. Laravel Sail supports macOS, Linux, and Windows (WSL2).
```

Native Windows PHP is not an option either — `laravel/horizon` requires the
`pcntl` and `posix` extensions, which don't exist on Windows PHP builds at
all (this is why the project moved to Sail in the first place).

**Rule:** every `artisan` / `composer` / `sail` command runs from a **WSL2
Ubuntu terminal**, inside the project directory, e.g.:

```bash
cd ~/projects/docmind-ai/backend
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan horizon
./vendor/bin/sail up -d
```

Never run these via Git Bash, PowerShell, or cmd.exe on the Windows side —
either the `sail` script will refuse to run, or (for plain `php artisan`)
it'll fail to resolve the `pgsql` / `redis` hostnames, which only exist
inside the Docker Compose network.

## 2. PHP version: 8.4 in Sail, not the Sail default of 8.3

`backend/compose.yaml` builds `laravel.test` from
`vendor/laravel/sail/runtimes/8.4` (image tag `sail-8.4/app`), not the
`8.3` runtime Sail scaffolds by default.

Why: `composer.lock` carries several transitive packages (`symfony/css-selector`,
`symfony/event-dispatcher`, etc.) that Composer's solver resolves to versions
requiring PHP `>=8.4.1`. On PHP 8.3 this leaves `composer update` unable to
find a consistent, installable set of package versions — not just a warning,
a hard resolution failure — and blocks clearing the outstanding security
advisories on those packages. `vendor/composer/platform_check.php` enforces
this at runtime: the app will fail loudly on PHP <8.4.1 rather than silently
misbehave, so an accidental revert to the 8.3 runtime is caught immediately
instead of surfacing later as a confusing composer or runtime error.

**Rule:** don't change `backend/compose.yaml`'s build context back to
`runtimes/8.3` (or any version <8.4) without first re-resolving
`composer.lock` against that older PHP version and confirming it still
installs cleanly — it won't, as of this writing, so treat "downgrade the
Sail PHP version" as requiring a dependency review, not a one-line revert.

`composer.json`'s `"php": "^8.2"` constraint is intentionally left as-is —
it already permits 8.4, and narrowing it isn't necessary now that Sail
itself pins the concrete runtime.

If you rebuild the image (`sail build --no-cache`), confirm the version
actually took:

```bash
./vendor/bin/sail php -v   # expect PHP 8.4.x
```

## 3. Non-default local ports

| Service | Host port | Container port | Why not the default |
|---|---|---|---|
| App (`laravel.test`) | `localhost:8080` | 80 | Port 80 is already bound by another local service on this machine |
| Postgres (`pgsql`) | `localhost:5434` | 5432 | Port 5432 is already bound by an unrelated project's Postgres container (`reconflow-db-1`) |
| Redis (`redis`) | `localhost:6380` | 6379 | Kept consistent with the other two remaps; avoids clashing with any other local Redis instance |

These are configured in two places that have to agree:

- **`backend/.env`** (and `.env.example`): `APP_PORT`, `FORWARD_DB_PORT`,
  `FORWARD_REDIS_PORT` — these are the host-side port numbers.
- **`backend/compose.yaml`**: each service's `ports:` entry reads those same
  env vars with a fallback (e.g. `'${FORWARD_DB_PORT:-5432}:5432'`), so
  `.env` is the single source of truth — don't hardcode a port directly in
  `compose.yaml`.

Container-to-container traffic (the app talking to `pgsql`/`redis`) always
uses the *container* port (5432/6379) over the internal `sail` Docker
network, regardless of these host-side remaps — `DB_HOST=pgsql` /
`REDIS_HOST=redis` in `.env` never need to change.

If you're debugging with `psql`, a GUI DB client, or `redis-cli` from the
Windows/WSL host (not from inside a container), use the remapped ports
above, not 5432/6379/80.

## 4. Project location: native WSL2 filesystem, not `/mnt/c`

The project lives at `~/projects/docmind-ai` inside the WSL2 Ubuntu
filesystem (ext4), **not** under `/mnt/c/...` (a Windows path mounted into
WSL2, e.g. under OneDrive).

Why: `/mnt/c` paths are backed by the 9p protocol between WSL2 and Windows,
which is noticeably slower for the kind of heavy file I/O Sail/Composer/
file-watching do, and OneDrive's on-demand sync on top of that can cause
occasional stalls. Keeping the code on the native WSL2 filesystem avoids
both.

Practical implications:

- Edit the project from a WSL2-aware editor path (e.g. VS Code's
  Remote - WSL, or the `\\wsl.localhost\Ubuntu\home\<user>\projects\docmind-ai`
  UNC path from Windows tools) rather than a `C:\...` / OneDrive path.
- Don't move the project back under `/mnt/c` or into an OneDrive-synced
  folder — that reintroduces the performance issue this move was meant to
  fix.
- The Postgres/Redis data lives in **named Docker volumes**
  (`backend_sail-pgsql`, `backend_sail-redis`), which are independent of
  where the project's source files live. The volume name is derived from
  the Compose project name, which defaults to the directory basename
  (`backend`) — so the Laravel app's folder must stay named `backend` for
  Sail to keep reattaching to the same volumes across moves.

## 5. Embedding dimension: 1024 (Voyage AI `voyage-3`)

`document_chunks.embedding` is a `pgvector` column sized `vector(1024)`,
matching Voyage AI's `voyage-3` model output dimension — this is the
standardized embedding provider/model for this project, not OpenAI's
default (1536). This is documented as a comment directly on the migration:

```
backend/database/migrations/2026_09_15_105655_create_document_chunks_table.php
```

If the embedding provider or model ever changes, this column's dimension
must change with it via a new migration — pgvector requires the column's
dimension to match every vector written to it, so a silent provider swap
without a matching migration will fail at write time, not at migration
time.
