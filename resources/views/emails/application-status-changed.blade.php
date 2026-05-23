<x-mail::message>
# {{ $newStatus === \App\Enums\ApplicationStatus::Interview ? '🎉 Вас запросили на співбесіду!' : 'Оновлення статусу заявки' }}

Привіт, **{{ $candidateName }}**!

@if($newStatus === \App\Enums\ApplicationStatus::Interview)
Чудові новини! Компанія **{{ $companyName }}** запросила вас на співбесіду на вакансію **{{ $vacancyTitle }}**.

Очікуйте контакту від роботодавця найближчим часом.
@elseif($newStatus === \App\Enums\ApplicationStatus::Rejected)
На жаль, ваша заявка на вакансію **{{ $vacancyTitle }}** у компанії **{{ $companyName }}** не пройшла далі.

Не засмучуйтесь — продовжуйте подавати заявки на інші вакансії!
@endif

<x-mail::button :url="$dashboardUrl">
Переглянути мої заявки
</x-mail::button>

З повагою,
**{{ config('app.name') }}**
</x-mail::message>
