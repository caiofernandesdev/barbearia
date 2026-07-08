# Roteiro de Teste — Sistema SaaS Barbearia (v2)

## Credenciais

| Painel | URL | Email | Senha |
|---|---|---|---|
| Super Admin | `/super-admin/login` | `super@saas.com` | `password` |
| Admin Don Alexandre | `/admin/login` | `admin@barbearia.com` | `password` |
| Admin Doce Menina | `/admin/login` | `admin@docemenina.com` | `password` |
| Booking Don Alexandre | `/donalexandre` | — | — |
| Booking Doce Menina | `/docemenina` | — | — |

---

## 1. SUPER ADMIN (`/super-admin`)

### 1.1 Login e acesso simultâneo
- [ ] Logar no super-admin com `super@saas.com` / `password`
- [ ] Em outra aba, logar no admin com `admin@barbearia.com` → ambos funcionam sem conflito
- [ ] Voltar no super-admin → ainda logado

### 1.2 Tipos de Estabelecimento
- [ ] Lista mostra 5+ tipos (Barbearia, Salão, Clínica, Pet Shop, Studio)
- [ ] Criar tipo → aparece na lista
- [ ] Editar tipo → salva
- [ ] Excluir tipo → some

### 1.3 Planos
- [ ] Lista mostra 3 planos (Básico, Pro, Enterprise)
- [ ] Editar Básico → checkboxes corretos (mensalistas + indisponibilidades)
- [ ] Toggle features → salvar → recarregar → mantém

### 1.4 Estabelecimentos
- [ ] Lista mostra os tenants
- [ ] Editar → seção "Usuários do estabelecimento" com tabela nativa Filament
- [ ] Criar novo estabelecimento com admin inicial → user criado corretamente
- [ ] Editar → seção "Usuários" mostra o admin criado
- [ ] WhatsApp config → preencher e salvar
- [ ] Excluir tenant → dados em cascata removidos (users, profissionais, serviços, etc.)

---

## 2. ADMIN — Dashboard

### 2.1 Dashboard Admin (dono)
- [ ] Logar como admin → Dashboard mostra widgets: Faturamento Hoje, Agendamentos, Clientes, Ocupação
- [ ] Resumo do Mês com sparklines e comparativo vs mês anterior
- [ ] Gráfico de linha (faturamento 14 dias)
- [ ] Gráfico rosca (atendimentos por barbeiro)
- [ ] Tabela próximos agendamentos

### 2.2 Dashboard Barbeiro
- [ ] Logar como barbeiro → Dashboard mostra stats: Hoje, Faturamento, Receita do Mês, A Receber
- [ ] NÃO mostra os widgets do admin (gráficos, próximos agendamentos do admin)

---

## 3. ADMIN — CRUD

### 3.1 Profissionais
- [ ] Listar → mostra profissionais do tenant
- [ ] Criar → salvar → aparece na lista
- [ ] Editar → mudar comissão → salvar
- [ ] Excluir

### 3.2 Serviços
- [ ] Listar → mostra serviços do tenant
- [ ] Criar → salvar → aparece
- [ ] Excluir

### 3.3 Usuários
- [ ] Listar → mostra APENAS users do tenant (não de outros)
- [ ] Criar user → salvar → aparece na lista (não dá 404)
- [ ] Excluir

### 3.4 Mensalistas
- [ ] Listar → mostra mensalistas do tenant
- [ ] Criar → salvar → aparece
- [ ] Excluir

### 3.5 Agendamentos
- [ ] Listar com filtros (período, status, profissional, mensalistas)
- [ ] Pedir confirmação individual → modal → envia WhatsApp
- [ ] Selecionar vários → Pedir confirmação em massa → envia pra todos
- [ ] Criar agendamento → salvar → aparece

### 3.6 Indisponibilidades
- [ ] Criar → salvar → aparece
- [ ] Excluir

### 3.7 Configurações
- [ ] Nome da barbearia → salvar → mantém
- [ ] Horários: seletor de hora (type=time) funciona
- [ ] Intervalo: opções de 15min a 2h
- [ ] Mensagem padrão de repescagem → salvar → usada na repescagem
- [ ] Percentual da barbearia → barbeiros calcula automaticamente

