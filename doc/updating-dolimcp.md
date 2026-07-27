# Updating DoliMCP

How to upgrade the **DoliMCP** custom module on Dolibarr (Docker, VPS, or shared hosting), following common Dolibarr practices.

## How Dolibarr handles custom modules

DoliMCP lives under `htdocs/custom/dolimcp/`. Dolibarr does not provide a separate plugin updater (unlike WordPress). You **replace module files** and, when the release requires it, run **database migrations** from the module `init()` logic.

## Recommended update flow (production)

### 1. Before you change anything

- Backup the **database** and **`documents/`**.
- Note the current module version: **Home → Setup → Modules**, or `core/modules/modDolimcp.class.php` → `$this->version`.
- Test the new release ZIP on a **staging** instance (same Dolibarr major version as production).

### 2. Deploy new files

1. Download `dolimcp-<tag>.zip` from [GitHub Releases](../README.md#github-releases) (or build from this repository).
2. Extract so the result is still `htdocs/custom/dolimcp/`.
3. Overwrite the existing folder on the server (FTP, SFTP, or file manager on shared hosting).
4. Keep file permissions typical for hosting: files `644`, directories `755`, and **`documents/`** writable by the web server.

### 3. Usually you do **not** disable the module

For **PHP-only** changes (MCP tools, API bridge, `mcp.php`, translations):

- Replace files → done.
- **No** disable/enable step.
- MCP tokens in `llx_dolimcp_token` remain valid.
- Clear PHP opcode cache on the host if applicable (cPanel “Reset PHP opcache”, or wait for cache TTL).

### 4. When disable/enable **is** required

In Dolibarr: **Home → Setup → Modules → DoliMCP Bridge → Disable → Enable** when the release:

- Adds **new permissions** or admin menus (registered on `init()`).
- Adds **new database tables** loaded from `sql/` on activation.
- Changes **hooks** in `module_parts` (for example the user card tab).

That runs `modDolimcp::init()` again and recreates data directories under `documents/dolimcp/` if needed.

### 5. Verify after update

```bash
curl -sS "https://YOUR-DOLIBARR/custom/dolimcp/mcp.php"
```

Expect JSON with `"status":"ok"`. Then test an MCP tool (for example `dolibarr_list_projects`). Users do **not** need new MCP tokens unless the release explicitly changes token behavior.

## Versioning (maintainers)

| Practice | Location / action |
|----------|-------------------|
| Module version | `$this->version` in `htdocs/custom/dolimcp/core/modules/modDolimcp.class.php` |
| MCP server info | Keep `serverInfo.version` in `class/mcpstreamablehandler.class.php` aligned if you change it |
| Git tags | Use tags that match the module version, for example `v0.4.0` → ZIP `dolimcp-v0.4.0.zip` |
| Dolibarr compatibility | Update `need_dolibarr_version` in `modDolimcp.class.php` when requirements change |
| Release notes | GitHub Release + optional `CHANGELOG.md` inside the module |

## Database changes (future releases)

Initial install uses `sql/llx_dolimcp_token*.sql` via `_load_tables()` on **first enable**.

When the schema changes in a later version:

1. **Do not** only edit the original `.sql` files on sites that are already installed.
2. Add **upgrade steps** in `modDolimcp::init()`, for example:
   - Read `DOLIMCP_VERSION` from `llx_const`.
   - If the stored version is older than `$this->version`, run `ALTER TABLE` / `CREATE TABLE` statements in the `$sql` array passed to `_init()`.
   - Set `DOLIMCP_VERSION` to `$this->version` after migrations succeed.
3. Prefer **idempotent** migrations (`IF NOT EXISTS`, check column existence).
4. **Do not drop** `llx_dolimcp_token` on upgrade unless you ship a documented migration path for tokens.

## What to preserve across updates

| Preserve | Safe to replace or clear |
|----------|---------------------------|
| `llx_dolimcp_token` (per-user MCP tokens) | PHP files under `custom/dolimcp/` |
| Module enabled flag in Dolibarr | `documents/dolimcp/mcp_sessions/` (temporary MCP sessions) |
| Official REST API keys (separate from MCP) | `documents/dolimcp/temp/` |

## Development vs production

| Environment | Practice |
|-------------|----------|
| **Docker (this repo)** | Volume mount `htdocs/custom/dolimcp`; pull or copy files from git. |
| **Production** | Install from GitHub Release ZIP; see [README — Production install on shared hosting](../README.md#production-install-on-shared-hosting). |
| **Rollback** | Restore the previous ZIP and database backup; disable/enable the module only if the failed upgrade required it. |

## When **not** to disable the module

- Bug fixes in `class/mcpapibridge.class.php`
- New or changed MCP tools in `class/mcptoolregistry.class.php`
- CORS, session handling, or `mcp.php` transport
- Language files under `langs/`

These updates are **file replace only**.

## Optional improvements (long term)

1. Store **`DOLIMCP_VERSION`** in `llx_const` and run migrations in `init()`.
2. Ship **`CHANGELOG.md`** inside the module (include it in the release ZIP).
3. State in each GitHub Release whether the upgrade is **file-only** or needs **disable/enable**.
4. CI check that the git tag matches `$this->version` in `modDolimcp.class.php`.

## Checklist per release

- [ ] Version bumped in `modDolimcp.class.php`
- [ ] Git tag pushed (`vX.Y.Z`) and GitHub Release ZIP downloaded
- [ ] Production database and `documents/` backed up
- [ ] Files extracted to `htdocs/custom/dolimcp/`
- [ ] Database migrations applied (via `init()` or release instructions)
- [ ] Module disabled/enabled **only if** release notes require it
- [ ] `GET /custom/dolimcp/mcp.php` returns `"status":"ok"`
- [ ] At least one MCP tool tested with a real user token

## Current installs (0.4.x)

For routine fixes in the **0.4.x** line, **replacing files is enough**; tokens and database data are kept. Disable/enable is only needed when a release note says so (for example when module dependencies change — 0.4.0 adds **Third parties** as a hard dependency).
