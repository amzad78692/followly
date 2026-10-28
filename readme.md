## Setup Steps

- Configure env file

- run the following commands

```
composer install
```

```
php artisan migrate
```

```
php artisan key:generate
```

- Now you need to create a super admin. Run this command to create a super admin

```
php artisan shield:install
php artisan shield:super-admin
run_php artisan shield:generate --all
```

- Now run this command to add all countries and cities

```
php -d memory_limit=512M -d max_execution_time=300 artisan db:seed --class=WorldSeeder
```
