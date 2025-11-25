<?php

declare(strict_types=1);

namespace GPHP\Protocol;

enum PacketDirection: string {
    case TO_CLIENT = 'TOCLIENT';
    case TO_SERVER = 'TOSERVER';
}

enum GEarthOutgoingPacket: int {
    case INFO = 1;
    case MANIPULATED_PACKET = 2;
    case REQUEST_FLAGS = 3;
    case SEND_MESSAGE = 4;
    case PACKET_TO_STRING = 20;

    case STRING_TO_PACKET = 21;
    case EXTENSION_CONSOLE_LOG = 98;
}

enum GEarthIncomingPacket: int {
    case ON_DOUBLE_CLICK = 1;
    case INFO_REQUEST = 2;
    case PACKET_INTERCEPT = 3;
    case FLAGS_CHECK = 4;
    case CONNECTION_START = 5;
    case CONNECTION_END = 6;
    case INIT = 7;
    case PACKET_TO_STRING_RESPONSE = 20;
    case STRING_TO_PACKET_RESPONSE = 21;
}

class HPacket {
    private string $buffer;
    private int $readPosition = 6;
    private bool $isEdited = false;
    private ?PacketDirection $destination = null;

    public function __construct(string|int|HPacket|\BackedEnum $headerOrData, string $data = '') {
        if ($headerOrData instanceof \BackedEnum) {
            $className = get_class($headerOrData);
            if (str_ends_with($className, 'Outgoing')) {
                $this->destination = PacketDirection::TO_SERVER;
            } elseif (str_ends_with($className, 'Incoming')) {
                $this->destination = PacketDirection::TO_CLIENT;
            }
            $headerOrData = $headerOrData->value;
        }

        if ($headerOrData instanceof HPacket) {
            $this->buffer = $headerOrData->buffer;
            $this->isEdited = $headerOrData->isEdited;
            $this->readPosition = $headerOrData->readPosition;
        } elseif (is_int($headerOrData)) {
            $this->buffer = pack('N', 0) . pack('n', $headerOrData) . $data;
            $this->fixLength();
        } else {
            if (class_exists(\GPHP\Extension\PacketInfoManager::class)) {
                $resolvedId = \GPHP\Extension\PacketInfoManager::resolveId($headerOrData);
                if ($resolvedId !== null) {
                    $this->buffer = pack('N', 0) . pack('n', $resolvedId) . $data;
                    $this->fixLength();
                    return;
                }
            }

            $this->buffer = $headerOrData;
            $this->readPosition = 6;
        }
    }

    public static function fromBuffer(string $buffer): self {
        return new self($buffer);
    }

    public static function fromString(string $str): self {
        $isEdited = $str[0] === '1';
        $buffer = substr($str, 1);
        $packet = new self($buffer);
        $packet->isEdited = $isEdited;
        return $packet;
    }

    public function getBytes(): string {
        return $this->buffer;
    }

    public function length(): int {
        return strlen($this->buffer);
    }

    public function getBytesLength(): int {
        return strlen($this->buffer);
    }

    public function headerId(): int {
        return unpack('n', substr($this->buffer, 4, 2))[1];
    }

    public function isEdited(): bool {
        return $this->isEdited;
    }

    public function fixLength(): void {
        $len = strlen($this->buffer) - 4;
        $this->replaceInt(0, $len);
    }

    public function getDestination(): ?PacketDirection {
        return $this->destination;
    }

    public function setDestination(PacketDirection $destination): void {
        $this->destination = $destination;
    }

    // --- Read Methods ---

