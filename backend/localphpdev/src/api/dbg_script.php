<?php
require 'vendor/autoload.php';
\ = require_once 'bootstrap/app.php';
\ = \->make(Illuminate\Contracts\Console\Kernel::class);
\->bootstrap();

\ = new App\Services\Simulation\OctaveSimulationRunner();
\ = new ReflectionClass(\);
\ = \->getMethod('buildInvertedPendulumScript');
\->setAccessible(true);

\ = \->invoke(\, 0.2, 0, 0, 10, 0.05);
file_put_contents('/tmp/inverted_pendulum_debug.m', \);
echo 'Script generated and saved to /tmp/inverted_pendulum_debug.m' . PHP_EOL;
