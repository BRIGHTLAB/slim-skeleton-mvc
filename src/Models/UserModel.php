<?php

namespace App\Models;

use App\Interfaces\ModelValidationInterface;

class UserModel extends BaseModel implements ModelValidationInterface, \JsonSerializable {

	protected $id;
	protected $first_name;
	protected $last_name;
	protected $salt_hash;
	protected $enc_password;
	protected $email;
	protected $speciality_id;
	protected $country;
	protected $activate_token;
	protected $reset_token;
	protected $removed = 0;
	protected $deactivated = 0;

	public function getId(){return $this->id;}
	public function getFirstName(){return $this->first_name;}
	public function getLastName(){return $this->last_name;}
	public function getSaltHash(){return $this->salt_hash;}
	public function getEncPassword(){return $this->enc_password;}
	public function getEmail(){return $this->email;}
	public function getSpecialityId(){return $this->speciality_id;}
	public function getCountry(){return $this->country;}
	public function getActivateToken(){return $this->activate_token;}
	public function getResetToken(){return $this->reset_token;}
	public function getRemoved(){return $this->removed;}
	public function getDeactivated(){return $this->deactivated;}

	public function setId($value){$this->id=$value;}
	public function setFirstName($value){$this->first_name=$value;}
	public function setLastName($value){$this->last_name=$value;}
	public function setSaltHash($value){$this->salt_hash=$value;}
	public function setEncPassword($value){$this->enc_password=$value;}
	public function setEmail($value){$this->email=$value;}
	public function setSpecialityId($value){$this->speciality_id=$value;}
	public function setCountry($value){$this->country=$value;}
	public function setActivateToken($value){$this->activate_token=$value;}
	public function setResetToken($value){$this->reset_token=$value;}
	public function setRemoved($value){$this->removed=$value;}
	public function setDeactivated($value){$this->deactivated=$value;}


	// what to dispaly for the serializer
	public function jsonSerialize() {
        return [
        	"id" => $this->getId(),
			"first_name" => $this->getFirstName(),
			"last_name" => $this->getLastName(),
			"salt_hash" => $this->getSaltHash(),
			"enc_password" => $this->getEncPassword(),
			"email" => $this->getEmail(),
			"activate_token" => $this->getActivateToken(),
			"reset_token" => $this->getResetToken(),
			"removed" => $this->getRemoved(),
			"deactivated" => $this->getDeactivated(),
			"speciality_id" => $this->getSpecialityId(),
			"country" => $this->getCountry()
        ];
    }

	// interface requirments
	public function getRequiredProperties () {
		return [
			'first_name', 'last_name', 'email', 'speciality_id', 'country'];
	}

}
