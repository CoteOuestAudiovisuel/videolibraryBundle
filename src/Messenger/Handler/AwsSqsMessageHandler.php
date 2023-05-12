<?php
namespace Coa\MessengerBundle\Messenger\Handler;
use Coa\MessengerBundle\Messenger\Message\AwsSqsMessage;
use Coa\MessengerBundle\Messenger\Message\AwsSqsNativeMessage;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;


/**
 * @author Zacharie Assagou <zacharie.assagou@coteouest.ci>
 */
class AwsSqsMessageHandler implements MessageHandlerInterface{

    private ContainerBagInterface $container;

    public function __construct(ContainerBagInterface $container){
        $this->container = $container;
    }

    public function __invoke(AwsSqsMessage $message){
        $payload = $message->getPayload();
        $action = $message->getAction();
        $date = (new \DateTime())->format("Y-m-d");
        $fs = new Filesystem();
        $folder = $this->container->get('kernel.project_dir')."/applog/broker/log";
        if(!$fs->exists($folder)){
            $fs->mkdir($folder);
        }
        $log_file = $folder."/$date.log";
        $fs->appendToFile($log_file, json_encode(["action"=>$action,"payload"=>$payload])."\n");
    }
}