<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\Profissional;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(100),

            TextInput::make('email')
                ->label('E-mail')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                ->minLength(8)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText('Deixe em branco para manter a senha atual (somente na edição).'),

            Select::make('role')
                ->label('Perfil')
                ->options([
                    'admin'    => 'Dono / Admin (acesso total)',
                    'barbeiro' => 'Barbeiro (painel próprio)',
                ])
                ->default('barbeiro')
                ->required()
                ->live(),

            Select::make('profissional_id')
                ->label('Barbeiro vinculado')
                ->options(Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->placeholder('Selecione o profissional...')
                ->searchable()
                ->nullable()
                ->visible(fn ($get) => $get('role') === 'barbeiro')
                ->helperText('Vincule este login ao cadastro do barbeiro para filtrar os dados dele.'),
        ]);
    }
}
