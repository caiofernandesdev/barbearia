# Guia de Deploy — Barbearia

> Checklist completo para subir o sistema em produção num VPS.
> Marque cada item conforme for concluindo.

---

## 1. Servidor — Primeira Vez

### Requisitos mínimos
- VPS com **2 GB RAM** (DigitalOcean $12/mês — São Paulo, ou Hetzner CX22 ~€4/mês)
- Ubuntu 22.04 LTS
- Acesso SSH como root

### Instalar dependências
```bash
# Atualizar sistema
apt update && apt upgrade -y

# PHP 8.3 + extensões
apt install -y software-properties-common
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
    php8.3-xml php8.3-curl php8.3-zip php8.3-intl php8.3-gd php8.3-bcmath

# Nginx
apt install -y nginx

# MySQL 8
apt install -y mysql-server
mysql_secure_installation

# Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Node (para build do Vite/Filament se necessário)
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Supervisor (mantém queue worker rodando)
apt install -y supervisor

# Docker (para Evolution API)
curl -fsSL https://get.docker.com | bash
```

---

## 2. Banco de Dados

```sql
-- No MySQL do servidor
mysql -u root -p

CREATE DATABASE barbearia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'barbearia'@'localhost' IDENTIFIED BY 'SENHA_FORTE_AQUI';
GRANT ALL PRIVILEGES ON barbearia.* TO 'barbearia'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

> ⚠️ Anote: usuário `barbearia`, senha escolhida acima — vai no `.env`.

---

## 3. Código no Servidor

```bash
# Criar pasta
mkdir -p /var/www/barbearia
cd /var/www

# Clonar repositório (GitHub)
git clone https://github.com/caiofernandesdev/barbearia.git barbearia
cd barbearia

# Instalar dependências PHP (sem dev)
composer install --no-dev --optimize-autoloader

# Copiar e editar .env
cp .env.example .env
nano .env        # preencher conforme seção 4

# Gerar chave da aplicação
php artisan key:generate

# Migrations e storage
php artisan migrate --force
php artisan storage:link

# Caches de produção
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissões
chown -R www-data:www-data /var/www/barbearia
chmod -R 775 /var/www/barbearia/storage
chmod -R 775 /var/www/barbearia/bootstrap/cache
```

---

## 4. Arquivo .env de Produção

```env
APP_NAME="Barbearia Don Alexandre"
APP_ENV=production
APP_KEY=                          # gerado pelo artisan key:generate
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_TIMEZONE=America/Sao_Paulo

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR

LOG_CHANNEL=daily
LOG_LEVEL=warning                 # só erros em produção

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=barbearia
DB_USERNAME=barbearia
DB_PASSWORD=SENHA_FORTE_AQUI

SESSION_DRIVER=database
FILESYSTEM_DISK=public
QUEUE_CONNECTION=database
CACHE_STORE=database

