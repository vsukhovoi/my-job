<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Vacancy;
use Illuminate\View\Component;
use Illuminate\View\View;

final class JobPostingSchema extends Component
{
    /** @var array<string, mixed> */
    public readonly array $ldJson;

    public function __construct(public readonly Vacancy $vacancy)
    {
        $this->ldJson = $this->buildSchema();
    }

    /** @return array<string, mixed> */
    private function buildSchema(): array
    {
        $vacancy = $this->vacancy;
        $company = $vacancy->relationLoaded('company') ? $vacancy->company : $vacancy->company()->first();
        $city    = $vacancy->relationLoaded('city') ? $vacancy->city : $vacancy->city()->first();

        $data = [
            '@context'    => 'https://schema.org/',
            '@type'       => 'JobPosting',
            'title'       => $vacancy->title,
            'description' => strip_tags($vacancy->description ?? ''),
            'datePosted'  => $vacancy->created_at->toDateString(),
            'url'         => url("/jobs/{$vacancy->slug}"),
            'identifier'  => [
                '@type' => 'PropertyValue',
                'name'  => 'MyJob',
                'value' => $vacancy->id,
            ],
        ];

        if ($vacancy->expires_at) {
            $data['validThrough'] = $vacancy->expires_at->toIso8601String();
        }

        if ($company && ! $vacancy->isAnonymous() && $company->logo_url) {
            $data['image'] = $company->logo_url;
        }

        if ($company) {
            $data['hiringOrganization'] = $vacancy->isAnonymous()
                ? [
                    '@type' => 'Organization',
                    'name'  => $vacancy->display_company_name,
                ]
                : [
                    '@type'  => 'Organization',
                    'name'   => $company->name,
                    'sameAs' => url('/employers/' . $company->slug),
                ];
        }

        $isRemote = $this->isRemoteVacancy();
        $isHybrid = $this->isHybridVacancy();

        if ($city && (! $isRemote || $isHybrid)) {
            $data['jobLocation'] = [
                '@type'   => 'Place',
                'address' => [
                    '@type'           => 'PostalAddress',
                    'addressLocality' => $city->name,
                    'addressCountry'  => 'UA',
                ],
            ];
        }

        if ($isRemote) {
            $data['jobLocationType']                = 'TELECOMMUTE';
            $data['applicantLocationRequirements']  = [
                '@type' => 'Country',
                'name'  => 'Ukraine',
            ];
        }

        $mappedTypes = $this->resolveEmploymentTypes();
        if (! empty($mappedTypes)) {
            $data['employmentType'] = count($mappedTypes) === 1 ? $mappedTypes[0] : $mappedTypes;
        }

        if ($vacancy->salary_from || $vacancy->salary_to) {
            $currency  = $vacancy->currency ?? 'UAH';
            $valueData = ['@type' => 'QuantitativeValue', 'unitText' => 'MONTH'];

            if ($vacancy->salary_from && $vacancy->salary_to) {
                $valueData['minValue'] = $vacancy->salary_from;
                $valueData['maxValue'] = $vacancy->salary_to;
            } elseif ($vacancy->salary_from) {
                $valueData['value'] = $vacancy->salary_from;
            } else {
                $valueData['value'] = $vacancy->salary_to;
            }

            $data['baseSalary'] = [
                '@type'    => 'MonetaryAmount',
                'currency' => $currency,
                'value'    => $valueData,
            ];
        }

        return $data;
    }

    /** @return array<string> */
    private function resolveEmploymentTypes(): array
    {
        $types = $this->vacancy->employment_type ?? [];

        return collect($types)
            ->reject(fn (string $t) => in_array($t, ['remote', 'hybrid'], true))
            ->map(fn (string $t) => match ($t) {
                'full-time' => 'FULL_TIME',
                'part-time' => 'PART_TIME',
                'contract'  => 'CONTRACTOR',
                default     => 'OTHER',
            })
            ->values()
            ->toArray();
    }

    private function isRemoteVacancy(): bool
    {
        $types = $this->vacancy->employment_type ?? [];
        return in_array('remote', $types, true) || in_array('hybrid', $types, true);
    }

    private function isHybridVacancy(): bool
    {
        $types = $this->vacancy->employment_type ?? [];
        return in_array('hybrid', $types, true);
    }

    public function render(): View
    {
        return view('components.job-posting-schema');
    }
}
