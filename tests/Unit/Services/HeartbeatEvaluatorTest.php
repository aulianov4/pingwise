<?php

namespace Tests\Unit\Services;

use App\Models\Server;
use App\Services\Servers\HeartbeatEvaluator;
use Database\Factories\ServerHeartbeatFactory;
use Tests\TestCase;

class HeartbeatEvaluatorTest extends TestCase
{
    protected function evaluator(): HeartbeatEvaluator
    {
        return new HeartbeatEvaluator;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_replace_recursive(ServerHeartbeatFactory::healthyValue(), $overrides);
    }

    public function test_healthy_payload_is_success(): void
    {
        $result = $this->evaluator()->evaluate($this->payload(), Server::defaultSettings());

        $this->assertSame('success', $result->status);
        $this->assertNotSame('', $result->message);
        $this->assertSame([], $result->reasons);
    }

    public function test_disk_used_percent_warning(): void
    {
        $payload = $this->payload();
        $payload['disks'][0]['used_bytes'] = 86 * 1024 * 1024 * 1024;
        $payload['disks'][0]['total_bytes'] = 100 * 1024 * 1024 * 1024;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('warning', $result->status);
        $this->assertStringContainsString('Диск /', $result->message);
    }

    public function test_disk_used_percent_critical(): void
    {
        $payload = $this->payload();
        $payload['disks'][0]['used_bytes'] = 96 * 1024 * 1024 * 1024;
        $payload['disks'][0]['total_bytes'] = 100 * 1024 * 1024 * 1024;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('failed', $result->status);
    }

    public function test_disk_absolute_free_space_warning(): void
    {
        $payload = $this->payload();
        $payload['disks'][0]['total_bytes'] = 10 * 1024 * 1024 * 1024;
        $payload['disks'][0]['used_bytes'] = (int) (8.2 * 1024 * 1024 * 1024);

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('warning', $result->status);
        $this->assertStringContainsString('свободно', $result->message);
    }

    public function test_inode_critical(): void
    {
        $payload = $this->payload();
        $payload['disks'][0]['inodes_total'] = 1000;
        $payload['disks'][0]['inodes_used'] = 960;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('inodes', $result->message);
    }

    public function test_unmonitored_mount_is_ignored(): void
    {
        $payload = $this->payload([
            'disks' => [[
                'mount' => '/mnt/data',
                'total_bytes' => 100 * 1024 * 1024 * 1024,
                'used_bytes' => 99 * 1024 * 1024 * 1024,
                'inodes_total' => 1000,
                'inodes_used' => 10,
                'fstype' => 'ext4',
            ]],
        ]);

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('success', $result->status);
    }

    public function test_memory_available_critical(): void
    {
        $payload = $this->payload();
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('failed', $result->status);
        $this->assertStringContainsString('Память', $result->message);
    }

    public function test_high_used_ram_without_low_available_is_ok(): void
    {
        $payload = $this->payload();
        $payload['memory']['total_bytes'] = 32 * 1024 * 1024 * 1024;
        $payload['memory']['available_bytes'] = 8 * 1024 * 1024 * 1024;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('success', $result->status);
    }

    public function test_swap_warning(): void
    {
        $payload = $this->payload();
        $payload['memory']['swap_total_bytes'] = 2 * 1024 * 1024 * 1024;
        $payload['memory']['swap_free_bytes'] = (int) (0.4 * 1024 * 1024 * 1024);

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('warning', $result->status);
        $this->assertStringContainsString('Swap', $result->message);
    }

    public function test_reboot_is_warning(): void
    {
        $previous = $this->payload(['uptime_seconds' => 100000]);
        $current = $this->payload(['uptime_seconds' => 120]);

        $result = $this->evaluator()->evaluate($current, Server::defaultSettings(), $previous);

        $this->assertSame('warning', $result->status);
        $this->assertStringContainsString('перезагрузился', $result->message);
    }

    public function test_load_is_ignored_by_default(): void
    {
        $payload = $this->payload();
        $payload['cpu']['load15'] = 40;
        $payload['cpu']['cores'] = 2;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('success', $result->status);
    }

    public function test_load_alert_when_enabled(): void
    {
        $payload = $this->payload();
        $payload['cpu']['load15'] = 8;
        $payload['cpu']['cores'] = 2;
        $settings = Server::defaultSettings();
        $settings['load_alert_enabled'] = true;

        $result = $this->evaluator()->evaluate($payload, $settings);

        $this->assertSame('warning', $result->status);
        $this->assertStringContainsString('Нагрузка', $result->message);
    }

    public function test_silence_is_failed(): void
    {
        $result = $this->evaluator()->silence(35);

        $this->assertSame('failed', $result->status);
        $this->assertSame('Нет пинга 35 мин', $result->message);
    }

    public function test_worst_of_prefers_failed(): void
    {
        $payload = $this->payload();
        $payload['disks'][0]['used_bytes'] = 86 * 1024 * 1024 * 1024;
        $payload['memory']['available_bytes'] = 32 * 1024 * 1024;

        $result = $this->evaluator()->evaluate($payload, Server::defaultSettings());

        $this->assertSame('failed', $result->status);
    }
}
