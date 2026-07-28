# DoliMCP — Dolibarr custom module

MCP **Streamable HTTP** server embedded in Dolibarr (v0.4.2).

## Endpoint

`/custom/dolimcp/mcp.php`

## Install

1. Copy this folder to `htdocs/custom/dolimcp` (or use the repo Docker volume mount).
2. Enable module: **Home → Setup → Modules → DoliMCP Bridge**.
3. Ensure **REST API**, **Projects**, **Users**, and **Third parties** are enabled.
4. For finance tools: enable **Invoices**, **Suppliers**, and **Banks**.
5. For service tools: enable **Services**.

## Tool groups

| Group | Examples |
|-------|----------|
| Projects / tasks / time | `dolibarr_create_project`, `dolibarr_add_task_timespent`, … |
| Users | `dolibarr_list_employees`, `dolibarr_get_current_user`, … |
| Third parties | `dolibarr_create_thirdparty`, `dolibarr_list_customers`, `dolibarr_list_suppliers`, … |
| Contacts | `dolibarr_create_contact`, `dolibarr_list_contacts`, … |
| Customer invoices | `dolibarr_create_invoice`, `dolibarr_add_invoice_line`, `dolibarr_add_invoice_payment`, … |
| Supplier invoices | `dolibarr_create_supplier_invoice`, `dolibarr_validate_supplier_invoice`, … |
| Services | `dolibarr_list_services`, `dolibarr_create_service`, `dolibarr_update_service`, … |

## Cursor configuration

```json
{
  "mcpServers": {
    "dolibarr-streamable-http": {
      "url": "https://YOUR-DOLIBARR/custom/dolimcp/mcp.php",
      "headers": {
        "DOLIMCPKEY": "${env:DOLIBARR_MCP_TOKEN}"
      }
    }
  }
}
```

## Extend tools

Edit `class/mcptoolregistry.class.php` (schemas + REST routes) and `class/mcpapibridge.class.php` (native dispatch). Test via Dolibarr REST explorer.
