<?php

namespace Tests\Unit\Support;

use App\Support\PostDiff;
use PHPUnit\Framework\TestCase;

class PostDiffTest extends TestCase
{
    public function test_identical_input_returns_empty_diff(): void
    {
        $this->assertSame([], PostDiff::lineDiff('same content', 'same content'));
    }

    public function test_pure_addition_marks_added_lines(): void
    {
        $diff = PostDiff::lineDiff('hello', "hello\nworld");
        $this->assertSame([
            ['op' => 'unchanged', 'text' => 'hello'],
            ['op' => 'added', 'text' => 'world'],
        ], $diff);
    }

    public function test_pure_removal_marks_removed_lines(): void
    {
        $diff = PostDiff::lineDiff("hello\nworld", 'hello');
        $this->assertSame([
            ['op' => 'unchanged', 'text' => 'hello'],
            ['op' => 'removed', 'text' => 'world'],
        ], $diff);
    }

    public function test_replacement_emits_remove_then_add(): void
    {
        $diff = PostDiff::lineDiff("a\nb\nc", "a\nB\nc");
        $this->assertSame([
            ['op' => 'unchanged', 'text' => 'a'],
            ['op' => 'removed', 'text' => 'b'],
            ['op' => 'added', 'text' => 'B'],
            ['op' => 'unchanged', 'text' => 'c'],
        ], $diff);
    }

    public function test_oversize_input_falls_back_to_single_chunk(): void
    {
        $a = str_repeat("line\n", 1000);
        $b = str_repeat("other\n", 1000);
        $diff = PostDiff::lineDiff($a, $b);
        $this->assertCount(2, $diff);
        $this->assertSame('removed', $diff[0]['op']);
        $this->assertSame('added', $diff[1]['op']);
    }
}
