<?php

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\ScoringConfig;

$fixture = json_decode(file_get_contents(__DIR__.'/../fixtures/cefr-parity.json'), true);

it('matches the band parity fixture', function (array $case) use ($fixture) {
    $config = ScoringConfig::fromArray(array_replace($fixture['defaults'], $case['override'] ?? []));
    $r = CefrCalculator::calculate($config, $case['scores'])->toArray();
    $e = $case['expected'];

    expect($r['level'])->toBe($e['level'])
        ->and($r['overall_level'])->toBe($e['overall_level'])
        ->and($r['strongest'])->toBe($e['strongest'])
        ->and($r['total'])->toEqual($e['total']);
})->with(array_combine(array_column($fixture['cases'], 'name'), array_map(fn ($c) => [$c], $fixture['cases'])));

it('builds the automatic report comment', function () use ($fixture) {
    $config = ScoringConfig::fromArray($fixture['defaults']);
    $result = CefrCalculator::calculate($config, ['listening' => 15, 'reading' => 17, 'writing' => 11, 'speaking' => 13]);
    $comments = ['Intermediate' => 'can understand and use English in everyday situations.'];

    expect($result->overallLevel)->toBe('Intermediate')
        ->and(CefrCalculator::reportComment($result, 'Kevin Wijaya', null, $comments))
        ->toBe('Kevin Wijaya can understand and use English in everyday situations. Strongest skill this term: Reading.')
        ->and(CefrCalculator::reportComment($result, 'Kevin Wijaya', '  ', $comments, withStrongest: false))
        ->toBe('Kevin Wijaya can understand and use English in everyday situations.')
        ->and(CefrCalculator::reportComment($result, 'Kevin', 'Great job!', $comments))->toBe('Great job!');

    $incomplete = CefrCalculator::calculate($config, ['listening' => 15]);
    expect(CefrCalculator::reportComment($incomplete, 'Kevin', null, $comments))->toBeNull();
});
