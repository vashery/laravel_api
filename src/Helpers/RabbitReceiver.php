<?php

namespace uhin\laravel_api;

use Exception;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class RabbitReceiver
 *
 * Example for reading from a queue:
 *
 *   (new RabbitReceiver)->receive(function($message) {
 *       $channel = $message->delivery_info['channel'];
 *       $deliveryTag = $message->delivery_info['delivery_tag'];
 *       $channel->basic_ack($deliveryTag);
 *   });
 *
 * @package uhin\laravel_api
 */
class RabbitReceiver
{

    /** @var null|string */
    private $host = null;

    /** @var null|integer */
    private $port = null;

    /** @var null|string */
    private $username = null;

    /** @var null|string */
    private $password = null;

    /** @var null|string */
    private $queue = null;

    /** @var null|string */
    private $consumerTag = null;

    /** @var integer */
    private $prefetchCount;

    public function __construct(RabbitBuilder $builder = null)
    {

        if(!is_null($builder))
        {
            $builder->execute();
        }

        $this->setSettings($builder);
        $this->consumerTag = null;
        $this->prefetchCount = 1;
    }

    private function setSettings(?RabbitBuilder $builder)
    {
        /* Set the host */
        if(!is_null($builder) && method_exists($builder,'getHost') && !is_null($builder->getHost()))
        {
            $this->host = $builder->getHost();
        }
        else
        {
            $this->host = config('uhin.rabbit.host');
        }

        /* Set the port */
        if(!is_null($builder) && method_exists($builder,'getPort') && !is_null($builder->getPort()))
        {
            $this->port = $builder->getPort();
        }
        else
        {
            $this->port = config('uhin.rabbit.port');
        }

        /* Set the username */
        if(!is_null($builder) && method_exists($builder,'getUsername') && !is_null($builder->getUsername()))
        {
            $this->username = $builder->getUsername();
        }
        else
        {
            $this->username = config('uhin.rabbit.username');
        }

        /* Set the password */
        if(!is_null($builder) && method_exists($builder,'getPassword') && !is_null($builder->getPassword()))
        {
            $this->password = $builder->getPassword();
        }
        else
        {
            $this->password = config('uhin.rabbit.password');
        }

        /* Set the Exchange */
        if(!is_null($builder) && method_exists($builder,'getExchange') && !is_null($builder->getExchange()))
        {
            $this->exchange = $builder->getExchange();
        }
        else
        {
            $this->exchange = config('uhin.rabbit.exchange');
        }

        /* Set the RoutingKey */
        if(!is_null($builder) && method_exists($builder,'getRoutingKey') && !is_null($builder->getRoutingKey()))
        {
            $this->routingKey = $builder->getRoutingKey();
        }
        else
        {
            $this->routingKey = config('uhin.rabbit.routing_key');
        }

        /* Set the Queue */
        if(!is_null($builder) && method_exists($builder,'getQueue') && !is_null($builder->getQueue()))
        {
            $this->queue = $builder->getQueue();
        }
        else
        {
            $this->queue = config('uhin.rabbit.queue');
        }
    }

