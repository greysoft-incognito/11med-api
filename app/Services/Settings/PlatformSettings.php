<?php

namespace App\Services\Settings;

use App\Enums\PlatformSetting;
use App\Models\User;
use App\Services\Audit\AuditLog;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Toneflix\DbConfig\Models\Configuration;

/**
 * The values the team changes at runtime, stored as dbconfig rows in the `platform` group and laid
 * over the config file on every boot and before every queued job. An unset row means the config
 * file's value, which is what `default` reports.
 */
#[Singleton]
class PlatformSettings
{
    public const GROUP = 'platform';

    private const CACHE_KEY = 'platform-settings';

    /** @var array<string, int|float|bool> The config file's values, kept once overridden. */
    private array $defaults = [];

    /** @var array<string, true> The settings currently laid over the config. */
    private array $applied = [];

    public function __construct(
        private readonly AuditLog $audit,
    ) {}

    /**
     * Lays the stored values over the config, and puts back the config file's value for one the team
     * reset since. Before the table exists (a fresh install mid-migration) there is nothing to lay.
     */
    public function apply(): void
    {
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn() => $this->stored());
        } catch (QueryException) {
            return;
        }

        foreach ($this->settings() as $setting) {
            $key = $setting->value;

            if (isset($stored[$key])) {
                $this->defaults[$key] ??= config($setting->configPath());
                $this->applied[$key] = true;

                config([$setting->configPath() => $setting->cast($stored[$key])]);
            } elseif (isset($this->applied[$key])) {
                unset($this->applied[$key]);

                config([$setting->configPath() => $this->defaults[$key]]);
            }
        }
    }

    /**
     * Every setting with its value and the config file's default.
     *
     * @return list<SettingValue>
     */
    public function all(): array
    {
        $this->apply();

        $rows = Configuration::query()
            ->where('group', self::GROUP)
            ->get()
            ->keyBy('key');

        return array_map(fn(PlatformSetting $setting) => $this->describe($setting, $rows->get($setting->storageKey())), $this->settings());
    }

    /**
     * Sets a value, or back to the config file's default with null (its row removed), and records
     * who changed it from what.
     */
    public function update(User $actor, PlatformSetting $setting, int|float|bool|null $value): SettingValue
    {
        $this->apply();

        $row = DB::transaction(function () use ($actor, $setting, $value) {
            $row = Configuration::query()
                ->where('key', $setting->storageKey())
                ->lockForUpdate()
                ->first();

            $row ??= tap(new Configuration, fn(Configuration $row) => $row->forceFill([
                'key' => $setting->storageKey(),
                'title' => $setting->label(),
                'type' => 'string',
                'group' => self::GROUP,
            ]));

            $before = config($setting->configPath());

            if ($value === null && $row->exists) {
                $row->delete();
            } elseif ($value !== null) {
                $row->value = $setting->store($value);
                $row->save();
            }

            Cache::forget(self::CACHE_KEY);

            $this->audit->record($actor, 'setting.changed', $row, [
                'key' => $setting->value,
                'from' => $before,
                'to' => $value ?? $this->defaultOf($setting),
            ]);

            return $row;
        });

        $this->apply();

        return $this->describe($setting, $row->exists ? $row : null);
    }

    /**
     * The settings whose config value exists in this build; one another module has not yet
     * configured is left out.
     *
     * @return list<PlatformSetting>
     */
    public function settings(): array
    {
        return array_values(array_filter(
            PlatformSetting::cases(),
            fn(PlatformSetting $setting) => $this->defaultOf($setting) !== null
        ));
    }

    /**
     * The config file's value, whether or not the team overrides it.
     */
    private function defaultOf(PlatformSetting $setting): int|float|bool|null
    {
        return $this->defaults[$setting->value] ?? config($setting->configPath());
    }

    private function describe(PlatformSetting $setting, ?Configuration $row): SettingValue
    {
        return new SettingValue(
            $setting,
            config($setting->configPath()),
            $this->defaultOf($setting),
            $row?->value !== null,
            $row?->updated_at,
        );
    }

    /**
     * @return array<string, string>
     */
    private function stored(): array
    {
        return Configuration::query()
            ->where('group', self::GROUP)
            ->whereNotNull('value')
            ->toBase()
            ->pluck('value', 'key')
            ->mapWithKeys(fn($value, string $key) => [str($key)->after('platform.')->toString() => (string) $value])
            ->all();
    }
}
