<?php

return [
  'prefix' => 'ncxcache', //ncx mediacache
  'ttl' => [
    # Photo and videmo mongodb query
    'videometa' => 10800, //10800s  = 3h
    'photometa' => 10800, //10800s  = 3h

    # Http response video cache header tag
    'videohttp' => 10800, //10800s = 3h

    # Http response photo cache header tag
    'photohttp' => 604800, //recommended 7 days

  ],
];
