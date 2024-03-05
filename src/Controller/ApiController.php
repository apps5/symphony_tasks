<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;


class ApiController extends AbstractController
{   
    protected $service = false;

    #[Route(path: '/', name: 'api', methods: ['POST'])]
    public function process(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
      $params = $this->getRequestParams($request);
      $this->setService($params, $entityManager);
      $action = $this->getAction($params);
      $data = $this->getData($params);
      if($action){
        $userService = new \App\Services\UsersService($entityManager); 
        $check = $userService->checkAuthorization($params, $this->getBearerToken(), $this->service);
        if($check){
          $result = $this->service->{$action}($data);
        } else {
          $result = ['error' => 1, 'message' => 'access is denied...'];
        }
      } else {
        $result = ['error' => 1, 'message' => 'module or action invalid...'];
      }
      return new JsonResponse($result);
    }

    protected function getRequestParams(Request $request): array
    {
      $params = json_decode($request->getContent(), true); 
      return $params ? $params : [];
    }

    protected function setService(array $params, EntityManagerInterface $entityManager): bool
    {
      if(isset($params['module']) && !empty($params['module'])){
        $serviceName = '\\App\\Services\\'.$params['module'].'Service';       
        if(class_exists($serviceName)) {             
          $this->service = new $serviceName ($entityManager);
          return true;
        }
      }
      return false;
    }
    
    protected function getAction(array $params): bool|string
    { 
      $action = false;
      if(isset($params['action']) && !empty($params['action']) && $this->service){
        if(method_exists($this->service, $params['action'])){
          $action = $params['action'];
        }
      }
      return $action;
    }

    protected function getData(array $params): array
    { 
      $user = [];
      if(isset($params['params']) && is_array($params['params']) && count($params['params']) > 0){
        $user = $params['params'];
      }
      return $user;
    }

    public function getAuthorizationHeader(){
      $headers = null;
      if (isset($_SERVER['Authorization'])) {
          $headers = trim($_SERVER["Authorization"]);
      }
      else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
          $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
      } 
      
      
      else if (isset($_SERVER['X-Authorization'])) {
          $headers = trim($_SERVER["X-Authorization"]);
      }
      else if (isset($_SERVER['HTTP_X_AUTHORIZATION'])) {
          $headers = trim($_SERVER["HTTP_X_AUTHORIZATION"]);
      } 
      
      
      else if (function_exists('apache_request_headers')) {
          $requestHeaders = apache_request_headers();
          $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
          if (isset($requestHeaders['Authorization'])) {
              $headers = trim($requestHeaders['Authorization']);
          }
      }
      return $headers;
    }
  
    public function getBearerToken() {
      $headers = $this->getAuthorizationHeader();
      if (!empty($headers)) {
          if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
              return $matches[1];
          }
      }
      return null;
    }
}