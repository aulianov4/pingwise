<?php

namespace Tests\Feature\Filament;

use App\Enums\ProjectRole;
use App\Filament\Resources\Servers\ServerResource;
use App\Models\Project;
use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_project_servers(): void
    {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->projects()->attach($project1, ['role' => ProjectRole::Admin->value]);
        $user2->projects()->attach($project2, ['role' => ProjectRole::Admin->value]);
        $server1 = Server::factory()->create(['project_id' => $project1->id, 'name' => 'Srv1']);
        $server2 = Server::factory()->create(['project_id' => $project2->id, 'name' => 'Srv2']);

        $this->actingAs($user1);
        $servers = ServerResource::getEloquentQuery()->get();

        $this->assertTrue($servers->contains('id', $server1->id));
        $this->assertFalse($servers->contains('id', $server2->id));
    }

    public function test_user_without_projects_sees_no_servers(): void
    {
        Server::factory()->count(2)->create();
        $user = User::factory()->create();

        $this->actingAs($user);
        $servers = ServerResource::getEloquentQuery()->get();

        $this->assertCount(0, $servers);
    }

    public function test_observer_cannot_update_server(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $user->projects()->attach($project, ['role' => ProjectRole::Observer->value]);
        $server = Server::factory()->create(['project_id' => $project->id]);

        $this->assertFalse($user->can('update', $server));
        $this->assertTrue($user->can('view', $server));
    }

    public function test_project_admin_can_update_server(): void
    {
        $project = Project::factory()->create();
        $user = User::factory()->create();
        $user->projects()->attach($project, ['role' => ProjectRole::Admin->value]);
        $server = Server::factory()->create(['project_id' => $project->id]);

        $this->assertTrue($user->can('update', $server));
        $this->assertTrue($user->can('delete', $server));
    }
}
