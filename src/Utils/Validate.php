<?php

namespace App\Utils;

use DateTime;

class Validate
{
    public function checkRequired(array $params, array $required): array
    {
      foreach ($required as $fieldName) {    
        if (!isset($params[$fieldName]) || empty($params[$fieldName])) {       
          return ['error' => 1, 'message'=> $fieldName .' required parameter...'];
        }
      }      
      return ['error' => 0];
    }

    public function checkByType(array $params, array $fieldsTypes): array
    {
      foreach ($fieldsTypes as $fieldName => $fieldType) {    
        if (isset($params[$fieldName]) && !empty($params[$fieldName])) {     
          $resultCheckByType = $this->{'check'.$fieldType}($params[$fieldName]);
          if($resultCheckByType['error']){
            return $resultCheckByType;
          }
        }
      }
      return ['error' => 0];
    }

    public function check(array $params, array $required, array $fieldsTypes): array
    {
      $resultCheckRequired = $this->checkRequired($params, $required);
      if($resultCheckRequired['error']){
        return $resultCheckRequired;
      }
      $resultCheckByType = $this->checkByType($params, $fieldsTypes);
      if($resultCheckByType['error']){
        return $resultCheckByType;
      }
      return ['error' => 0];
    }

    public function checkEmail(string $email): array
    {
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
        return ['error' => 1, 'message'=> $email .' is not a valid email address...'];
      }
      return ['error' => 0];
    }

    public function checkText(string $string): array
    {
      if(trim($string)){
        return ['error' => 0];
      } else {
        return ['error' => 1, 'message'=> $string .' cannot be empty...'];
      }
    }

    public function checkNumber(string $number): array
    {
      if ((is_int($number) || ctype_digit($number)) && (int)$number >= 0 ){
        return ['error' => 0];
      } else {
        return ['error' => 1, 'message'=> $number .' is not a valid number...'];
      }
    }

    public function checkPhone(string $phone): array
    {
      $phone = preg_replace('/\s|\+|-|\(|\)/','', $phone);
      if ((substr($phone, 0, 1) != '7' || substr($phone, 0, 1) != '8') && strlen($phone) != 11) {
        return ['error' => 1, 'message'=> $phone .' is not a valid phone...'];
      }
      return ['error' => 0];
    }

    public function checkDate(string $date): array
    {
      $format = 'Y-m-d';
      $d = DateTime::createFromFormat($format, $date);
      if($d && $d->format($format) == $date){
        return ['error' => 0];
      }
      return ['error' => 1, 'message'=> $date .' is not a valid date '.$format.'...'];
    }

    public function checkDateTime(string $datetime): array
    {
      $format = 'Y-m-d H:i:s';
      $d = DateTime::createFromFormat($format, $datetime);
      if($d && $d->format($format) == $datetime){
        return ['error' => 0];
      }
      return ['error' => 1, 'message'=> $datetime .' is not a valid datetime '.$format.'...'];
    }
}