<?php

namespace App\Imports;

use App\Models\Mensalista;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class MensalistasImport implements SkipsOnError, SkipsOnFailure, ToModel, WithHeadingRow, WithValidation
{
    use SkipsErrors, SkipsFailures;

    /** Linhas que viraram cliente novo. */
    public int $importados = 0;

    /** Linhas puladas por já existir (mesmo telefone). */
    public int $duplicados = 0;

    public function model(array $row): ?Mensalista
    {
        if (empty($row['nome']) || empty($row['telefone'])) {
            return null;
        }

        $mensalista = Mensalista::firstOrCreate(
            ['telefone' => preg_replace('/\D/', '', $row['telefone'])],
            [
                'nome' => $row['nome'],
                'tipo' => $this->normalizarTipo($row['tipo'] ?? null),
                'limite_cortes_semana' => (int) ($row['limite_cortes_semana'] ?? 1),
                'valor_mensalidade' => (float) str_replace(',', '.', $row['valor_mensalidade'] ?? 0),
            ]
        );

        $mensalista->wasRecentlyCreated ? $this->importados++ : $this->duplicados++;

        // firstOrCreate já persistiu o registro novo; retornar null evita
        // que o Excel salve de novo (update redundante) o mesmo modelo.
        return null;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string',
            'telefone' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nome.required' => 'Coluna "nome" é obrigatória.',
            'telefone.required' => 'Coluna "telefone" é obrigatória.',
        ];
    }

    /** Linhas rejeitadas pela validação (nome/telefone em branco). */
    public function invalidas(): int
    {
        return count($this->failures());
    }

    /**
     * Normaliza a coluna "tipo" para um valor válido da enum. Aceita apelidos
     * amigáveis ("fixo" → mensalista_fixo). Padrão: avulso.
     */
    private function normalizarTipo(?string $tipo): string
    {
        return match (strtolower(trim((string) $tipo))) {
            'mensalista' => 'mensalista',
            'mensalista_fixo', 'fixo' => 'mensalista_fixo',
            default => 'avulso',
        };
    }
}
