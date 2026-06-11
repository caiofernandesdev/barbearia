# Guia de Instalação e Deploy — Barbearia

## Pré-requisitos

- PHP 8.3+ com extensões: `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `gd`
- Composer 2+
- Node.js 18+ e NPM
- ngrok (para testes com WhatsApp / acesso externo)

---

## 1. Instalação completa (primeira vez)

```bash
# 1. Entrar na pasta do projeto
cd barbearia

# 2. Instalar dependências PHP
composer install

# 3. Instalar dependências JS
npm install

# 4. Criar arquivo de ambiente
cp .env.example .env

# 5. Gerar chave da aplicação
php artisan key:generate

# 6. Criar banco de dados SQLite
# Windows:
type nul > database/database.sqlite
# Linux/Mac:
touch database/database.sqlite

# 7. Rodar migrations
php artisan migrate

# 8. Popular banco com dados de exemplo
php artisan db:seed

# 9. Criar link de storage (para fotos e logo)
php artisan storage:link

# 10. Compilar assets do painel admin
npm run build

# 11. Criar usuário administrador
php artisan make:filament-user
```

---

## 2. Subir o sistema em desenvolvimento

Recomendado: rodar tudo simultaneamente com o comando abaixo.

```bash
composer run dev
```

Isso inicia em paralelo:
- Servidor PHP (`php artisan serve`) em `http://localhost:8000`
- Queue worker (`php artisan queue:listen`)
- Log watcher (`php artisan pail`)
- Vite HMR (`npm run dev`) para os assets do painel

---

## 3. Acessar o sistema

| URL | Descrição |
|-----|-----------|
| `http://localhost:8000` | Chatbot público de agendamento |
| `http://localhost:8000/admin` | Painel administrativo |

**Login padrão do seeder:**
- E-mail: `admin@barbearia.com`
- Senha: `password`

> Altere a senha após o primeiro acesso em **Admin → Perfil**.

---

## 4. Configurar ngrok (testes externos / WhatsApp)

### 4.1 Instalar ngrok

```bash
# Windows (com winget)
winget install ngrok

# Ou baixe em: https://ngrok.com/download
```

### 4.2 Autenticar (só na primeira vez)

```bash
ngrok config add-authtoken SEU_TOKEN_AQUI
# Token disponível em: https://dashboard.ngrok.com
```

### 4.3 Expor o servidor local

```bash
# Com o servidor já rodando na porta 8000:
ngrok http 8000
```

Anote a URL gerada, ex: `https://abc123.ngrok-free.app`

### 4.4 Atualizar o .env com a URL do ngrok

```env
APP_URL=https://abc123.ngrok-free.app
```

Reinicie o servidor após alterar o .env:

```bash
php artisan config:clear
php artisan serve
```

### 4.5 Configurar webhook do Z-API (WhatsApp)

No painel do Z-API (`app.z-api.io`):
1. Acesse sua instância
2. Vá em **Webhooks → Ao receber**
3. Coloque a URL: `https://abc123.ngrok-free.app/webhook/whatsapp`
4. Salve

---

## 5. Variáveis de ambiente (.env) obrigatórias

```env
APP_NAME="Barbearia Studio"
APP_URL=http://localhost:8000

DB_CONNECTION=sqlite

# WhatsApp via Z-API (obrigatório para notificações)
ZAPI_INSTANCE_ID=SUA_INSTANCIA_AQUI
ZAPI_TOKEN=SEU_TOKEN_AQUI
```

---

## 6. Scheduler (lembretes automáticos)

O scheduler precisa estar rodando para:
- Concluir agendamentos passados (a cada hora)
- Enviar lembretes D-1 por WhatsApp (09:00 todo dia)

**Em desenvolvimento** — o `composer run dev` já inclui o worker de filas, mas o scheduler precisa de um processo separado:

```bash
php artisan schedule:work
```

**Em produção** — adicionar ao cron do servidor:

```bash
# Editar crontab:
crontab -e

# Adicionar esta linha:
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

---

## 7. Comandos úteis do dia a dia

```bash
# Rodar migrations pendentes
php artisan migrate

# Reverter última migration
php artisan migrate:rollback

# Resetar banco e re-popular (CUIDADO: apaga tudo)
php artisan migrate:fresh --seed

# Limpar todos os caches
php artisan optimize:clear

# Verificar rotas registradas
php artisan route:list

# Rodar testes
composer run test

# Formatar código (Laravel Pint)
./vendor/bin/pint

# Ver logs em tempo real
php artisan pail

# Disparar lembretes manualmente (para testar WhatsApp)
php artisan agendamentos:lembretes

# Concluir agendamentos passados manualmente
php artisan agendamentos:concluir
```

---

## 8. Checklist antes de abrir para clientes

- [ ] Configurar **nome da barbearia** e **logo** em Admin → Configurações
- [ ] Cadastrar **profissionais** com foto e % de comissão
- [ ] Cadastrar **serviços** com nome, preço e duração
- [ ] Confirmar **dias e horários** de funcionamento em Configurações
- [ ] Testar fluxo completo de agendamento pelo celular
- [ ] Confirmar que o WhatsApp está conectado no Z-API
- [ ] Testar envio de mensagem (fazer um agendamento de teste)
- [ ] Executar `php artisan storage:link` se fotos não aparecerem
