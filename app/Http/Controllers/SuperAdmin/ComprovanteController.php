<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pagamento;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download de comprovante de pagamento. Fica fora do disco público: só o
 * super admin autenticado acessa, e o arquivo é servido pelo PHP.
 */
class ComprovanteController extends Controller
{
    public function __invoke(Pagamento $pagamento): StreamedResponse
    {
        abort_unless($pagamento->comprovante, 404);
        abort_unless(Storage::disk('local')->exists($pagamento->comprovante), 404);

        return Storage::disk('local')->download($pagamento->comprovante);
    }
}
