<?php

namespace App\Planner;

use App\Enums\TripPlanStatus;
use App\Models\TripPlanItem;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Setelah login atau daftar, draf tamu dari browser yang sama dipindah ke akun (FILE 05).
 * Syaratnya: token cocok dengan hash, belum kedaluwarsa, dan belum punya pemilik.
 * Isi divalidasi ulang: item yang tempatnya tidak lagi terbit dibuang dan dilaporkan.
 */
final class ClaimGuestDrafts
{
    public const SAVE_AFTER_LOGIN = 'planner.save_after_login';

    public function __construct(private readonly GuestDrafts $drafts) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->claim($event->user);
    }

    /** @return array{claimed: int, dropped: int} */
    public function claim(User $user): array
    {
        $saveId = (int) session()->pull(self::SAVE_AFTER_LOGIN, 0);
        $claimed = 0;
        $dropped = 0;

        foreach ($this->drafts->all() as $plan) {
            DB::transaction(function () use ($plan, $user, $saveId, &$claimed, &$dropped) {
                $stale = TripPlanItem::query()
                    ->whereIn('trip_plan_day_id', $plan->days()->pluck('id'))
                    ->whereDoesntHave('place', fn ($q) => $q->published())
                    ->get();

                foreach ($stale as $item) {
                    $item->delete();
                    $dropped++;
                }

                $plan->forceFill([
                    'user_id' => $user->id,
                    'guest_token_hash' => null,
                    'expires_at' => null,
                    'status' => $plan->id === $saveId ? TripPlanStatus::Saved : TripPlanStatus::Draft,
                ])->save();

                $claimed++;
            });

            $this->drafts->forget($plan->id);
        }

        if ($dropped > 0) {
            session()->flash('status', "Rencana dipindah ke akun Anda. {$dropped} tempat dilepas karena tidak lagi terbit di arsip.");
        } elseif ($claimed > 0) {
            session()->flash('status', 'Rencana dipindah ke akun Anda.');
        }

        return ['claimed' => $claimed, 'dropped' => $dropped];
    }
}
