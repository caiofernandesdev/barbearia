<?php

namespace App\Filament\Resources\Agendamentos;

use App\Filament\Resources\Agendamentos\Pages\CreateAgendamento;
use App\Filament\Resources\Agendamentos\Pages\EditAgendamento;
use App\Filament\Resources\Agendamentos\Pages\ListAgendamentos;
use App\Filament\Resources\Agendamentos\Schemas\AgendamentoForm;
use App\Filament\Resources\Agendamentos\Tables\AgendamentosTable;
use App\Models\Agendamento;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;

class AgendamentoResource extends Resource
{
    protected static ?string $model = Agendamento::class;

    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agendamentos';

    protected static ?string $modelLabel = 'Agendamento';

    protected static ?string $pluralModelLabel = 'Agendamentos';

    protected static ?string $recordTitleAttribute = 'cliente_nome';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return AgendamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgendamentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAgendamentos::route('/'),
            'create' => CreateAgendamento::route('/create'),
            'edit' => EditAgendamento::route('/{record}/edit'),
        ];
    }
}
