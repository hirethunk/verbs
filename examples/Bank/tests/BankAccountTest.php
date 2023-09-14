<?php

test('a bank account can be opened', function () {
    $this->actingAs(User::factory()->create())
        ->post(
            route('bank.accounts.store'),
            [
                'initial_deposit_in_cents' => 1000_00,
            ]
        )->assertStatus(201);
});