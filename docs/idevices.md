# iDevices

Modern iDevices expose their rendered HTML and normalized state.

```php
foreach ($parser->getIdevices() as $idevice) {
    echo $idevice['type'] . PHP_EOL;
    echo $idevice['storagePattern'] . PHP_EOL;
    print_r($idevice['data']);
}
```

Supported storage patterns:

- `standard-json`: state in `jsonProperties`;
- `data-game`: URI-encoded DataGame state in HTML;
- `embedded-json`: JSON embedded in HTML, including interactive-video packages;
- `html-only`: no structured state payload.

Malformed state is isolated in `stateDecodeError`; it does not make the entire package unparsable.

Use `getUsedIdeviceTypes()`, `getAvailableIdeviceTypes()` and `getMissingIdeviceRuntimes()` to inspect packaged runtime support.
