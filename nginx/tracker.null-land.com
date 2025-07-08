# HTTP → HTTPS redirect
server {
    listen 80;
    server_name tracker.null-land.com;
    return 301 https://$host$request_uri;
}

# HTTPS with Laravel (via FastCGI in Docker)
server {
    listen 443 ssl;
    http2 on;
    server_name tracker.null-land.com;

    ssl_certificate /etc/letsencrypt/live/null-land.com-0001/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/null-land.com-0001/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    root /var/www/public;  # nginx only, Laravel container serves PHP
    index index.php index.html;

    location / {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:8080;
        fastcgi_param SCRIPT_FILENAME /var/www/public/index.php;
        fastcgi_param PATH_INFO $uri;
        fastcgi_param QUERY_STRING $query_string;
    }

    # Deny access to hidden files (.env и т.п.)
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
