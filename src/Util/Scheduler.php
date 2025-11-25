<?php

declare(strict_types=1);

namespace GPHP\Util;

use Fiber;

class Scheduler {
    private static array $fibers = [];

    public static function run(callable $callback): void {
        $fiber = new Fiber($callback);
        self::$fibers[] = $fiber;
    }

    public static function tick(): void {
        foreach (self::$fibers as $key => $fiber) {
            try {
                if (!$fiber->isStarted()) {
                    $fiber->start();
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                } elseif ($fiber->isTerminated()) {
                    unset(self::$fibers[$key]);
                }
            } catch (\Throwable $e) {
                echo "[Scheduler] Error in fiber: " . $e->getMessage() . "\n";
                unset(self::$fibers[$key]);
            }
        }
    }
}
