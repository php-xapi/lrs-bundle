LRS Bundle
==========

This Symfony bundle helps you generate the server side of a Learning Record Store, as defined by the xAPI (or Tin Can API).

To setup, you will need to:
- add the repository and require to the composer.json of your project
```
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/php-xapi/lrs-bundle"
        }
    ],
    "require": {
        ...,
        "php-xapi/lrs-bundle": "0.1.x-dev"
    }
```
(replace php-xapi by your own user if you have forked the project)
- launch `composer update` to download the corresponding libraries
- add the bundle to app/AppKernel.php in your application
```
        $bundles = [
            ...
            new XApi\LrsBundle\XApiLrsBundle(),
        ];
```
- update the config.yml (or config_dev.yml)
```
xapi_lrs:
    type: orm
    object_manager_service: doctrine.orm.entity_manager
```

There are still issues with the current version of this bundle requesting classes from dependencies which have removed them (documented in php-xapi/lrs-bundle/CHANGELOG.md).

