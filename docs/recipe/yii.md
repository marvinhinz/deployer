<!-- DO NOT EDIT THIS FILE! -->
<!-- Instead edit recipe/yii.php -->
<!-- Then run bin/docgen -->

# yii

[Source](/recipe/yii.php)



* Requires
  * [common](/docs/recipe/common.md)

## Configuration
### shared_dirs
[Source](https://github.com/deployphp/deployer/blob/master/recipe/yii.php#L9)

Overrides [shared_dirs](/docs/recipe/common.md#shared_dirs) from `recipe/common.php`.

Yii shared dirs

```php title="Default value"
['runtime']
```


### writable_dirs
[Source](https://github.com/deployphp/deployer/blob/master/recipe/yii.php#L12)

Overrides [writable_dirs](/docs/recipe/deploy/writable.md#writable_dirs) from `recipe/deploy/writable.php`.

Yii writable dirs

```php title="Default value"
['runtime']
```



## Tasks

### deploy
[Source](https://github.com/deployphp/deployer/blob/master/recipe/yii.php#L18)

Deploy your project.

Main task


This task is group task which contains next tasks:
* [deploy:prepare](/docs/recipe/common.md#deployprepare)
* [deploy:vendors](/docs/recipe/deploy/vendors.md#deployvendors)
* [deploy:publish](/docs/recipe/common.md#deploypublish)

