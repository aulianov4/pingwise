<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = Server::generatePlainToken();

        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->domainWord().'-srv',
            'hostname' => null,
            'token_hash' => Server::hashToken($plain),
            'token_prefix' => Server::prefixFromPlain($plain),
            'is_active' => true,
            'last_seen_at' => null,
            'last_status' => null,
            'last_message' => null,
            'settings' => Server::defaultSettings(),
        ];
    }

    /**
     * Известный plaintext-токен для API-тестов.
     */
    public function withToken(string $plain): static
    {
        return $this->state(fn (array $attributes): array => [
            'token_hash' => Server::hashToken($plain),
            'token_prefix' => Server::prefixFromPlain($plain),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
