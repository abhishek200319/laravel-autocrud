
# Laravel AutoCrud

Laravel AutoCrud is a handy package that helps you quickly create CRUD (Create, Read, Update, Delete) API endpoints for your Laravel applications. With just a single Artisan command, you can generate all the necessary files to manage your resources effortlessly.

## Installation

You can install Laravel AutoCrud via Composer:

````
composer require abhishekdixit0407/laravel-autocrud
````
After installation, just add below provider in app.php inside 'providers' key:
````
Api\LaravelAutocrud\AutoCrudServiceProvider::class
````
To generate CRUD API endpoints for a resource, use the following command:
````
php artisan autocrud:api ResourceName --columns="name:string,email:string" 
````
Replace ResourceName with the name of your unique resource(Example:User,Post,Medicine....etc).
Use the --columns option to specify the columns of your database with its type (their is no limaitation to add column)

Modify migration file according to your need and run:
````
php artisan migrate
````