    /**
     * Override the default host.
     *
     * @param null|string $host
     * @return $this
     */
    public function setHost(?string $host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * Override the default port.
     *
     * @param null|integer $port
     * @return $this
     */
    public function setPort(?integer $port)
    {
        $this->port = $port;
        return $this;
    }

    /**
     * Override the default username.
     *
     * @param null|string $username
     * @return $this
     */
    public function setUsername(?string $username)
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Override the default password.
     *
     * @param null|string $password
     * @return $this
     */
    public function setPassword(?string $password)
    {
        $this->password = $password;
        return $this;
    }

    /**
     * Override the default queue.
     *
     * @param null|string $queue
     * @return $this
     */
    public function setQueue(?string $queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Override the default consumer tag.
     *
     * @param null|string $consumerTag
     * @return $this
     */
    public function setConsumerTag(?string $consumerTag)
    {
        $this->consumerTag = $consumerTag;
        return $this;
    }

    /**
     * Override the prefetch count (1).
     *
     * @param integer $prefetchCount
     * @return $this
     */
    public function setPrefetchCount(integer $prefetchCount)
    {
        $this->prefetchCount = $prefetchCount;
        return $this;
    }

    /**
     * Opens a connection to Rabbit and initializes the queues. If the exchange/queues don't exist, then
     * the exchange will be created, as well as a default queue and a dead letter exchange queue that are
     * automatically bound to the exchange.
     *
     * @param AMQPStreamConnection $connection
     * @param AMQPChannel $channel
     * @return boolean
     */
    private function openConnection(&$connection, &$channel)
    {
        // host
        $host = $this->host;
        if ($host === null) {
            throw new InvalidArgumentException("RabbitMQ host is undefined. Either set the RABBITMQ_HOST in the .env file or call ->setHost(...)");
        }

        // port
        $port = $this->port;
        if ($port === null) {
            throw new InvalidArgumentException("RabbitMQ port is undefined. Either set the RABBITMQ_PORT in the .env file or call ->setPort(...)");
        }

        // username
        $username = $this->username;
        if ($username === null) {
            throw new InvalidArgumentException("RabbitMQ username is undefined. Either set the RABBITMQ_USERNAME in the .env file or call ->setUsername(...)");
        }

        // password
        $password = $this->password;
        if ($password === null) {
            throw new InvalidArgumentException("RabbitMQ host is undefined. Either set the RABBITMQ_PASSWORD in the .env file or call ->setPassword(...)");
        }

        // Create the connection to Rabbit
        $connection = new AMQPStreamConnection($host, $port, $username, $password);
        $channel = $connection->channel();

        return true;
    }

    /**
     * Closes the connection that was previously opened.
     *
     * @param AMQPStreamConnection $connection
     * @param AMQPChannel $channel
     * @return bool
     */
    private function closeConnection(&$connection, &$channel)
    {
        try {
            $channel->close();
            $connection->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Start reading the messages from the queue.
     *
     * @param callable $callback The function that will be called for each message in the queue - this
     * callback will take one argument: the Rabbit message
     * @return bool
     */
    public function receive(callable $callback)
    {
        // queue
        $queue = $this->queue;
        if ($queue === null) {
            throw new InvalidArgumentException("RabbitMQ queue is undefined. Either set the RABBITMQ_QUEUE in the .env file or call ->setQueue(...)");
        }

        // consumer tag
        $consumerTag = $this->consumerTag;
        if ($consumerTag === null || strlen($consumerTag) <= 0) {
            $consumerTag = gethostname();
        }

        // Open the connection
        /** @var AMQPStreamConnection $connection */
        $connection = null;
        /** @var AMQPChannel $channel */
        $channel = null;

        $this->openConnection($connection, $channel);

        $this->setupGracefulStop($channel, $consumerTag);

        try {
            // Start reading the queue
            $channel->basic_qos(null, $this->prefetchCount, null);
            $channel->basic_consume($queue, $consumerTag, false, false, false, false, function ($message) use ($callback) {
                $callback($message);
            });

            // Don't exit until the callbacks have been released (basically... never exit)
            while (count($channel->callbacks))
            {
                /* Check for worker drain */
                if($this->isDownForMaintenance())
                {
                    //Message needs to be reprocessed place back in queue.
                    $channel->basic_cancel($consumerTag, false, true);
                    die("Worker is draining.\r\n");
                }
                try
                {
                    $channel->wait();
                }
                catch(Exception $e)
                {
                    /** @noinspection PhpUndefinedMethodInspection */
                    Log::debug("Error while reading queue . {$this->host}:{$this->port} {$this->queue}: " . $e->getMessage());
                    die();
                }
            }

            /** @noinspection PhpUndefinedMethodInspection */
            Log::debug("Queue finished reading. {$this->host}:{$this->port} {$this->queue}");
            return true;
        } catch (Exception $e) {
            $message = "Error in " . __FILE__ . " line " . __LINE__ .
                " - Failed to read queue. " .
                $e->getMessage() .
                json_encode([
                    'host' => $this->host,
                    'port' => $this->port,
                    'username' => $this->username,
                    'password' => $this->password,
                    'queue' => $this->queue,
                    'consumerTag' => $this->consumerTag,
                    'prefetchCount' => $this->prefetchCount,
                ], JSON_PRETTY_PRINT);

            /** @noinspection PhpUndefinedMethodInspection */
            Log::error($message);
            return false;
        } finally {
            // Close the connection
            $this->closeConnection($connection, $channel);
        }
    }

    /**
     * setupGracefulStop
     * If the consumer would potentially have issues reprocessing the message we want to reduce those issues as much as possible
     * By watching signals from outside of the application we can know if the connection needs to be closed and close gracefully
     * @param $channel
     * @param $consumerTag
     */
    protected function setupGracefulStop(&$channel, &$consumerTag) {
        // Create anonymous $shutdown function because $connection isn't set on the object and there isn't a good way to access with with the pcntl_signals otherwise
        $shutdown = function($signal , $signinfo) use ($channel, $consumerTag)
        {
            Log::info('Shutting down worker gracefully');
            $channel->basic_cancel($consumerTag, false, true);
            return;
        };

        // Watch kill signals (from outside the application) asynchronously
        pcntl_async_signals(true);

        // Watch for service being killed externally
        pcntl_signal(SIGINT, $shutdown);

        // Watch for CTRL+C
        pcntl_signal(SIGTERM, $shutdown);
    }
}
