<?php

namespace App\Helpers;

use Aws;
use Aws\S3\S3Client;
use Aws\CommandPool;
use Aws\ResultInterface;
use Aws\AwsException;
use Aws\Credentials\Credentials;
use Aws\Credentials\CredentialProvider;
use Aws\Sts\StsClient;

// ******************************************************
// ***** HELPER THAT HANDLES EVERYTHING S3 UPLOADS ******
// ******************************************************
class S3Uploader 
{

  // CONSTRUCT
  protected $settings;
  protected $skip_domains = [];
  protected $s3_client;
  
  public function __construct($settings, $is_amrole=false) {
      $this->settings = $settings;

      if($is_amrole)
      {
        // TODO CHANGE IAM ROLE TO NOVO AFRICA
        // Passing Aws\Credentials\AssumeRoleCredentialProvider options directly
        $credentials = CredentialProvider::assumeRole([
          'client' => new StsClient(['region' => 'eu-west-1', 'version' => 'latest']),
          'assume_role_params' => [
              'RoleArn' => 'arn:aws:iam::950568853576:role/service-role/codebuild-apotex_webserver-service-role',
              'RoleSessionName' => 'test_session',
          ]
        ]);
        
        // Prepare credentials
        $credentials = CredentialProvider::memoize($credentials);

        $this->s3_client = new S3Client([
          'region'      => 'eu-west-1',
          'version'     => 'latest'
        ]);

      }else{
        
        // Prepare credentials
        $credentials = new Credentials($this->settings['s3_bucket']['access_key'], $this->settings['s3_bucket']['secret_key']);

        $this->s3_client = new S3Client([
          'region'      => 'eu-west-1',
          'version'     => 'latest',
          'credentials' => $credentials
        ]);

      }
      
  }

  // Set skip domains
  public function setSkipDomains (array $domain) {
    $this->skip_domains = $domain;
  }

  // Create a signed url
  public function createSignUrl ($file_ext) {

    $bucket = $this->settings['s3_bucket']['bucket_tmp'];
    $filename = uniqid() . "." .$file_ext;

    $cmd = $this->s3_client->getCommand('PutObject', [
      'Bucket' => $bucket,
      'Key' => $filename,
    ]);

    $aws_request = $this->s3_client->createPresignedRequest($cmd, '+20 minutes');

    // Get the actual presigned-url
    $presignedUrl = (string) $aws_request->getUri();

    $data = [
       "signedUrl" => $presignedUrl,
       "key" => $filename

    ];

    return $data;
  }


  // This function checks wether the given file should be moved or not
  // If the file already exists in the s3 bucket, then dont move it
  public function checkSkipDomain ($file) {

    foreach ($this->skip_domains as $key => $domain) {
      //echo $domain;
      //echo $file;
      if (strpos($file, $domain) !== false) { 
          // Skip moving this file 
          return true;
      }
    }

    return false;
  }

  // Upload files - either multiple or single
  public function move ($files, $path) {

    // Prepare urls return
    $urls = [];

    // Upload multiple files
    if(is_array($files))
    {
      // Perform a batch of CopyObject operations
      $batch = [];
      
      foreach ($files as $key => $row) {

        // Check skip domain first
        if($this->checkSkipDomain($row))
        {
          //echo $row;
          $urls[] = $row;
        }else
        {
          $batch[] = $this->s3_client->getCommand('CopyObject', [
              'Bucket'     => $this->settings['s3_bucket']['bucket_name'], // target
              'Key'        => $path . '/' . $row, // the ojbect
              'CopySource' => "{$this->settings['s3_bucket']['bucket_tmp']}/{$row}", // "{$sourceBucket}/{$sourceKeyname}",
              'ACL'    => 'public-read',
          ]);
        }
      }

      try {
          $results = CommandPool::batch($this->s3_client, $batch);
          foreach($results as $result) {
              if ($result instanceof ResultInterface) {
                  // Result handling here                 
                  $urls[] = $result['ObjectURL'];
              }
              if ($result instanceof AwsException) {
                  // AwsException handling here
                  throw new Exception($results->getMessage());
              }
          }
      } catch (\Exception $e) {
          // General error handling here
          //throw new Exception($e->getMessage());
          echo $e->getMessage();
      }

    }else // Upload a single file
    { 
      try {

        // Check skip domain first
        if($this->checkSkipDomain($files))
        {
          $urls[] = $files;
        }else
        {
          // Copy object from bucket
          $result = $this->s3_client->copyObject([
              'Bucket'     => $this->settings['s3_bucket']['bucket_name'], // target
              'Key'        => $path . '/' . $files, // the ojbect
              'CopySource' => "{$this->settings['s3_bucket']['bucket_tmp']}/{$files}",
              'ACL'    => 'public-read',
          ]);

          $urls[] = $result['ObjectURL'];
        }
                
      } catch (\Exception $e) {
        // General error handling here
        echo $e->getMessage();
      }
    
    }

    // Return the moves urls
    return $urls;
  }

  // Delete single or multiple files from S3
  public function delete ($files, $path) {

    // Delete multiple files
    if(is_array($files))
    {
       // Extract key name from path
      $images = [];

      foreach ($files as $key => $row) 
      {
        $files_str_array = explode("/", $row);
        $keyname = $path . "/" . $files_str_array[count($files_str_array)-1];
        $obj = [];
        $obj['Key'] = $keyname;
        $images[] = $obj;
      }

      $this->s3_client->deleteObjects([
            'Bucket'  => $this->settings['s3_bucket']['bucket_name'],
            'Delete' => [
                'Objects' => $images,
            ]
        ]);
      //}
    }else // delete single file
    {
      // Extract key name from path
      $files_str_array = explode("/", $files);
      // Get the last keyname, since its only 1
      $keyname = $files_str_array[count($files_str_array)-1];

      $result = $this->s3_client->deleteObject([
        'Bucket' => $this->settings['s3_bucket']['bucket_name'],
        'Key'    => $path . '/' . $keyname,
      ]);

    }

  }

  // Delete an entire s3 directory
  function delete_dir($keyname,$path){

    $results = $this->s3_client->listObjectsV2([
    'Bucket' => $this->settings['s3_bucket']['bucket_name'],
    'Prefix' => $path . '/' . $keyname
    ]);

    if (isset($results['Contents'])) {
        foreach ($results['Contents'] as $result) {
          $this->s3_client->deleteObject([
              'Bucket' => $this->settings['s3_bucket']['bucket_name'],
              'Key' => $result['Key']
          ]);
        }
    }

  }


}