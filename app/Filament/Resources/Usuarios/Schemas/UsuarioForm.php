<?php

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\Profissional;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('painel.usuario.nome'))
                ->required()
                ->maxLength(100),

            TextInput::make('email')
                ->label(__('painel.usuario.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(150),

            TextInput::make('password')
                ->label(__('painel.usuario.senha'))
                ->password()
                ->revealable()
                ->minLength(8)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $operation) => $operation === 'create')
                ->helperText(__('painel.usuario.senha_help')),

            Select::make('role')
                ->label(__('painel.usuario.perfil'))
                ->options([
                    'admin' => __('painel.usuario.role_admin'),
                    'barbeiro' => __('painel.usuario.role_barbeiro'),
                ])
                ->default('barbeiro')
                ->required()
                ->live(),

            Select::make('profissional_id')
                ->label(__('painel.usuario.prof_vinculado'))
                ->options(Profissional::where('ativo', true)->orderBy('nome')->pluck('nome', 'id'))
                ->placeholder(__('painel.usuario.prof_vinculado_ph'))
                ->searchable()
                ->nullable()
                ->visible(fn ($get) => $get('role') === 'barbeiro')
                ->helperText(__('painel.usuario.prof_vinculado_help')),

            Toggle::make('pode_cancelar')
                ->label(__('painel.usuario.pode_cancelar'))
                ->default(false)
                ->visible(fn ($get) => $get('role') === 'barbeiro')
                ->helperText(__('painel.usuario.pode_cancelar_help')),

            Section::make(__('painel.usuario.sec_permissoes'))
                ->description(__('painel.usuario.sec_permissoes_desc'))
                ->schema([
                    CheckboxList::make('permissoes')
                        ->hiddenLabel()
                        ->options(User::permissoesLabels())
                        ->columns(2)
                        ->bulkToggleable()
                        // Usuário sem permissões definidas mostra o padrão do perfil
                        ->formatStateUsing(fn ($state, ?User $record) => $state
                            ?? User::padraoPermissoes($record?->role ?? 'barbeiro'))
                        ->helperText(__('painel.usuario.permissoes_help')),
                ]),
        ]);
    }
}
