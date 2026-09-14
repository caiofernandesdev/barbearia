<?php

// Textos da página pública de agendamento (chatbot). Fonte: português.
return [
    'title' => 'Agendar Horário',
    'online' => 'Online',
    'greeting' => 'Olá! 👋 Seja bem-vindo à <strong>:name</strong>!<br>Vamos agendar seu horário. Qual é o seu <strong>nome</strong>?',

    // Modal: agendamento ativo
    'active_title' => 'Agendamento Ativo',
    'active_text' => 'Você já tem um agendamento marcado:',
    'active_hint' => 'Cancele-o antes de fazer um novo agendamento.',
    'see_my_bookings' => 'Ver meus agendamentos',
    'close' => 'Fechar',

    // Modal: mensalista fixo
    'fixed_title' => 'Seus horários fixos',
    'fixed_text' => 'Próximas sessões já agendadas para você:',
    'book_another' => 'Agendar outro horário',

    // Botões / rótulos gerais
    'back' => '← Voltar',
    'send' => 'Enviar',
    'continue' => 'Continuar ➜',
    'select' => 'Selecione...',
    'yes' => 'Sim',
    'no' => 'Não',
    'name_placeholder' => 'Seu nome e sobrenome',
    'today' => 'HOJE',

    // Serviços
    'tap_services' => 'Toque nos serviços para selecionar',
    'services_summary' => ':count serviço(s) — :price · ⏱ :min min',
    'pick_one_service' => '⚠️ Escolha pelo menos um serviço para continuar.',

    // Data e hora
    'pick_day_time' => 'Selecione um dia e horário',
    'pick_day_time_caps' => 'SELECIONE O DIA E HORÁRIO:',
    'swipe_more' => '→ ARRASTE PARA O LADO PARA VER MAIS',
    'loading_times' => 'Carregando horários...',
    'no_times' => 'Nenhum horário disponível nesse dia.',
    'load_error' => 'Erro ao carregar. Tente novamente.',

    // Confirmação
    'confirm_booking' => '✅ Confirmar Agendamento',
    'almost_there' => 'Quase lá! 🎉 Confirme os detalhes do seu agendamento:',
    'extra_fields_intro' => '📋 Precisamos de mais algumas informações:',
    'field_required' => '⚠️ O campo ":field" é obrigatório.',

    // Fluxo — mensagens do bot
    'ask_phone' => 'Ótimo, <strong>:name</strong>! 😊<br>Agora me diga seu <strong>telefone</strong> (com DDD):',
    'phone_placeholder' => '(00) 00000-0000',
    'invalid_phone' => '⚠️ Telefone inválido. Informe com DDD, ex: (11) 99999-9999',
    'limit_reached' => '⚠️ Você já utilizou <strong>:used</strong> de <strong>:limit</strong> corte(s) desta semana.<br>Tente novamente na próxima semana.',
    'connection_error' => '⚠️ Erro de conexão. Verifique sua internet e tente novamente.',
    'book_walkin' => 'Ok! Vamos agendar um horário avulso para você. ✂️',
    'ask_professional' => 'Perfeito! ✨ Com qual <strong>profissional</strong> você prefere ser atendido?',
    'load_professionals_error' => '⚠️ Erro ao carregar profissionais. Recarregue a página.',
    'ask_services' => 'Ótima escolha! ✂️ Quais <strong>serviços</strong> você deseja? Pode marcar mais de um!',
    'load_services_error' => '⚠️ Erro ao carregar serviços. Tente novamente.',
    'pick_day_time_bot' => 'Combinado! 📅 Escolha o <strong>dia e horário</strong> do seu atendimento:',
    'pick_day_first' => '⚠️ Escolha um dia antes de continuar.',
    'pick_time_first' => '⚠️ Escolha um horário antes de continuar.',
    'service_fallback' => 'Serviço',
    'professional_fallback' => 'Profissional',
    'with' => 'com',

    // Lista de espera
    'waitlist_enter_day' => '✋ Entrar na lista de espera desse dia',
    'waitlist_pick_time' => 'Escolha o horário que você gostaria para <strong>:date</strong>. Se abrir vaga, entramos em contato.',
    'waitlist_no_times' => 'Sem horários configurados.',
    'waitlist_enter' => 'Entrar na lista de espera',
    'waitlist_pick_hint' => '⚠️ Escolha o horário que você gostaria.',
    'waitlist_times_error' => '⚠️ Erro ao carregar horários. Tente novamente.',
    'waitlist_done' => '✅ Pronto, :name! Você entrou na lista de espera de <strong>:date</strong> às <strong>:time</strong>. Se abrir vaga, entraremos em contato. 😊',
    'waitlist_error' => '⚠️ Não foi possível entrar na lista. Tente novamente.',

    // Nomes de dias/meses para as listas do calendário
    'days_short' => ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'],
    'days_short_caps' => ['DOM', 'SEG', 'TER', 'QUA', 'QUI', 'SEX', 'SÁB'],
    'months_full' => ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'],
    'months_short_caps' => ['JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'],
    'date_connector' => 'de', // "12 de março de 2026"

    // Página "Meus Agendamentos"
    'my_bookings_title' => 'Meus Agendamentos',
    'with_label' => 'com',
    'at_time' => 'às',
    'status_confirmed' => 'Confirmado',
    'status_pending' => 'Aguardando',
    'cancel_confirm' => 'Cancelar este agendamento?',
    'cancel_booking' => 'Cancelar agendamento',
    'no_bookings' => 'Nenhum agendamento ativo no momento.',
    'make_booking' => 'Fazer agendamento',

    // Flash messages (controller)
    'flash_created' => 'Agendamento realizado com sucesso! 🎉',
    'flash_cancelled' => 'Seu agendamento foi cancelado.',

    // Página de confirmação
    'confirmed_title' => 'Agendamento Confirmado',
    'confirmed_heading' => 'Agendado!',
    'confirmed_subtitle' => 'Seu horário foi reservado com sucesso.',
    'label_client' => 'Cliente',
    'label_service' => 'Serviço',
    'label_professional' => 'Profissional',
    'label_datetime' => 'Data e hora',
    'label_price' => 'Valor',
    'label_duration' => 'Duração estimada',
    'minutes' => 'minutos',
    'back_home' => 'Voltar ao início',
];
