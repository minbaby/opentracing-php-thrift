<?php

namespace Minbaby\OpentracingTrift;

use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TProtocolDecorator;
use Thrift\Type\TType;
use Zipkin\Propagation\Map;
use const Zipkin\Kind\CLIENT;

class SpanProtocol extends TProtocolDecorator
{
    const SPAN_FIELD_ID = 3333;
    
    /**
     * @var TracingManager
     */
    protected $mt;
    
    /**
     * @inheritdoc
     */
    public function __construct(TProtocol $protocol, TracingManager $mt)
    {
        parent::__construct($protocol);
        $this->mt = $mt;
    }
    
    /**
     * @inheritdoc
     */
    public function writeMessageBegin($name, $type, $seqid)
    {
        $activeSpan = $this->mt->getTacker()->newTrace();
        $activeSpan->setKind(CLIENT);
        
        SpanDecorator::decorate($activeSpan, $name, $type, $seqid);
        
        return parent::writeMessageBegin($name, $type, $seqid);
    }
    
    public function writeFieldStop()
    {
        $this->writeFieldBegin('span', TType::MAP, static::SPAN_FIELD_ID);
        
        $injector = $this->mt->getTracing()->getPropagation()->getInjector(new Map());
    
        $data = [];
        $injector($this->mt->getCurrentSpan()->getContext(), $data);
    
        $this->writeMapBegin(TType::STRING, TType::STRING, count($data));
        
        foreach ($data as $key => $val) {
            $this->writeString($key);
            $this->writeString($val);
        }
        
        $this->writeMapEnd();
        
        $this->writeFieldEnd();
        
        return parent::writeFieldStop();
    }
}
