<?php

namespace App\Imports;

final class MappedRow
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array{level: string, code: string, message: string}>  $issues
     */
    public function __construct(
        public readonly int $rowNumber,
        public readonly ?string $entity,
        public readonly ?string $externalId,
        public array $payload,
        public array $issues = [],
        public string $action = 'create',
    ) {}

    public function hasError(): bool
    {
        return (bool) array_filter($this->issues, fn (array $i) => $i['level'] === 'error');
    }

    public function status(): string
    {
        if ($this->hasError()) {
            return 'error';
        }

        return array_filter($this->issues, fn (array $i) => $i['level'] === 'warning') ? 'warning' : 'valid';
    }

    public function add(string $level, string $code, string $message): void
    {
        $this->issues[] = ['level' => $level, 'code' => $code, 'message' => $message];
    }
}
