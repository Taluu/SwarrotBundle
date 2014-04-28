<?php

namespace Swarrot\SwarrotBundle\Broker;

use Swarrot\Broker\MessageProvider\PeclPackageMessageProvider;
use Swarrot\Broker\MessagePublisher\PeclPackageMessagePublisher;

class PeclFactory implements FactoryInterface
{
    protected $connections       = array();
    protected $channels          = array();
    protected $messageProviders  = array();
    protected $messagePublishers = array();

    /**
     * {@inheritDoc}
     */
    public function addConnection($name, array $connection)
    {
        $this->connections[$name] = $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function getMessageProvider($name, $connection)
    {
        if (!isset($this->messageProviders[$connection])) {
            $this->messageProviders[$connection] = array();
        }

        if (!isset($this->messageProviders[$connection][$name])) {
            $queue = new \AMQPQueue(
                $this->getChannel($connection)
            );

            $queue->setName($name);
            $queue->declareQueue();

            $this->messageProviders[$connection][$name] = new PeclPackageMessageProvider($queue);
        }

        return $this->messageProviders[$connection][$name];
    }

    /**
     * {@inheritDoc}
     */
    public function getMessagePublisher($name, $connection)
    {
        if (!isset($this->messagePublishers[$connection])) {
            $this->messagePublishers[$connection] = array();
        }

        if (!isset($this->messagePublishers[$connection][$name])) {
            $exchange = new \AMQPExchange(
                $this->getChannel($connection)
            );

            $exchange->setName($name);
            $exchange->setType(AMQP_EX_TYPE_DIRECT);
            $exchange->declareExchange();

            $this->messagePublishers[$connection][$name] = new PeclPackageMessagePublisher($exchange);
        }

        return $this->messagePublishers[$connection][$name];
    }

    /**
     * getChannel
     *
     * @param string $connection
     *
     * @throws \AMQPConnectionException
     *
     * @return \AMQPChannel
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

        $conn = new \AMQPConnection($this->connections[$connection]);
        $conn->connect();

        $this->channels[$connection] = new \AMQPChannel($conn);

        return $this->channels[$connection];
    }
}
