# Despliegue en producción

Guía para llevar un proyecto JB Framework a ambiente de producción con seguridad endurecida.

---

## Checklist de seguridad pre-despliegue

### Configuración

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `JWT_SECRET` configurado con valor fuerte (≥32 caracteres, random)
  ```bash
  php -r "echo bin2hex(random_bytes(32));"
  ```
- [ ] `CORS_ALLOWED_ORIGINS` especificado (NO usar `*`)
  ```ini
  CORS_ALLOWED_ORIGINS=https://midominio.com,https://www.midominio.com
  ```
- [ ] Todas las variables de BD completadas
- [ ] `MAIL_FROM_ADDRESS` con dominio propio
- [ ] `RATE_LIMIT_MAX` y `RATE_LIMIT_WINDOW` configurados según tráfico

### Base de datos

- [ ] Usuario de BD con permisos limitados (solo SELECT, INSERT, UPDATE, DELETE; no DROP, ALTER)
- [ ] Contraseña fuerte
- [ ] BD no accesible desde internet (solo desde VPC/red interna)
- [ ] Backups automáticos configurados (diarios mínimo)
- [ ] SSL/TLS habilitado entre app y BD (si está remota)
- [ ] Character set = `utf8mb4` en MySQL

### Servidor web

#### Apache

```apache
<VirtualHost *:443>
    ServerName api.midominio.com
    SSLEngine on
    SSLCertificateFile /ruta/a/cert.pem
    SSLCertificateKeyFile /ruta/a/key.pem
    SSLProtocol TLSv1.2 TLSv1.3
    SSLCipherSuite HIGH:!aNULL:!MD5

    DocumentRoot /ruta/a/proyecto/public
    
    <Directory /ruta/a/proyecto/public>
        Require all granted
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>

    # Denegar acceso a archivos sensibles
    <FilesMatch "^\.(env|env\.example|git|md|json)$|^composer\.(json|lock)$|^\.ht">
        Require all denied
    </FilesMatch>

    # Logs
    ErrorLog /var/log/apache2/mi_api_error.log
    CustomLog /var/log/apache2/mi_api_access.log combined
</VirtualHost>

# Redirigir HTTP → HTTPS
<VirtualHost *:80>
    ServerName api.midominio.com
    Redirect permanent / https://api.midominio.com/
</VirtualHost>
```

Habilitar módulos:
```bash
sudo a2enmod rewrite
sudo a2enmod ssl
sudo systemctl restart apache2
```

#### Nginx

```nginx
upstream php_backend {
    server 127.0.0.1:9000;
}

server {
    listen 80;
    server_name api.midominio.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name api.midominio.com;

    ssl_certificate /ruta/a/cert.pem;
    ssl_certificate_key /ruta/a/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    root /ruta/a/proyecto/public;

    # Denegar acceso a archivos sensibles
    location ~ /^\.(env|git|md|json)$|^composer\.(json|lock)$ {
        deny all;
    }

    # PHP
    location ~ \.php$ {
        fastcgi_pass php_backend;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Reescritura a index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Logs
    access_log /var/log/nginx/mi_api_access.log combined;
    error_log /var/log/nginx/mi_api_error.log;
}
```

### SSL/TLS

