# Docker run modes

This image can run in two modes:

1) Local dev with docker-compose (Nginx + PHP-FPM as separate services)
- HTTP available at http://localhost:8080

2) Single-container mode (for PaaS with dynamic $PORT)
- The image includes Nginx and will bind to $PORT.

## Local dev

- docker compose build
- docker compose up -d
- Open http://localhost:8080

## Single container (PaaS)

The container can serve HTTP itself:

- Environment variables:
  - ENABLE_NGINX=1 (default) – start Nginx + php-fpm
  - PORT – port to listen on (default 8080)

Example:

- docker run -e ENABLE_NGINX=1 -e PORT=8080 -p 8080:8080 your-image

## Notes
- Nginx template: conf/nginx/nginx-site.template (listens on ${PORT})
- Entry point: docker/entrypoint.sh
