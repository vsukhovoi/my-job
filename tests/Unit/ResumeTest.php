<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    private User   $user;
    private Resume $resume;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user   = User::factory()->create();
        $this->resume = Resume::factory()->for($this->user)->create();
    }

    #[Test]
    public function it_creates_a_resume(): void
    {
        $this->assertDatabaseHas('resumes', [
            'user_id' => $this->user->id,
            'status'  => 'draft',
        ]);
    }

    #[Test]
    public function it_updates_personal_info_without_overwriting(): void
    {
        $this->resume->updatePersonalInfo(['first_name' => 'Іван']);
        $this->resume->updatePersonalInfo(['last_name'  => 'Петренко']);
        $this->resume->refresh();

        $this->assertEquals('Іван',     $this->resume->personal_info['first_name']);
        $this->assertEquals('Петренко', $this->resume->personal_info['last_name']);
    }

    #[Test]
    public function it_updates_location(): void
    {
        $this->resume->updateLocation([
            'city'      => 'Київ',
            'latitude'  => 50.4501,
            'longitude' => 30.5241,
        ]);
        $this->resume->refresh();

        $this->assertEquals('Київ',  $this->resume->location['city']);
        $this->assertEquals(50.4501, $this->resume->location['latitude']);
    }

    #[Test]
    public function it_validates_publishable_state(): void
    {
        $this->assertFalse($this->resume->isPublishable());

        $this->resume->updatePersonalInfo([
            'first_name'        => 'Іван',
            'last_name'         => 'Петренко',
            'email'             => 'ivan@example.com',
            'email_verified_at' => now()->toIso8601String(),
        ]);
        $this->resume->experiences()->create([
            'position'     => 'Developer',
            'company_name' => 'TechCorp',
            'start_date'   => now()->subYear(),
            'end_date'     => now(),
        ]);
        $this->resume->refresh();

        $this->assertTrue($this->resume->isPublishable());
    }

    #[Test]
    public function it_is_not_publishable_with_info_but_no_content(): void
    {
        $this->resume->updatePersonalInfo([
            'first_name'        => 'Іван',
            'last_name'         => 'Петренко',
            'email'             => 'ivan@example.com',
            'email_verified_at' => now()->toIso8601String(),
        ]);
        $this->resume->refresh();

        $this->assertFalse($this->resume->isPublishable());
    }

    #[Test]
    public function it_is_publishable_with_skill_instead_of_experience(): void
    {
        $this->resume->updatePersonalInfo([
            'first_name'        => 'Іван',
            'last_name'         => 'Петренко',
            'email'             => 'ivan@example.com',
            'email_verified_at' => now()->toIso8601String(),
        ]);
        $this->resume->skills()->create(['skill_name' => 'Laravel']);
        $this->resume->refresh();

        $this->assertTrue($this->resume->isPublishable());
    }

    #[Test]
    public function it_generates_stepper_status(): void
    {
        $this->resume->updatePersonalInfo([
            'first_name' => 'Іван',
            'last_name'  => 'Петренко',
        ]);
        $this->resume->refresh();

        $status = $this->resume->getStepperStatus();

        $this->assertTrue($status['personal_info']);
        $this->assertTrue($status['auth']);
        $this->assertFalse($status['experience']);
        $this->assertFalse($status['skills']);
        $this->assertArrayHasKey('location', $status);
        $this->assertArrayHasKey('notifications', $status);
    }

    #[Test]
    public function it_updates_notifications(): void
    {
        $this->resume->updateNotifications(['email' => true, 'telegram' => true]);
        $this->resume->refresh();

        $this->assertTrue($this->resume->notifications['email']);
        $this->assertTrue($this->resume->notifications['telegram']);
        $this->assertTrue($this->resume->notifications['site']);
    }
}
