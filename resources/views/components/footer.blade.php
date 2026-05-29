<style>
    .site-footer {
        background: #1F2937;
        margin-top: 80px;
    }
    .site-footer__inner {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
        text-align: center;
    }
    .site-footer__nav {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 8px 12px;
        margin-bottom: 32px;
    }
    .site-footer__nav a {
        color: #D1D5DB;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.2s;
    }
    .site-footer__nav a:hover { color: #ffffff; }
    .site-footer__nav span { color: #4B5563; }
    .site-footer__bottom {
        border-top: 1px solid #374151;
        padding-top: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        color: #6B7280;
    }
    .site-footer__bottom a {
        color: #6B7280;
        text-decoration: none;
        transition: color 0.2s;
    }
    .site-footer__bottom a:hover { color: #ffffff; }
    .site-footer__dot { color: #4B5563; }
</style>

<footer class="site-footer">
    <div class="site-footer__inner">

        {{-- Logo --}}
        <div style="display:flex; justify-content:center; margin-bottom:16px;">
            <img src="{{ asset('img/logo/mj-logo-100x100-dark-theme.webp') }}"
                 alt="My Job"
                 style="width:48px; height:48px; object-fit:contain;">
        </div>

        {{-- Company name --}}
        <p style="font-size:16px; font-weight:700; color:#ffffff; margin-bottom:8px;">MyJob</p>

        {{-- Description --}}
        <p style="font-size:14px; color:#D1D5DB; line-height:1.6; max-width:480px; margin:0 auto 24px;">
            Найшвидший сайт вакансій України. З'єднуємо таланти з провідними роботодавцями країни.
        </p>

        {{-- Links --}}
        <nav class="site-footer__nav">
            <a href="{{ route('home') }}">Перегляд вакансій</a>
            <span>•</span>
            <a href="{{ route('home') }}">Категорії</a>
            <span>•</span>
            <button
                onclick="Livewire.dispatch('open-quick-publish')"
                style="background:none; border:none; cursor:pointer; font-size:14px;
                       color:#D1D5DB; padding:0; transition:color 0.2s;"
                onmouseover="this.style.color='#ffffff'"
                onmouseout="this.style.color='#D1D5DB'"
            >
                Розмістити вакансію
            </button>
            <span>•</span>
            <a href="{{ route('login') }}">Увійти</a>
            <span>•</span>
            <a href="{{ route('about') }}">Про нас</a>
            <span>•</span>
            <a href="{{ route('contacts') }}">Контакти</a>
        </nav>

        {{-- Bottom --}}
        <div class="site-footer__bottom">
            <p style="margin:0;">&copy; {{ date('Y') }} MyJob. Всі права захищено.</p>
            <span class="site-footer__dot">•</span>
            <a href="{{ route('offer') }}">Публічна оферта</a>
            <span class="site-footer__dot">•</span>
            <a href="#">Конфіденційність</a>
        </div>

    </div>
</footer>
