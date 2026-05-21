<?php

use App\Enums\ContactRole;
use App\Models\ContactMessage;
use Livewire\Volt\Component;

new class extends Component {

    public string $name    = '';
    public string $contact = '';
    public string $role    = 'seeker';
    public string $topic   = '';
    public string $message = '';
    public bool   $sent    = false;

    public function topics(): array
    {
        return match($this->role) {
            'seeker'      => [
                'Перегляд або фільтрація оголошень',
                'Проблема з резюме або профілем',
                'Питання щодо відгуку на вакансію',
                'Технічна помилка',
                'Питання про підписку чи оплату',
                'Інше',
            ],
            'employer'    => [
                'Публікація або редагування вакансії',
                'Доступ до бази CV',
                'Питання про тарифи та оплату',
                'Анонімна публікація',
                'Технічна помилка',
                'Інше',
            ],
            'partnership' => [
                'Корпоративне рішення',
                'API-доступ',
                'Медіаспівпраця',
                'Реклама на платформі',
                'Інше',
            ],
            default       => ['Загальний відгук', 'Повідомити про помилку', 'Інше'],
        };
    }

    public function recipientEmail(): string
    {
        return ContactRole::from($this->role)->recipientEmail();
    }

    public function updatedRole(): void
    {
        $this->topic = '';
    }

    public function submit(): void
    {
        $this->validate([
            'name'    => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:255'],
            'role'    => ['required', 'in:seeker,employer,partnership,other'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        ContactMessage::create([
            'name'    => $this->name,
            'contact' => $this->contact,
            'role'    => $this->role,
            'topic'   => $this->topic ?: null,
            'message' => $this->message,
        ]);

        $this->sent = true;
    }
};
?>

<div>
    @if($sent)
        <div class="text-center py-10">
            <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-medium mb-1">Звернення надіслано!</h3>
            <p class="text-sm text-gray-500">Ми отримали ваше повідомлення і відповімо протягом 1 робочого дня на <strong>{{ $contact }}</strong>.</p>
        </div>
    @else
        <form wire:submit="submit" novalidate>

            {{-- Role toggle --}}
            <div class="flex gap-2 mb-4 flex-wrap">
                @foreach(\App\Enums\ContactRole::cases() as $case)
                    <button
                        type="button"
                        wire:click="$set('role', '{{ $case->value }}')"
                        @class([
                            'flex-1 py-2 px-3 text-sm rounded-lg border transition',
                            'bg-green-50 border-green-500 text-green-800 font-medium' => $role === $case->value,
                            'bg-gray-50 border-gray-200 text-gray-500'               => $role !== $case->value,
                        ])
                    >
                        {{ $case->label() }}
                    </button>
                @endforeach
            </div>

            {{-- Recipient hint --}}
            <p class="text-xs text-gray-400 mb-4">
                Лист надійде на <strong>{{ $this->recipientEmail() }}</strong>
            </p>

            {{-- Name + Contact --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Ім'я</label>
                    <input wire:model="name" type="text" placeholder="Олексій"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300" />
                    @error('name') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Email або Telegram</label>
                    <input wire:model="contact" type="text" placeholder="@username або email"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300" />
                    @error('contact') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Topic --}}
            <div class="mb-3">
                <label class="block text-xs text-gray-500 mb-1">Тема звернення</label>
                <select wire:model="topic"
                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300">
                    <option value="">Оберіть тему...</option>
                    @foreach($this->topics() as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Message --}}
            <div class="mb-4">
                <label class="block text-xs text-gray-500 mb-1">Повідомлення</label>
                <textarea wire:model="message" rows="5" placeholder="Опишіть ваше питання або ситуацію..."
                          class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-300 resize-y"></textarea>
                @error('message') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white text-sm font-medium py-2.5 rounded-lg transition">
                Надіслати звернення
            </button>

            <p class="text-[11px] text-gray-400 text-center mt-3 leading-relaxed">
                Натискаючи «Надіслати», ви погоджуєтесь з обробкою персональних даних
                відповідно до Політики конфіденційності My&nbsp;Job.
            </p>
        </form>
    @endif
</div>
