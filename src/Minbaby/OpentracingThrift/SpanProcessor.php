<?php

namespace Minbaby\OpentracingTrift;

use Thrift\Exception\TException;
use Thrift\Protocol\TProtocol;
use Thrift\Type\TMessageType;
use Zipkin\Tracer;

class SpanProcessor
{
    protected $handler_ = null;
    
    /**
     * @var Tracer
     */
    private $tracker;
    
    public function __construct($handler, $tracker)
    {
        $this->handler_ = $handler;
        $this->tracker = $tracker;
    }
    
    /**
     * @param TProtocol $input
     * @param TProtocol $output
     * @return mixed
     * @throws TException
     * @throws \Exception
     */
    public function process($input, $output)
    {
        $name = $type = $seqId = null;
        $input->readMessageBegin($name, $type, $seqId);
        
        if ($type != TMessageType::CALL && $type != TMessageType::ONEWAY) {
                throw new TException("This should not have happened!?");
        }
        
        try {
            return $this->handler_->process(
                new ServerProtocolDecorator($input, $name, $type, $seqId, $this->tracker),
                $output
            );
        } catch (\Exception $e) {
            SpanDecorator::onError($e, $this->tracker->newTrace());
            throw $e;
        }
    }
}
