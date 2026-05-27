<x-mail::message>
# 📨 Нова заявка на вашу вакансію

Привіт, **{{ $employerName }}**!

Кандидат **{{ $candidateName }}** подав заявку на вакансію **{{ $vacancyTitle }}**.

@if($resumeUrl)
📎 Резюме: [переглянути]({{ $resumeUrl }})
@endif

<x-mail::button :url="$applicationUrl">
Переглянути заявку
</x-mail::button>

З повагою,
**{{ config('app.name') }}**
</x-mail::message>
