<?php
namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Exceptions\CustomException;

final class JwtHelper
{
    protected $secret;
    protected $tokenKey;

    public function __construct(Array $config) {
        $this->secret = $config['secret'] ?? null ;
        $this->tokenKey = $config['tokenKey'] ?? null ;
    }

	public function generateToken(Array $data)
	{
        // retrieve uid from array data
        $uid = $data['uid'];

        // Generate JWT Token
        $factory = new \PsrJwt\Factory\Jwt();
        $builder = $factory->builder();

        // Prepare token date
        $token_date = new \DateTime('');
        $token_date->modify('+120 minute');
        
        $token = $builder->setSecret($this->secret)
				->setPayloadClaim('uid', $uid)
				->setPayloadClaim('date', $token_date)
                ->build();

        return $token->getToken();
    }

    public function generateRefreshToken(Array $data)
	{
        // retrieve uid from array data
        $uid = $data['uid'];

        // Generate JWT Token
        $factory = new \PsrJwt\Factory\Jwt();
        $builder = $factory->builder();

        // Prepare refresh token date
        $refresh_token_date = new \DateTime('');
        $refresh_token_date->modify('+7 day');

        $refresh_token = $builder->setSecret($this->secret)
            ->setPayloadClaim('uid', $uid)
            ->setPayloadClaim('date', $refresh_token_date)
            ->build();

        return $refresh_token->getToken();
    }

    // Get playload of a token
    public function getPayload(string $token)
	{
         // Parse token - get its content
         $factory = new \PsrJwt\Factory\Jwt();
         $parser = $factory->parser($token, $this->secret);
         $parser->validate();
         $parsed = $parser->parse();
         $parsed_content = $parsed->getPayload();
         return $parsed_content;
    }


    // Takes a refresh token, checks if its valid and then regenerates tokens
    public function regenerate($uid, $request)  {
        
        try {

            $bearer_token = $request->getHeader('Authorization');
            $token = explode(" ", $bearer_token[0]);

            // Get playload 
            $parsed_content = $this->getPayload($token[1]);
            $token_date = $parsed_content['date']['date'];
            $date_now = new \DateTime();
            $date2    = new \DateTime($token_date);

            // Check if token date has expired
            if ($date_now > $date2) {
                throw new CustomException(2006,'Invalid Authorization','Token is expired.',406);
            }

            // Generate JWT tokens
            $token = $this->generateToken(["uid" => $uid]);
            $refresh_token = $this->generateRefreshToken(["uid" => $uid]);

            $data['id'] = $uid;
            $data['token'] = $token;
            $data['refresh_token'] = $refresh_token;

            return $data;

            // $payload = json_encode(['id'=>$args['users_id'] ,'token' => $token, 'refresh_token' => $refresh_token], JSON_PRETTY_PRINT);
            // $response->getBody()->write($payload);
            // return $response->withHeader('Content-Type', 'application/json')->withStatus(200);

        } catch (\Exception $e) {

            throw new CustomException(2000,'Invalid Authorization','Supplied Token is invalid or expired',403);
        }

        return [];
        //return $response->withStatus(400);
    }
    
}
