server {
    server_name ghost.voidcyborg.com www.ghost.voidcyborg.com;

    root /var/www/ghost.voidcyborg.com;
    index index.html index.htm;

    location / {
        try_files $uri $uri/ =404;
    }

    listen 443 ssl; # managed by Certbot
    ssl_certificate /etc/letsencrypt/live/ghost.voidcyborg.com/fullchain.pem; # managed by Certbot
    ssl_certificate_key /etc/letsencrypt/live/ghost.voidcyborg.com/privkey.pem; # managed by Certbot
    include /etc/letsencrypt/options-ssl-nginx.conf; # managed by Certbot
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem; # managed by Certbot


}
server {
    if ($host = www.ghost.voidcyborg.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    if ($host = ghost.voidcyborg.com) {
        return 301 https://$host$request_uri;
    } # managed by Certbot


    listen 80;
    server_name ghost.voidcyborg.com www.ghost.voidcyborg.com;

    location /.well-known/acme-challenge/ {
        root /var/www/ghost.voidcyborg.com;
    }

    location / {
        return 301 https://$host$request_uri;
    }




}