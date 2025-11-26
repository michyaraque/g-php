<?php

declare(strict_types=1);

namespace GPHP\Protocol;

class HMessage {
    private HPacket $packet;
    private int $index;
    private PacketDirection $direction;
    private bool $isBlocked = false;

    public function __construct(HPacket|string $packetOrString, PacketDirection $direction = PacketDirection::TO_CLIENT, int $index = 0) {
        if (is_string($packetOrString)) {
            $this->constructFromString($packetOrString);
        } else {
            $this->packet = $packetOrString;
            $this->direction = $direction;
            $this->index = $index;
        }
    }

    private function constructFromString(string $str): void {
        $parts = explode("\t", $str);

        $this->isBlocked = $parts[0] === "1";
        $this->index = (int)$parts[1];
        $this->direction = $parts[2] === "TOCLIENT" ? PacketDirection::TO_CLIENT : PacketDirection::TO_SERVER;

        $packetStr = implode("\t", array_slice($parts, 3));
        $this->packet = HPacket::fromString($packetStr);
    }

    public function getPacket(): HPacket {
        return $this->packet;
    }

    public function getIndex(): int {
        return $this->index;
    }

    public function getDestination(): PacketDirection {
        return $this->direction;
    }

    public function isBlocked(): bool {
        return $this->isBlocked;
    }

    public function setBlocked(bool $blocked): void {
        $this->isBlocked = $blocked;
    }

    public function stringify(): string {
        return ($this->isBlocked ? "1" : "0") . "\t" .
            $this->index . "\t" .
            ($this->direction === PacketDirection::TO_CLIENT ? "TOCLIENT" : "TOSERVER") . "\t" .
            $this->packet->stringify();
    }
}
