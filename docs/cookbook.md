# Cookbook

## Validate an uploaded project

```php
$stream = fopen($_FILES['project']['tmp_name'], 'rb');

try {
    $parser = ELPParser::fromStream($stream, 'elpx');
    $result = $parser->validate();
} finally {
    fclose($stream);
}
```

## Build a searchable catalog row

```php
$info = ELPParser::inspect($path);

$row = [
    'title' => $info['title'],
    'author' => $info['author'],
    'language' => $info['language'],
    'profile' => $info['packageProfile'],
];
```

## Compare two revisions

```php
$old = ELPParser::fromFile('old.elpx');
$new = ELPParser::fromFile('new.elpx');

if (!$old->hasSameContentAs($new)) {
    print_r($old->diff($new));
}
```

## Find broken resources

```php
print_r($parser->getMissingAssets());
print_r($parser->getBrokenInternalLinks());
print_r($parser->getMissingIdeviceRuntimes());
```

## Generate a detailed JSON document

```php
file_put_contents(
    'project.json',
    $parser->exportDetailedJson()
);
```

## Symfony uploaded file

```php
// $uploadedFile is Symfony\Component\HttpFoundation\File\UploadedFile
$stream = fopen($uploadedFile->getPathname(), 'rb');

try {
    $parser = ELPParser::fromStream(
        $stream,
        $uploadedFile->getClientOriginalExtension()
    );
} finally {
    fclose($stream);
}
```

## Laravel uploaded file

```php
// $uploadedFile is Illuminate\Http\UploadedFile
$stream = fopen($uploadedFile->getRealPath(), 'rb');

try {
    $parser = ELPParser::fromStream(
        $stream,
        $uploadedFile->getClientOriginalExtension()
    );
} finally {
    fclose($stream);
}
```
