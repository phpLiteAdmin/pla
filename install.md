# phpLiteAdmin

install with composer
```bash
$ composer require planetacodigo/pla
```

who to use
```php
require("vendor/autoload.php");

use phpLiteAdmin\phpLiteAdmin;

$phpLiteAdmin = new phpLiteAdmin();
$phpLiteAdmin->setPassword('admin');
$phpLiteAdmin->setDirectory('.');
$phpLiteAdmin->setDatabases([
    [
        'path'=> 'database1.sqlite',
        'name'=> 'Database 1'
    ],
    [
        'path'=> 'database2.sqlite',
        'name'=> 'Database 2'
    ]
]);

$phpLiteAdmin->web();

```