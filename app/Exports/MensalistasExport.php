<?php

namespace App\Exports;

use App\Models\Mensalista;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MensalistasExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function collection()
    {
        return Mensalista::orderBy('nome')->get();
    }

    public function headings(): array
    {
        return ['nome', 'telefone', 'tipo', 'limite_cortes_semana', 'valor_mensalidade'];
    }

    public function map($mensalista): array
    {
        return [
            $mensalista->nome,
            $mensalista->telefone,
            $mensalista->tipo,
            $mensalista->limite_cortes_semana,
            number_format($mensalista->valor_mensalidade, 2, ',', '.'),
        ];
    }
}