    public function read(string $format): mixed {
        $results = [];
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $char = $format[$i];
            $results[] = match ($char) {
                'b' => $this->readByte(),
                's' => $this->readShort(),
                'u' => $this->readUShort(),
                'i' => $this->readInt(),
                'l' => $this->readLong(),
                'f' => $this->readFloat(),
                'd' => $this->readDouble(),
                'S' => $this->readString(),
                'L' => $this->readLongString(),
                'B' => $this->readBool(),
                default => null
            };
        }
        return $len === 1 ? $results[0] : $results;
    }

    public function readByte(?int $pos = null): int {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 1;
        }
        return unpack('C', substr($this->buffer, $pos, 1))[1];
    }

    public function readShort(?int $pos = null): int {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 2;
        }
        $val = unpack('n', substr($this->buffer, $pos, 2))[1];
        if ($val >= 32768) $val -= 65536;
        return $val;
    }

    public function readUShort(?int $pos = null): int {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 2;
        }
        if ($pos + 2 > strlen($this->buffer)) {
            return 0;
        }
        return unpack('n', substr($this->buffer, $pos, 2))[1];
    }

    public function readInt(?int $pos = null): int {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 4;
        }
        $val = unpack('N', substr($this->buffer, $pos, 4))[1];
        if ($val >= 2147483648) $val -= 4294967296;
        return $val;
    }

    public function readLong(?int $pos = null): int {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 8;
        }
        return unpack('J', substr($this->buffer, $pos, 8))[1];
    }

    public function readFloat(?int $pos = null): float {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 4;
        }
        return unpack('G', substr($this->buffer, $pos, 4))[1];
    }

    public function readDouble(?int $pos = null): float {
        if ($pos === null) {
            $pos = $this->readPosition;
            $this->readPosition += 8;
        }
        return unpack('E', substr($this->buffer, $pos, 8))[1];
    }

    public function readString(?int $pos = null): string {
        if ($pos === null) {
            $len = $this->readUShort();
            $str = substr($this->buffer, $this->readPosition, $len);
            $this->readPosition += $len;
            return $str;
        } else {
            $len = $this->readUShort($pos);
            return substr($this->buffer, $pos + 2, $len);
        }
    }

    public function readLongString(?int $pos = null): string {
        if ($pos === null) {
            $len = $this->readInt();
            $str = substr($this->buffer, $this->readPosition, $len);
            $this->readPosition += $len;
            return $str;
        } else {
            $len = $this->readInt($pos);
            return substr($this->buffer, $pos + 4, $len);
        }
    }

    public function readBool(?int $pos = null): bool {
        return $this->readByte($pos) === 1;
    }

    // --- Append Methods ---

    public function appendByte(int $b): self {
        $this->isEdited = true;
        $this->buffer .= pack('C', $b);
        $this->fixLength();
        return $this;
    }

    public function appendShort(int $s): self {
        $this->isEdited = true;
        $this->buffer .= pack('n', $s);
        $this->fixLength();
        return $this;
    }

    public function appendUShort(int $s): self {
        $this->isEdited = true;
        $this->buffer .= pack('n', $s);
        $this->fixLength();
        return $this;
    }

    public function appendInt(int $i): self {
        $this->isEdited = true;
        $this->buffer .= pack('N', $i);
        $this->fixLength();
        return $this;
    }

    public function appendLong(int $l): self {
        $this->isEdited = true;
        $this->buffer .= pack('J', $l);
        $this->fixLength();
        return $this;
    }

    public function appendFloat(float $f): self {
        $this->isEdited = true;
        $this->buffer .= pack('G', $f);
        $this->fixLength();
        return $this;
    }

    public function appendDouble(float $d): self {
        $this->isEdited = true;
        $this->buffer .= pack('E', $d);
        $this->fixLength();
        return $this;
    }

    public function appendString(string $s): self {
        $this->isEdited = true;
        $this->buffer .= pack('n', strlen($s)) . $s;
        $this->fixLength();
        return $this;
    }

    public function appendLongString(string $s): self {
        $this->isEdited = true;
        $this->buffer .= pack('N', strlen($s)) . $s;
        $this->fixLength();
        return $this;
    }

    public function appendBool(bool $b): self {
        return $this->appendByte($b ? 1 : 0);
    }

    public function appendBytes(string $bytes): self {
        $this->isEdited = true;
        $this->buffer .= $bytes;
        $this->fixLength();
        return $this;
    }

    // --- Replace Methods ---

    public function replaceByte(int $pos, int $b): self {
        $this->isEdited = true;
        $this->buffer[$pos] = pack('C', $b);
        return $this;
    }

    public function replaceShort(int $pos, int $s): self {
        $this->isEdited = true;
        $packed = pack('n', $s);
        $this->buffer[$pos] = $packed[0];
        $this->buffer[$pos + 1] = $packed[1];
        return $this;
    }

    public function replaceInt(int $pos, int $i): self {
        $this->isEdited = true;
        $packed = pack('N', $i);
        for ($j = 0; $j < 4; $j++) $this->buffer[$pos + $j] = $packed[$j];
        return $this;
    }

    public function replaceString(int $pos, string $s): self {
        $this->isEdited = true;
        $oldLen = $this->readUShort($pos);
        $newLen = strlen($s);

        $before = substr($this->buffer, 0, $pos);
        $after = substr($this->buffer, $pos + 2 + $oldLen);

        $this->buffer = $before . pack('n', $newLen) . $s . $after;
        $this->fixLength();
        return $this;
    }

    // --- Misc ---

    public function toBytes(): string {
        return $this->buffer;
    }

    public function __toString(): string {
        return bin2hex($this->buffer);
    }

    public function stringify(): string {
        return ($this->isEdited ? "1" : "0") . $this->buffer;
    }

    public function getReadPosition(): int {
        return $this->readPosition;
    }

    public function setReadPosition(int $pos): void {
        $this->readPosition = $pos;
    }

    public function resetReadIndex(): void {
        $this->readPosition = 6;
    }
}

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
