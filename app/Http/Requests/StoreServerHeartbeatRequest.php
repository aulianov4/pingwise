<?php

namespace App\Http\Requests;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;

class StoreServerHeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->monitoredServer() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hostname' => ['required', 'string', 'max:255'],
            'uptime_seconds' => ['required', 'integer', 'min:0'],
            'cpu' => ['required', 'array'],
            'cpu.load1' => ['required', 'numeric'],
            'cpu.load5' => ['required', 'numeric'],
            'cpu.load15' => ['required', 'numeric'],
            'cpu.cores' => ['required', 'integer', 'min:1'],
            'memory' => ['required', 'array'],
            'memory.total_bytes' => ['required', 'integer', 'min:1'],
            'memory.available_bytes' => ['required', 'integer', 'min:0'],
            'memory.swap_total_bytes' => ['nullable', 'integer', 'min:0'],
            'memory.swap_free_bytes' => ['nullable', 'integer', 'min:0'],
            'disks' => ['required', 'array', 'min:1'],
            'disks.*.mount' => ['required', 'string', 'max:255'],
            'disks.*.total_bytes' => ['required', 'integer', 'min:0'],
            'disks.*.used_bytes' => ['required', 'integer', 'min:0'],
            'disks.*.inodes_total' => ['nullable', 'integer', 'min:0'],
            'disks.*.inodes_used' => ['nullable', 'integer', 'min:0'],
            'disks.*.fstype' => ['nullable', 'string', 'max:64'],
            'agent_version' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'hostname.required' => 'Укажите hostname.',
            'disks.required' => 'Передайте хотя бы одну точку монтирования.',
            'cpu.required' => 'Передайте данные по CPU.',
            'memory.required' => 'Передайте данные по памяти.',
        ];
    }

    public function monitoredServer(): ?Server
    {
        $server = $this->attributes->get('monitoredServer');

        return $server instanceof Server ? $server : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
