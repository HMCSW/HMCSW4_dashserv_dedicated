<?php

namespace hmcswModule\dashserv_dedicated\src;

use dashserv\api\dashservApiClient;
use GuzzleHttp\Exception\GuzzleException;
use hmcsw\exception\ServiceAuthorizationException;
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
        return ["success" => false, "response" => ["error_code" => 500, "error_message" => "unknown error", "error_response" => $server->getData()]];
      }
      $statement = Services::getDatabaseService()->prepare("UPDATE services SET external_id = ?, host_id = ?, external_name = ? WHERE service_id = ?", [$server->getData(), $host_id, $subdomain, $this->getService()->service_id]);
      return ["success" => true];
    } catch (GuzzleException $e) {
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage(), "error_response" => $e->getTrace()]];
    }
  }

  public function onDelete (bool $reinstall = false): array
  {
    DiscordService::addMessageToQueue("service", "Delete Service of ".$this->service->service_id."@".$this->service->type['hostType']." require manuel delete.");
    return ["success" => true, "response" => []];
  }

  public function onEnable (): array
  {
    try {
      $server = $this->getExternalOBJ()->dedicatedServer()->start($this->getService()->external['id']);
      return ["success" => true];
    } catch (GuzzleException $e) {
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage(), "error_response" => $e->getTrace()]];
    }
  }

  public function onDisable (): array
  {
    try {
      $server = $this->getExternalOBJ()->dedicatedServer()->stop($this->getService()->external['id']);
      return ["success" => true];
    } catch (GuzzleException $e) {
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage(), "error_response" => $e->getTrace()]];
    }
  }

  public function onTerminate (): array
  {
    return ["success" => true];
  }

  public function onTerminateInstant (): array
  {
    return ["success" => true];
  }

  public function onWithdrawTerminate (): array
  {
    return ["success" => true];
  }

  public function onExtend (int $time): array
  {
    return ["success" => true];
  }

  public function onLogin (string $key): array
  {
    try {
      $url = $this->getExternalOBJ()->dedicatedServer()->getConsole($this->getService()->external['id'])->getData()->getData()->url;
      return ["success" => true, "response" => ["url" => $url, "type" => "iframe"]];
    } catch (GuzzleException $e) {
      return ["success" => false, "response" => ["error_code" => $e->getCode(), "error_message" => $e->getMessage(), "error_response" => $e->getTrace()]];
    }

  }

  public function onSetName (string $name): array
  {
    return ["success" => true];
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