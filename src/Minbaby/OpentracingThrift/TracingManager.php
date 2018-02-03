<?php
/**
 * Created by PhpStorm.
 * User: zhangshaomin
 * Date: 2018/2/3
 * Time: 15:44
 */

namespace Minbaby\OpentracingTrift;


use Zipkin\Endpoint;
use Zipkin\Propagation\TraceContext;
use Zipkin\Samplers\BinarySampler;
use Zipkin\Span;
use Zipkin\Tracer;
use Zipkin\TracingBuilder;

class TracingManager
{
    /**
     * @var \Zipkin\DefaultTracing
     */
    private $tracing;
    
    /**
     * @var Span
     */
    private $currentSpan;
    
    public function __construct($name)
    {
        $endpoint  = Endpoint::create($name, '127.0.0.1', null, 9411);
    
        $logger = app('log');
//        $logger->pushHandler(new \Monolog\Handler\ErrorLogHandler());
        $reporter = new \Zipkin\Reporters\Http(new \Zipkin\Reporters\Http\CurlFactory(), $logger);
        $sampler = BinarySampler::createAsAlwaysSample();
        $this->tracing = TracingBuilder::create()
            ->havingLocalEndpoint($endpoint)
            ->havingSampler($sampler)
            ->havingReporter($reporter)
            ->build();
    }
    
    /**
     * @return Tracer
     */
    public function getTacker()
    {
        return $this->tracing->getTracer();
    }
    
    /**
     * @return \Zipkin\DefaultTracing
     */
    public function getTracing()
    {
        return $this->tracing;
    }
    
    /**
     * @return Span
     */
    public function getCurrentSpan()
    {
        return $this->currentSpan;
    }
    
    /**
     * @param Span $currentSpan
     */
    public function setCurrentSpan($currentSpan)
    {
        $this->currentSpan = $currentSpan;
    }
    
    /**
     * @param TraceContext $context
     * @return Span
     */
    public function joinSpan(TraceContext $context)
    {
        $span = $this->getTacker()->joinSpan($context);
        $this->setCurrentSpan($span);
        
        return $span;
    }
    
    /**
     * @param Span $span
     */
    public function finishSpan(Span $span)
    {
        $span->finish();
        $this->setCurrentSpan($span);
    }
    
    /**
     * @param bool              $hasParent
     * @param TraceContext|null $parent
     * @return Span
     */
    public function newChild($hasParent = true, TraceContext $parent = null)
    {
        if (!$parent && $hasParent) {
            $parent = $this->getCurrentSpan()->getContext();
        }
        
        return $this->getTacker()->newChild($parent);
    }
}
