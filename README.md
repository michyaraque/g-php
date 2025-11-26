# G-PHP

PHP extension API for the Habbo packet interceptor [G-Earth](https://github.com/sirjonasxx/G-Earth).

## Requirements

*   PHP 8.4+
*   `ext-sockets` enabled

## Installation

```sh
composer install michyaraque/g-php
```

## Usage

### Getting Started

Create a new PHP script (e.g., `extension.php`):

```php
<?php

require_once 'src/G.php'; // or vendor/autoload.php

use GPHP\Extension\{Extension, ExtensionInfo, HostInfo};
use GPHP\Protocol\{HMessage, HPacket};
use GPHP\Packets\Outgoing;
use GPHP\Packets\Incoming;

$extInfo = new ExtensionInfo(
    title: "My Extension",
    description: "A cool PHP extension",
    version: "1.0",
    author: "You"
);

$ext = new Extension($extInfo);

$ext->onConnect(function (HostInfo $hostInfo) {
    echo "Connected to " . $hostInfo->host . "\n";
});

$ext->run();
```

### How to run

```bash
php extension.php
```

### Intercepting Packets

You can intercept packets using the generated `Incoming` and `Outgoing` Enums.

```php
$ext->intercept(Incoming::Chat, function (HMessage $msg) {
    $packet = $msg->getPacket();
    $text = $packet->readString();
    
    echo "User said: $text\n";
});
```

### Sending Packets

Create an `HPacket` using the Enum and send it. The extension automatically detects the destination.

```php
$packet = new HPacket(Outgoing::Chat);
$packet->appendString("Hello World!");
$packet->appendInt(0);
$packet->appendInt(-1);

$ext->sendPacket($packet);
```

### Reading & Writing Data

```php
// Reading
$val = $packet->readInt();
$str = $packet->readString();
[$x, $y] = $packet->read('ii');

// Writing
$packet->appendInt(123);
$packet->appendString("foo");
$packet->appendBool(true);
```

### Background Tasks (Scheduler)

Use the `Scheduler` to run tasks without blocking packet processing.

```php
use GPHP\Util\Scheduler;

Scheduler::run(function() use ($ext) {
    for ($i = 0; $i < 10; $i++) {
        // Send a packet
        $ext->sendPacket(new HPacket(Outgoing::AvatarExpression)->appendInt(1));
        
        // Wait 1 second (non-blocking)
        $start = microtime(true);
        while (microtime(true) - $start < 1.0) {
            \Fiber::suspend();
        }
    }
});
```

### Packet Definitions

Packet definitions (Enums) are automatically generated in `src/Packets.php` when the extension connects to G-Earth for the first time. This provides Intellisense support in your IDE.

## Running with Docker (Sandboxed)

You can run the extension inside a Docker container while still communicating with G-Earth.

1.  Ensure Docker and Docker Compose are installed.
2.  Run the extension:

```bash
docker-compose up --build
```

This uses `host.docker.internal` to connect to G-Earth running on your main OS.