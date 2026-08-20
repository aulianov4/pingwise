<?php

namespace Tests\Feature\Filament;

use App\Enums\ProjectRole;
use App\Filament\Resources\NotificationChannels\NotificationChannelResource;
use App\Filament\Resources\NotificationChannels\Pages\EditNotificationChannel;
use App\Models\NotificationChannel;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationChannelResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_admin_sees_only_own_project_channels(): void
    {
        $project = Project::factory()->create();
        $otherProject = Project::factory()->create();
        $admin = User::factory()->create();
        $admin->projects()->attach($project, ['role' => ProjectRole::Admin->value]);

        $own = NotificationChannel::factory()->create(['project_id' => $project->id]);
        $foreign = NotificationChannel::factory()->create(['project_id' => $otherProject->id]);

        $this->actingAs($admin);
        $ids = NotificationChannelResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($own->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_observer_can_view_but_cannot_create(): void
    {
        $project = Project::factory()->create();
        $observer = User::factory()->create();
        $observer->projects()->attach($project, ['role' => ProjectRole::Observer->value]);
        $channel = NotificationChannel::factory()->create(['project_id' => $project->id]);

        $this->actingAs($observer);

        $this->assertTrue(Gate::forUser($observer)->allows('view', $channel));
        $this->assertTrue(Gate::forUser($observer)->allows('viewAny', NotificationChannel::class));
        $this->assertFalse(Gate::forUser($observer)->allows('create', NotificationChannel::class));
        $this->assertFalse(Gate::forUser($observer)->allows('update', $channel));
    }

    public function test_project_admin_can_create_and_update(): void
    {
        $project = Project::factory()->create();
        $admin = User::factory()->create();
        $admin->projects()->attach($project, ['role' => ProjectRole::Admin->value]);
        $channel = NotificationChannel::factory()->create(['project_id' => $project->id]);

        $this->assertTrue(Gate::forUser($admin)->allows('create', NotificationChannel::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $channel));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $channel));
    }

    public function test_superadmin_sees_all_channels(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $channelA = NotificationChannel::factory()->create();
        $channelB = NotificationChannel::factory()->create();

        $this->actingAs($superadmin);
        $ids = NotificationChannelResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($channelA->id));
        $this->assertTrue($ids->contains($channelB->id));
    }

    public function test_regenerate_token_issues_new_connect_code(): void
    {
        $superadmin = User::factory()->superadmin()->create();
        $channel = NotificationChannel::factory()->create([
            'connect_token' => 'PW-OLD1',
            'connect_token_expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($superadmin);

        Livewire::test(EditNotificationChannel::class, ['record' => $channel->getKey()])
            ->callAction(TestAction::make('regenerate_token')->schemaComponent('telegramBinding'));

        $channel->refresh();

        $this->assertNotSame('PW-OLD1', $channel->connect_token);
        $this->assertTrue($channel->hasActiveConnectToken());
    }
}
