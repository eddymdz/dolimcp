# DoliMCP — Dolibarr custom module

MCP **Streamable HTTP** server embedded in Dolibarr.

## Endpoint

`/custom/dolimcp/mcp.php`

## Install

1. Copy this folder to `htdocs/custom/dolimcp` (or use the repo Docker volume mount).
2. Enable module: **Home → Setup → Modules → DoliMCP Bridge**.
3. Ensure **REST API**, **Projects**, and **Users** modules are enabled.

## Cursor configuration

```json
{
  "mcpServers": {
    "dolibarr-streamable-http": {
      "url": "https://YOUR-DOLIBARR/custom/dolimcp/mcp.php",
      "headers": {
        "DOLAPIKEY": "${env:DOLIBARR_API_KEY}"
      }
    }
  }
}
```

## Extend tools

Edit `class/mcptoolregistry.class.php` (schemas + REST routes) and test via Dolibarr REST explorer.
