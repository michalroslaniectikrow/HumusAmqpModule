<?php

namespace HumusAmqpModule\Service;

use HumusAmqpModule\Amqp\RpcServer;
use HumusAmqpModule\Exception;
use Zend\ServiceManager\AbstractPluginManager;
use Zend\ServiceManager\ServiceLocatorInterface;

class RpcServerAbstractServiceFactory extends AbstractAmqpCallbackAwareAbstractServiceFactory
{
    /**
     * @var string Second-level configuration key indicating connection configuration
     */
    protected $subConfigKey = 'rpc_servers';

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param $name
     * @param $requestedName
     * @return mixed
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        // get global service locator, if we are in a plugin manager
        if ($serviceLocator instanceof AbstractPluginManager) {
            $serviceLocator = $serviceLocator->getServiceLocator();
        }

        $config = $this->getConfig($serviceLocator);

        $spec = $config[$this->subConfigKey][$requestedName];

        if (isset($spec['class'])) {
            $class = $spec['class'];
        } else {
            $class = $config['classes']['rpc_server'];
        }

        // use default connection if nothing else present
        if (!isset($spec['connection'])) {
            $spec['connection'] = 'default';
        }

        $connectionManager = $this->getConnectionManager($serviceLocator);
        $callbackManager   = $this->getCallbackManager($serviceLocator);

        $connection = $connectionManager->get($spec['connection']);
        $rpcServer = new $class($connection);

        if (!$rpcServer instanceof RpcServer) {
            throw new Exception\RuntimeException(sprintf(
                'Consumer of type %s is invalid; must extends %s',
                (is_object($rpcServer) ? get_class($rpcServer) : gettype($rpcServer)),
                'HumusAmqpModule\Amqp\RpcServer'
            ));
        }

        if (!isset($spec['callback'])) {
            throw new Exception\RuntimeException('callback is missing for rpc server');
        }

        $rpcServer->setCallback($callbackManager->get($spec['callback']));

        if (isset($spec['qos_options'])) {
            $rpcServer->setQosOptions($spec['qos_options']);
        }

        return $rpcServer;
    }
}