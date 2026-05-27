<style>
    .site-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 120px;
        background-color: #2d323b;
        color: #ffffff;
        font-size: 1.2rem;
        z-index: 9999;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .site-header.is-hidden {
        transform: translateY(-100%);
    }
    .site-header.is-solid {
        box-shadow: 0 2px 12px rgba(0,0,0,0.3);
    }
    .site-header__inner {
        max-width: 1280px;
        margin: 0 auto;
        padding: 0 24px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 24px;
    }
    .site-header__nav {
        display: flex;
        align-items: center;
        gap: 24px;
        flex: 1;
        justify-content: center;
    }
    .site-header__nav a {
        color: #ffffff;
        text-decoration: none;
        font-weight: 600;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .site-header__nav a:hover {
        opacity: 0.75;
    }
    .site-header__nav a.active {
        color: #818cf8;
    }
    .site-header__auth {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .site-header__btn-login {
        padding: 8px 20px;
        font-size: 1.2rem;
        font-weight: 600;
        color: #ffffff;
        background: transparent;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        white-space: nowrap;
        transition: background-color 0.2s;
    }
    .site-header__btn-login:hover {
        background-color: rgba(255,255,255,0.1);
    }

    .header-burger {
        display: none;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: #2d323b;
        border: 2px solid #a7a7a7;
        border-radius: 6px;
        cursor: pointer;
        flex-shrink: 0;
        gap: 0;
        flex-direction: column;
        padding: 6px 7px;
    }
    .header-burger__bar {
        display: block;
        width: 100%;
        height: 2px;
        background: #a7a7a7;
        border-radius: 2px;
        margin: 2px 0;
    }
    @media (max-width: 1023px) {
        .header-burger { display: flex; }
    }

    @media (max-width: 767px) {
        .site-header {
            height: 64px;
            font-size: 1rem;
        }
        .site-header__inner {
            padding: 0 16px;
            gap: 12px;
        }
        .site-header__nav {
            display: none;
        }
        .site-header__logo img {
            height: 48px !important;
        }
        .site-header__btn-login {
            padding: 6px 14px;
            font-size: 0.95rem;
        }
        .site-header__auth {
            display: none;
        }
    }

    /* Mobile profile icon + dropdown */
    .header-profile {
        position: relative;
        align-items: center;
        flex-shrink: 0;
        display: none; /* shown via @media below */
    }
    @media (max-width: 767px) {
        .header-profile { display: flex; }
    }
    .header-profile__btn {
        width: 36px;
        height: 36px;
        background: rgba(255,255,255,0.12);
        border: 2px solid rgba(255,255,255,0.3);
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: background 0.2s;
    }
    .header-profile__btn:hover {
        background: rgba(255,255,255,0.22);
    }
    .header-profile__btn svg {
        width: 18px;
        height: 18px;
        fill: #ffffff;
    }
    .header-profile__menu {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        width: 220px;
        background: #1f2937;
        border: 1px solid #374151;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.5);
        z-index: 10000;
        overflow: hidden;
    }
    .header-profile__user {
        padding: 14px 16px 10px;
        color: #f9fafb;
        font-weight: 700;
        font-size: 0.95rem;
        border-bottom: 1px solid #374151;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .header-profile__item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 11px 16px;
        color: #d1d5db;
        text-decoration: none;
        font-size: 0.92rem;
        font-weight: 500;
        transition: background 0.15s, color 0.15s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        cursor: pointer;
    }
    .header-profile__item:hover {
        background: rgba(255,255,255,0.07);
        color: #f9fafb;
    }
    .header-profile__item svg {
        width: 16px;
        height: 16px;
        flex-shrink: 0;
        opacity: 0.7;
    }
    .header-profile__divider {
        height: 1px;
        background: #374151;
        margin: 4px 0;
    }
    .header-profile__item--logout {
        color: #f87171;
    }
    .header-profile__item--logout:hover {
        background: rgba(248,113,113,0.08);
        color: #fca5a5;
    }
</style>

<nav class="site-header">
    <div class="site-header__inner">

        {{-- Logo --}}
        <a href="{{ route('home') }}" class="site-header__logo" style="flex-shrink:0; display:flex; align-items:center;">
            <img src="{{ asset('img/logo/mj-logo-100x100-dark-theme.webp') }}"
                 alt="My Job"
                 style="height:100px; width:auto; display:block;">
        </a>

        {{-- Burger (mobile only) --}}
        <button class="header-burger" onclick="window.toggleMobileNav()" aria-label="Меню">
            <span class="header-burger__bar"></span>
            <span class="header-burger__bar"></span>
            <span class="header-burger__bar"></span>
        </button>

        {{-- Mobile profile icon (replaces username+logout on mobile) --}}
        @auth
        <div class="header-profile">
            <button class="header-profile__btn" onclick="window.toggleProfileMenu(event)" aria-label="Профіль">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
            </button>
            <div id="header-profile-menu" class="header-profile__menu" style="display:none;">
                <div class="header-profile__user">{{ auth()->user()->name }}</div>

                @if(auth()->user()->role === \App\Enums\UserRole::Employer)
                    <a href="{{ route('employer.my-profile') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                        Мій профіль
                    </a>
                    <a href="{{ route('employer.profile') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7V3H2v18h20V7H12zM6 19H4v-2h2v2zm0-4H4v-2h2v2zm0-4H4V9h2v2zm0-4H4V5h2v2zm4 12H8v-2h2v2zm0-4H8v-2h2v2zm0-4H8V9h2v2zm0-4H8V5h2v2zm10 12h-8v-2h2v-2h-2v-2h2v-2h-2V9h8v10zm-2-8h-2v2h2v-2zm0 4h-2v2h2v-2z"/></svg>
                        Профіль компанії
                    </a>
                    <a href="{{ route('employer.dashboard') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 6h-2.18c.07-.44.18-.86.18-1.3C18 2.55 15.45 1 12 1S6 2.55 6 4.7c0 .44.11.86.18 1.3H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-8-3c1.9 0 4 .96 4 1.7S15.9 6.4 12 6.4 8 5.44 8 4.7 10.1 3 12 3zm8 17H4V8h16v12z"/></svg>
                        Вакансії
                    </a>
                    <a href="{{ route('employer.candidates') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Кандидати
                    </a>
                    <a href="{{ route('employer.analytics') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                        Аналітика
                    </a>
                    <div class="header-profile__divider"></div>
                    <a href="{{ route('employer.billing') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        Тарифи
                    </a>
                    <a href="{{ route('employer.payments') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        Оплата
                    </a>
                    <a href="{{ route('employer.support') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/></svg>
                        Підтримка
                    </a>
                @elseif(auth()->user()->role === \App\Enums\UserRole::Candidate)
                    <a href="{{ route('seeker.dashboard') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                        Мій кабінет
                    </a>
                @else
                    <a href="{{ route('filament.admin.pages.dashboard') }}" class="header-profile__item" onclick="window.closeProfileMenu()">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        Адмін-панель
                    </a>
                @endif

                <div class="header-profile__divider"></div>
                <form method="POST"
                      action="{{ \Illuminate\Support\Facades\Route::has('logout') ? route('logout') : route('filament.admin.auth.logout') }}"
                      style="margin:0;">
                    @csrf
                    <button type="submit" class="header-profile__item header-profile__item--logout">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                        Вийти
                    </button>
                </form>
            </div>
        </div>
        @endauth

        {{-- Nav links --}}
        <div class="site-header__nav">
            <a href="{{ route('resumes.create') }}" {{ request()->routeIs('resumes.*') ? 'class=active' : '' }}>Розмістити Резюме</a>
            <button
                onclick="Livewire.dispatch('open-quick-publish')"
                style="background:none; border:none; cursor:pointer; font-size:inherit; font-weight:600;
                       color:#ffffff; padding:0; transition:opacity 0.2s; white-space:nowrap;"
                onmouseover="this.style.opacity='0.75'"
                onmouseout="this.style.opacity='1'"
            >
                Розмістити вакансію
            </button>

            <a href="{{ route('for-employers') }}" {{ request()->routeIs('for-employers') ? 'class=active' : '' }}>Для роботодавців</a>

            @auth
                @if(auth()->user()->role === \App\Enums\UserRole::Employer)
                    <a href="{{ route('employer.dashboard') }}"
                       {{ request()->routeIs('employer.*') ? 'class=active' : '' }}>
                        Мої вакансії
                    </a>
                @endif
                @if(auth()->user()->role === \App\Enums\UserRole::Candidate)
                    <a href="{{ route('seeker.dashboard') }}"
                       {{ request()->routeIs('seeker.*') ? 'class=active' : '' }}>
                        Мій кабінет
                    </a>
                @endif
                @if(auth()->user()->role === \App\Enums\UserRole::Admin)
                    <a href="{{ route('filament.admin.pages.dashboard') }}">Адмін-панель</a>
                @endif
            @endauth
        </div>

        {{-- Dark mode toggle --}}
        <button class="dark-toggle" onclick="window.toggleDarkMode()" aria-label="Перемкнути тему" title="Перемкнути тему">
            <span id="darkToggleIcon">🌙</span>
        </button>

        {{-- Auth --}}
        <div class="site-header__auth">
            @auth
                <span style="color:#ffffff; font-weight:600;">
                    {{ auth()->user()->name }}
                </span>
                @if(\Illuminate\Support\Facades\Route::has('logout'))
                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="site-header__btn-login">Вийти</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('filament.admin.auth.logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit" class="site-header__btn-login">Вийти</button>
                    </form>
                @endif
            @else
                @if(\Illuminate\Support\Facades\Route::has('login'))
                    <a href="{{ route('login') }}" class="site-header__btn-login">Увійти</a>
                @endif
            @endauth
        </div>

    </div>
</nav>

{{-- Mobile nav drawer --}}
<div id="mobile-nav-overlay"
     onclick="window.toggleMobileNav()"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9998;"></div>

<div id="mobile-nav-drawer"
     style="display:none; position:fixed; top:0; left:0; bottom:0; width:280px;
            background:#2d323b; z-index:9999; padding:24px 0; overflow-y:auto;
            box-shadow:4px 0 16px rgba(0,0,0,0.4);">

    <div style="display:flex; align-items:center; justify-content:space-between; padding:0 20px 20px;">
        <span style="color:#fff; font-weight:700; font-size:1.1rem;">Меню</span>
        <button onclick="window.toggleMobileNav()"
                style="background:none; border:none; color:#a7a7a7; cursor:pointer; font-size:1.5rem; line-height:1;">✕</button>
    </div>

    <nav style="display:flex; flex-direction:column;">
        <a href="{{ route('home') }}"
           style="padding:14px 20px; color:#fff; text-decoration:none; font-weight:600; font-size:1rem;
                  border-bottom:1px solid rgba(255,255,255,0.08); transition:background 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.08)'"
           onmouseout="this.style.background=''"
        >Знайти вакансії</a>

        <a href="{{ route('resumes.create') }}"
           style="padding:14px 20px; color:#fff; text-decoration:none; font-weight:600; font-size:1rem;
                  border-bottom:1px solid rgba(255,255,255,0.08); transition:background 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.08)'"
           onmouseout="this.style.background=''"
        >Розмістити Резюме</a>

        <button onclick="Livewire.dispatch('open-quick-publish'); window.toggleMobileNav();"
                style="padding:14px 20px; color:#fff; text-align:left; font-weight:600; font-size:1rem;
                       background:none; border:none; border-bottom:1px solid rgba(255,255,255,0.08);
                       cursor:pointer; transition:background 0.2s; width:100%;"
                onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                onmouseout="this.style.background=''"
        >Розмістити вакансію</button>

        <a href="{{ route('for-employers') }}"
           style="padding:14px 20px; color:#fff; text-decoration:none; font-weight:600; font-size:1rem;
                  border-bottom:1px solid rgba(255,255,255,0.08); transition:background 0.2s;"
           onmouseover="this.style.background='rgba(255,255,255,0.08)'"
           onmouseout="this.style.background=''"
        >Для роботодавців</a>

        @auth
            @if(auth()->user()->role === \App\Enums\UserRole::Candidate)
                <a href="{{ route('seeker.dashboard') }}"
                   style="padding:14px 20px; color:#fff; text-decoration:none; font-weight:600; font-size:1rem;
                          border-bottom:1px solid rgba(255,255,255,0.08); transition:background 0.2s;"
                   onmouseover="this.style.background='rgba(255,255,255,0.08)'"
                   onmouseout="this.style.background=''"
                >Мій кабінет</a>
            @endif
        @endauth
    </nav>
</div>

<script>
    function initHeaderScroll() {
        const header = document.querySelector('.site-header');
        if (!header) return;

        let lastScrollY = window.pageYOffset;
        const THRESHOLD = 80;

        function onScroll() {
            const scrollY = window.pageYOffset;
            header.classList.toggle('is-solid', scrollY > THRESHOLD);
            header.classList.toggle('is-hidden', scrollY > lastScrollY && scrollY > THRESHOLD);
            lastScrollY = scrollY <= 0 ? 0 : scrollY;
        }

        window.removeEventListener('scroll', window._headerScrollHandler);
        window._headerScrollHandler = onScroll;
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    document.addEventListener('DOMContentLoaded', initHeaderScroll);
    document.addEventListener('livewire:navigated', initHeaderScroll);

    // Глобальна функція — викликається через inline onclick, без дублювання listeners
    window.toggleDarkMode = function() {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        localStorage.setItem('theme', next);
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.classList.toggle('dark', next === 'dark');
        const icon = document.getElementById('darkToggleIcon');
        if (icon) icon.textContent = next === 'dark' ? '☀️' : '🌙';
    };

    function syncDarkIcon() {
        const icon  = document.getElementById('darkToggleIcon');
        const theme = document.documentElement.getAttribute('data-theme') || 'light';
        if (icon) icon.textContent = theme === 'dark' ? '☀️' : '🌙';
    }

    document.addEventListener('DOMContentLoaded', syncDarkIcon);
    document.addEventListener('livewire:navigated', syncDarkIcon);

    window.toggleMobileFilters = function() {
        const aside = document.querySelector('aside.mj-filters');
        if (aside) aside.classList.toggle('is-open');
    };

    window.toggleProfileMenu = function(e) {
        e.stopPropagation();
        const menu = document.getElementById('header-profile-menu');
        if (!menu) return;
        const isOpen = menu.style.display !== 'none';
        menu.style.display = isOpen ? 'none' : 'block';
    };

    window.closeProfileMenu = function() {
        const menu = document.getElementById('header-profile-menu');
        if (menu) menu.style.display = 'none';
    };

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('header-profile-menu');
        if (menu && !menu.closest('.header-profile').contains(e.target)) {
            menu.style.display = 'none';
        }
    });

    window.toggleMobileNav = function() {
        const drawer  = document.getElementById('mobile-nav-drawer');
        const overlay = document.getElementById('mobile-nav-overlay');
        const open    = drawer.style.display === 'none';
        drawer.style.display  = open ? 'block' : 'none';
        overlay.style.display = open ? 'block' : 'none';
        document.body.style.overflow = open ? 'hidden' : '';
    };
</script>
