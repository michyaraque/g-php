<?php

declare(strict_types=1);

namespace GPHP\Extension;

use Socket;
use GPHP\Protocol\HPacket;
use GPHP\Protocol\HMessage;
use GPHP\Protocol\PacketDirection;
use GPHP\Protocol\GEarthIncomingPacket;
use GPHP\Protocol\GEarthOutgoingPacket;

readonly class HostInfo {
    public function __construct(
        public string $host,
        public int $port,
        public string $version,
        public string $clientIdentifier,
        public string $clientType
    ) {
    }
}

readonly class ExtensionInfo {
    public function __construct(
        public string $title,
        public string $description,
        public string $version,
        public string $author
    ) {
    }
}

readonly class PacketInfo {
    public function __construct(
        public PacketDirection $destination,
        public int $headerId,
        public string $hash,
        public string $name,
        public string $structure,
        public string $source
    ) {
    }
}

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

class Extension {
    private ?Socket $socket = null;
    private array $args = [];
    private array $incomingListeners = []; // Map<int, callable[]>
    private array $outgoingListeners = []; // Map<int, callable[]>
    private ?PacketInfoManager $packetInfoManager = null;

    private bool $isRunning = false;

    public function __construct(
        public readonly ExtensionInfo $info
    ) {
        $this->parseArgs();
    }

    private function parseArgs(): void {
        global $argv;
        $count = count($argv);
        for ($i = 0; $i < $count; $i++) {
            if ($argv[$i] === '-p' && isset($argv[$i + 1])) $this->args['port'] = (int)$argv[$i + 1];
            if ($argv[$i] === '-f' && isset($argv[$i + 1])) $this->args['file'] = $argv[$i + 1];
            if ($argv[$i] === '-c' && isset($argv[$i + 1])) $this->args['cookie'] = $argv[$i + 1];
        }
        if (!isset($this->args['port'])) $this->args['port'] = 9092;
        if (!isset($this->args['host'])) $this->args['host'] = '127.0.0.1';
    }

    public function on(string $event, callable $callback): void {
        if (!isset($this->incomingListeners[$event])) {
            $this->incomingListeners[$event] = [];
        }
        $this->incomingListeners[$event][] = $callback;
    }

    public function onConnect(callable $callback): void {
        $this->on('connect', $callback);
    }

    public function onDisconnect(callable $callback): void {
        $this->on('end', $callback);
    }

    public function onInit(callable $callback): void {
        $this->on('init', $callback);
    }

    public function onDoubleClick(callable $callback): void {
        $this->on('double_click', $callback);
    }

    public function intercept(PacketDirection|\BackedEnum $directionOrEnum, int|string|callable $headerIdOrNameOrCallback, ?callable $callback = null): void {
        if ($directionOrEnum instanceof \BackedEnum) {
            $callback = $headerIdOrNameOrCallback;
            $name = $directionOrEnum->value;

            $className = get_class($directionOrEnum);
            if (str_ends_with($className, 'Incoming')) {
                $direction = PacketDirection::TO_CLIENT;
            } elseif (str_ends_with($className, 'Outgoing')) {
                $direction = PacketDirection::TO_SERVER;
            } else {
                return;
            }

            $this->interceptByNameOrHash($direction, $name, $callback);
            return;
        }

        $direction = $directionOrEnum;
        $headerIdOrName = $headerIdOrNameOrCallback;

        if (is_string($headerIdOrName)) {
            $this->interceptByNameOrHash($direction, $headerIdOrName, $callback);
            return;
        }

        if ($direction === PacketDirection::TO_CLIENT) {
            $this->incomingListeners[$headerIdOrName][] = $callback;
        } else {
            $this->outgoingListeners[$headerIdOrName][] = $callback;
        }
    }

    public function getPacketInfo(PacketDirection $direction, string $name): ?PacketInfo {
        return $this->packetInfoManager?->getPacketInfoByName($direction, $name);
    }

