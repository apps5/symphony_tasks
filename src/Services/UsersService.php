<?php

namespace App\Services;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UsersRepository;
use App\Utils\Validate;
use DateTime;

class UsersService
{
    protected EntityManagerInterface $entityManager;
    protected UsersRepository $usersRepository;
    protected Validate $validator;
    protected array $requiredFields;
    protected array $availableFieldsTypes;
    protected array $availableFieldsRead;
    protected array $availableFieldsEdit;

    public function __construct(EntityManagerInterface $entityManager)
    {
      $this->entityManager = $entityManager;
      $this->usersRepository = $this->entityManager->getRepository(Users::class);
      $this->availableFieldsTypes = ['id'=> 'Number', 'email'=>'Email', 'name'=>'Text', 'age'=>'Number', 'sex'=>'Text', 'birthday'=>'Date', 'phone'=>'Phone', 'created_at'=>'DateTime', 'updated_at'=>'DateTime', 'token'=>'Text', 'pass'=>'Text'];
      $this->availableFieldsRead = ['id', 'email', 'name', 'age', 'sex', 'birthday', 'phone', 'created_at', 'updated_at'];
      $this->availableFieldsEdit = ['email', 'name', 'sex', 'birthday', 'phone'];
      $this->requiredFields = ['email', 'name'];
      $this->validator = new Validate();
    }

    public function checkAuthorization(array $params, string $token, $service): bool
    {
      //Temporarily for demo cretae users
      $res = true;
      if($params['module'] != 'Users'){
        if($token){
          $userEntyti = $this->usersRepository->findOneBy([
            'token' => $token
          ]);
          if($userEntyti){
            $service->currentUser = $userEntyti;
          } else {
            $res = false;
          }
        } else {
          $res = false;
        }
      }
      return $res;
    }

    public function createUser(array $params): array
    {
      if(isset($params['id'])){
        $result = ['error' => 1, 'message' => 'should not be parameter id ...'];
      } else {
        if(isset($params['pass']) && !empty($params['pass'])){
          $result = $this->saveUser($params);
        } else {
          $result = ['error' => 1, 'message' => 'should be parameter pass ...'];
        }
      }
      return $result;
    }