# Evolution API — roda localmente no mesmo VPS
EVOLUTION_URL=http://127.0.0.1:8001
EVOLUTION_API_KEY=CHAVE_SECRETA_PRODUCAO
EVOLUTION_INSTANCE=BARBEARIA
```

> ⚠️ Nunca commitar este arquivo. O `.gitignore` já protege o `.env`.

---

## 5. Nginx

```bash
nano /etc/nginx/sites-available/barbearia
```

```nginx
server {
    listen 80;
    server_name seudominio.com.br www.seudominio.com.br;
    root /var/www/barbearia/public;
    index index.php;

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

```bash
ln -s /etc/nginx/sites-available/barbearia /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### SSL (HTTPS) — obrigatório
```bash
apt install -y certbot python3-certbot-nginx
certbot --nginx -d seudominio.com.br -d www.seudominio.com.br
# Certbot renova automaticamente via cron
```

---

## 6. Queue Worker (Supervisor)

```bash
nano /etc/supervisor/conf.d/barbearia-worker.conf
```

```ini
[program:barbearia-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/barbearia/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/barbearia/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start barbearia-worker:*
```

---

## 7. Scheduler (Cron)

```bash
crontab -e -u www-data
```

Adicionar:
```
* * * * * cd /var/www/barbearia && php artisan schedule:run >> /dev/null 2>&1
```

Isso roda:
- `agendamentos:concluir` — a cada hora
- `agendamentos:lembretes` — todo dia às 09h

---

## 8. Evolution API no VPS

```bash
# Copiar os arquivos da Evolution (docker-compose.yml + .env)
mkdir -p /opt/evolution-api
cd /opt/evolution-api

# Criar docker-compose.yml igual ao local, porém com senha de produção
nano docker-compose.yml
nano .env

# Subir
docker compose up -d

# Aguardar inicializar e criar instância
sleep 10
curl -X POST "http://127.0.0.1:8001/instance/create" \
  -H "apikey: CHAVE_SECRETA_PRODUCAO" \
  -H "Content-Type: application/json" \
  -d '{"instanceName": "BARBEARIA", "qrcode": true, "integration": "WHATSAPP-BAILEYS"}'

# Escanear QR em http://seudominio.com.br:8001/manager
# ⚠️ Após escanear, fechar porta 8001 no firewall (só acesso interno)

# Registrar webhook com URL de produção
curl -X POST "http://127.0.0.1:8001/webhook/set/BARBEARIA" \
  -H "apikey: CHAVE_SECRETA_PRODUCAO" \
  -H "Content-Type: application/json" \
  -d '{
    "webhook": {
      "enabled": true,
      "url": "https://seudominio.com.br/webhook/whatsapp",
      "webhook_by_events": false,
      "webhook_base64": false,
      "events": ["MESSAGES_UPSERT"]
    }
  }'
```

---

## 9. Firewall

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
# NÃO abrir porta 8001 externamente (Evolution API só acesso interno)
ufw enable
ufw status
```

---

## 10. Deploy de Atualizações (após o primeiro)

```bash
cd /var/www/barbearia

# Modo manutenção (exibe tela de manutenção pros usuários)
php artisan down

# Puxar código novo
git pull origin main

# Atualizar dependências (se mudou composer.json)
composer install --no-dev --optimize-autoloader

# Rodar migrations novas
php artisan migrate --force

# Atualizar caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Reiniciar workers
supervisorctl restart barbearia-worker:*

# Voltar online
php artisan up
```

---

## Checklist Final Antes de Passar pra Cliente

- [ ] Domínio apontando pro IP do VPS (DNS propagado)
- [ ] HTTPS funcionando (cadeado no navegador)
- [ ] Login no `/admin` funcionando
- [ ] Agendamento público (`/`) funcionando no celular
- [ ] Foto de profissional/serviço aparecendo
- [ ] WhatsApp conectado (QR escaneado no VPS)
- [ ] Mandar mensagem de teste pelo botão "Pedir confirmação"
- [ ] Responder "1" e confirmar que status atualiza
- [ ] Responder "2" e confirmar que status cancela
- [ ] Queue worker rodando (`supervisorctl status`)
- [ ] Cron rodando (`crontab -l -u www-data`)
- [ ] APP_DEBUG=false (nunca expor erros em produção)
- [ ] Senha do banco de dados forte e única
- [ ] Porta 8001 (Evolution API) fechada no firewall

---

## Comandos Úteis no Dia a Dia

```bash
# Ver logs de erro
tail -f /var/www/barbearia/storage/logs/laravel-$(date +%Y-%m-%d).log

# Ver status dos workers
supervisorctl status

# Ver jobs na fila
php artisan queue:monitor

# Forçar envio de lembretes (teste manual)
php artisan agendamentos:lembretes

# Ver status Evolution API
curl http://127.0.0.1:8001/instance/connectionState/BARBEARIA \
  -H "apikey: CHAVE_SECRETA_PRODUCAO"
```
