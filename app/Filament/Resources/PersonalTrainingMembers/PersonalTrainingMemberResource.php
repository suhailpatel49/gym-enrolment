<?php

namespace App\Filament\Resources\PersonalTrainingMembers;

use App\Filament\Resources\PersonalTrainingMembers\Pages\CreatePersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\EditPersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Pages\ListPersonalTrainingMembers;
use App\Filament\Resources\PersonalTrainingMembers\Pages\ViewPersonalTrainingMember;
use App\Filament\Resources\PersonalTrainingMembers\Schemas\PersonalTrainingMemberForm;
use App\Filament\Resources\PersonalTrainingMembers\Schemas\PersonalTrainingMemberInfolist;
use App\Filament\Resources\PersonalTrainingMembers\Tables\PersonalTrainingMembersTable;
use App\Models\PersonalTrainingMember;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PersonalTrainingMemberResource extends Resource
{
    protected static ?string $model = PersonalTrainingMember::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'client_name';

    public static function form(Schema $schema): Schema
    {
        return PersonalTrainingMemberForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PersonalTrainingMemberInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PersonalTrainingMembersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPersonalTrainingMembers::route('/'),
            'create' => CreatePersonalTrainingMember::route('/create'),
            'view' => ViewPersonalTrainingMember::route('/{record}'),
            'edit' => EditPersonalTrainingMember::route('/{record}/edit'),
        ];
    }
}
