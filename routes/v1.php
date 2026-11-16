<?php

// Every route the 11med clients call, one file per module in routes/v1/.
foreach (glob(__DIR__ . '/v1/*.php') as $file) {
    require $file;
}
