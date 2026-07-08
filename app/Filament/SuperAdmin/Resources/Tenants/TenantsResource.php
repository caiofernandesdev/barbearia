<?php

namespace App\Filament\SuperAdmin\Resources\Tenants;

use App\Filament\SuperAdmin\Resources\Tenants\Pages\CreateTenant;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\EditTenant;
use App\Filament\SuperAdmin\Resources\Tenants\Pages\ListTenants;
use App\Filament\SuperAdmin\Resources\Tenants\RelationManagers;
use App\Models\Plano;
use App\Models\Tenant;
use App\Models\TipoEstabelecimento;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TenantsResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationLabel = 'Estabelecimentos';

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-building-storefront';
    }
    protected static ?string $modelLabel = 'Estabelecimento';
    protected static ?string $pluralModelLabel = 'Estabelecimentos';

    public static function getNavigationGroup(): ?string
    {
        return 'Tenants';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nome')
                ->label('Nome')
                ->required()
                ->maxLength(255),

            TextInput::make('slug')
                ->label('Slug (URL)')
                ->required()
                ->unique(Tenant::class, 'slug', ignoreRecord: true)
                ->helperText('Identificador único na URL: /{slug}/admin')
                ->alphaDash()
                ->maxLength(60),

            Select::make('tipo_estabelecimento_id')
                ->label('Tipo')
                ->options(TipoEstabelecimento::where('ativo', true)->pluck('nome', 'id'))
                ->required()
                ->searchable(),

            Select::make('plano_id')
                ->label('Plano')
                ->options(Plano::where('ativo', true)->pluck('nome', 'id'))
                ->nullable()
                ->searchable(),

            Toggle::make('ativo')
                ->label('Ativo')
                ->default(true),

            \Filament\Schemas\Components\Section::make('Admin inicial')
                ->description('Cria o usuário administrador do estabelecimento (somente na criação)')
                ->hiddenOn('edit')
                ->schema([
                    TextInput::make('admin_nome')
                        ->label('Nome do admin')
                        ->required()
                        ->maxLength(100),

                    TextInput::make('admin_email')
                        ->label('E-mail de login')
                        ->email()
                        ->required()
                        ->rules(['unique:users,email']),

                    TextInput::make('admin_senha')
                        ->label('Senha')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(6),
                ]),


            \Filament\Schemas\Components\Section::make('WhatsApp / Evolution API')
                ->description('Configuração de integração com WhatsApp para este estabelecimento')
                ->collapsed()
                ->schema([
                    TextInput::make('whatsapp_config.base_url')
                        ->label('URL da Evolution API')
                        ->placeholder('https://api.evolution.com.br')
                        ->url(),

                    TextInput::make('whatsapp_config.api_key')
                        ->label('API Key')
                        ->password()
                        ->revealable(),

                    TextInput::make('whatsapp_config.instance')
                        ->label('Nome da Instância')
                        ->placeholder('minha-instancia'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('tipoEstabelecimento.nome')
                    ->label('Tipo')
                    ->badge()
                    ->default('—'),

                TextColumn::make('plano.nome')
                    ->label('Plano')
                    ->default('—'),

                IconColumn::make('ativo')
                    ->label('Ativo')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Criado em')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTenants::route('/'),
            'create' => CreateTenant::route('/create'),
            'edit'   => EditTenant::route('/{record}/edit'),
        ];
    }
}
