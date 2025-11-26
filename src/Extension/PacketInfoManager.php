<?php

declare(strict_types=1);

namespace GPHP\Extension;

use GPHP\Protocol\HPacket;
use GPHP\Protocol\PacketDirection;

class PacketInfoManager {
    private array $packetInfoList = [];
    private static array $globalPacketMap = [];

    public static function resolveId(string $name): ?int {
        return self::$globalPacketMap[$name] ?? null;
    }

    public static function readFromPacket(HPacket $packet): self {
        $manager = new self();
        $count = $packet->readInt();
        for ($i = 0; $i < $count; $i++) {
            $headerId = $packet->readInt();
            $hash = $packet->readString();
            $name = $packet->readString();
            $structure = $packet->readString();
            $isOutgoing = $packet->readBool();
            $source = $packet->readString();

            $destination = $isOutgoing ? PacketDirection::TO_SERVER : PacketDirection::TO_CLIENT;

            $info = new PacketInfo($destination, $headerId, $hash, $name, $structure, $source);
            $manager->packetInfoList[] = $info;
            self::$globalPacketMap[$name] = $headerId;
            self::$globalPacketMap[$hash] = $headerId;
        }
        return $manager;
    }

    public function getAll(): array {
        return $this->packetInfoList;
    }

    /**
     * @return PacketInfo[]
     */
    public function getPacketInfos(PacketDirection $direction, int $headerId): array {
        $results = [];
        foreach ($this->packetInfoList as $info) {
            if ($info->headerId === $headerId && $info->destination === $direction) {
                $results[] = $info;
            }
        }
        return $results;
    }

    public function getPacketInfoByName(PacketDirection $direction, string $name): ?PacketInfo {
        foreach ($this->packetInfoList as $info) {
            if ($info->name === $name && $info->destination === $direction) {
                return $info;
            }
        }
        return null;
    }
}
