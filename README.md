# Slim 4 MVC Skeleton

This is a simple web application skeleton project that uses the [Slim4 Framework](http://www.slimframework.com/):
* [PHP-DI](http://php-di.org/) as dependency injection container
* [Slim-Psr7](https://github.com/slimphp/Slim-Psr7) as PSR-7 implementation
* [Twig](https://twig.symfony.com/) as template engine
* [Monolog](https://github.com/Seldaek/monolog)
* [Console](https://github.com/symfony/console)

## CAUTION

**The Slim Twig-View is still in active development and can introduce breaking changes. It is 
an beta release. Of course you can use this skeleton, but be warned. As soon as
you update the Slim Twig-View, you might have to modify your code.**


## Prepare

1. Create your project:


   ```bash
   clone <this repo>
   composer install
   docker-composer up
   ```

## Run it:

1. `cd [your-app]`
2. `php -S 0.0.0.0:8888 -t public/`
3. Browse to http://localhost:8888


### Notice

- Set `var` folder permission to writable when deploy to production environment