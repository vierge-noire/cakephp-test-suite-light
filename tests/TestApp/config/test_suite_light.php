<?php

return [
    'TestSuiteLightSniffers' => [
        '\testDriver' => '\testTableSniffer'
    ],
    // Do not remove that dummy connection
    'TestSuiteLightIgnoredConnections' => [
        'test_dummy',
    ],
];
