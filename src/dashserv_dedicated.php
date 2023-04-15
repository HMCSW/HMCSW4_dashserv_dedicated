<?php

namespace hmcswModule\dashserv_dedicated\src;

use hmcsw\controller\web\cp\CPController;
use hmcsw\controller\web\error\error;
use hmcsw\exception\InvalidRequestException;
use hmcsw\exception\NotEnoughPermissionException;
use hmcsw\objects\user\teams\service\Service;
use hmcsw\objects\user\teams\service\ServiceRepository;
use hmcsw\service\module\ModuleServiceRepository;

class dashserv_dedicated implements ModuleServiceRepository
{
  protected Service $service;
  protected array $config;

  public function __construct ()
  {
    $this->config = json_decode(file_get_contents(__DIR__.'/../config/config.json'), true);
  }

  public function startModule (): bool
  {
    if($this->config['enabled']){
      return true;
    } else {
      return false;
    }
  }
  public function getModuleInfo(): array
  {
    return json_decode(file_get_contents(__DIR__.'/../module.json'), true);
  }

  public function getProperties(): array
  {
    return json_decode(file_get_contents(__DIR__.'/../properties.json'), true);
  }

  public function loadPage(array $args, ServiceRepository $serviceRepository, CPController $CPController): void
  {
    if (isset($_GET['vnc'])) {
      try {
        $action = $serviceRepository->getService()->createLoginInSession();
        header('Location: ' . $action['url']);
      } catch (InvalidRequestException|NotEnoughPermissionException $e) {
        (new error())->serverError($e->getCode(), $e->getMessage());
      }
      die();
    }
    $get = $serviceRepository->getData();

    $args['data'] = $get;

    $CPController->renderPage('cp/teams/services/dashServDedi.twig', $args);
  }

  public function getMessages (string $lang): array|bool
  {
    if(!file_exists(__DIR__.'/../messages/'.$lang.'.json')){
      return false;
    }

    return json_decode(file_get_contents(__DIR__.'/../messages/'.$lang.'.json'), true);
  }

  public function getConfig (): array
  {
    return $this->config;
  }

  public function getInstance (Service $service): ServiceRepository
  {
    return new dashserv_dedicatedService($service, $this);
  }

}