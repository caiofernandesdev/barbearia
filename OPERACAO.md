# Guia de Operação — Atendix

Como desenvolver, testar e subir alterações para produção (atendix.cc).

> Regra de ouro: **NUNCA edite código direto no VPS.** Tudo nasce na sua máquina,
> passa pelos testes, vai pro GitHub e só então entra em produção via `git pull`.

---

## O ciclo completo (resumo)

```
1. Desenvolver na sua máquina  →  composer run dev
2. Testar                      →  php artisan test
3. Commitar e subir pro GitHub →  git add -A && git commit && git push
4. Deployar no VPS             →  ssh + roteiro de deploy (abaixo)
```

---

## 1. Desenvolvendo algo novo (na sua máquina)

```bash
# Rodar o ambiente local (servidor + fila + logs + vite)
composer run dev
```

- Sistema local: http://127.0.0.1:8000
- Faça a alteração, teste manualmente no navegador
- **Rode a suíte antes de commitar:**

```bash
php artisan test
```

Se algum teste quebrar, conserte antes de subir. Os testes são sua rede de
proteção — é o que impede de derrubar os clientes com uma mudança.

## 2. Commitando e subindo pro GitHub

```bash
git add -A
git commit -m "feat: descricao curta do que mudou"
git push origin main
```

Prefixos úteis pra mensagem: `feat:` (funcionalidade nova), `fix:` (correção),
`docs:` (documentação), `chore:` (manutenção).

## 3. Deploy no VPS (roteiro manual)

```bash
ssh root@179.197.66.135
cd /var/www/barbearia

php artisan down                                  # tela de manutenção
git pull origin main                              # puxa o código novo

composer install --no-dev --optimize-autoloader   # SÓ se mudou composer.json
npm ci && npm run build                           # SÓ se mudou front/package.json

php artisan migrate --force                       # SÓ se criou migration

# Sempre (recompila os caches):
php artisan config:cache && php artisan route:cache && php artisan view:cache \
  && php artisan event:cache && php artisan filament:cache-components
chown -R www-data:www-data storage bootstrap/cache   # artisan como root deixa arquivos do root → 500
supervisorctl restart barbearia-worker:*          # workers pegam código novo
systemctl reload php8.3-fpm                       # limpa OPcache

php artisan up                                    # volta ao ar
```

### O que rodar dependendo do que mudou

| Mudança | git pull | composer | npm build | migrate | caches+workers+fpm |
|---|:-:|:-:|:-:|:-:|:-:|
| Só código PHP / Blade | ✅ | — | — | — | ✅ |
| Nova migration | ✅ | — | — | ✅ | ✅ |
| Nova dependência PHP | ✅ | ✅ | — | — | ✅ |
| Mudou CSS/JS do Filament | ✅ | — | ✅ | — | ✅ |
| Mudou o `.env` do servidor | — | — | — | — | ✅ (só caches+fpm) |

Na dúvida: rode tudo. Não quebra nada, só demora ~1 min a mais.

---

## 4. Adicionando um cliente novo (tenant)

1. **Criar a instância WhatsApp** no VPS:
   ```bash
   cd /opt/evolution-api
   curl -X POST "http://127.0.0.1:8001/instance/create" \
     -H "apikey: $(grep AUTHENTICATION_API_KEY .env | cut -d= -f2)" \
     -H "Content-Type: application/json" \
     -d '{"instanceName": "NOME_DO_CLIENTE", "qrcode": true, "integration": "WHATSAPP-BAILEYS"}'
   ```
2. **Escanear o QR** com o WhatsApp do cliente (túnel SSH + manager):
   ```powershell
   # na sua máquina:
   ssh -L 8001:127.0.0.1:8001 root@179.197.66.135
   # navegador: http://localhost:8001/manager
   ```
3. **Registrar o webhook** da instância (token do .env do Laravel!):
   ```bash
   curl -X POST "http://127.0.0.1:8001/webhook/set/NOME_DO_CLIENTE" \
     -H "apikey: $(grep AUTHENTICATION_API_KEY .env | cut -d= -f2)" \
     -H "Content-Type: application/json" \
     -d '{"webhook": {"enabled": true, "url": "https://atendix.cc/webhook/whatsapp/SEU_TOKEN", "webhook_by_events": false, "webhook_base64": false, "events": ["MESSAGES_UPSERT"]}}'
   ```
4. **Criar o tenant** em `https://atendix.cc/super-admin`:
   - slug (vira a URL do cliente: `atendix.cc/{slug}`), nome, tipo, plano
   - WhatsApp: `base_url = http://127.0.0.1:8001`, `api_key` = chave da Evolution,
     `instance = NOME_DO_CLIENTE`
5. **Criar o usuário admin** do cliente (aba usuários do tenant) e entregar a URL
   `atendix.cc/{slug}` + login do painel `/admin`

---

## 5. Dia a dia — monitoramento

```bash
# Logs do Laravel (erros da aplicação)
tail -f /var/www/barbearia/storage/logs/laravel-$(date +%Y-%m-%d).log

# Workers da fila rodando? (deve mostrar 2x RUNNING)
supervisorctl status

# Evolution API de pé?
docker compose -f /opt/evolution-api/docker-compose.yml ps

# WhatsApp da instância conectado?
curl http://127.0.0.1:8001/instance/connectionState/BARBEARIA \
  -H "apikey: $(grep AUTHENTICATION_API_KEY /opt/evolution-api/.env | cut -d= -f2)"

# Jobs travados na fila?
cd /var/www/barbearia && php artisan queue:failed

# Espaço em disco / memória
df -h / && free -h
```

### Sintomas comuns

| Sintoma | Causa provável | Solução |
|---|---|---|
| WhatsApp não envia | Instância desconectada | Reescanear QR no manager |
| Confirmação "1" não atualiza status | Worker parado | `supervisorctl restart barbearia-worker:*` |
| Site fora do ar após deploy | Esqueceu `php artisan up` | `php artisan up` |
| Mudança não aparece | OPcache/cache velho | caches + `systemctl reload php8.3-fpm` |
| Erro 500 genérico | Ver o log do Laravel | `tail -100` no log do dia |

---

## 6. Backups

- **Banco**: cron diário às 3h30 já configurado → `/var/backups/barbearia/`
- **Testar restauração de vez em quando:**
  ```bash
  zcat /var/backups/barbearia/db-2026-XX-XX.sql.gz | head -20   # backup íntegro?
  ```
- **Fotos** (uploads): estão em `/var/www/barbearia/storage/app/public` — inclua no
  backup se os clientes subirem muitas fotos

## 7. O que NUNCA fazer em produção

- ❌ Editar arquivo PHP direto no VPS (o próximo `git pull` sobrescreve tudo)
- ❌ `php artisan migrate:fresh` (APAGA o banco inteiro!)
- ❌ `php artisan db:seed` de novo (duplica/reseta dados de demo)
- ❌ Ligar `APP_DEBUG=true` (vaza informação sensível nos erros)
- ❌ `docker compose down -v` na Evolution (o `-v` apaga as sessões do WhatsApp —
  todos os clientes teriam que reescanear o QR)
- ❌ Commitar o `.env` (o .gitignore protege, não force)
