<?php

namespace Minbaby\OpentracingTrift;


use Zipkin\Span;
use const Zipkin\Tags\ERROR;
use const Zipkin\Tags\LOCAL_COMPONENT;

class SpanDecorator
{
    public static function decorate(Span $span, $name, $type, $seqId)
    {
        $span->tag(LOCAL_COMPONENT, OPEN_TRACING_NAME);
        $span->tag("message.name", $name);
        $span->tag("message.type", $type);
        $span->tag("message.seqid", $seqId);
    }
    
    public static function onError(\Exception $exception, Span $span)
    {
        if ($span == null) {
            return;
        }
        $span->tag(ERROR, true);
    }
    
    private static function errorLogs(\Exception $exception)
    {
        return [
            'event' => ERROR,
            'error.kind' => get_class($exception),
            'error.object' => $exception,
            'message' => $exception->getMessage(),
            'stack' => $exception->getTraceAsString()
        ];
    }
}
