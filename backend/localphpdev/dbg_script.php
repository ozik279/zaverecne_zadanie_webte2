<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runner = new App\Services\Simulation\OctaveSimulationRunner();
$reflection = new ReflectionClass($runner);
$method = $reflection->getMethod("buildInvertedPendulumScript");
$method->setAccessible(true);

$script = $method->invoke($runner, 0.2, 0, 0, 10, 0.05);
file_put_contents("/tmp/inverted_pendulum_debug.m", $script);
echo "Script generated and saved to /tmp/inverted_pendulum_debug.m\n";
