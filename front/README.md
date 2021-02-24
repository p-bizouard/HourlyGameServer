# HourlyGameServer - hgs (/hgs)

## Requirements

-   Git
-   Docker / docker-compose

## Local installation - execute once

-   Install dependencies, configure environments, run containers:

```
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php composer install # for IDE completion
ln -s ../../bin/pre-commit.sh .git/hooks/pre-commit
cp -f .env .env.local
```

-   Setup assets :

```
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php yarn install
```

-   Launch php + database + assets

```
docker-compose up
```

> If errors pop up because you already have containers using required ports, you can stop all running containers with `docker stop $(docker ps -aq)`

-   Load fixtures:

```
docker exec -it -w /app hgs_php_1 php bin/console hautelook:fixtures:load --env=dev
```

## Development environement - execute daily

### Launch the development environment

```
docker-compose up
docker cp .openrc "$(docker-compose ps -q php)":/home/user/.openrc
docker cp id_rsa "$(docker-compose ps -q php)":/home/user/.ssh/id_rsa
docker-compose exec php chmod 0600 /home/user/.ssh/id_rsa
```

### Access to your containers

-   Mailhog : http://localhost:8025/
-   phpMyAdmin : http://localhost:9080/
-   Webapp : http://localhost/

## Useful commands - execute if needed

### To fix lint errors (this is automaticaly executed on commit, and should be executed in your IDE on save):

```
docker-compose run php vendor/bin/php-cs-fixer fix
```

### To add a composer dependency

The first command install vendors on the docker volume the second install vendors on your workstation for the IDE features

```
docker-compose run php composer require YOUR_PACKAGE
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php composer install
```

### To update composer dependencies (if someone updated composer.json):

The first command install vendors on the docker volume the second install vendors on your workstation for the IDE features

```
docker-compose run php composer install
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php composer install
```

### To add an asset dependency

The first command install vendors on the docker volume the second install vendors on your workstation for the IDE features

```
docker-compose run php yarn install YOUR_PACKAGE
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php yarn install
```

### To update asset dependencies (if someone updated packages.json):

The first command install vendors on the docker volume the second install vendors on your workstation for the IDE features

```
docker-compose run php composer install
docker run -it --rm -v ${PWD}/front:/app -w /app hgs_php composer install
```

### To add properties in your entities :

1. Add your property in the entity
2. Generate getters and setters :

```
docker-compose run php php bin/console make:entity --regenerate 'App\Entity'
```

3. Create a migration file :

```
docker-compose run php php bin/console make:migration
```

4. Execute the migration

```
docker-compose run php php bin/console doctrine:migrations:migrate
```

## Tests

### Run tests locally

Launch docker environment :

```shell script
docker-compose up -d
```

Execute migrations and load fixtures :

```shell script
docker-compose run php composer install # If not done yet
docker-compose run php yarn install # If not done yet
docker-compose run php yarn run dev
docker-compose run php php bin/console doctrine:migrations:migrate -n -e test
docker-compose run php php bin/console hautelook:fixtures:load -n -e test
```

Run tests :

```shell script
docker-compose run php php bin/phpunit
```

Close docker environment :

```shell script
docker-compose down
```

### Run tests in gitlabrunner

With gitlab runner installed you can run :

```shell script
gitlab-runner exec docker --docker-pull-policy="if-not-present" test
```
