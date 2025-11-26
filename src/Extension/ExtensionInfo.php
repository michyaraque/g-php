<?php

declare(strict_types=1);

namespace GPHP\Extension;

readonly class ExtensionInfo {
    public function __construct(
        public string $title,
        public string $description,
        public string $version,
        public string $author
    ) {
    }
}
