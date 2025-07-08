# HTTP → HTTPS
server {
    listen 80;
    server_name tracker.null-land.com;
    return 301 https://$host$request_uri;
}

# HTTPS + Laravel
server {
    listen 443 ssl;
    http2 on;
    server_name tracker.null-land.com;

    ssl_certificate     /etc/letsencrypt/live/null-land.com-0001/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/null-land.com-0001/privkey.pem;
    include             /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam         /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        proxy_pass       http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}