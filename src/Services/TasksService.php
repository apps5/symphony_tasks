<?php

namespace App\Services;

use App\Entity\Tasks;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\TasksRepository;
use App\Utils\Validate;
use DateTime;

class TasksService
{
    public Users $currentUser;
    protected EntityManagerInterface $entityManager;
    protected TasksRepository $tasksRepository;
    protected Validate $validator;
    protected array $requiredFields;
    protected array $availableFieldsTypes;
    protected array $availableFieldsRead;
    protected array $availableFieldsEdit;

    public function __construct(EntityManagerInterface $entityManager)
    {
      $this->entityManager = $entityManager;
      $this->tasksRepository = $this->entityManager->getRepository(Tasks::class);
      $this->availableFieldsTypes = ['id'=> 'Number', 'subject'=>'Text', 'description'=>'Text', 'status'=>'Number', 'assigned_user_id'=>'Number', 'completed_at'=>'DateTime', 'created_at'=>'DateTime', 'updated_at'=>'DateTime'];
      $this->availableFieldsRead = ['id', 'subject', 'description', 'status', 'assigned_user_id', 'completed_at', 'created_at', 'updated_at'];
      $this->availableFieldsEdit = ['subject', 'description', 'status', 'assigned_user_id'];
      $this->requiredFields = ['subject', 'assigned_user_id'];
      $this->validator = new Validate();
    }

    public function createTask(array $params): array
    {
      if(isset($params['id'])){
        $result = ['error' => 1, 'message' => 'should not be parameter id ...'];
      } else {
        $result = $this->saveTask($params);
      }
      return $result;
    }

    public function updateTask(array $params): array
    {
      if(isset($params['id']) && $params['id'] > 0){
        $result = $this->saveTask($params);
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result ;
    }

    public function getTask(array $params): array
    {
      if(isset($params['id']) && (int)$params['id'] > 0){
        $taskEntyti = $this->getEntytiFromRequest($params);
        if($taskEntyti){
          $result = ['result' => 'success', 'task' => $this->getDataTaskFromEntyti($taskEntyti)];
        } else {
          $result = ['error' => 1, 'message' => 'error get task data, task not found ...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result;
    }

    public function deleteTask(array $params): array
    {
      if(isset($params['id']) && (int)$params['id'] > 0){
        $taskEntyti = $this->getEntytiFromRequest($params);
        if($taskEntyti){
          $this->entityManager->remove($taskEntyti);
          $this->entityManager->flush();
          $result = ['result' => 'success', 'task' => 'id '.$params['id'].' deleted ...'];
        } else {
          $result = ['error' => 1, 'message' => 'error delete, task not found ...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'required parameter id ...'];
      }
      return $result;
    }

    public function getTasks(array $params): array
    {
      $tasks = [];
      $tasksEntyti = $this->tasksRepository->findAll();
      foreach($tasksEntyti as $taskEntyti){
        $tasks[] = $this->getDataTaskFromEntyti($taskEntyti);
      }
      return ['result' => 'success', 'tasks' => $tasks];
    }

    public function saveTask(array $params): array
    {
      $requiredValidateResult = $this->validator->checkByType($params, $this->availableFieldsTypes);
      if($requiredValidateResult['error']){
        $result = ['error' => 1, 'message' => $requiredValidateResult['message']];
      } else {
        $taskEntyti = $this->getEntytiFromRequest($params, $this->availableFieldsEdit);
        if($taskEntyti){
          $this->updateDateTime($taskEntyti);
          $resultRequiredFieldsPreSave = $this->checkRequiredFieldsPreSave($taskEntyti);
          if(!$resultRequiredFieldsPreSave['error']){
            $this->entityManager->persist($taskEntyti);
            $this->entityManager->flush();
            $result = ['result' => 'success', 'id' => $taskEntyti->getId()];
          } else {
            $result = ['error' => 1, 'message' => $resultRequiredFieldsPreSave['message']];
          }
        } else {
          $result = ['error' => 1, 'message' => 'error get task data, task not found ...'];
        }
      }
      return $result;
    }


    public function updateDateTime(Tasks $taskEntyti): Tasks
    {
      if(!$taskEntyti->getId()){
        $taskEntyti->setCreatedAt(new DateTime('now'));
        $taskEntyti->setUpdatedAt(new DateTime('now'));
        $taskEntyti->setAssignedUserId($this->currentUser->getId());
      } else {
        if($taskEntyti->getStatus() == 1){
          $taskEntyti->setCompletedAt(new DateTime('now'));
        } else {
          $taskEntyti->setCompletedAt(null);
        }
        $taskEntyti->setUpdatedAt(new DateTime('now'));
      }
      return $taskEntyti;
    }

    public function checkRequiredFieldsPreSave(Tasks $taskEntyti): array
    {
      $result = ['error' => 0];
      foreach($this->requiredFields as $fieldName){
        if($nameFunction = $this->getGetNameFunctionFields('get', $fieldName, $taskEntyti)){
          if(!$taskEntyti->{$nameFunction}()){
            $result = ['error' => 1, 'message'=> $fieldName .' cannot be empty ...'];
          }
        }
      }
      return $result;
    }

    public function getEntytiFromRequest(array $params, array $availableFields=[]): null|Tasks
    {
      if(isset($params['id']) && !empty($params['id']) && $params['id'] > 0){
        $taskEntyti = $this->tasksRepository->find($params['id']);
      } else {
        $taskEntyti = new Tasks();
      }
      if(!$taskEntyti){
        return null;
      }
      foreach ($availableFields as $fieldName){
        if(isset($params[$fieldName])){
          if($nameFunction = $this->getGetNameFunctionFields('set', $fieldName, $taskEntyti)){
            if($this->availableFieldsTypes[$fieldName] == 'Date' || $this->availableFieldsTypes[$fieldName] == 'DateTime'){
              if($params[$fieldName]){
                $taskEntyti->{$nameFunction}(new DateTime($params[$fieldName]));
              } else {
                $taskEntyti->{$nameFunction}(null);
              }
            } else {
              $taskEntyti->{$nameFunction}($params[$fieldName]);
            }
          }
        }
      } 
      return $taskEntyti;
    }

    public function getDataTaskFromEntyti(Tasks $taskEntyti): array
    {
      $dataTask = [];
      foreach($this->availableFieldsRead as $fieldName){
        $value = '';
        if($nameFunction = $this->getGetNameFunctionFields('get', $fieldName, $taskEntyti)){
          if($this->availableFieldsTypes[$fieldName] == 'Date'){
            if($taskEntyti->{$nameFunction}()){
              $value = $taskEntyti->{$nameFunction}()->format('Y-m-d');
            }
          } else if($this->availableFieldsTypes[$fieldName] == 'DateTime'){
            if($taskEntyti->{$nameFunction}()){
              $value = $taskEntyti->{$nameFunction}()->format('Y-m-d H:i:s');
            }
          } else {
            $value = $taskEntyti->{$nameFunction}() ? $taskEntyti->{$nameFunction}() : '';
          }
          $dataTask[$fieldName] = $value;
        }
      }
      return $dataTask;
    }

    public function getGetNameFunctionFields(string $prefix, string $nameRequest, Tasks $taskEntyti): bool|string
    {
      $nameFunction = false;
      $nameRequestArray = explode('_', $nameRequest);
      foreach($nameRequestArray as $key => $name){
        $nameRequestArray[$key] = strtolower($name);
      }
      $nameFunction = $prefix . implode('', $nameRequestArray);  
      if($nameFunction){
        if(!method_exists($taskEntyti, $nameFunction)){
          $nameFunction = false;
        } 
      }
      return $nameFunction;
    }
}