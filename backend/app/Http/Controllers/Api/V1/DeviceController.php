<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->devices()
            ->orderByDesc('last_sync_at')
            ->get();

        return $this->success($devices);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
            'name' => ['nullable', 'string', 'max:255'],
            'push_token' => ['nullable', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $maxDevices = config('sync.max_devices', 10);
        $currentCount = $request->user()->devices()
            ->where('device_id', '!=', $validated['device_id'])
            ->count();

        if ($currentCount >= $maxDevices) {
            return $this->error('Maximum device limit reached ('.$maxDevices.')', 422);
        }

        $device = $request->user()->devices()->updateOrCreate(
            ['device_id' => $validated['device_id']],
            $validated,
        );

        return $this->success($device, 'Device registered', 201);
    }

    public function show(Request $request, string $device): JsonResponse
    {
        return $this->success($request->user()->devices()->findOrFail($device));
    }

    public function update(Request $request, string $device): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'push_token' => ['sometimes', 'nullable', 'string', 'max:500'],
            'app_version' => ['sometimes', 'string', 'max:20'],
        ]);

        $deviceModel = $request->user()->devices()->findOrFail($device);
        $deviceModel->update($validated);

        return $this->success($deviceModel->fresh(), 'Device updated');
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        $deviceModel = $request->user()->devices()->findOrFail($device);

        // Revoke any tokens associated with this device
        $request->user()->tokens()
            ->where('name', 'like', '%'.$deviceModel->device_id.'%')
            ->delete();

        $deviceModel->delete();

        return $this->success(null, 'Device removed');
    }
}
