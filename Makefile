.PHONY: up down logs dolibarr

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f web

dolibarr: up
	@echo "Dolibarr UI:  http://localhost:$${DOLI_HTTP_PORT:-8081}"
	@echo "MCP (HTTP):   http://localhost:$${DOLI_HTTP_PORT:-8081}/custom/dolimcp/mcp.php"
