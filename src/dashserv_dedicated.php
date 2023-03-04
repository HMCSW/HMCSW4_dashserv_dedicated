<?php

namespace hmcswModule\dashserv_dedicated\src;

use hmcsw\controller\web\cp\CPController;
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
    $get = $serviceRepository->getData();

    $args['domain'] = $get['response'];

    $CPController->renderPage('cp/teams/services/plesk.twig', $args);
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