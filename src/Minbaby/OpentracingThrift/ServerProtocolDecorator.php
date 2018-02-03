<?php

namespace Minbaby\OpentracingTrift;

use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TProtocolDecorator;
use Thrift\Type\TType;
use Zipkin\Propagation\Map;
use Zipkin\Tracer;

class ServerProtocolDecorator extends TProtocolDecorator
{
    protected $name;
    protected $type;
    protected $seqId;
    /**
     * @var TracingManager
     */
    protected $mt;
    
    private $data = [];
    
    protected $nextSpan = false;
    
    /**
     * @inheritdoc
     */
    public function __construct(
        TProtocol $protocol,
        $name,
        $type,
        $seqId,
        TracingManager $mt
    ) {
        parent::__construct($protocol);
        $this->name = $name;
        $this->type = $type;
        $this->seqId = $seqId;
        $this->mt = $mt;
    }
    
    /**
     * @inheritdoc
     */
    public function readMessageBegin(&$name, &$type, &$seqid)
    {
        $ret = parent::readMessageBegin($name, $type, $seqid);
        $this->name = $name;
        $this->type = $type;
        $this->seqId = $seqid;
        
        return $ret;
    }
    
    /**
     * @inheritdoc
     */
    public function readFieldEnd()
    {
        if ($this->nextSpan) {
            $this->nextSpan = false;
            $this->buildSpan();
        }
        
        return parent::readFieldEnd();
    }
    
    public function readMessageEnd()
    {
        $activeSpan = $this->mt->getTacker()->newTrace();
        SpanDecorator::decorate($activeSpan, $this->name, $this->type, $this->seqId);
        
        return parent::readMessageEnd();
    }
    
    private function buildSpan()
    {
        $map = [];
        for ($i = 0; $i < count($this->data); $i += 2) {
            $map[$this->data[$i]] = $this->data[$i+1];
        }
    
        $extractor = $this->mt->getTracing()->getPropagation()->getExtractor(new Map());
        
        \Log::debug(__METHOD__);
        
        $activeSpan = $this->mt->getTacker()->joinSpan($extractor($map));
        $this->mt->setCurrentSpan($activeSpan);
        SpanDecorator::decorate($activeSpan, $this->name, $this->type, $this->seqId);
    }
    
    /**
     * @inheritDoc
     */
    public function skip($type)
    {
        if ($type == TType::MAP) {
            $this->nextSpan = true;
        }
        
        if ($this->nextSpan === true && TType::STRING == $type) {
            $v = '';
            $ret = $this->readString($v);
            $this->data[] = $v;
            return $ret;
        }
        return parent::skip($type);
    }
}
