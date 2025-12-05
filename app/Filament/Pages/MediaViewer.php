<?php

namespace App\Filament\Pages;

use App\Models\Media;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class MediaViewer extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-play';

    protected static string $view = 'filament.pages.media-viewer';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'view/{record}';

    public ?Media $media = null;

    public function mount(int|string $record): void
    {
        $this->media = Media::findOrFail($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->media->name;
    }

    public function getHeading(): string|Htmlable
    {
        return $this->media->name;
    }
}
