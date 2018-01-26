<?php

namespace Minbaby\OpentracingTrift;

use OpenTracing\Span;

class SpanDecorator
{
    public static function decorate(Span $span, $name, $type, $seqId)
    {
        $span->setTags([
            OPEN_TRACING_TAGS_COMPONENT => OPEN_TRACING_NAME,
            'message.name' => $name,
            'message.type' => $type,
            'message.seqid' => $seqId,
        ]);
    }
    
    public static function onError(\Exception $exception, Span $span)
    {
        if ($span == null) {
            return;
        }
        $span->setTags([OPEN_TRACING_TAGS_ERROR => true]);
        $span->log(static::errorLogs($exception));
    }
    
    private static function errorLogs(\Exception $exception)
    {
        return [
            'event' => OPEN_TRACING_TAGS_ERROR,
            'error.kind' => get_class($exception),
            'error.object' => $exception,
            'message' => $exception->getMessage(),
            'stack' => $exception->getTraceAsString()
        ];
    }
}