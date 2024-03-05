# Docker + PHP 8.2 + MySQL + Nginx + Symfony 6.2

## Description

This is a complete stack for running Symfony 6.2 into Docker containers using docker-compose tool.

It is composed by 3 containers:

- `nginx`, acting as the webserver.
- `php`, the PHP-FPM container with the 8.2 version of PHP.
- `db` which is the MySQL database container with a **MySQL 8.0** image.

## Installation

1. Clone this repo.

2. Go inside folder `.docker` and run `docker compose up -d` to start containers.

3. Inside the `php` container, run `composer install` to install dependencies from `/var/www/symfony` folder.

4. Inside the `php` container, run `php bin/console doctrine:schema:create` to create tables entyti

## Example requests

 host : 
  - http://localhost:8088

 body json for Users:
  - {"module": "Users", "action": "createUser", "params":{"name": "Василий","email": "test@2test.ru", "phone":"79302112211 ", "birthday": "2001-02-28", "pass":"1234"}}
  - {"module": "Users", "action": "authorizationUser", "params":{"email": "test@2test.ru", "pass":"1234"}}
  - {"module": "Users", "action": "getUsers"}

 body json for Tasks:
  - {"module": "Tasks", "action": "createTask", "params":{"subject": "Test Task","description": "Test description task"}}
  - {"module": "Tasks", "action": "getTasks"}
  - {"module": "Tasks", "action": "getTask", "params": {"id":1}}
  - {"module": "Tasks", "action": "updateTask", "params": {"id":1, "subject": "Test Task2"}
  - {"module": "Tasks", "action": "updateTask", "params": {"id":1, "status": "1"}}
  - {"module": "Tasks", "action": "deleteTask", "params": {"id":1}}

headers for Tasks:
  - Authorization Bearer {token}

