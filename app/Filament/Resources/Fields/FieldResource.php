<?php

namespace App\Filament\Resources\Fields;

use App\Filament\Resources\Fields\Pages\CreateField;
use App\Filament\Resources\Fields\Pages\EditField;
use App\Filament\Resources\Fields\Pages\ListFields;
use App\Filament\Resources\Fields\Pages\ViewField;
use App\Filament\Resources\Fields\Schemas\FieldForm;
use App\Filament\Resources\Fields\Schemas\FieldInfolist;
use App\Filament\Resources\Fields\Tables\FieldsTable;
use App\Models\Election;
use App\Models\Field;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FieldResource extends Resource
{
    protected static ?string $model = Field::class;

    protected static ?string $navigationLabel = 'Bidang';

    protected static string|UnitEnum|null $navigationGroup = 'Data';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $modelLabel = 'Bidang';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return ! Election::isCurrentLocked() && parent::canCreate();
    }

    public static function canDelete(Model $record): bool
    {
        return ! Election::isCurrentLocked() && parent::canDelete($record);
    }

    public static function form(Schema $schema): Schema
    {
        return FieldForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FieldInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FieldsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFields::route('/'),
            'create' => CreateField::route('/create'),
            'view' => ViewField::route('/{record}'),
            'edit' => EditField::route('/{record}/edit'),
        ];
    }
}
