<?php

namespace Swarrot\SwarrotBundle\Broker;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;

use Swarrot\Broker\MessageProvider\PhpAmqpLibMessageProvider;
use Swarrot\Broker\MessageProvider\PhpAmqpLibMessagePublisher;

class PhpAmqpLibFactory implements FactoryInterface
{
    protected $channels    = array();
    protected $providers   = array();
    protected $publishers  = array();
    protected $connections = array();

    /** {@inheritDoc} */
    public function addConnection($name, array $connection)
    {
        $conn = new AMQPLazyConnection($connection['host'],
                                       $connection['port'],
                                       $connection['login'],
                                       $connection['password'],
                                       $connection['vhost']);

        $this->connections[$name] = $conn;
    }

    /** {@inheritDoc} */
    public function getMessageProvider($name, $connection)
    {
        if (!isset($this->providers[$connection][$name])) {
            if (!isset($this->providers[$connection])) {
                $this->providers[$connection] = array();
            }

            $channel = $this->getChannel($connection);
            $channel->queue_declare($name, false, true, false, false);

            $this->providers[$connection][$name] = new PhpAmqpLibMessageProvider($this->getChannel($connection), $name);
        }

        return $this->providers[$connection][$name];
    }

    /** {@inheritDoc} */
    public function getMessagePublisher($name, $connection)
    {
        if (!isset($this->publishers[$connection][$name])) {
            if (!isset($this->publishers[$connection])) {
                $this->publishers[$connection] = array();
            }

            $channel = $this->getChannel($connection);
            $channel->exchange_declare($name, 'direct', false, true, false);

            $this->publishers[$connection][$name] = new PhpAmqpLibMessagePublisher($this->getChannel($connection), $name);
        }

        return $this->publishers[$connection][$name];
    }

    /**
     * getChannel
     *
     * @param string $connection
     *
     * @return AMQPChannel
     */
    protected function getChannel($connection)
    {
        if (isset($this->channels[$connection])) {
            return $this->channels[$connection];
        }

        if (!isset($this->connections[$connection])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown connection "%s". Available: [%s]',
                $connection,
                implode(', ', array_keys($this->connections))
            ));
        }

        if (!isset($this->channels[$connection])) {
            $this->channels[$connection] = array();
        }

        return $this->channels[$connection] = $this->connections[$connection]->channel();
    }

    protected function getExchange($connection)
    {

    }

    public function __destruct()
    {
        foreach ($this->channels as $channel) {
            $channel->close();
        }

        foreach ($this->connections as $connection) {
            $connection->close();
        }
    }
}
