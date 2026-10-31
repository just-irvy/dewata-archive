<?php

namespace App\Planner;

use App\Models\TripPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Draf tamu (FILE 05 dan FILE 10). Token acak 48 karakter disimpan di cookie terenkripsi
 * HttpOnly milik browser itu. Database hanya menyimpan hash SHA-256-nya, sehingga isi
 * database yang bocor tidak membuka draf siapa pun. Draf berumur terbatas (expires_at).
 *
 * Bukan localStorage: token tidak pernah bisa dibaca JavaScript.
 */
final class GuestDrafts
{
    public const COOKIE = 'dewata_drafts';

    /** @var array<int, string>|null */
    private ?array $tokens = null;

    /** Cache token hanya berlaku untuk satu objek request, agar tidak bocor antar-request. */
    private ?int $requestId = null;

    private function request(): Request
    {
        $request = request();
        if ($this->requestId !== spl_object_id($request)) {
            $this->requestId = spl_object_id($request);
            $this->tokens = null;
        }

        return $request;
    }

    /** @return array<int, string> id rencana => token mentah */
    public function tokens(): array
    {
        $request = $this->request();
        if ($this->tokens !== null) {
            return $this->tokens;
        }

        $raw = json_decode((string) $request->cookie(self::COOKIE, ''), true);
        $tokens = [];

        foreach (is_array($raw) ? $raw : [] as $id => $token) {
            if (ctype_digit((string) $id) && is_string($token) && preg_match('/^[A-Za-z0-9]{48}$/', $token)) {
                $tokens[(int) $id] = $token;
            }
        }

        return $this->tokens = $tokens;
    }

    /** Menjadikan rencana baru sebagai draf tamu milik browser ini. */
    public function issue(TripPlan $plan): void
    {
        $token = Str::random(48);

        $plan->forceFill([
            'user_id' => null,
            'guest_token_hash' => self::hash($token),
            'expires_at' => now()->addDays(config('planner.guest_ttl_days')),
        ])->save();

        $tokens = $this->tokens();
        $tokens[$plan->id] = $token;

        // Batas draf per browser. Draf tertua dihapus karena tidak lagi bisa dibuka.
        $max = (int) config('planner.guest_max_drafts');
        if (count($tokens) > $max) {
            ksort($tokens);
            $drop = array_slice(array_keys($tokens), 0, count($tokens) - $max);
            foreach ($drop as $id) {
                TripPlan::query()->whereKey($id)->whereNull('user_id')
                    ->where('guest_token_hash', self::hash($tokens[$id]))->delete();
                unset($tokens[$id]);
            }
        }

        $this->store($tokens);
    }

    /** Draf tamu yang masih berlaku dan tokennya dipegang browser ini. */
    public function find(int $id): ?TripPlan
    {
        $token = $this->tokens()[$id] ?? null;

        if ($token === null) {
            return null;
        }

        return TripPlan::query()
            ->whereKey($id)
            ->whereNull('user_id')
            ->where('guest_token_hash', self::hash($token))
            ->notExpired()
            ->first();
    }

    /** @return Collection<int, TripPlan> */
    public function all(): Collection
    {
        return collect(array_keys($this->tokens()))
            ->map(fn (int $id) => $this->find($id))
            ->filter()
            ->sortByDesc('updated_at')
            ->values();
    }

    /** Memperpanjang masa simpan setelah draf diubah, dan menyegarkan umur cookie. */
    public function touch(TripPlan $plan): void
    {
        if ($plan->user_id !== null) {
            return;
        }

        $plan->forceFill(['expires_at' => now()->addDays(config('planner.guest_ttl_days'))])->save();
        $this->store($this->tokens());
    }

    public function forget(int $id): void
    {
        $tokens = $this->tokens();
        unset($tokens[$id]);
        $this->store($tokens);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @param  array<int, string>  $tokens */
    private function store(array $tokens): void
    {
        $this->request();
        $this->tokens = $tokens;

        if ($tokens === []) {
            Cookie::queue(Cookie::forget(self::COOKIE));

            return;
        }

        Cookie::queue(Cookie::make(
            self::COOKIE,
            json_encode($tokens),
            minutes: (int) config('planner.guest_ttl_days') * 24 * 60,
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: 'lax',
        ));
    }
}
