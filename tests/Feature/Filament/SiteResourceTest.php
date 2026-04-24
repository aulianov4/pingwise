<?php

namespace Tests\Feature\Filament;

use App\Enums\ProjectRole;
use App\Filament\Resources\SiteResource;
use App\Filament\Resources\TestResultResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\TestResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_own_sites(): void
    {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->projects()->attach($project1, ['role' => ProjectRole::Admin->value]);
        $user2->projects()->attach($project2, ['role' => ProjectRole::Admin->value]);
        $site1 = Site::factory()->createQuietly(['user_id' => $user1->id, 'project_id' => $project1->id, 'name' => 'User1 Site']);
        $site2 = Site::factory()->createQuietly(['user_id' => $user2->id, 'project_id' => $project2->id, 'name' => 'User2 Site']);

        $this->actingAs($user1);
        $query = SiteResource::getEloquentQuery();
        $sites = $query->get();

        $this->assertTrue($sites->contains('id', $site1->id));
        $this->assertFalse($sites->contains('id', $site2->id));
    }

    public function test_user_sees_only_own_test_results(): void
    {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user1->projects()->attach($project1, ['role' => ProjectRole::Observer->value]);
        $user2->projects()->attach($project2, ['role' => ProjectRole::Observer->value]);
        $site1 = Site::factory()->createQuietly(['user_id' => $user1->id, 'project_id' => $project1->id]);
        $site2 = Site::factory()->createQuietly(['user_id' => $user2->id, 'project_id' => $project2->id]);
        $result1 = TestResult::factory()->create(['site_id' => $site1->id]);
        $result2 = TestResult::factory()->create(['site_id' => $site2->id]);

        $this->actingAs($user1);
        $query = TestResultResource::getEloquentQuery();
        $results = $query->get();

        $this->assertTrue($results->contains('id', $result1->id));
        $this->assertFalse($results->contains('id', $result2->id));
    }

    public function test_unauthenticated_user_sees_no_sites(): void
    {
        // Пользователь без проектов не видит сайты
        Site::factory()->count(3)->createQuietly();
        $user = User::factory()->create();

        $this->actingAs($user);
        $query = SiteResource::getEloquentQuery();
        $sites = $query->get();

        $this->assertCount(0, $sites);
    }
}
