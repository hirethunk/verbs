<?php

use Thunk\Verbs\Models\VerbEvent;
use Thunk\Verbs\Models\VerbSnapshot;
use Thunk\Verbs\Models\VerbStateEvent;

test('VerbEvent table name can be configured', function () {
    $expected_table_name = 'verb_events';

    $verb_model = new VerbEvent;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});

test('VerbEvent table name can be configured with different table name', function () {
    $expected_table_name = 'sys_verb_events';

    config()->set('verbs.tables.events', $expected_table_name);

    $verb_model = new VerbEvent;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});

test('VerbSnapshot table name can be configured', function () {
    $expected_table_name = 'verb_snapshots';

    $verb_model = new VerbSnapshot;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});

test('VerbSnapshot table name can be configured with different table name', function () {
    $expected_table_name = 'sys_verb_snapshots';

    config(['verbs.tables.snapshots' => $expected_table_name]);

    $verb_model = new VerbSnapshot;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});

test('VerbStateEvent table name can be configured', function () {
    $expected_table_name = 'verb_state_events';

    $verb_model = new VerbStateEvent;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});

test('VerbStateEvent table name can be configured with different table name', function () {
    $expected_table_name = 'sys_verb_state_events';

    config(['verbs.tables.state_events' => $expected_table_name]);

    $verb_model = new VerbStateEvent;
    $actual_table_name = $verb_model->getTable();

    expect($expected_table_name)->toBe($actual_table_name);
});
