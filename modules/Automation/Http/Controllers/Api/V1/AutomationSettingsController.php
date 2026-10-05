<?php

namespace Modules\Automation\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Modules\Automation\Domain\AutomationKey;
use Modules\Automation\Http\Requests\UpdateAutomationSettingRequest;
use Modules\Automation\Services\AutomationGradeLimitExceeded;
use Modules\Automation\Services\AutomationSettingsManager;

/**
 * Spec §18's five automations, read and toggled one at a time — there is no bulk save, each
 * toggle takes effect immediately, the same shape `/admin/booking`'s business-hours card and
 * other single-setting screens in this product already use.
 */
final class AutomationSettingsController
{
    public function __construct(private readonly AutomationSettingsManager $settings) {}

    public function index(): JsonResponse
    {
        $rows = array_map(
            static fn (array $row): array => [
                'key' => $row['key']->value,
                'label' => $row['key']->label(),
                'description' => $row['key']->description(),
                'needs_delay' => $row['key']->needsDelay(),
                'is_enabled' => $row['is_enabled'],
                'delay_days' => $row['delay_days'],
            ],
            array_values($this->settings->all()),
        );

        return response()->json([
            'data' => $rows,
            'meta' => [
                'max_enabled' => $this->settings->maxEnabled(),
                'currently_enabled' => count(array_filter($rows, static fn (array $r): bool => $r['is_enabled'])),
            ],
        ]);
    }

    public function update(UpdateAutomationSettingRequest $request, AutomationKey $key): JsonResponse
    {
        try {
            $setting = $this->settings->update(
                $key,
                $request->boolean('is_enabled'),
                $request->integer('delay_days') ?: null,
            );
        } catch (AutomationGradeLimitExceeded $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'key' => $key->value,
                'is_enabled' => $setting->is_enabled,
                'delay_days' => $setting->delay_days,
            ],
        ]);
    }
}
