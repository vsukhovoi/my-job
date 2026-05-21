<?php

declare(strict_types=1);

namespace App\Filament\Resources\InterviewRequests;

use App\Filament\Resources\InterviewRequests\Pages\ListInterviewRequests;
use App\Filament\Resources\InterviewRequests\Tables\InterviewRequestsTable;
use App\Models\InterviewRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InterviewRequestResource extends Resource
{
    protected static ?string $model = InterviewRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeft;

    protected static ?string $navigationLabel  = 'Інтерв\'ю';
    protected static ?string $modelLabel       = 'Запит на інтерв\'ю';
    protected static ?string $pluralModelLabel = 'Запити на інтерв\'ю';
    protected static ?int    $navigationSort   = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return InterviewRequestsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterviewRequests::route('/'),
        ];
    }
}
