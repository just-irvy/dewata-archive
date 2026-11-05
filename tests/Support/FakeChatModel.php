<?php

namespace Tests\Support;

use App\Services\Chat\ChatException;
use App\Services\Chat\ChatModel;

/** Model palsu berskrip: setiap panggilan generate() mengambil satu langkah dari antrean. */
final class FakeChatModel implements ChatModel
{
    /** @var array<int, array<string, mixed>|ChatException> */
    private array $script;

    /** @var array<int, array<string, mixed>> Semua `contents` yang diterima, per panggilan. */
    public array $received = [];

    public int $calls = 0;

    /** @param  array<int, array<string, mixed>|ChatException>  $script */
    public function __construct(array $script)
    {
        $this->script = $script;
    }

    public static function say(string $text): array
    {
        return ['role' => 'model', 'parts' => [['text' => $text]]];
    }

    public static function call(string $name, array $args = []): array
    {
        return ['role' => 'model', 'parts' => [['functionCall' => ['name' => $name, 'args' => $args]]]];
    }

    public function generate(string $systemPrompt, array $contents, array $functionDeclarations): array
    {
        $this->received[] = $contents;
        $this->calls++;

        $step = array_shift($this->script) ?? self::say('Selesai.');

        if ($step instanceof ChatException) {
            throw $step;
        }

        return $step;
    }
}
