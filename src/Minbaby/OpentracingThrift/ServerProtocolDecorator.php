<?php
namespace Minbaby\OpentracingTrift;

use Thrift\Protocol\TProtocol;
use Thrift\Protocol\TProtocolDecorator;
use Thrift\Type\TType;
use Zipkin\Tracer;

class ServerProtocolDecorator extends TProtocolDecorator
{
    protected $name;
    protected $type;
    protected $seqId;
    /**
     * @var MT
     */
    protected $mt;
    
    
    protected $nextSpan = false;
    
    /**
     * @inheritdoc
     */
    public function __construct(
        TProtocol $protocol,
        $name,
        $type,
        $seqId,
        MT $mt
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
    public function readFieldBegin(&$name, &$fieldType, &$fieldId)
    {
        if ($fieldId == SpanProtocol::SPAN_FIELD_ID && $fieldType == TType::MAP) {
            $this->nextSpan = true;
        }
        
        return parent::readFieldBegin($name, $fieldType, $fieldId);
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
        $parent = $this->mt->getTacker()->openScope();
        $activeSpan = $this->mt->getTacker()->newTrace();
        SpanDecorator::decorate($activeSpan, $this->name, $this->type, $this->seqId);
    }
}