- [ ] Certificado SSL válido (Let's Encrypt, DigiCert, etc.)
- [ ] Renovación automática configurada (para Let's Encrypt: certbot renew)
- [ ] HSTS habilitado en respuestas HTTP (el framework lo hace automáticamente)
- [ ] Verificar con [SSL Labs](https://www.ssllabs.com/ssltest/)

### Permisos de archivos

```bash
# Proyecto es de solo lectura excepto storage/
chmod 755 /ruta/a/proyecto
chmod 755 /ruta/a/proyecto/public
chmod 755 /ruta/a/proyecto/app
chmod 755 /ruta/a/proyecto/config

# Storage necesita escritura
chmod 775 /ruta/a/proyecto/storage
chmod 775 /ruta/a/proyecto/storage/logs
chmod 775 /ruta/a/proyecto/storage/cache

# Usuario web
chown -R www-data:www-data /ruta/a/proyecto/storage

# .env debe ser secreto
chmod 600 /ruta/a/proyecto/.env
chown www-data:www-data /ruta/a/proyecto/.env
```

### Sistema operativo

- [ ] SO actualizado con últimos patches de seguridad
- [ ] Firewall habilitado, solo puertos 80/443 abiertos al público
- [ ] SSH en puerto no estándar, con key-based auth (no password)
- [ ] Fail2ban o similar para proteger contra fuerza bruta
- [ ] SELinux o AppArmor habilitado
- [ ] Monitoreo y alertas configuradas

### Logs

- [ ] Logs dirigidos a archivo en `storage/logs/`
- [ ] Rotación de logs configurada (logrotate)
- [ ] Logs no accesibles públicamente
- [ ] Monitoreo de logs para errores críticos

### Monitoreo y alertas

- [ ] Uptime monitoring (Pingdom, UptimeRobot, etc.)
- [ ] Alertas por errores 5xx
- [ ] Alertas por exceso de tráfico
- [ ] Alertas por uso de disco
- [ ] Alertas por rate-limit activado

### Headers de respuesta esperados

Verificar con `curl -i`:

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...
Access-Control-Allow-Origin: https://midominio.com  (no wildcard)
```

---

## Despliegue paso a paso

### 1. Clonar y preparar

```bash
cd /var/www
git clone https://github.com/tuusuario/tu_api.git mi_api
cd mi_api
composer install --no-dev --optimize-autoloader
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
nano .env
# Completar todas las variables para producción
```

### 3. Verificar seguridad

El framework valida automáticamente en bootstrap:

```bash
php -r "require 'vendor/autoload.php'; require 'public/index.php';" 2>&1
# Si hay errores de JWT_SECRET o CORS, fallará claramente
```

### 4. Migraciones y seeders

```bash
php jb migrate
php jb seed  # si hay seeders de datos iniciales
```

### 5. Configurar servidor web

Ver secciones Apache/Nginx arriba.

### 6. SSL con Let's Encrypt (Nginx)

```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot certonly --nginx -d api.midominio.com
```

### 7. Configurar monitoreo

Ejemplo con Systemd service para PHP-FPM:

```ini
[Unit]
Description=PHP-FPM para mi_api
After=network.target

[Service]
Type=notify
Listen=127.0.0.1:9000
User=www-data
Group=www-data
ExecStart=/usr/sbin/php-fpm8.2 --nodaemonize --fpm-config /etc/php/8.2/fpm/php-fpm.conf
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### 8. Backups automáticos

```bash
# Backup diario de base de datos
0 2 * * * mysqldump -u usuario -p'contraseña' base_datos | gzip > /backups/bd_$(date +\%Y\%m\%d).sql.gz
```

---

## Verificación final

```bash
# Prueba de endpoint simple
curl -k https://api.midominio.com/api/health

# Verificar headers de seguridad
curl -i https://api.midominio.com/api/health | grep -E "Strict-Transport-Security|X-Frame-Options|X-Content-Type-Options"

# Test de CORS (debe ser específico, no wildcard)
curl -i -H "Origin: https://otro-dominio.com" https://api.midominio.com/api/health
```

---

## Troubleshooting

### "JWT_SECRET debe configurarse con un valor seguro"

```bash
# Generar JWT_SECRET fuerte
php -r "echo bin2hex(random_bytes(32));"

# Poner en .env
JWT_SECRET=<el_valor_generado>
```

### "CORS con wildcard detectado en producción"

Especificar orígenes permitidos:

```ini
CORS_ALLOWED_ORIGINS=https://midominio.com,https://www.midominio.com
```

### Logs permiso denegado

```bash
chmod 775 storage/logs
chown www-data:www-data storage/logs
```

### PHP-FPM no encuentra extensión

```bash
php -i | grep -E "pdo|json|mbstring"
# Si falta, instalar: apt-get install php8.2-json php8.2-mbstring
```

---

## Referencias

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [OWASP Cheat Sheet: PHP](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [Let's Encrypt Best Practices](https://letsencrypt.org/docs/faq/)
- [Mozilla SSL Generator](https://ssl-config.mozilla.org/)
