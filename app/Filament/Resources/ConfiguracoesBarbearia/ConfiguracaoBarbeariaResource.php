<?php

namespace App\Filament\Resources\ConfiguracoesBarbearia;

use App\Filament\Resources\ConfiguracoesBarbearia\Pages\EditConfiguracaoBarbearia;
use App\Filament\Resources\ConfiguracoesBarbearia\Schemas\ConfiguracaoBarbeariaForm;
use App\Models\ConfiguracaoBarbearia;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ConfiguracaoBarbeariaResource extends Resource
{
    protected static ?string $model = ConfiguracaoBarbearia::class;

    protected static \BackedEnum|string|null $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Configurações';
    protected static ?string $modelLabel      = 'Configuração';
    protected static ?int    $navigationSort  = 99;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return ConfiguracaoBarbeariaForm::configure($schema);
    }

    // Não usa tabela — acessa direto o form do singleton
    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            // A rota "/" aponta direto para o Edit, sem tela de listagem
            'index' => EditConfiguracaoBarbearia::route('/'),
        ];
    }
}
