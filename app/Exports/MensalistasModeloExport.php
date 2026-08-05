<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Planilha-modelo para importação de mensalistas: cabeçalho na ordem esperada
 * + uma linha de exemplo para o usuário substituir. Serve como template mesmo
 * quando ainda não há nenhum cliente cadastrado.
 */
class MensalistasModeloExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function headings(): array
    {
        return ['nome', 'telefone', 'tipo', 'limite_cortes_semana', 'valor_mensalidade'];
    }

    public function array(): array
    {
        return [
            ['João da Silva', '11987654321', 'avulso', 1, '99,90'],
        ];
    }
}
