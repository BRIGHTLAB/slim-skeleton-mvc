<?php

namespace App\Helpers;

use infobip\api\client\SendSingleTextualSms;
use infobip\api\configuration\ApiKeyAuthConfiguration;
use infobip\api\model\sms\mt\send\textual\SMSTextualRequest;

// ******************************************************
// ************* HELPER THAT SENDS SMS  *****************
// ******************************************************
class SMSHelper 
{

  // CONSTRUCT
  protected $settings;
  protected $key;
  protected $from;
  protected $credentials;
  protected $client;
  
  public function __construct($settings) {
    $this->settings = $settings;

    // Assign global settings
    $this->key = $this->settings['sms']['key'];
    $this->from = $this->settings['sms']['from'];

    // init
    $this->credentials = new ApiKeyAuthConfiguration($this->key);
    $this->client = new SendSingleTextualSms($this->credentials);

  }

  // Send SMS to someone
  public function sendSMS (string $to, string $message) {

    try {
        $to_formatted = str_replace([" ", "(", ")", "-", "+"], '', $to);
        $requestBody = new SMSTextualRequest();
        $requestBody->setFrom($this->from);
        $requestBody->setTo($to_formatted);
        $requestBody->setText($message);
        $response = $this->client->execute($requestBody);

    } catch(\Exception $e){
        throw new CustomException(1010,'Send Failed','Could not send SMS',400);
    }
  }

}