---

## 4. ADMIN — Relatórios e Financeiro

### 4.1 Relatórios
- [ ] Stat cards com dados corretos
- [ ] Filtro por período + barbeiro + **status**
- [ ] Stats, tabela barbeiros e tabela agendamentos respeitam o filtro de status
- [ ] Exportar Excel → arquivo correto com filtro aplicado
- [ ] Exportar PDF → arquivo correto

### 4.2 Salário Emocional
- [ ] Stats e tabela carregam
- [ ] Comissão usa `comissao_percentual` individual de cada barbeiro

### 4.3 Repescagem
- [ ] Lista clientes ausentes há mais de X dias
- [ ] Filtro de dias (15/30/45/60/90)
- [ ] Chamar de volta individual → modal de confirmação → envia mensagem padrão
- [ ] Selecionar vários → Chamar de volta → modal com textarea editável → envia pra todos
- [ ] Contagem correta no modal ("Chamar de volta X cliente(s)")

---

## 5. PAINEL DO BARBEIRO — Meu Painel

### 5.1 Agenda Visual
- [ ] Carrossel de dias (próximos 14 dias úteis) → scroll horizontal
- [ ] Clicar num dia → mostra grid 3 colunas com horários
- [ ] Horários verdes = disponíveis, vermelhos = ocupados (com nome + serviço), cinza = passado
- [ ] **Clicar em horário disponível → modal "Agendar"** → nome, telefone, serviço → cria agendamento confirmado
- [ ] Após agendar, slot muda de verde pra vermelho

### 5.2 Próximos Atendimentos
- [ ] Tabela com agendamentos futuros do barbeiro
- [ ] **Filtro de status** (Pendente/Confirmado/Concluído/Cancelado)
- [ ] Botão "Confirmar" individual → envia WhatsApp
- [ ] Selecionar vários → Pedir confirmação em massa

---

## 6. BOOKING PÚBLICO

### 6.1 Fluxo completo
- [ ] Acessar `/{slug}` → página carrega com nome correto do estabelecimento
- [ ] Nome → telefone → profissional → serviço → data → hora → confirmar
- [ ] Profissionais e serviços são do tenant correto
- [ ] Agendamento criado → página de confirmação
- [ ] "Ver meus agendamentos" → mostra o agendamento
- [ ] Cancelar → status muda

### 6.2 Isolamento entre tenants
- [ ] Don Alexandre mostra dados do Don Alexandre
- [ ] Doce Menina mostra dados da Doce Menina
- [ ] `/naoexiste` → 404

---

## 7. WEBHOOK WhatsApp

### 7.1 Resposta do cliente
- [ ] Cliente responde "1" → agendamento confirmado + mensagem de confirmação enviada
- [ ] Cliente responde "2" → agendamento cancelado + mensagem de cancelamento enviada
- [ ] Mensagem do próprio número conectado → ignorada (fromMe)

### 7.2 Instância por tenant
- [ ] Mensagem enviada pela instância do tenant correto (config WhatsApp do super-admin)
- [ ] Sem config no tenant → usa fallback do `.env`

---

## 8. MODULE GATING

### 8.1 Plano controla sidebar
- [ ] Plano Básico: NÃO mostra Relatórios, Salário Emocional, Repescagem
- [ ] Plano Básico: MOSTRA Mensalistas, Indisponibilidades
- [ ] Plano Enterprise: mostra TUDO
- [ ] Acessar `/admin/relatorios` direto sem módulo → 403

### 8.2 Trocar plano
- [ ] Super admin → editar tenant → mudar plano → salvar
- [ ] Relogar no admin → sidebar atualizada

---

## 9. EDGE CASES

- [ ] Excluir tenant → users e dados deletados em cascata
- [ ] Login com user de tenant excluído → logout automático → redirect login
- [ ] Sessões separadas: admin e super-admin funcionam em abas simultâneas
- [ ] Data passada na API de horários → 422
- [ ] Tenant inativo → 404 no booking
