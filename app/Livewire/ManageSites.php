<?php

namespace App\Livewire;

use App\Models\Site;
use App\Services\Download\InvalidYtDlpArgumentsException;
use App\Services\Download\YtDlpArguments;
use App\Services\Sites\InvalidCookiesException;
use App\Services\Sites\SiteResolver;
use Closure;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Get;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Settings → Sites: each site's domains, cookies and yt-dlp arguments.
 * Cookies are write-only here: the table and forms show how many are stored
 * and when, never their values.
 */
class ManageSites extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(Site::query()->orderBy('name'))
            ->columns([
                Tables\Columns\TextColumn::make('name')->weight('medium'),
                Tables\Columns\TextColumn::make('domains')->badge(),
                Tables\Columns\TextColumn::make('cookies')
                    ->state(fn (Site $record): string => self::cookieStatus($record)),
                Tables\Columns\TextColumn::make('extra_args')
                    ->label('Arguments')
                    ->fontFamily('mono')
                    ->limit(40)
                    ->placeholder('None')
                    ->description(fn (Site $record): string => $record->combine_args ? 'Added to the global arguments' : 'Replace the global arguments'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add site')
                    ->modalHeading('Add site')
                    ->form(fn (): array => $this->siteForm(null))
                    ->using(fn (array $data): Site => $this->saveSite(new Site, $data)),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(fn (Site $record): array => $this->siteForm($record))
                    ->using(fn (Site $record, array $data): Site => $this->saveSite($record, $data)),
                Tables\Actions\Action::make('clearCookies')
                    ->label('Clear cookies')
                    ->icon('heroicon-m-no-symbol')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Downloads from this site will run without cookies until you add new ones.')
                    ->visible(fn (Site $record): bool => $record->hasCookies())
                    ->action(fn (Site $record) => $record->clearCookies()),
                Tables\Actions\DeleteAction::make()
                    ->modalDescription('Its cookies are deleted too.'),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->modalDescription('Their cookies are deleted too.'),
            ])
            ->emptyStateHeading('No sites yet')
            ->emptyStateDescription('Add a site to give downloads from it their own cookies and yt-dlp arguments.')
            ->paginated(false);
    }

    public function render(): View
    {
        return view('livewire.manage-sites');
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private function siteForm(?Site $site): array
    {
        return [
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->placeholder('Reddit'),
            Forms\Components\TagsInput::make('domains')
                ->required()
                ->placeholder('reddit.com')
                ->splitKeys(['Tab', ',', ' '])
                ->helperText('Subdomains are included: reddit.com also covers old.reddit.com. Add short-link domains too, such as youtu.be for YouTube or redd.it for Reddit. A domain can belong to one site only.')
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($site): void {
                    foreach (Arr::wrap($value) as $domain) {
                        $normalized = Site::normalizeDomain((string) $domain);
                        if ($normalized === null) {
                            $fail(sprintf('"%s" is not a domain.', $domain));

                            continue;
                        }

                        $owner = app(SiteResolver::class)->domainOwner($normalized, $site?->id);
                        if ($owner !== null) {
                            $fail(sprintf('%s already belongs to the site "%s".', $normalized, $owner->name));
                        }
                    }
                }]),
            Forms\Components\Textarea::make('extra_args')
                ->label('Extra yt-dlp arguments')
                ->rows(3)
                ->placeholder('--impersonate firefox')
                ->helperText('For downloads from this site. Quote values that contain spaces; lines starting with # are comments.')
                ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    try {
                        app(YtDlpArguments::class)->validateText((string) $value);
                    } catch (InvalidYtDlpArgumentsException $e) {
                        $fail($e->getMessage());
                    }
                }]),
            Forms\Components\Checkbox::make('combine_args')
                ->label('Combine with the global arguments')
                ->helperText('Unchecked: these replace the global arguments for this site.')
                ->default(true),
            Forms\Components\Fieldset::make('Cookies')
                ->columns(1)
                ->schema([
                    Forms\Components\Placeholder::make('cookies_stored')
                        ->label('Stored')
                        ->content(fn (): string => $site !== null ? self::cookieStatus($site) : 'None'),
                    Forms\Components\Textarea::make('cookies_paste')
                        ->label($site?->hasCookies() ? 'Paste new cookies to replace them' : 'Paste cookies')
                        ->rows(4)
                        ->helperText(new HtmlString('Export them in Netscape cookies.txt format while logged into the site, e.g. with the <a href="https://addons.mozilla.org/en-US/firefox/addon/get-cookies-txt-locally/" target="_blank" class="text-primary-600 dark:text-primary-400 underline hover:no-underline">Get cookies.txt LOCALLY</a> extension. Leave empty to keep the stored cookies.'))
                        ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (trim((string) $value) !== '' && ($problem = self::cookieProblem((string) $value, $get('domains')))) {
                                $fail($problem);
                            }
                        }]),
                    Forms\Components\FileUpload::make('cookies_file')
                        ->label('…or upload a cookies.txt')
                        ->storeFiles(false)
                        ->acceptedFileTypes(['text/plain', '.txt'])
                        ->maxSize(1024)
                        // Applied to each uploaded file.
                        ->rules([fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if ($value instanceof UploadedFile && ($problem = self::cookieProblem((string) $value->get(), $get('domains')))) {
                                $fail($problem);
                            }
                        }]),
                ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveSite(Site $site, array $data): Site
    {
        $site->fill([
            'name' => $data['name'],
            'domains' => Arr::wrap($data['domains'] ?? []),
            'extra_args' => (string) ($data['extra_args'] ?? ''),
            'combine_args' => (bool) ($data['combine_args'] ?? true),
        ])->save();

        $cookies = self::submittedCookies($data);
        if ($cookies !== null) {
            $site->storeCookies($cookies);
        }

        return $site;
    }

    /**
     * The pasted cookies, else the uploaded file's, else null (keep). The
     * upload holds cookie values, so it is deleted right away either way
     * instead of waiting in livewire-tmp for Livewire's daily cleanup.
     *
     * @param  array<string, mixed>  $data
     */
    private static function submittedCookies(array $data): ?string
    {
        $uploaded = null;

        foreach (Arr::wrap($data['cookies_file'] ?? []) as $file) {
            if ($file instanceof UploadedFile) {
                $uploaded ??= (string) $file->get();
                if ($file instanceof TemporaryUploadedFile) {
                    $file->delete();
                }
            }
        }

        if (trim((string) ($data['cookies_paste'] ?? '')) !== '') {
            return (string) $data['cookies_paste'];
        }

        return $uploaded;
    }

    private static function cookieProblem(string $content, mixed $domains): ?string
    {
        try {
            (new Site(['domains' => Arr::wrap($domains)]))->validateCookies($content);

            return null;
        } catch (InvalidCookiesException $e) {
            return $e->getMessage();
        }
    }

    private static function cookieStatus(Site $site): string
    {
        $summary = $site->cookieSummary();
        if ($summary === null) {
            return 'None';
        }

        return sprintf(
            '%d %s · added %s',
            $summary['count'],
            Str::plural('cookie', $summary['count']),
            $site->cookies_updated_at?->diffForHumans() ?? 'some time ago',
        );
    }
}
