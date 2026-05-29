<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

final class BreadcrumbListSchema extends Component
{
    /** @var array<string, mixed> */
    public readonly array $ldJson;

    /**
     * @param array<int, array{name: string, url: string}> $items
     */
    public function __construct(public readonly array $items)
    {
        $this->ldJson = $this->buildSchema();
    }

    /** @return array<string, mixed> */
    private function buildSchema(): array
    {
        $elements = [];

        foreach ($this->items as $position => $item) {
            $elements[] = [
                '@type'    => 'ListItem',
                'position' => $position + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }

        return [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    public function render(): View
    {
        return view('components.breadcrumb-list-schema');
    }
}
