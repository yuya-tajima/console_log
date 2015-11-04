# console_log
This is a debug script for php programming.Especially,  WordPress.

## Sample usage ##

Like var_dump(), but console_log() write logs to a prepared file:

```php
<?php

// Write to this log file.
define('CONSOLE_LOG_FILE', '/var/log/console.log');

// something to debug variable or literal value.
$foo = 'a';
console_log($foo);
```

### How to display a log file in real time. ###

Below command is a one of the best solution that will give you a scrolling view of the logfile for any Unix-like operating system.

But, you should set the permission of /var/log/console.log to be writable.
```bash
tail -f /var/log/console.log
```
