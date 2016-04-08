<?php
namespace Bot\Controller;

use Bot\Helper\File;
use Bot\Module;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;
use Zend\Mvc\Controller\AbstractActionController;

class IndexController extends AbstractActionController
{
    protected $messageStream = null;
    protected $logger = null;

    protected function getConfig($name)
    {
        $module = new Module();
        $config = $module->getConfig();
        return isset($config[$name]) ? $config[$name] : null;
    }

    public function getMessageStream()
    {
        if(null === $this->messageStream) {
            $config = $this->getConfig('amqp');
            $this->messageStream = new AMQPStreamConnection($config['host'], $config['port'], $config['user'], $config['password']);
        }
        return $this->messageStream;
    }

    protected function getLogger()
    {
        if (null === $this->logger) {
            $this->logger = new Logger();
            $this->logger->addWriter(new Stream('php://stdout'));
        }
        return $this->logger;
    }

    public function scheduleAction()
    {
        $request = $this->getRequest();
        $fileList = $request->getParam('fileList');
        if(!($content = file_get_contents($fileList)) ||
            !($files = explode(PHP_EOL, $content))) {
            return false;
        }
        $channel = $this->getMessageStream()->channel();
        $channel->exchange_declare('download', 'fanout', false, true, false);
        $channel->exchange_declare('failed', 'fanout', false, true, false);
        foreach($files as $fileUrl)
        {
            $queueName = (filter_var($fileUrl, FILTER_VALIDATE_URL) === false ||
                !in_array(parse_url($fileUrl, PHP_URL_SCHEME), ['http', 'https'])) ? 'failed' : 'download';
            $msg = new AMQPMessage($fileUrl);
            $channel->basic_publish($msg, $queueName);
        }

        $channel->close();
        $this->getMessageStream()->close();

        return true;
    }

    public function downloadAction()
    {
        $channel = $this->getMessageStream()->channel();

        $channel->exchange_declare('download', 'fanout', false, true, false);

        list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);

        $channel->queue_bind($queue_name, 'download');
        $this->getLogger()->info(' Waiting for the files. To exit press CTRL+C');

        $channel->basic_consume($queue_name, '', false, true, false, false, [$this, 'processMessage']);

        while(count($channel->callbacks)) {
            $channel->wait();
        }

        $channel->close();
        $this->getMessageStream()->close();
    }

    public function processMessage($message)
    {
        if(!($imageUrl = $message->body)) {
            return false;
        }

        if(!File::isValidImg($imageUrl)) {
            $this->getLogger()->debug($imageUrl. " is not a valid image, moving to the failed query");
            $channel = $this->getMessageStream()->channel();
            $channel->exchange_declare('failed', 'fanout', false, true, false);
            $msg = new AMQPMessage($imageUrl);
            $channel->basic_publish($msg, 'failed');
        } else {
            $tmpFileName = tempnam(sys_get_temp_dir(), 'Image');

            if(File::downloadImage($imageUrl, $tmpFileName)) {
                $this->getLogger()->debug(sprintf("uploaded image %s to the %s" ,$imageUrl ,$tmpFileName));
            }
        }
    }

}