    public function updateUser(array $params): array
    {
      if(isset($params['id']) && $params['id'] > 0){
        $result = $this->saveUser($params);
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result ;
    }

    public function getUser(array $params): array
    {
      if(isset($params['id']) && (int)$params['id'] > 0){
        $userEntyti = $this->getEntytiFromRequest($params);
        if($userEntyti){
          $result = ['result' => 'success', 'user' => $this->getDataUserFromEntyti($userEntyti)];
        } else {
          $result = ['error' => 1, 'message' => 'error get user data, user not found ...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result;
    }

    public function deleteUser(array $params): array
    {
      if(isset($params['id']) && (int)$params['id'] > 0){
        $userEntyti = $this->getEntytiFromRequest($params);
        if($userEntyti){
          $this->entityManager->remove($userEntyti);
          $this->entityManager->flush();
          $result = ['result' => 'success', 'user' => 'id '.$params['id'].' deleted ...'];
        } else {
          $result = ['error' => 1, 'message' => 'error delete, user not found ...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result;
    }

    public function authorizationUser(array $params): array
    {
      if(isset($params['pass']) && !empty($params['pass']) && isset($params['email']) && !empty($params['email'])){
        $userEntyti = $this->checkLogin($params);
        if($userEntyti){
          $result = ['result' => 'success', 'token' => $this->updateToken($userEntyti)];
        } else {
          $result = ['error' => 1, 'message' => 'user not found ...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'required parameters email and pass ...'];
      }
      return $result;
    }

    public function getUsers(array $params): array
    {
      $users = [];
      $usersEntyti = $this->usersRepository->findAll();
      foreach($usersEntyti as $userEntyti){
        $users[] = $this->getDataUserFromEntyti($userEntyti);
      }
      return ['result' => 'success', 'user' => $users];
    }

    public function saveUser(array $params): array
    {
      $requiredValidateResult = $this->validator->checkByType($params, $this->availableFieldsTypes);
      if($requiredValidateResult['error']){
        $result = ['error' => 1, 'message' => $requiredValidateResult['message']];
      } else {
        $userEntyti = $this->getEntytiFromRequest($params, $this->availableFieldsEdit);
        $this->recalculateAge($userEntyti);
        $this->updateDateTime($userEntyti);
        $resultRequiredFieldsPreSave = $this->checkRequiredFieldsPreSave($userEntyti);
        $this->setPass($userEntyti, $params);
        if(!$resultRequiredFieldsPreSave['error']){
          $this->entityManager->persist($userEntyti);
          $this->entityManager->flush();
          $result = ['result' => 'success', 'id' => $userEntyti->getId()];
        } else {
          $result = ['error' => 1, 'message' => $resultRequiredFieldsPreSave['message']];
        }
      }
      return $result;
    }

    public function recalculateAge(Users $userEntyti): Users
    {
      $birthday = $userEntyti->getBirthday();
      if($birthday){
        $date2 = new DateTime('now');
        $interval = $birthday->diff($date2);
        if($interval->y > 0){
          $userEntyti->setAge($interval->y);
        }
      } else {
        $userEntyti->setAge(null);
      }
      return $userEntyti;
    }

    public function updateDateTime(Users $userEntyti): Users
    {
      if(!$userEntyti->getId()){
        $userEntyti->setCreatedAt(new DateTime('now'));
        $userEntyti->setUpdatedAt(new DateTime('now'));
      } else {
        $userEntyti->setUpdatedAt(new DateTime('now'));
      }
      return $userEntyti;
    }

    public function checkRequiredFieldsPreSave(Users $userEntyti): array
    {
      $result = ['error' => 0];
      foreach($this->requiredFields as $fieldName){
        if($nameFunction = $this->getGetNameFunctionFields('get', $fieldName, $userEntyti)){
          if(!$userEntyti->{$nameFunction}()){
            $result = ['error' => 1, 'message'=> $fieldName .' cannot be empty ...'];
          }
        }
      }
      return $result;
    }

    public function setPass(Users $userEntyti, array $params): Users
    {
      if(!$userEntyti->getId()){
        $userEntyti->setPass($this->createPass($params['pass']));
      }
      return $userEntyti;
    }

    public function createPass($pass){
      return password_hash($pass, PASSWORD_BCRYPT, ['cost' => 14]);
    }
    
    public function checkPass($pass, $hash){
      return password_verify($pass, $hash);
    }

    public function checkLogin(array $params): null|Users
    {
      $userEntyti = $this->usersRepository->findOneBy([
        'email' => $params['email']
      ]);
      if($userEntyti){
        if($this->checkPass($params['pass'], $userEntyti->getPass())){
          return $userEntyti;
        }
      }
      return null;
    }

    public function updateToken(Users $userEntyti): string
    {
      $userEntyti->setToken(bin2hex(random_bytes(8)));
      $this->entityManager->persist($userEntyti);
      $this->entityManager->flush();
      return $userEntyti->getToken();
    }

    public function getEntytiFromRequest(array $params, array $availableFields=[]): null|Users
    {
      if(isset($params['id']) && !empty($params['id']) && $params['id'] > 0){
        $userEntyti = $this->usersRepository->find($params['id']);
      } else {
        $userEntyti = new Users();
      }
      if(!$userEntyti){
        return null;
      }
      foreach ($availableFields as $fieldName){
        if(isset($params[$fieldName])){
          if($nameFunction = $this->getGetNameFunctionFields('set', $fieldName, $userEntyti)){
            if($this->availableFieldsTypes[$fieldName] == 'Date' || $this->availableFieldsTypes[$fieldName] == 'DateTime'){
              if($params[$fieldName]){
                $userEntyti->{$nameFunction}(new DateTime($params[$fieldName]));
              } else {
                $userEntyti->{$nameFunction}(null);
              }
            } else {
              $userEntyti->{$nameFunction}($params[$fieldName]);
            }
          }
        }
      } 
      return $userEntyti;
    }

    public function getDataUserFromEntyti(Users $userEntyti): array
    {
      $dataUser = [];
      foreach($this->availableFieldsRead as $fieldName){
        $value = '';
        if($nameFunction = $this->getGetNameFunctionFields('get', $fieldName, $userEntyti)){
          if($this->availableFieldsTypes[$fieldName] == 'Date'){
            if($userEntyti->{$nameFunction}()){
              $value = $userEntyti->{$nameFunction}()->format('Y-m-d');
            }
          } else if($this->availableFieldsTypes[$fieldName] == 'DateTime'){
            if($userEntyti->{$nameFunction}()){
              $value = $userEntyti->{$nameFunction}()->format('Y-m-d H:i:s');
            }
          } else {
            $value = $userEntyti->{$nameFunction}() ? $userEntyti->{$nameFunction}() : '';
          }
          $dataUser[$fieldName] = $value;
        }
      }
      return $dataUser;
    }

    public function getGetNameFunctionFields(string $prefix, string $nameRequest, Users $userEntyti): bool|string
    {
      $nameFunction = false;
      $nameRequestArray = explode('_', $nameRequest);
      foreach($nameRequestArray as $key => $name){
        $nameRequestArray[$key] = strtolower($name);
      }
      $nameFunction = $prefix . implode('', $nameRequestArray);  
      if($nameFunction){
        if(!method_exists($userEntyti, $nameFunction)){
          $nameFunction = false;
        } 
      }
      return $nameFunction;
    }
}