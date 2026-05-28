<?php

declare(strict_types=1);

namespace Tests\Feature\Seeker;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $candidate;
    protected User $employer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidate = User::factory()->create([
            'name'  => 'Тест Кандидат',
            'email' => 'candidate@test.com',
            'phone' => '+380991234567',
        ]);

        $this->employer = User::factory()->employer()->create();
    }

    #[Test]
    public function profile_page_loads_successfully(): void
    {
        $this->actingAs($this->candidate)
            ->get('/dashboard/seeker/profile')
            ->assertStatus(200);
    }

    #[Test]
    public function unauthenticated_user_is_redirected_to_login(): void
    {
        $this->get('/dashboard/seeker/profile')
            ->assertRedirect('/login');
    }

    #[Test]
    public function employer_cannot_access_seeker_profile(): void
    {
        $this->actingAs($this->employer)
            ->get('/dashboard/seeker/profile')
            ->assertStatus(403);
    }

    #[Test]
    public function profile_form_is_prefilled_with_user_data(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->assertSet('name', 'Тест Кандидат')
            ->assertSet('email', 'candidate@test.com')
            ->assertSet('phone', '+380991234567');
    }

    #[Test]
    public function candidate_can_update_name_and_email(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('name', 'Нове Імя')
            ->set('email', 'newemail@test.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $this->candidate->id,
            'name'  => 'Нове Імя',
            'email' => 'newemail@test.com',
        ]);
    }

    #[Test]
    public function candidate_can_update_phone(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('phone', '+380671111111')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id'    => $this->candidate->id,
            'phone' => '+380671111111',
        ]);
    }

    #[Test]
    public function candidate_can_unlink_telegram(): void
    {
        $this->candidate->update(['telegram_id' => 1234567890]);
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->call('unlinkTelegram')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id'          => $this->candidate->id,
            'telegram_id' => null,
        ]);
    }

    #[Test]
    public function candidate_can_change_password(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('save')
            ->assertHasNoErrors();

        $this->candidate->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->candidate->password));
    }

    #[Test]
    public function password_confirmation_must_match(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'doesnotmatch')
            ->call('save')
            ->assertHasErrors(['password_confirmation']);
    }

    #[Test]
    public function password_minimum_length_is_enforced(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('save')
            ->assertHasErrors(['password']);
    }

    #[Test]
    public function name_is_required(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    #[Test]
    public function email_must_be_valid(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('email', 'not-an-email')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    #[Test]
    public function save_dispatches_profile_saved_event(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('name', 'Нове Імя')
            ->call('save')
            ->assertDispatched('profile-saved');
    }

    #[Test]
    public function saved_flag_is_set_after_successful_save(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('name', 'Нове Імя')
            ->call('save')
            ->assertSet('saved', true);
    }

    #[Test]
    public function password_is_cleared_after_save(): void
    {
        $this->actingAs($this->candidate);

        Volt::test('pages.seeker.profile')
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('save')
            ->assertSet('password', '');
    }
}
