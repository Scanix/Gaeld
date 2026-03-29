<?php

namespace Tests\Unit;

use App\Support\ValidatesFromArray;
use PHPUnit\Framework\TestCase;

class ValidatesFromArrayTest extends TestCase
{
    public function test_passes_when_all_required_keys_present(): void
    {
        $dto = TestDtoStub::fromArray([
            'name' => 'Test',
            'email' => 'test@example.com',
        ]);

        $this->assertEquals('Test', $dto->name);
        $this->assertEquals('test@example.com', $dto->email);
    }

    public function test_throws_when_required_key_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required keys');

        TestDtoStub::fromArray(['email' => 'test@example.com']);
    }

    public function test_exception_lists_missing_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('name');

        TestDtoStub::fromArray(['email' => 'test@example.com']);
    }
}

/**
 * @internal
 */
readonly class TestDtoStub
{
    use ValidatesFromArray;

    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name', 'email']);

        return new self(
            name: $data['name'],
            email: $data['email'],
        );
    }
}
