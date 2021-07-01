<?php

namespace App\Helpers;

class Cloudfront {

    private $keypairid;
    private $pvtkeyfile;
    private $cloudfront_domain;

    function __construct (array $config = []) {

        if (isset($config['key_pair_id']))
            $this->keypairid = $config['key_pair_id'];
        
        if (isset($config['private_key_location']))
            $this->pvtkeyfile = $config['private_key_location'];

        if (isset($config['cloudfront_domain']))
            $this->cloudfront_domain = $config['cloudfront_domain'];
    }

    private function rsa_sha1_sign($policy) {
        $signature = "";

        // load the private key
        $fp = fopen($this->pvtkeyfile, "r");
        $priv_key = fread($fp, 8192);
        fclose($fp);
        $pkeyid = openssl_get_privatekey($priv_key);

        // compute signature
        openssl_sign($policy, $signature, $pkeyid);

        // free the key from memory
        openssl_free_key($pkeyid);
        return $signature;
    }

    private function url_safe_base64_encode($value) {
        $encoded = base64_encode($value);
        // replace unsafe characters +, = and / with the safe characters -, _ and ~
        return str_replace(array('+', '=', '/'), array('-', '_', '~'), $encoded);
    }

    private function create_stream_name($stream, $signature, $expires) {
        $result = $stream;
        // if the stream already contains query parameters, attach the new query parameters to the end
        // otherwise, add the query parameters
        $separator = strpos($stream, '?') == FALSE ? '?' : '&';
        $result .= $separator . "Expires=" . $expires . "&Key-Pair-Id=" . $this->keypairid . "&Signature=" . $signature;
        // new lines would break us, so remove them
        return str_replace('\n', '', $result);
    }

    public function get_signed_stream_name($video_path, $expires) {

        $video_path = $video_path;
        
        // this policy is well known by CloudFront, but you still need to sign it, since it contains your parameters
        $canned_policy = '{"Statement":[{"Resource":"' . $video_path . '","Condition":{"DateLessThan":{"AWS:EpochTime":' . $expires . '}}}]}';

        // sign the original policy, not the encoded version
        $signature = $this->rsa_sha1_sign($canned_policy);

        // make the signature safe to be included in a url
        $encoded_signature = $this->url_safe_base64_encode($signature);

        // combine the above into a stream name
        $stream_name = $this->create_stream_name($video_path, $encoded_signature, $expires);

        return $stream_name;
    }

}