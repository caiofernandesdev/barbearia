<?php

namespace App\Imports;

use App\Models\Mensalista;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;

class MensalistasImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnError
{
    use SkipsErrors;

    public function model(array $row): ?Mensalista
    {
        if (empty($row['nome']) || empty($row['telefone'])) {
            return null;
        }

        return Mensalista::firstOrCreate(
            ['telefone' => preg_replace('/\D/', '', $row['telefone'])],
            [
                'nome'                 => $row['nome'],
                'tipo'                 => in_array($row['tipo'] ?? '', ['fixo', 'avulso']) ? $row['tipo'] : 'fixo',
                'limite_cortes_semana' => (int) ($row['limite_cortes_semana'] ?? 1),
                'valor_mensalidade'    => (float) str_replace(',', '.', $row['valor_mensalidade'] ?? 0),
            ]
        );
    }

    public function rules(): array
    {
        return [
            'nome'     => 'required|string',
            'telefone' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nome.required'     => 'Coluna "nome" é obrigatória.',
            'telefone.required' => 'Coluna "telefone" é obrigatória.',
        ];
    }
}
