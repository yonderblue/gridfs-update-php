# gridfs-update-php

[![Build Status](https://travis-ci.org/gaillard/gridfs-update-php.png)](https://travis-ci.org/gaillard/gridfs-update-php)

Library to make mongo gridfs in-place updates

## Requirements

Requires PHP 5.4.0 (or later).

## Installation
To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)!
```json
{
    "require": {
        "gaillard/gridfs-update": "~1.0"
    }
}
```

## Example

```php
$id = new \MongoId();

$gridfs = (new \MongoClient())->selectDB('gridfsUpdaterExample')->getGridFS();
$gridfs->storeBytes('123456', ['_id' => $id, 'metadata' => ['key1' => 'Hello', 'key2' => 'Mr.', 'key3' => 'Smith']]);

$before = $gridfs->findOne();
echo 'metadata is ';
var_dump($before->file['metadata']);
echo "bytes are {$before->getBytes()}\n";

GridFsUpdater::update(
    $gridfs,
    $id,
    '7890',
    [
        '$set' => ['metadata.key2' => 'Bob'],
        '$unset' => ['metadata.key3' => ''],
    ]
);

$after = $gridfs->findOne();
echo 'metadata is now ';
var_dump($after->file['metadata']);
echo "bytes are now {$after->getBytes()}\n";
```

prints

```sh
metadata is array(3) {
  'key1' =>
  string(5) "Hello"
  'key2' =>
  string(3) "Mr."
  'key3' =>
  string(5) "Smith"
}
bytes are 123456
metadata is now array(2) {
  'key1' =>
  string(5) "Hello"
  'key2' =>
  string(3) "Bob"
}
bytes are now 7890
```

Which has updated the chunks without removing them (unless there are extra) and the file doc as well. Primary benefit is speed. Please be
aware of any side effects in your system for concurrent access. The [mongo-lock-php](https://github.com/gaillard/mongo-lock-php) library could
help!

## Contributing
If you would like to contribute, please use the build process for any changes
and after the build passes, send a pull request on github!
```sh
./build.php
```
