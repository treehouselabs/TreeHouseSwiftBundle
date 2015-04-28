Implementation of the OpenStack Swift protocol. Can be used for an object-store.

[![Build Status](https://travis-ci.org/treehouselabs/TreeHouseSwiftBundle.svg)](https://travis-ci.org/treehouselabs/TreeHouseSwiftBundle)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseSwiftBundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseSwiftBundle/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseSwiftBundle/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/treehouselabs/TreeHouseSwiftBundle/?branch=master)

## Installation

Add dependency:

```sh
composer require treehouselabs/swift-bundle:~1.0
```

Enable bundles (the KeystoneBundle is a dependency for this bundle):

```php
$bundles[] = new TreeHouse\KeystoneBundle\TreeHouseKeystoneBundle();
$bundles[] = new TreeHouse\SwiftBundle\TreeHouseSwiftBundle();
```

## Configuration

If you haven't done so already, configure the KeystoneBundle. For more information check the
[documentation][1] for that bundle. Then add the object store(s) to the Swift configuration.

[1]: https://github.com/treehouselabs/TreeHouseKeystoneBundle/blob/master/src/TreeHouse/KeystoneBundle/Resources/doc/01-setup.md

```yaml
# app/config/config.yml
tree_house_keystone:
  user_class: Acme\DemoBundle\Entity\User
  services:
    cdn:
      type: object-store
      endpoint: http://cdn.acme.org/

tree_house_swift:
  stores:
    cdn: tree_house.keystone.service.cdn
```

Enable the routing:

```yaml
# app/config/routing.yml
cdn:
  resource: @TreeHouseSwiftBundle/Resources/config/routing.yml
  host:     cdn.acme.org
```

By default, all the routes are secured with the `ROLE_USER` expression, except for the `head_object` and `get_object` route.
You can override this expression for each individual route if you want to, or you can set the default expression in the
configuration:

```yaml
# app/config/config.yml
tree_house_swift:
  expression: ROLE_CDN_USER
```

Configure a firewall for the object store if you want to use token-based authentication.
The bundle does **not** do this automatically, you have to configure it yourself.
Fortunately it's really easy to do:

```yaml
# app/config/security.yml
security:
  firewalls:
    cdn:
      pattern:   ^/
      host:      cdn.acme.org
      anonymous: true
      stateless: true
      simple_preauth:
        authenticator: tree_house.keystone.token_authenticator
```

That's it, now all the object-store requests will try to authenticate using a token. By setting the firewall to allow
anonymous users we ensure that you don't have to authenticate to request an object (which is kind of the point of an
object-store).
