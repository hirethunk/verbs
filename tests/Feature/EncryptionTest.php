<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Thunk\Verbs\Attributes\Autodiscovery\StateId;
use Thunk\Verbs\Attributes\Serialization\Encrypted;
use Thunk\Verbs\Event;
use Thunk\Verbs\Facades\Verbs;
use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\State;
use Thunk\Verbs\Support\PropertyEncryption;

it('stores #[Encrypted] event properties as ciphertext', function () {
    EncryptionTestEvent::fire(state_id: snowflake_id(), ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    $row = VerbEvent::sole();
    $data = json_decode($row->getRawOriginal('data'), true);

    expect($row->getRawOriginal('data'))->not->toContain('123-45-6789')
        ->and($data['name'])->toBe('Daniel')
        ->and(PropertyEncryption::isEnvelope($data['ssn']))->toBeTrue()
        ->and(Crypt::decryptString($data['ssn'][PropertyEncryption::MARKER]))->toBe('"123-45-6789"');
});

it('stores #[Encrypted] state properties as ciphertext in snapshots', function () {
    EncryptionTestEvent::fire(state_id: snowflake_id(), ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    $row = VerbSnapshot::sole();
    $data = json_decode($row->data, true);

    expect($row->data)->not->toContain('123-45-6789')
        ->and($data['name'])->toBe('Daniel')
        ->and(PropertyEncryption::isEnvelope($data['ssn']))->toBeTrue();
});

it('decrypts #[Encrypted] properties when hydrating events and snapshots', function () {
    EncryptionTestEvent::fire(state_id: snowflake_id(), ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    expect(VerbEvent::sole()->event()->ssn)->toBe('123-45-6789')
        ->and(VerbSnapshot::sole()->state()->ssn)->toBe('123-45-6789');
});

it('round-trips non-scalar #[Encrypted] properties', function () {
    EncryptionTestComplexEvent::fire(
        state_id: snowflake_id(),
        secrets: ['api_key' => 'abc123', 'nested' => ['pin' => 1234]],
    );
    Verbs::commit();

    $data = json_decode(VerbEvent::sole()->getRawOriginal('data'), true);

    expect(PropertyEncryption::isEnvelope($data['secrets']))->toBeTrue()
        ->and(VerbEvent::sole()->event()->secrets)->toBe(['api_key' => 'abc123', 'nested' => ['pin' => 1234]])
        ->and(VerbSnapshot::sole()->state()->secrets)->toBe(['api_key' => 'abc123', 'nested' => ['pin' => 1234]]);
});

it('hydrates legacy plaintext values for properties that are now #[Encrypted]', function () {
    $id = snowflake_id();

    EncryptionTestEvent::fire(state_id: $id, ssn: 'ignored', name: 'Daniel');
    Verbs::commit();

    VerbEvent::query()->toBase()->update([
        'data' => json_encode(['state_id' => $id, 'ssn' => 'plaintext-ssn', 'name' => 'Daniel']),
    ]);

    VerbSnapshot::query()->toBase()->update([
        'data' => json_encode(['ssn' => 'plaintext-ssn', 'name' => 'Daniel', 'secrets' => null]),
    ]);

    expect(VerbEvent::sole()->event()->ssn)->toBe('plaintext-ssn')
        ->and(VerbSnapshot::sole()->state()->ssn)->toBe('plaintext-ssn');
});

it('throws when an encrypted value cannot be decrypted', function () {
    $id = snowflake_id();

    EncryptionTestEvent::fire(state_id: $id, ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    VerbEvent::query()->toBase()->update([
        'data' => json_encode([
            'state_id' => $id,
            'ssn' => [PropertyEncryption::MARKER => 'tampered-ciphertext'],
            'name' => 'Daniel',
        ]),
    ]);

    expect(fn () => VerbEvent::sole()->event())->toThrow(DecryptException::class);
});

it('verifies snapshots with encrypted properties without false drift', function () {
    EncryptionTestEvent::fire(state_id: snowflake_id(), ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    $this->artisan('verbs:verify')
        ->expectsOutputToContain('All verified snapshots match their events.')
        ->assertExitCode(0);
});

it('still detects genuine drift in encrypted snapshots', function () {
    $id = snowflake_id();

    EncryptionTestEvent::fire(state_id: $id, ssn: '123-45-6789', name: 'Daniel');
    Verbs::commit();

    VerbSnapshot::query()->toBase()->update([
        'data' => json_encode(['ssn' => 'wrong-value', 'name' => 'Daniel', 'secrets' => null]),
    ]);

    $this->artisan('verbs:verify')->assertExitCode(1);
});

it('backfills legacy plaintext rows with verbs:encrypt', function () {
    $id = snowflake_id();

    EncryptionTestEvent::fire(state_id: $id, ssn: 'ignored', name: 'Daniel');
    Verbs::commit();

    VerbEvent::query()->toBase()->update([
        'data' => json_encode(['state_id' => $id, 'ssn' => 'plaintext-ssn', 'name' => 'Daniel']),
    ]);

    VerbSnapshot::query()->toBase()->update([
        'data' => json_encode(['ssn' => 'plaintext-ssn', 'name' => 'Daniel', 'secrets' => null]),
    ]);

    $this->artisan('verbs:encrypt')
        ->expectsOutputToContain('1 event(s) and 1 snapshot(s) encrypted.')
        ->assertExitCode(0);

    $event_data = json_decode(VerbEvent::sole()->getRawOriginal('data'), true);
    $snapshot_data = json_decode(VerbSnapshot::sole()->data, true);

    expect(PropertyEncryption::isEnvelope($event_data['ssn']))->toBeTrue()
        ->and(PropertyEncryption::isEnvelope($snapshot_data['ssn']))->toBeTrue()
        ->and($event_data['name'])->toBe('Daniel')
        ->and(VerbEvent::sole()->event()->ssn)->toBe('plaintext-ssn')
        ->and(VerbSnapshot::sole()->state()->ssn)->toBe('plaintext-ssn');
});

it('reports without writing on verbs:encrypt --dry-run', function () {
    $id = snowflake_id();

    EncryptionTestEvent::fire(state_id: $id, ssn: 'ignored', name: 'Daniel');
    Verbs::commit();

    VerbEvent::query()->toBase()->update([
        'data' => $plaintext = json_encode(['state_id' => $id, 'ssn' => 'plaintext-ssn', 'name' => 'Daniel']),
    ]);

    $this->artisan('verbs:encrypt', ['--dry-run' => true])
        ->expectsOutputToContain('1 event(s) and 0 snapshot(s) need encryption.')
        ->assertExitCode(0);

    expect(VerbEvent::sole()->getRawOriginal('data'))->toBe($plaintext);
});

class EncryptionTestState extends State
{
    #[Encrypted]
    public ?string $ssn = null;

    public ?string $name = null;

    #[Encrypted]
    public ?array $secrets = null;
}

class EncryptionTestEvent extends Event
{
    #[StateId(EncryptionTestState::class)]
    public int $state_id;

    #[Encrypted]
    public string $ssn;

    public string $name;

    public function apply(EncryptionTestState $state): void
    {
        $state->ssn = $this->ssn;
        $state->name = $this->name;
    }
}

class EncryptionTestComplexEvent extends Event
{
    #[StateId(EncryptionTestState::class)]
    public int $state_id;

    #[Encrypted]
    public array $secrets;

    public function apply(EncryptionTestState $state): void
    {
        $state->secrets = $this->secrets;
    }
}
