<?php

declare(strict_types=1);

use function Pest\Laravel\artisan;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Sammyjo20\LaravelHaystack\Models\Haystack;
use Sammyjo20\LaravelHaystack\Tests\Fixtures\Jobs\NameJob;

it('can delete a haystack by ID', function () {
    $haystack = Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->addJob(new NameJob('Steve'))
        ->create();

    $this->assertDatabaseHas('haystacks', ['id' => $haystack->id]);
    $this->assertDatabaseHas('haystack_bales', ['haystack_id' => $haystack->id]);

    artisan('haystacks:forget', ['id' => $haystack->id]);

    $this->assertDatabaseMissing('haystacks', ['id' => $haystack->id]);
    $this->assertDatabaseMissing('haystack_bales', ['haystack_id' => $haystack->id]);
});

it('can clear all haystacks', function () {
    Collection::times(10, fn () => Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->addJob(new NameJob('Steve'))
        ->create());

    $this->assertDatabaseCount('haystacks', 10);
    $this->assertDatabaseCount('haystack_bales', 20);

    artisan('haystacks:clear');

    $this->assertDatabaseCount('haystacks', 0);
    $this->assertDatabaseCount('haystack_bales', 0);
});

it('fails a haystack whose next bale can no longer be unserialized and keeps resuming the others', function () {
    Queue::fake();
    dontDeleteHaystack();

    $orphaned = Haystack::build()
        ->addJob(new NameJob('Sam'))
        ->catch(fn () => config()->set('failed', true))
        ->create();

    $healthy = Haystack::build()
        ->addJob(new NameJob('Steve'))
        ->create();

    $orphaned->bales()->update(['job' => 'O:28:"App\Jobs\DeletedStackableJob":0:{}']);

    Haystack::query()->update(['started_at' => now(), 'resume_at' => now()->subMinute()]);

    artisan('haystacks:resume')->assertSuccessful();

    expect($orphaned->refresh()->finished)->toBeTrue()
        ->and(config('failed'))->toBeTrue()
        ->and($healthy->refresh()->finished)->toBeFalse();

    Queue::assertPushed(NameJob::class, 1);
    Queue::assertPushed(NameJob::class, fn (NameJob $job) => $job->name === 'Steve');
});
