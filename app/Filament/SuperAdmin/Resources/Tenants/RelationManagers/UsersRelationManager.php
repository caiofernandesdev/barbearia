<?php

namespace App\Filament\SuperAdmin\Resources\Tenants\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Usuários do estabelecimento';

    public function form(Schema $schema): Schema
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
                ->maxLength(150)
                // unicidade global (users não tem escopo de tenant no e-mail de login)
                ->unique('users', 'email', ignoreRecord: true),

            Select::make('role')
                ->label('Perfil')
                ->options([
                    'admin' => 'Admin (dono do estabelecimento)',
                    'barbeiro' => 'Profissional',
                ])
                ->default('admin')
                ->required(),

            TextInput::make('password')
                ->label('Senha')
                ->password()
                ->revealable()
                // Obrigatória ao criar; em branco na edição = mantém a atual.
                // O cast 'hashed' no model criptografa automaticamente.
                ->required(fn (string $operation) => $operation === 'create')
                ->minLength(6)
                ->dehydrated(fn (?string $state) => filled($state))
                ->helperText('Na edição, deixe em branco para não alterar a senha.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes())
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),

                TextColumn::make('role')
                    ->label('Perfil')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'admin' => 'warning',
                        'barbeiro' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'admin' => 'Admin',
                        'barbeiro' => 'Profissional',
                        default => $state,
                    }),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->headerActions([
                CreateAction::make()
                    ->label('Novo usuário')
                    // tenant_id vem do dono do relation manager (o Tenant sendo editado)
                    ->mutateDataUsing(function (array $data) {
                        $data['tenant_id'] = $this->getOwnerRecord()->getKey();

                        return $data;
                    }),
            ])
            ->recordActions([
                // Ação rápida: redefinir só a senha, sem abrir o form inteiro
                Action::make('redefinir_senha')
                    ->label('Redefinir senha')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->schema([
                        TextInput::make('password')
                            ->label('Nova senha')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6),
                    ])
                    ->action(function (array $data, $record) {
                        $record->forceFill(['password' => $data['password']])->save();
                        Notification::make()
                            ->title('Senha redefinida!')
                            ->body("A senha de {$record->name} foi atualizada.")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
                DeleteAction::make()->label('Excluir'),
            ]);
    }
}
