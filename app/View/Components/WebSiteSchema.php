<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class WebSiteSchema extends Component
{
    /** @var array<string, mixed> */
    public readonly array $ldJson;

    public function __construct()
    {
        $this->ldJson = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'name'     => config('app.name'),
            'url'      => url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => url('/') . '?search={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public function render(): View
    {
        return view('components.web-site-schema');
    }
}
