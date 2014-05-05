<?php

namespace ZendCommerce\Common\Service;


use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use ZendCommerce\Common\Event\FormEvent;
use Zend\View\Model\ViewModel;


class FormManager implements EventManagerAwareInterface{

    /**
     * @var
     */
    protected $events;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;


    public function __construct(\Doctrine\ORM\EntityManager $em){
        $this->entityManager = $em;
    }

    /**
     * @param \ZendCommerce\Common\Event\FormEvent $formEvent
     * @return \Zend\View\Model\ViewModel $viewModel
     * @throws \Exception
     */
    public function process(FormEvent $formEvent){

        $viewModel = new ViewModel();
        $entity = $formEvent->getEntity();
        $form = $formEvent->getForm();
        $id = $formEvent->getEntityId();
        $repository = $formEvent->getRepository();
        $filter = $formEvent->getFilter();
        $hydrator = $formEvent->getHydrator();
        $postData = $form->getPostData();
        $entityClass = $formEvent->getEntityClass();


        if (!$entity){
            if (!empty($id)){
                if (method_exists($repository, 'find')){
                    $entity = $repository->find($id);
                    if ($entity === false ) throw new \Exception('Entity not found');

                } else {
                    throw new \Exception('Repository does not support \'find\' method');
                }
            } else {

                if (!$entityClass){
                    throw new \Exception('Undefined Entity Class');
                }
                $entity = new $entityClass;
            }
        }
        $form->init();
        $form->setHydrator($hydrator);
        $form->setInputFilter($filter);
        $form->bind($entity);


        if(count($postData) > 0){
            $form->setData($postData);
            $this->trigger($formEvent::EVENT_VALIDATE_PRE, $formEvent);
            if ($form->isValid()){
                $validatedEntity = $form->getObject();
                $formEvent->setEntity($validatedEntity);
                $this->trigger($formEvent::EVENT_VALIDATE_SUCCESS, $formEvent));
                $this->entityManager->persist($validatedEntity);
                $this->entityManager->flush();
            } else {
                $this->trigger($formEvent::EVENT_VALIDATE_ERROR, $formEvent));
            }
        }

        $viewModel->setTemplate($this->template);
        $viewModel->setVariable('form', $form);
        return $viewModel;
    }

    public function trigger($event, $argv){

        $em = $this->getEventManager();
        $response = $em->trigger($event, $this, $argv);
        return $response;
    }

    /**
     * Set the identifiers and the event manager
     * @param EventManagerInterface $events
     * @return self
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_called_class(),
        ));

        $this->events = $events;

        return $this;
    }

    /**
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (null === $this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }
}

?>