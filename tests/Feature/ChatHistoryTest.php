<?php

namespace Tests\Feature;

use App\Enums\PriceBasis;
use App\Enums\VerificationStatus;
use App\Models\ChatMessage;
use App\Models\Hotel;
use App\Models\Place;
use App\Services\Chat\ChatException;
use App\Services\Chat\ChatHistory;
use App\Services\Chat\ChatModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsArchiveData;
use Tests\Support\FakeChatModel;
use Tests\Support\WithoutViteAssets;
use Tests\TestCase;

class ChatHistoryTest extends TestCase
{
    use BuildsArchiveData;
    use RefreshDatabase;
    use WithoutViteAssets;

    protected function setUp(): void
    {
        parent::setUp();

        config(['chatbot.enabled' => true, 'services.gemini.key' => 'test-key']);
        Cache::flush();
    }

    private function fake(array $script): FakeChatModel
    {
        $fake = new FakeChatModel($script);
        $this->app->instance(ChatModel::class, $fake);

        return $fake;
    }

    private function hotel(string $name, int $price): Hotel
    {
        return Hotel::factory()->published()->create([
            'name' => $name,
            'price_min' => $price,
            'price_basis' => PriceBasis::Curated,
            'price_status' => VerificationStatus::Verified,
        ]);
    }

