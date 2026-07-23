<?php

use App\Http\Controllers\Admin\PushSubscriptionController;
use App\Http\Controllers\Admin\RelatorioExportController;
use App\Http\Controllers\AgendamentoController;
use App\Http\Controllers\SuperAdmin\ComprovanteController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Models\Plano;
use Illuminate\Support\Facades\Route;

// ─── Landing page ─────────────────────────────────────────────────────────────
// Nome e preço vêm dos planos do super admin — fonte única de verdade.
// A ligação é pelo slug (starter/pro/enterprise), que não muda quando o plano
// é renomeado; plano desativado some do site em vez de exibir preço fantasma.
Route::get('/', function () {
    $planos = Plano::where('ativo', true)->get()->keyBy('slug');

    return view('pages.landing', compact('planos'));
})->name('landing');

// ─── Webhook global — sem session, sem CSRF, sem cookie (Evolution API) ──────
// O {token} é o segredo compartilhado (WHATSAPP_WEBHOOK_TOKEN) validado no controller
Route::post('/webhook/whatsapp/{token?}', [WhatsAppWebhookController::class, 'handle'])
    ->name('webhook.whatsapp')
    ->withoutMiddleware(['web'])
    ->middleware(['throttle:600,1']);

// ─── Rotas por tenant ─────────────────────────────────────────────────────────
Route::prefix('{tenant}')
    ->middleware('tenant')
    ->group(function () {

        // Agendamento público
        Route::get('/', [AgendamentoController::class, 'index'])->name('agendamento.index');
        Route::post('/agendar', [AgendamentoController::class, 'store'])->name('agendamento.store');
        Route::get('/confirmado/{agendamentoId}', [AgendamentoController::class, 'confirmado'])->name('agendamento.confirmado');

        Route::match(['get', 'post'], '/meus-agendamentos', [AgendamentoController::class, 'meusAgendamentos'])
            ->name('agendamento.meus-agendamentos')
            ->middleware('throttle:30,1');

        Route::post('/cancelar/{agendamentoId}', [AgendamentoController::class, 'cancelar'])
            ->name('agendamento.cancelar');

        // Lista de espera — cliente entra quando o dia não tem horário
        Route::post('/lista-espera', [AgendamentoController::class, 'entrarListaEspera'])
            ->name('agendamento.lista-espera')->middleware('throttle:20,1');

        // APIs públicas
        Route::get('/api/profissionais', [AgendamentoController::class, 'profissionais'])
            ->name('api.profissionais')->middleware('throttle:60,1');
        Route::get('/api/servicos', [AgendamentoController::class, 'servicos'])
            ->name('api.servicos')->middleware('throttle:60,1');
        Route::post('/api/verificar-telefone', [AgendamentoController::class, 'verificarTelefone'])
            ->name('api.verificar-telefone')->middleware('throttle:20,1');
        Route::get('/api/horarios-disponiveis', [AgendamentoController::class, 'horariosDisponiveis'])
            ->name('api.horarios-disponiveis')->middleware('throttle:60,1');
        Route::get('/api/grade-horarios', [AgendamentoController::class, 'gradeHorarios'])
            ->name('api.grade-horarios')->middleware('throttle:60,1');
        Route::get('/api/campos-extras', [AgendamentoController::class, 'camposExtras'])
            ->name('api.campos-extras')->middleware('throttle:60,1');

    });

// ─── Exportações admin (fora do grupo tenant — usa guard admin) ──────────────
Route::middleware('auth:admin')->prefix('admin-exports')->group(function () {
    Route::get('/relatorio-excel', [RelatorioExportController::class, 'excel'])->name('admin.relatorio.excel');
    Route::get('/relatorio-pdf', [RelatorioExportController::class, 'pdf'])->name('admin.relatorio.pdf');
});

// ─── Push do painel — registro dos aparelhos do usuário logado ───────────────
Route::middleware('auth:admin')->prefix('admin-push')->group(function () {
    Route::post('/inscrever', [PushSubscriptionController::class, 'store'])->name('admin.push.inscrever');
    Route::post('/desinscrever', [PushSubscriptionController::class, 'destroy'])->name('admin.push.desinscrever');
    Route::post('/teste', [PushSubscriptionController::class, 'teste'])
        ->name('admin.push.teste')->middleware('throttle:10,1');
});

// ─── Comprovantes de pagamento (disco privado, só super admin) ───────────────
Route::middleware('auth:super_admin')->get('/super-admin-files/comprovante/{pagamento}', ComprovanteController::class)
    ->name('superadmin.comprovante');
