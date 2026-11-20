<?php

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\ScoringConfig;

$fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/cefr-parity.json'), true);

it('matches the Excel parity fixture', function (array $case) use ($fixture) {
    $config = ScoringConfig::fromArray(array_replace($fixture['defaults'][(string) $case['grade']], $case['override'] ?? []));
    $r = CefrCalculator::calculate($config, $case['scores'])->toArray();
    $e = $case['expected'];

    foreach ($e['percent'] as $skill => $pct) {
        $pct === null
            ? expect($r['percent'][$skill])->toBeNull()
            : expect($r['percent'][$skill])->toEqualWithDelta($pct, 1e-9);
    }

    expect($r['level'])->toBe($e['level'])
        ->and($r['overall_level'])->toBe($e['overall_level'])
        ->and($r['status'])->toBe($e['status'])
        ->and($r['strongest'])->toBe($e['strongest']);

    $e['overall_percent'] === null
        ? expect($r['overall_percent'])->toBeNull()
        : expect($r['overall_percent'])->toEqualWithDelta($e['overall_percent'], 1e-9);
})->with(array_combine(array_column($fixture['cases'], 'name'), array_map(fn ($c) => [$c], $fixture['cases'])));

it('builds the automatic report comment', function () use ($fixture) {
    $config = ScoringConfig::fromArray($fixture['defaults']['5']);
    $result = CefrCalculator::calculate($config, ['listening' => 15, 'reading' => 17, 'writing' => 11, 'speaking' => 13]);
    $comments = ['A2' => 'can understand and use English in everyday situations.'];

    expect(CefrCalculator::reportComment($result, 'Kevin Wijaya', null, $comments))
        ->toBe('Kevin Wijaya can understand and use English in everyday situations. Strongest skill this term: Reading.')
        ->and(CefrCalculator::reportComment($result, 'Kevin Wijaya', '  ', $comments, withStrongest: false))
        ->toBe('Kevin Wijaya can understand and use English in everyday situations.')
        ->and(CefrCalculator::reportComment($result, 'Kevin', 'Great job!', $comments))->toBe('Great job!');

    $incomplete = CefrCalculator::calculate($config, ['listening' => 15]);
    expect(CefrCalculator::reportComment($incomplete, 'Kevin', null, $comments))->toBeNull();
});
