<?php

use OpenTracing\GlobalTracer;

require __DIR__ . '/../vendor/autoload.php';

$tracer = GlobalTracer::get();

GlobalTracer::set($tracer);

$tracer->flush();
