<?php

namespace App\Services\Identity;

use App\Enums\AuthClient;
use App\Events\Identity\NewSignIn;
use App\Events\Identity\SessionsEnded;
use App\Exceptions\ApiException;
use App\Models\RefreshToken;
use App\Models\SignIn;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token pairs: a Sanctum access token that lives fifteen minutes and a refresh token that
 * rotates on use. Every pair from one sign-in shares a family; reuse of a spent refresh
 * token is taken as theft and ends the family.
 */
class SessionTokens
{
    /**
     * A new pair. Without a family this is a sign-in, and it opens a session row for the device.
     */
    public function issue(User $user, AuthClient $client, ?string $installId = null, ?string $family = null, ?DeviceInfo $device = null): TokenPair
    {
        $refresh = Str::random(64);

        if ($family === null) {
            $family = (string) Str::uuid7();

            $this->open($user, $client, $family, $installId, $device ?? DeviceInfo::fromRequest(request(), $installId));
        }

        $access = $user->createToken(
            $client->value,
            [$client->value],
            now()->addMinutes(config('elevenmed.auth.access_minutes')),
        );

        RefreshToken::create([
            'user_id' => $user->id,
            'family' => $family,
            'token_hash' => self::hash($refresh),
            'access_token_id' => $access->accessToken->id,
            'install_id' => $installId,
            'client' => $client,
            'expires_at' => now()->addDays(config('elevenmed.auth.refresh_days')),
        ]);

        return new TokenPair($access->plainTextToken, $refresh, $family);
    }

    /**
     * Exchange a refresh token for a new pair in the same family. The old pair dies.
     *
     * @return array{0: User, 1: TokenPair}
     */
    public function rotate(string $refresh, AuthClient $client): array
    {
        $result = DB::transaction(function () use ($refresh, $client) {
            $row = RefreshToken::query()
                ->where('token_hash', self::hash($refresh))
                ->where('client', $client)
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->revoked_at !== null || $row->expires_at->isPast()) {
                return null;
            }

            if ($row->used_at !== null) {
                $this->revokeFamily($row->family);

                return null;
            }

            $row->update(['used_at' => now()]);

            SignIn::query()
                ->where('family', $row->family)
                ->update(['last_used_at' => now()]);

            PersonalAccessToken::whereKey($row->access_token_id)->delete();

            return [$row->user, $this->issue($row->user, $client, $row->install_id, $row->family)];
        });

        return $result ?? throw ApiException::unauthorized($client->refreshFailure());
    }

    /**
     * End the session a refresh token belongs to. Unknown tokens are ignored.
     */
    public function revokeByRefreshToken(string $refresh): void
    {
        $family = RefreshToken::where('token_hash', self::hash($refresh))->value('family');

        if ($family !== null) {
            $this->revokeFamily($family);
        }
    }

    /**
     * End the session the given access token belongs to, or just the token when it has none.
     */
    public function revokeByAccessToken(PersonalAccessToken $token): void
    {
        $family = $this->familyOf($token);

        $family === null ? $token->delete() : $this->revokeFamily($family);
    }

    /**
     * End every session of the user except one family, if named.
     */
    public function revokeAll(User $user, ?string $exceptFamily = null): void
    {
        $families = RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->when($exceptFamily !== null, fn($q) => $q->where('family', '!=', $exceptFamily))
            ->distinct()
            ->pluck('family');

        $families->each(fn(string $family) => $this->revokeFamily($family));

        $keep = $exceptFamily === null
            ? collect()
            : RefreshToken::where('family', $exceptFamily)->pluck('access_token_id');

        $user->tokens()
            ->whereNotIn('id', $keep->filter())
            ->delete();
    }

    public function familyOf(PersonalAccessToken $token): ?string
    {
        return RefreshToken::where('access_token_id', $token->id)->value('family');
    }

    public function revokeFamily(string $family): void
    {
        $rows = RefreshToken::query()
            ->where('family', $family)
            ->get(['id', 'user_id', 'access_token_id', 'install_id', 'revoked_at']);

        if ($rows->isEmpty()) {
            return;
        }

        PersonalAccessToken::whereIn('id', $rows->pluck('access_token_id')->filter())->delete();

        RefreshToken::query()
            ->where('family', $family)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        SignIn::query()
            ->where('family', $family)
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        $user = User::find($rows->first()->user_id);
        $installs = $rows->pluck('install_id')->filter()->unique()->values()->all();

        if ($user !== null && $installs !== []) {
            SessionsEnded::dispatch($user, $installs);
        }
    }

    /**
     * Record the sign-in; one from a device the account has not used before is announced.
     */
    private function open(User $user, AuthClient $client, string $family, ?string $installId, DeviceInfo $device): SignIn
    {
        $history = SignIn::where('user_id', $user->id);
        $isNew = $device->key !== null
            && $history->clone()->exists()
            && ! $history->clone()->where('device_key', $device->key)->exists();

        $signIn = SignIn::create([
            'user_id' => $user->id,
            'family' => $family,
            'client' => $client,
            'install_id' => $installId,
            'device_key' => $device->key,
            'device_name' => $device->name,
            'platform' => $device->platform,
            'signed_in_at' => now(),
            'last_used_at' => now(),
        ]);

        if ($isNew) {
            NewSignIn::dispatch($user, $signIn);
        }

        return $signIn;
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
