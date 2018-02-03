<?php

namespace Minbaby\OpentracingTrift;

use Thrift\Exception\TApplicationException;
use Thrift\Exception\TException;
use Thrift\Protocol\TProtocol;
use Thrift\Type\TMessageType;
use Thrift\Type\TType;
use const Zipkin\Kind\SERVER;

class SpanProcessor
{
    /**
     * @var TracingManager
     */
    protected $mt;
    
    private $name;
    
    private $type;
    
    private $seqId;
    
    public function __construct($handler, TracingManager $mt)
    {
        $this->handler = $handler;
        $this->mt = $mt;
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
        
        $this->name = $name;
        $this->type = $type;
        $this->seqId = $seqId;
        
        if ($type != TMessageType::CALL && $type != TMessageType::ONEWAY) {
            throw new TException("This should not have happened!?");
        }
        
        try {
            $newInput  = new ServerProtocolDecorator($input, $name, $type, $seqId, $this->mt);
 
            $ret = $this->parentProcess($newInput, $output);
            
            return $ret;
        } catch (\Exception $e) {
            SpanDecorator::onError($e, $this->mt->getTacker()->newTrace());
            throw $e;
        } finally {
            $this->mt->getTacker()->flush();
        }
    }
    
    private function parentProcess($input, $output)
    {
        $rseqid = $this->seqId;
        $fname = $this->name;
        $mtype = $this->type;
    
        $methodName = 'process_'. $fname;
    
        $reflection = new \ReflectionClass($this->handler);
        
        
        if (!$reflection->hasMethod($methodName)) {
            $input->skip(TType::STRUCT);
            $input->readMessageEnd();
            $x = new TApplicationException('Function '.$fname.' not implemented.', TApplicationException::UNKNOWN_METHOD);
            $output->writeMessageBegin($fname, TMessageType::EXCEPTION, $rseqid);
            $x->write($output);
            $output->writeMessageEnd();
            $output->getTransport()->flush();
            return;
        }
        \Log::debug(__METHOD__, [$methodName]);

        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        $method->invoke($this->handler, $rseqid, $input, $output);
        
        return true;
    }
}
