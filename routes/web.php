<?php

use App\Http\Controllers\Admin\RelatorioExportController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AgendamentoController::class, 'index'])->name('agendamento.index');
Route::post('/agendar', [AgendamentoController::class, 'store'])->name('agendamento.store');
Route::get('/confirmado/{id}', [AgendamentoController::class, 'confirmado'])->name('agendamento.confirmado');

// GET+POST: GET vem do redirect pós-cancelamento (lê sessão), POST vem dos forms (lê body)
Route::match(['get', 'post'], '/meus-agendamentos', [AgendamentoController::class, 'meusAgendamentos'])
    ->name('agendamento.meus-agendamentos')
    ->middleware('throttle:30,1');

Route::post('/cancelar/{id}', [AgendamentoController::class, 'cancelar'])->name('agendamento.cancelar');

// Fotos servidas diretamente pelo web server via symlink public/storage → storage/app/public
// (rota PHP removida — sem overhead desnecessário de passar pelo Laravel)

// Webhook Z-API — recebe respostas do cliente (1=confirmar, 2=cancelar)
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhook.whatsapp');

Route::get('/api/profissionais', [AgendamentoController::class, 'profissionais'])
    ->name('api.profissionais')->middleware('throttle:60,1');
Route::get('/api/servicos', [AgendamentoController::class, 'servicos'])
    ->name('api.servicos')->middleware('throttle:60,1');
Route::post('/api/verificar-telefone', [AgendamentoController::class, 'verificarTelefone'])
    ->name('api.verificar-telefone')->middleware('throttle:20,1');
Route::get('/api/horarios-disponiveis', [AgendamentoController::class, 'horariosDisponiveis'])
    ->name('api.horarios-disponiveis')->middleware('throttle:60,1');

// Exportações do painel admin (protegidas por autenticação)
Route::middleware('auth')->prefix('admin-exports')->group(function () {
    Route::get('/relatorio-excel', [RelatorioExportController::class, 'excel'])->name('admin.relatorio.excel');
    Route::get('/relatorio-pdf', [RelatorioExportController::class, 'pdf'])->name('admin.relatorio.pdf');
});