    public function run(): void {
        echo "[G-PHP] Starting extension '{$this->info->title}'...\n";

        $this->socket = \socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            die("Socket create failed: " . \socket_strerror(\socket_last_error()));
        }

        // Disable Nagle's algorithm for faster packets
        \socket_set_option($this->socket, SOL_TCP, TCP_NODELAY, 1);

        echo "[G-PHP] Connecting to {$this->args['host']}:{$this->args['port']}...\n";

        $result = @\socket_connect($this->socket, $this->args['host'], $this->args['port']);
        if ($result === false) {
            die("Could not connect to G-Earth at {$this->args['host']}:{$this->args['port']}. Ensure it is running.\n");
        }

        $this->isRunning = true;

        $this->requestFlags();

        $this->loop();
    }

    private function sendExtensionInfo(): void {
        $packet = new HPacket(GEarthOutgoingPacket::INFO->value);
        $packet->appendString($this->info->title)
            ->appendString($this->info->author)
            ->appendString($this->info->version)
            ->appendString($this->info->description)
            ->appendBool(true)
            ->appendBool(isset($this->args['file']))
            ->appendString($this->args['file'] ?? "")
            ->appendString($this->args['cookie'] ?? "")
            ->appendBool(true)
            ->appendBool(true);

        $this->sendRaw($packet);
        echo "[G-PHP] Handshake sent.\n";
    }

    private function requestFlags(): void {
        $packet = new HPacket(GEarthOutgoingPacket::REQUEST_FLAGS->value);
        $this->sendRaw($packet);
    }

    private string $readBuffer = '';

    private function loop(): void {
        socket_set_nonblock($this->socket);

        while ($this->isRunning) {
            $read = [$this->socket];
            $write = null;
            $except = null;

            $num = @socket_select($read, $write, $except, 0, 10000);

            if ($num > 0) {
                $chunk = @socket_read($this->socket, 4096);
                if ($chunk === false || $chunk === '') {
                    $this->isRunning = false;
                    break;
                }
                $this->readBuffer .= $chunk;
                $this->processBuffer();
            }

            \GPHP\Util\Scheduler::tick();
        }

        echo "[G-PHP] Disconnected from G-Earth socket.\n";
        if ($this->socket) \socket_close($this->socket);
    }

    private function processBuffer(): void {
        while (strlen($this->readBuffer) >= 4) {
            $lenBuf = substr($this->readBuffer, 0, 4);
            $length = unpack('N', $lenBuf)[1];

            if (strlen($this->readBuffer) < 4 + $length) {
                break;
            }

            $payload = substr($this->readBuffer, 4, $length);
            $this->readBuffer = substr($this->readBuffer, 4 + $length);

            $packet = HPacket::fromBuffer($lenBuf . $payload);
            $this->handlePacket($packet);
        }
    }
    private function handlePacket(HPacket $packet): void {
        $headerId = $packet->headerId();

        switch ($headerId) {
            case GEarthIncomingPacket::INFO_REQUEST->value:
                $this->sendExtensionInfo();
                break;

            case GEarthIncomingPacket::PACKET_INTERCEPT->value:
                $packetString = $packet->readLongString();
                $hMessage = new HMessage($packetString);

                $this->modifyMessage($hMessage);

                $response = new HPacket(GEarthOutgoingPacket::MANIPULATED_PACKET->value);
                $response->appendLongString($hMessage->stringify());
                $this->sendRaw($response);
                break;

            case GEarthIncomingPacket::CONNECTION_START->value:
                $host = $packet->readString();
                $port = $packet->readInt();
                $version = $packet->readString();
                $clientIdentifier = $packet->readString();
                $clientType = $packet->readString();

                $this->packetInfoManager = PacketInfoManager::readFromPacket($packet);

                $hostInfo = new HostInfo($host, $port, $version, $clientIdentifier, $clientType);
                $this->emit('connect', $hostInfo);
                $this->generateDefinitions();
                break;

            case GEarthIncomingPacket::CONNECTION_END->value:
                $this->emit('end');
                break;

            case GEarthIncomingPacket::INIT->value:
                $delayedInit = false;
                $host = "";
                $port = 0;
                $version = "";
                $clientIdentifier = "";

                try {
                    $delayedInit = $packet->readBool();
                    $host = $packet->readString();
                    $port = $packet->readInt();
                    $packet->readString(); // type
                    $version = $packet->readString();
                    $clientIdentifier = $packet->readString();
                    $packet->readString(); // clientType
                } catch (\Throwable $e) {
                }

                $this->emit('init', [
                    'delayed_init' => $delayedInit,
                    'host' => $host,
                    'port' => $port,
                    'version' => $version,
                    'clientIdentifier' => $clientIdentifier
                ]);
                break;

            case GEarthIncomingPacket::ON_DOUBLE_CLICK->value:
                $this->emit('double_click');
                break;
        }
    }

    private function modifyMessage(HMessage $hMessage): void {
        $headerId = $hMessage->getPacket()->headerId();
        $direction = $hMessage->getDestination();

        $listeners = $direction === PacketDirection::TO_CLIENT ? $this->incomingListeners : $this->outgoingListeners;

        // check by id
        if (isset($listeners[$headerId])) {
            foreach ($listeners[$headerId] as $cb) {
                $cb($hMessage);
            }
        }

        // check by name or hash
        if ($this->packetInfoManager) {
            $infos = $this->packetInfoManager->getPacketInfos($direction, $headerId);
            foreach ($infos as $info) {
                if (isset($listeners[$info->name])) {
                    foreach ($listeners[$info->name] as $cb) $cb($hMessage);
                }
                if (isset($listeners[$info->hash])) {
                    foreach ($listeners[$info->hash] as $cb) $cb($hMessage);
                }
            }
        }
    }

    public function interceptByNameOrHash(PacketDirection $direction, string $nameOrHash, callable $callback): void {
        if ($direction === PacketDirection::TO_CLIENT) {
            $this->incomingListeners[$nameOrHash][] = $callback;
        } else {
            $this->outgoingListeners[$nameOrHash][] = $callback;
        }
    }

    public function sendPacket(HPacket $packet): void {
        $dest = $packet->getDestination();
        if ($dest === PacketDirection::TO_SERVER) {
            $this->sendToServer($packet);
        } elseif ($dest === PacketDirection::TO_CLIENT) {
            $this->sendToClient($packet);
        } else {
            throw new \Exception("Packet destination unknown. Use sendToServer() or sendToClient(), or construct HPacket with an Enum.");
        }
    }

    public function sendToClient(HPacket $packet): void {
        $wrapper = new HPacket(GEarthOutgoingPacket::SEND_MESSAGE->value);
        $wrapper->appendByte(0); // To Client
        $wrapper->appendInt($packet->getBytesLength());
        $wrapper->appendBytes($packet->getBytes());
        $this->sendRaw($wrapper);
    }

    public function sendToServer(HPacket $packet): void {
        $wrapper = new HPacket(GEarthOutgoingPacket::SEND_MESSAGE->value);
        $wrapper->appendByte(1); // To Server
        $wrapper->appendInt($packet->getBytesLength());
        $wrapper->appendBytes($packet->getBytes());
        $this->sendRaw($wrapper);
    }

    public function writeToConsole(string $s, string $color = "black"): void {
        $packet = new HPacket(GEarthOutgoingPacket::EXTENSION_CONSOLE_LOG->value);
        $packet->appendString("[$color]" . $this->info->title . " --> " . $s);
        $this->sendRaw($packet);
    }

    public function sendRaw(HPacket $packet): void {
        $data = $packet->toBytes();
        @\socket_write($this->socket, $data, strlen($data));
    }

    private function emit(string $event, mixed $data = null): void {
        if (isset($this->incomingListeners[$event])) {
            foreach ($this->incomingListeners[$event] as $cb) {
                $cb($data);
            }
        }
    }

    private function generateDefinitions(): void {
        \GPHP\Util\PacketGenerator::generate($this->packetInfoManager);
    }
}
