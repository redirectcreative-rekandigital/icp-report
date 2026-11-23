<?php

use App\Domain\Cefr\CefrCalculator;
use App\Domain\Cefr\Level;
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
        ->and(CefrCalculator::reportComment($result, 'Kevin', 'Great job!', $comments))->toBe('Kevin great job!');

    $incomplete = CefrCalculator::calculate($config, ['listening' => 15]);
    expect(CefrCalculator::reportComment($incomplete, 'Kevin', null, $comments))->toBeNull();
});

it('puts the student name before the teacher comment', function (array $case) {
    expect(CefrCalculator::withName($case['student'], $case['comment']))->toBe($case['expected']);
})->with(array_combine(array_column($fixture['teacher_comments'], 'name'), array_map(fn ($c) => [$c], $fixture['teacher_comments'])));

it('lists National Geographic sub-levels and maps them to their category', function () {
    expect(Level::subValues())->toHaveCount(13)
        ->and(Level::subLevelGroups()['Pre-A1'])->toBe(['Pre-A1'])
        ->and(Level::subLevelGroups()['B2'])->toBe(['B2.1', 'B2.2', 'B2.3'])
        ->and(Level::ofSub('A2.3'))->toBe(Level::A2)
        ->and(Level::ofSub('Pre-A1'))->toBe(Level::PreA1)
        ->and(Level::ofSub('A2'))->toBeNull() // a category is not a sub-level
        ->and(Level::ofSub('C1'))->toBeNull();
});
