# Assets and package entries

## Referenced assets

```php
$parser->getAssets();
$parser->getAssetsDetailed();
$parser->getImages();
$parser->getAudioFiles();
$parser->getVideoFiles();
$parser->getDocuments();
```

References are resolved against actual archive entries. External URLs are not reported as package assets.

## Diagnostics

```php
$parser->getMissingAssets();
$parser->getBrokenReferences();
$parser->getOrphanAssets();
```

## Package manifest

```php
$manifest = $parser->getPackageManifest();
```

The manifest categorizes root files, theme files, shared libraries, iDevice runtime files, project resources and other files.

## Internal links

```php
$parser->getInternalLinks();
$parser->getBrokenInternalLinks();
```

Internal `exe-node:<pageId>` references are tracked with their page/iDevice origins.
