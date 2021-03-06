<?php
namespace SBC\NotificationsBundle\EventListener;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use SBC\NotificationsBundle\Builder\NotificationBuilder;
use SBC\NotificationsBundle\Model\BaseNotification;
use SBC\NotificationsBundle\Model\NotifiableInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * Class HistoryService
 * @package SBC\NotificationsBundle\EventListener
 * 
 * @author: Haithem Mrad <haithem.mrad@sbc.tn>
 * @author: Slimen Arnaout <arnaout.slimen@gmail.com>
 */
class HistoryService implements EventSubscriber
{

    private $container;

    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }


    public function getSubscribedEvents()
    {
        return array(
            'postPersist',
        );
    }
    
    public function postPersist(LifecycleEventArgs $args)
    {

        $entity = $args->getEntity();
        $entityManager = $args->getEntityManager();
        
        if ($entity instanceof NotifiableInterface )
        {

            $builder = new NotificationBuilder();
            $builder =  $entity->buildNotifications($builder);
            
            if($builder == null || !$builder instanceof NotificationBuilder){
                throw new RuntimeException('"buildNotifications()" must return an instance of "SBC\NotificationsBundle\Builder\NotificationBuilder" !');
            }else{
                if(!$builder->isEmpty()){

                    foreach ($builder->getNotifications() as $notification){
                        // if notification contain route build full URL
                        $notification = $this->buildFullURL($notification);
                        $entityManager->persist($notification);
                    }

                    $entityManager->flush();
                    $this->broadcastDataToClient($builder->getNotifications());

                }
            }
        }

    }

    /**
     * @param BaseNotification $notification
     * @return BaseNotification
     */
    private function buildFullURL(BaseNotification $notification){
        if($notification->getRoute() != null && $notification->getRoute() != ''){
            if($notification->getParameters() != null){
                $fullUr = $this->container
                    ->get('router')
                    ->generate($notification->getRoute(), $notification->getParameters());
            }else{
                $fullUr = $this->container
                    ->get('router')
                    ->generate($notification->getRoute());
            }
            $notification->setFullUrl($fullUr);
        }

        return $notification;
    }

    /**
     * Trigger data to reach clients
     * @param array $notifications
     */
    private function broadcastDataToClient(array $notifications){
        $pusher = $this->container->get('mrad.pusher.notificaitons');
        $pusher->trigger($notifications);
    }
    
}