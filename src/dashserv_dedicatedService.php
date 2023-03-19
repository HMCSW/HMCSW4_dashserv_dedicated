<?php

namespace hmcswModule\dashserv_dedicated\src;

use dashserv\api\dashservApiClient;
use GuzzleHttp\Exception\GuzzleException;
use hmcsw\exception\ServiceAuthorizationException;
use hmcsw\exception\ServiceException;
use hmcsw\objects\user\teams\service\Service;
use hmcsw\objects\user\teams\service\ServiceRepository;
use hmcsw\service\general\DiscordService;
use hmcsw\service\module\ModuleServiceRepository;
use hmcsw\service\Services;

class dashserv_dedicatedService implements ServiceRepository
{
  public ?dashservApiClient $externalOBJ = null;
  protected Service $service;
  protected ModuleServiceRepository $module;
  protected array $get = ["success" => false];

  public function __construct (Service $service, ModuleServiceRepository $module)
  {
    $this->service = $service;
    $this->module = $module;

    if ($this->service->host->host_id != 0) $this->externalOBJ = $this->getExternalOBJ();
    $this->get = $this->get();
  }

  public function get(){
    if($this->get['success']) return $this->get;

    try {
      $server = $this->getExternalOBJ()->dedicatedServer()->get($this->getService()->external['id']);
    } catch (GuzzleException $e) {
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage(), "error_response" => $e->getTrace()]];
    }
    $data = $server->getData();

    return ["success" => true, "response" => ["status" => $data->status, "device" => $data->device]];

  }

  public function getService (): Service
  {
    return $this->service;
  }

  public function getModule (): ModuleServiceRepository
  {
    return $this->module;
  }

  public function onCreate (bool $reinstall = false): array
  {
    $host = $this->getService()->host;
    $host_name = $host->name;
    $host_subdomain = $host->subdomain;
    $host_id = $host->host_id;

    $subdomain = $this->getService()->service_id . "." . $host_name . "." . $host_subdomain;
    $package = $this->getService()->package;
    $orderArray = array(
      'cpu' => 0,
      'ram' => 0,
      'traffic' => 0,
      'bandwidth' => 1,
      'disk' => 0,
      "os" => 295,
      "hostname" => $subdomain,
    );

    try {
      $server = $this->getExternalOBJ()->order()->placeOrder("dedicated-server:".$package['external_name'], null, "kc7aV", $orderArray);
      if (!$server->isSuccessfull()) {
        throw new ServiceException("Error while creating server", 0, $server->getData());
      }

      Services::getDatabaseService()->prepare("UPDATE services SET external_id = ?, host_id = ?, external_name = ? WHERE service_id = ?", [$server->getData(), $host_id, $subdomain, $this->getService()->service_id]);

      return ['external_id' => 0, 'external_name' => $subdomain, "host_id" => $host_id];
    } catch (GuzzleException $e) {
      throw new ServiceException("Error while creating server", $e->getCode());
    }
  }

  public function onDelete (bool $reinstall = false): void
  {
    DiscordService::addMessageToQueue("service", "Delete Service of ".$this->service->service_id."@".$this->service->type['hostType']." require manuel delete.");
  }

  public function onEnable (): void
  {
    try {
      $server = $this->getExternalOBJ()->dedicatedServer()->start($this->getService()->external_id);
    } catch (GuzzleException $e) {
      throw new ServiceException("Error while starting server", $e->getCode());
    }
  }

  public function onDisable (): void
  {
    try {
      $server = $this->getExternalOBJ()->dedicatedServer()->stop($this->getService()->external_id);
    } catch (GuzzleException $e) {
      throw new ServiceException("Error while stopping server", $e->getCode());
    }
  }

  public function onTerminate (): void
  {

  }

  public function onTerminateInstant (): void
  {

  }

  public function onWithdrawTerminate (): void
  {

  }

  public function onExtend (int $time): void
  {

  }

  public function onLogin (string $key): array
  {
    try {
      $url = $this->getExternalOBJ()->dedicatedServer()->getConsole($this->getService()->external['id'])->getData()->getData()->url;
      return ["url" => $url, "type" => "iframe"];
    } catch (GuzzleException $e) {
      throw new ServiceException("Error while getting console url", $e->getCode());
    }

  }

  public function onSetName (string $name): void
  {

  }

  public function getData (): array
  {
    return $this->get();
  }

  private function getExternalOBJ (): dashservApiClient
  {
    if (!is_null($this->externalOBJ)) {
      return $this->externalOBJ;
    } else {
      $host = $this->getService()->host;

      $dsClient = new dashservApiClient($host->auth['password']);
      try {
        $accountData = $dsClient->account()->getUserInfo();
      } catch (GuzzleException $e) {
        throw new ServiceAuthorizationException($e->getMessage(), $e->getCode(), $e->getTrace());
      }

      $this->externalOBJ = $dsClient;
      return $dsClient;
    }
  }
}