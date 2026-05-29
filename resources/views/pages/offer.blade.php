<x-app-layout>

@push('head')
<meta name="description" content="Договір публічної оферти про надання послуг з оброблення даних та розміщення інформації — My Job (myjob.co.ua).">
<meta name="robots" content="noindex, follow">
<meta property="og:title" content="Публічна оферта — My Job">
<meta property="og:url" content="{{ url('/offer') }}">
@endpush

<div class="max-w-3xl mx-auto px-6 py-16">

    {{-- ШАПКА ДОКУМЕНТА --}}
    <div class="mb-12 text-center border-b border-gray-200 pb-10">
        <h1 class="text-2xl font-extrabold text-gray-900 mb-3 leading-snug">
            ДОГОВІР ПУБЛІЧНОЇ ОФЕРТИ
        </h1>
        <p class="text-sm text-gray-500">
            про надання послуг з оброблення даних та розміщення інформації
        </p>
        <div class="mt-4 flex justify-center gap-8 text-xs text-gray-400">
            <span>Дата публікації: 13 травня 2026 року</span>
            <span>Місце публікації: myjob.co.ua</span>
        </div>
    </div>

    <div class="mb-10 text-sm text-gray-600 leading-relaxed bg-gray-50 rounded-xl p-6 border border-gray-100">
        <p>
            Товариство з обмеженою відповідальністю <strong>«ФЛАГМАН СВ»</strong>
            (код ЄДРПОУ 37490783, що є платником єдиного податку за ставкою 5% без ПДВ),
            в особі засновника, який діє на підставі Рішення №8 від 30.06.2025 р. (надалі — <strong>Виконавець</strong>),
            з однієї сторони, та будь-яка юридична особа, фізична особа-підприємець або дієздатна
            фізична особа, яка акцептувала цей Договір (надалі — <strong>Клієнт</strong>),
            з іншої сторони (разом надалі — <strong>Сторони</strong>), уклали цей Договір про таке:
        </p>
    </div>

    {{-- РОЗДІЛ 1 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            1. Загальні положення та акцепт
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">1.1.</span>
                Цей Договір є публічною офертою (пропозицією) відповідно до ст. 633 та ст. 641
                Цивільного кодексу України.
            </p>
            <p>
                <span class="font-semibold text-gray-800">1.2.</span>
                Повним і безумовним прийняттям (акцептом) умов цієї Оферти є здійснення Клієнтом
                будь-якої з таких дій: реєстрація Особистого кабінету на Вебсайті Виконавця
                myjob.co.ua, внесення передоплати за Послуги (оновлення тарифного плану),
                фактична публікація вакансії або використання будь-яких інформаційно-пошукових
                сервісів Сайту.
            </p>
            <p>
                <span class="font-semibold text-gray-800">1.3.</span>
                З моменту акцепту цей Договір набуває чинності договору приєднання (ст. 634 ЦК
                України) та має юридичну силу договору, підписаного Сторонами двосторонньо.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 2 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            2. Предмет договору
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">2.1.</span>
                Виконавець зобов'язується надати Клієнту послуги з оброблення даних, розміщення
                інформації на вебвузлах (Вебсайті), надання доступу до програмного інтерфейсу та
                пошукового механізму бази даних (надалі — <strong>Послуги</strong>), а Клієнт
                зобов'язується прийняти та оплатити Послуги на умовах цього Договору.
            </p>
            <p>
                <span class="font-semibold text-gray-800">2.2.</span>
                <span class="font-semibold text-gray-800">Юридичний статус послуг:</span>
                Сторони чітко усвідомлюють, що Виконавець надає виключно технічні та інформаційні
                ІТ-послуги (КВЕД 63.11). Виконавець не є агентством з працевлаштування (КВЕД 78.10),
                не здійснює професійний підбір, оцінку, тестування, працевлаштування чи гарантування
                найму персоналу.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 3 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            3. Порядок надання послуг та монетизація
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">3.1.</span>
                Послуги надаються шляхом надання Клієнту технічної можливості публікувати
                інформаційні оголошення (вакансії) у відповідних категоріях Сайту та/або отримувати
                допуск до пошукових фільтрів бази даних резюме користувачів на Сайті.
            </p>
            <p>
                <span class="font-semibold text-gray-800">3.2.</span>
                Обсяг, вартість та технічні переваги Послуг (ліміти на публікацію вакансій,
                терміни їх відображення) визначаються Тарифами, які розміщені на Вебсайті та є
                невід'ємною частиною цього Договору.
            </p>
            <p>
                <span class="font-semibold text-gray-800">3.3.</span>
                Виконавець надає Послуги на умовах 100% передоплати.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 4 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            4. Вартість послуг та порядок розрахунків
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">4.1.</span>
                Розрахунки за цим Договором здійснюються у національній валюті України — гривні,
                виключно у безготівковій формі.
            </p>
            <p>
                <span class="font-semibold text-gray-800">4.2.</span>
                Оплата здійснюється Клієнтом шляхом перерахування грошових коштів на поточний
                рахунок (IBAN) Виконавця або через інтегровані на Вебсайті сервіси
                інтернет-еквайрингу (платіжні системи за допомогою карт Visa/Mastercard).
            </p>
            <p>
                <span class="font-semibold text-gray-800">4.3.</span>
                Послуги надаються без ПДВ (у зв'язку із застосуванням Виконавцем спрощеної системи
                оподаткування).
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 5 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            5. Порядок приймання-передачі послуг
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">5.1.</span>
                Послуги вважаються наданими Виконавцем належним чином, в повному обсязі та
                прийнятими Клієнтом у момент активації відповідного тарифного пакету, успішної
                публікації вакансії на Сайті або відкриття доступу до бази даних.
            </p>
            <p>
                <span class="font-semibold text-gray-800">5.2.</span>
                Сторони погодили, що надання Послуг за цим Договором не потребує підписання
                двосторонніх паперових Актів приймання-передачі наданих послуг.
            </p>
            <p>
                <span class="font-semibold text-gray-800">5.3.</span>
                Якщо Клієнт протягом 3 (трьох) календарних днів з моменту надання Послуги не
                заявив обґрунтовану письмову претензію щодо її якості, Послуга вважається
                виконаною бездоганно і прийнятою Клієнтом.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 6 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            6. Обмеження відповідальності
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">6.1.</span>
                Виконавець не несе відповідальності за зміст, точність та законність інформації,
                яка самостійно розміщується третіми особами (користувачами, пошукачами) у формі
                резюме чи відгуків.
            </p>
            <p>
                <span class="font-semibold text-gray-800">6.2.</span>
                Виконавець не відповідає за результати співбесід, укладення чи неукладення
                трудових та господарських відносин між Клієнтом та кандидатами.
            </p>
            <p>
                <span class="font-semibold text-gray-800">6.3.</span>
                Виконавець залишає за собою право видалити будь-яку інформацію (вакансію) Клієнта
                без повернення коштів, якщо вона містить ознаки дискримінації (за статтю, віком,
                расою тощо), заклики до порушення чинного законодавства України або містить
                завідомо неправдиві дані.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 7 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            7. Персональні дані
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">7.1.</span>
                Клієнт дає згоду на обробку своїх персональних та корпоративних даних Виконавцем
                відповідно до Закону України «Про захист персональних даних» з метою виконання
                умов цього Договору, верифікації профілю компанії та проведення розрахунків.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 8 --}}
    <section class="mb-10">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            8. Порядок перерахунку та повернення коштів
        </h2>
        <div class="space-y-4 text-sm text-gray-600 leading-relaxed">
            <p>
                <span class="font-semibold text-gray-800">8.1.</span>
                Клієнт має право на повернення сплачених коштів виключно у випадку, якщо Послуга
                ще не була фактично надана. Відповідно до умов цього Договору, Послуга вважається
                наданою, а зобов'язання Виконавця виконаними в повному обсязі з моменту настання
                будь-якої з подій, зазначених у п. 5.1 цього Договору.
            </p>
            <p>
                <span class="font-semibold text-gray-800">8.2.</span>
                Кошти не підлягають поверненню у разі:
            </p>
            <ul class="list-none pl-6 space-y-2 text-sm text-gray-600 leading-relaxed">
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                    якщо Клієнт отримав доступ до бази даних або опублікував хоча б одне оголошення (вакансію);
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                    якщо доступ до Сайту був обмежений Виконавцем через порушення Клієнтом умов п. 6.3
                    цього Договору;
                </li>
                <li class="flex items-start gap-2">
                    <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-gray-400 flex-shrink-0"></span>
                    якщо Клієнт самостійно вирішив припинити використання Сайту після активації Послуг
                    без наявності технічних помилок з боку Виконавця.
                </li>
            </ul>
            <p>
                <span class="font-semibold text-gray-800">8.3.</span>
                Для здійснення повернення (якщо Послуга не була активована) Клієнт протягом
                3 (трьох) календарних днів з моменту оплати направляє письмову заяву на електронну
                пошту Виконавця. У заяві зазначаються: назва Клієнта (або ПІБ), сума, дата платежу
                та причина повернення.
            </p>
            <p>
                <span class="font-semibold text-gray-800">8.4.</span>
                Виконавець розглядає заяву протягом 5 (п'яти) робочих днів. У разі позитивного
                рішення повернення коштів здійснюється на той самий банківський рахунок (або карту),
                з якого було проведено оплату, протягом 14 (чотирнадцяти) банківських днів.
                Комісія платіжних систем за перерахування коштів утримується з суми, що підлягає
                поверненню.
            </p>
        </div>
    </section>

    {{-- РОЗДІЛ 9 --}}
    <section class="mb-2">
        <h2 class="text-base font-bold text-gray-900 mb-4 uppercase tracking-wide">
            9. Реквізити виконавця
        </h2>
        <div class="bg-gray-50 rounded-xl border border-gray-100 p-6">
            <table class="w-full text-sm text-gray-600 leading-relaxed">
                <tbody class="divide-y divide-gray-100">
                    <tr class="py-2">
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap w-48">Повна назва</td>
                        <td class="py-2.5">Товариство з обмеженою відповідальністю «ФЛАГМАН СВ»</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Код ЄДРПОУ</td>
                        <td class="py-2.5">37490783</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Юридична адреса</td>
                        <td class="py-2.5">52410, Дніпропетровська обл., Дніпровський район, село Сурсько-Михайлівка, вул. Виноградна, буд. 20</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">IBAN</td>
                        <td class="py-2.5 font-mono">UA753220010000026009700017366</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">МФО</td>
                        <td class="py-2.5">322001</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Банк</td>
                        <td class="py-2.5">АТ «УНІВЕРСАЛ БАНК»</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Оподаткування</td>
                        <td class="py-2.5">Єдиний податок 3 група, 5% без ПДВ</td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Email</td>
                        <td class="py-2.5">
                            <a href="mailto:info@myjob.co.ua" class="text-blue-600 hover:underline">info@myjob.co.ua</a>
                        </td>
                    </tr>
                    <tr>
                        <td class="py-2.5 pr-6 font-semibold text-gray-700 whitespace-nowrap">Підписант</td>
                        <td class="py-2.5">Засновник</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- НИЖНІЙ КОЛОНТИТУЛ --}}
    <div class="mt-10 pt-8 border-t border-gray-200 text-xs text-gray-400 text-center leading-relaxed">
        <p>
            Цей Договір є публічною офертою відповідно до ст. 633, 634, 641 Цивільного кодексу України.<br>
            Актуальна версія завжди доступна за адресою:
            <a href="{{ url('/offer') }}" class="text-blue-500 hover:underline">{{ url('/offer') }}</a>
        </p>
    </div>

</div>

</x-app-layout>