    public function test_guest_conversations_are_never_stored(): void
    {
        $this->fake([FakeChatModel::say('Halo tamu.')]);

        $this->postJson(route('chat.ask'), ['message' => 'halo arsip'])->assertOk()->assertJsonPath('saved', false);

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_a_logged_in_question_and_answer_are_stored_with_card_keys(): void
    {
        $user = $this->makeUser();
        $this->hotel('Hotel Sawah', 500000);
        $this->fake([
            FakeChatModel::call('search_hotels', ['price_max' => 700000]),
            FakeChatModel::say('Hotel Sawah cocok.'),
        ]);

        $this->actingAs($user)->postJson(route('chat.ask'), ['message' => 'hotel di bawah 700rb'])
            ->assertOk()->assertJsonPath('saved', true);

        $messages = ChatMessage::where('user_id', $user->id)->orderBy('id')->get();
        $this->assertSame(['user', 'model'], $messages->pluck('role')->all());
        $this->assertSame('hotel di bawah 700rb', $messages[0]->text);
        $this->assertSame('Hotel Sawah cocok.', $messages[1]->text);
        $this->assertSame(['hotel:'.Hotel::first()->slug], $messages[1]->card_keys);
    }

    public function test_for_logged_in_users_the_stored_history_is_used_and_browser_history_is_ignored(): void
    {
        $user = $this->makeUser();
        $user->chatMessages()->create(['role' => 'user', 'text' => 'pertanyaan tersimpan']);
        $user->chatMessages()->create(['role' => 'model', 'text' => 'jawaban tersimpan']);
        $fake = $this->fake([FakeChatModel::say('Lanjut.')]);

        $this->actingAs($user)->postJson(route('chat.ask'), [
            'message' => 'lanjutkan',
            'history' => [['role' => 'user', 'text' => 'RIWAYAT PALSU dari browser']],
        ])->assertOk();

        $sent = json_encode($fake->received[0]);
        $this->assertStringContainsString('pertanyaan tersimpan', $sent);
        $this->assertStringContainsString('jawaban tersimpan', $sent);
        $this->assertStringNotContainsString('RIWAYAT PALSU', $sent);
    }

    public function test_a_failed_answer_stores_nothing(): void
    {
        $user = $this->makeUser();
        $this->fake([new ChatException('quota', 429)]);

        $this->actingAs($user)->postJson(route('chat.ask'), ['message' => 'halo arsip'])->assertStatus(429);

        $this->assertSame(0, ChatMessage::count());
    }

    public function test_the_widget_restores_only_the_users_own_conversation(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $user->chatMessages()->create(['role' => 'user', 'text' => 'Percakapan milik saya']);
        $other->chatMessages()->create(['role' => 'user', 'text' => 'Rahasia orang lain']);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Percakapan milik saya')
            ->assertDontSee('Rahasia orang lain')
            ->assertSee('Lembar ini tersimpan di akun Anda');

        auth()->logout();
        $this->get('/')->assertOk()
            ->assertDontSee('Percakapan milik saya')
            ->assertSee('Lembar tamu hilang saat pindah halaman');
    }

    public function test_restored_cards_use_current_data_and_drop_unpublished_records(): void
    {
        $user = $this->makeUser();
        $hotel = $this->hotel('Hotel Harga Berubah', 500000);
        $hidden = Place::factory()->create(['slug' => 'tempat-draf']);
        $user->chatMessages()->create(['role' => 'user', 'text' => 'hotel?']);
        $user->chatMessages()->create(['role' => 'model', 'text' => 'Ini pilihannya.', 'card_keys' => ['hotel:'.$hotel->slug, 'place:tempat-draf', 'jahat:../../x']]);

        $hotel->update(['price_min' => 650000]);

        $messages = app(ChatHistory::class)->forWidget($user);
        $cards = $messages[1]['cards'];

        $this->assertCount(1, $cards);
        $this->assertStringContainsString('Rp650.000', $cards[0]['price']);
        $this->assertTrue($messages[0]['restored']);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertNotNull($hidden->id);
    }

    public function test_start_over_clears_only_the_users_own_messages(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $user->chatMessages()->create(['role' => 'user', 'text' => 'a']);
        $other->chatMessages()->create(['role' => 'user', 'text' => 'b']);

        $this->actingAs($user)->deleteJson(route('chat.clear'))->assertOk()->assertJsonPath('cleared', true);

        $this->assertSame(0, $user->chatMessages()->count());
        $this->assertSame(1, $other->chatMessages()->count());
    }

    public function test_start_over_requires_login_and_an_enabled_chatbot(): void
    {
        $this->deleteJson(route('chat.clear'))->assertUnauthorized();

        config(['chatbot.enabled' => false]);
        $this->actingAs($this->makeUser())->deleteJson(route('chat.clear'))->assertNotFound();
    }

    public function test_only_the_most_recent_messages_are_kept_per_user(): void
    {
        config(['chatbot.max_stored_messages' => 4]);
        $user = $this->makeUser();
        $this->fake([FakeChatModel::say('j1'), FakeChatModel::say('j2'), FakeChatModel::say('j3')]);

        foreach (['p1', 'p2', 'p3'] as $q) {
            $this->actingAs($user)->postJson(route('chat.ask'), ['message' => $q])->assertOk();
        }

        $this->assertSame(['p2', 'j2', 'p3', 'j3'], $user->chatMessages()->orderBy('id')->pluck('text')->all());
    }

    public function test_context_always_starts_with_a_user_turn(): void
    {
        config(['chatbot.max_history_messages' => 3]);
        $user = $this->makeUser();
        foreach ([['user', 'u1'], ['model', 'm1'], ['user', 'u2'], ['model', 'm2']] as [$role, $text]) {
            $user->chatMessages()->create(['role' => $role, 'text' => $text]);
        }

        $context = app(ChatHistory::class)->contextFor($user);

        $this->assertSame('user', $context[0]['role']);
        $this->assertSame(['u2', 'm2'], array_column($context, 'text'));
    }

    public function test_old_messages_are_purged_and_deleting_a_user_deletes_their_messages(): void
    {
        config(['chatbot.retention_days' => 30]);
        $user = $this->makeUser();
        $old = $user->chatMessages()->create(['role' => 'user', 'text' => 'lama']);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        $user->chatMessages()->create(['role' => 'user', 'text' => 'baru']);

        $this->artisan('archive:purge-chat')->expectsOutputToContain('1 pesan')->assertExitCode(0);
        $this->assertSame(['baru'], $user->chatMessages()->pluck('text')->all());

        $user->delete();
        $this->assertSame(0, ChatMessage::count());
    }
}
