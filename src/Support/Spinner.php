<?php

declare(strict_types=1);

namespace CarthageSoftware\ToolChainBenchmarks\Support;

use Psl\DateTime;
use Psl\IO;
use Psl\Iter;
use Psl\Math;
use Psl\Str;

/**
 * In-place terminal line animation using braille spinner frames.
 *
 * The spinner overwrites its own line on each tick via \r + CLEAR_LINE.
 * It does not own any async loop — the caller drives tick() externally.
 */
final class Spinner
{
    /** @var list<string> */
    private const array FRAMES = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];

    private int $frame = 0;

    private DateTime\Timestamp $startTime;

    private string $message;

    public function __construct(
        string $message,
        private readonly string $indent = '    ',
    ) {
        $this->message = $message;
        $this->startTime = DateTime\Timestamp::now();
        $this->render();
    }

    /**
     * Advance the spinner frame and optionally update the message.
     */
    public function tick(?string $message = null): void
    {
        if ($message !== null) {
            $this->message = $message;
        }

        $this->frame++;
        $this->render();
    }

    /**
     * Replace the spinner line with a green checkmark and elapsed time.
     */
    public function succeed(string $message): void
    {
        $this->finalize(Style::GREEN, Style::CHECK, $message);
    }

    /**
     * Replace the spinner line with a red cross and elapsed time.
     */
    public function fail(string $message): void
    {
        $this->finalize(Style::RED, Style::CROSS, $message);
    }

    private function finalize(string $color, string $symbol, string $message): void
    {
        IO\write_line(
            "\r%s%s%s%s%s %s %s(%s)%s",
            Style::CLEAR_LINE,
            $this->indent,
            $color,
            $symbol,
            Style::NC,
            $message,
            Style::DIM,
            $this->elapsed(),
            Style::NC,
        );
    }

    private function render(): void
    {
        $frame = self::FRAMES[$this->frame % Iter\count(self::FRAMES)];

        IO\write(
            "\r%s%s%s%s%s %s %s(%s)%s",
            Style::CLEAR_LINE,
            $this->indent,
            Style::CYAN,
            $frame,
            Style::NC,
            $this->message,
            Style::DIM,
            $this->elapsed(),
            Style::NC,
        );
    }

    private function elapsed(): string
    {
        $duration = DateTime\Timestamp::now()->since($this->startTime);
        $s = (int) $duration->getTotalSeconds();
        if ($s < 60) {
            return Str\format('%ds', $s);
        }

        $m = (int) Math\div($s, 60);

        return Str\format('%dm %ds', $m, $s % 60);
    }
}
